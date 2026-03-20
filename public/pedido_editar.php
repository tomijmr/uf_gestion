<?php
require_once __DIR__ . '/../app/auth.php';
require_login();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

$order_id = (int)($_GET['order_id'] ?? 0);
if ($order_id <= 0) {
  http_response_code(400);
  echo 'Pedido invalido';
  exit;
}

$flash_ok = '';
$flash_err = '';

function load_order(int $order_id) {
  $stmt = db()->prepare("SELECT o.*, COALESCE(c.nombre, o.cliente_manual) AS cliente
                         FROM orders o
                         LEFT JOIN customers c ON c.id=o.customer_id
                         WHERE o.id=?");
  $stmt->execute([$order_id]);
  return $stmt->fetch(PDO::FETCH_ASSOC);
}

function load_items(int $order_id): array {
  $s = db()->prepare("SELECT oi.product_id, p.codigo, p.nombre, oi.cant, oi.precio_unit, oi.subtotal, p.metros_cuadrados
                      FROM order_items oi
                      JOIN products p ON p.id=oi.product_id
                      WHERE oi.order_id=?");
  $s->execute([$order_id]);
  return $s->fetchAll();
}

function pedido_edit_total_bruto(array $items): float {
  $t = 0.0;
  foreach ($items as $it) $t += (float)$it['subtotal'];
  return $t;
}

$order = load_order($order_id);
if (!$order) {
  http_response_code(404);
  echo 'Pedido no encontrado';
  exit;
}

if (!isset($_SESSION['pedido_edit'])) {
  $_SESSION['pedido_edit'] = [];
}

if (!isset($_SESSION['pedido_edit'][$order_id]) || isset($_GET['reset'])) {
  $db_items = load_items($order_id);
  $_SESSION['pedido_edit'][$order_id] = [
    'items' => array_map(function ($it) {
      return [
        'product_id' => (int)$it['product_id'],
        'codigo' => $it['codigo'],
        'nombre' => $it['nombre'],
        'precio' => (float)$it['precio_unit'],
        'cant' => (float)$it['cant'],
        'subtotal' => (float)$it['subtotal'],
        'metros_cuadrados' => (float)($it['metros_cuadrados'] ?? 0),
      ];
    }, $db_items),
  ];
}
$P =& $_SESSION['pedido_edit'][$order_id];
if (!isset($P['descuento_pct'])) $P['descuento_pct'] = 0;
if (!isset($P['descuento_monto'])) $P['descuento_monto'] = 0;

// ---------- Acciones POST ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  if ($action === 'add_item') {
    $pid = (int)($_POST['product_id'] ?? 0);
    $cant = max(1, (float)($_POST['cant'] ?? 1));
    $stmt = db()->prepare("SELECT id, codigo, nombre, tipo, precio_std, metros_cuadrados FROM products WHERE id=? AND activo=1");
    $stmt->execute([$pid]);
    if ($prod = $stmt->fetch()) {
      if ($prod['tipo'] !== 'PT') {
        $flash_err = 'Solo se pueden agregar productos terminados (PT).';
      } else {
        $precio = (float)$prod['precio_std'];
        $subtotal = $precio * $cant;
        $metros_cuadrados = (float)($prod['metros_cuadrados'] ?? 0);
        $found = false;
        foreach ($P['items'] as &$it) {
          if ((int)$it['product_id'] === (int)$pid) {
            $it['cant'] += $cant;
            $it['subtotal'] = $it['cant'] * $it['precio'];
            // Asumimos que los metros cuadrados son constantes por producto, actualizamos por si cambiaron
            $it['metros_cuadrados'] = $metros_cuadrados;
            $found = true;
            break;
          }
        }
        unset($it);
        if (!$found) {
          $P['items'][] = [
            'product_id' => (int)$prod['id'],
            'codigo' => $prod['codigo'],
            'nombre' => $prod['nombre'],
            'precio' => $precio,
            'cant' => $cant,
            'subtotal' => $subtotal,
            'metros_cuadrados' => $metros_cuadrados,
          ];
        }
      }
    }
  }

  if ($action === 'add_item_by_code') {
    $codigo = trim($_POST['codigo'] ?? '');
    $cant = max(1, (float)($_POST['cant'] ?? 1));
    if ($codigo !== '') {
      $stmt = db()->prepare("SELECT id, codigo, nombre, tipo, precio_std, metros_cuadrados FROM products WHERE codigo=? AND activo=1");
      $stmt->execute([$codigo]);
      if ($prod = $stmt->fetch()) {
        if ($prod['tipo'] !== 'PT') {
          $flash_err = 'El codigo no corresponde a un producto terminado (PT).';
        } else {
          $precio = (float)$prod['precio_std'];
          $subtotal = $precio * $cant;
          $metros_cuadrados = (float)($prod['metros_cuadrados'] ?? 0);
          $found = false;
          foreach ($P['items'] as &$it) {
            if ((int)$it['product_id'] === (int)$prod['id']) {
              $it['cant'] += $cant;
              $it['subtotal'] = $it['cant'] * $it['precio'];
              $it['metros_cuadrados'] = $metros_cuadrados;
              $found = true;
              break;
            }
          }
          unset($it);
          if (!$found) {
            $P['items'][] = [
              'product_id' => (int)$prod['id'],
              'codigo' => $prod['codigo'],
              'nombre' => $prod['nombre'],
              'precio' => $precio,
              'cant' => $cant,
              'subtotal' => $subtotal,
              'metros_cuadrados' => $metros_cuadrados,
            ];
          }
        }
      } else {
        $flash_err = 'No se encontro un producto activo con ese codigo.';
      }
    }
  }

  if ($action === 'remove_item') {
    $pid = (int)($_POST['product_id'] ?? 0);
    $P['items'] = array_values(array_filter($P['items'], fn($it) => (int)$it['product_id'] !== $pid));
  }

  if ($action === 'update_items') {
    $cants = $_POST['cant'] ?? [];
    $precios = $_POST['precio'] ?? [];
    foreach ($P['items'] as &$it) {
      $id = (string)$it['product_id'];
      if (isset($cants[$id], $precios[$id])) {
        $it['cant'] = max(1, (float)$cants[$id]);
        $it['precio'] = max(0, (float)$precios[$id]);
        $it['subtotal'] = $it['cant'] * $it['precio'];
      }
    }
    unset($it);
  }

  // Manejar descuento
  if ($action === 'set_discount') {
    $P['descuento_pct'] = isset($_POST['descuento_pct']) ? (float)$_POST['descuento_pct'] : 0;
    $P['descuento_monto'] = isset($_POST['descuento_monto']) ? (float)$_POST['descuento_monto'] : 0;
  }

  if ($action === 'save_order' || $action === 'convert_to_order') {
    $convertToOrder = ($action === 'convert_to_order');
    
    if (empty($P['items'])) {
      $flash_err = 'El pedido no tiene items.';
    } else {
      try {
        db()->beginTransaction();

        $sOrder = db()->prepare("SELECT * FROM orders WHERE id=? FOR UPDATE");
        $sOrder->execute([$order_id]);
        $ord = $sOrder->fetch(PDO::FETCH_ASSOC);
        if (!$ord) throw new Exception('Pedido no encontrado.');

        if (in_array($ord['estado'], ['ENTREGADO','CERRADO'], true)) {
          throw new Exception('El pedido ya fue entregado/cerrado y no puede editarse.');
        }

        if ($convertToOrder) {
            $isManual = !empty($ord['cliente_manual']);
            if (!$ord['customer_id'] && !$isManual) {
                // Si no tiene ID y no es manual (caso extraño, quizas error en migracion)
                throw new Exception('El pedido no tiene cliente asignado.');
            }
        
            if ($ord['estado'] !== 'PRESUPUESTO') {
                throw new Exception('Solo se pueden convertir presupuestos.');
            }
        }

        $sOP = db()->prepare("SELECT COUNT(*) AS c FROM production_orders WHERE order_id=? AND estado IN ('EN_CURSO','FINALIZADA')");
        $sOP->execute([$order_id]);
        $opBusy = (int)($sOP->fetch()['c'] ?? 0);
        if ($opBusy > 0) {
          throw new Exception('Hay ordenes de produccion en curso/finalizadas.');
        }

        $isPresupuesto = ($ord['estado'] === 'PRESUPUESTO') && !$convertToOrder;
        $wasPresupuesto = ($ord['estado'] === 'PRESUPUESTO'); 

        // Liberar reservas anteriores (SOLO SI NO ERA PRESUPUESTO)
        if (!$wasPresupuesto) {
            $sOld = db()->prepare("SELECT oi.product_id, oi.cant
                                   FROM order_items oi
                                   JOIN products p ON p.id=oi.product_id
                                   WHERE oi.order_id=? AND p.tipo='PT' FOR UPDATE");
            $sOld->execute([$order_id]);
            $oldItems = $sOld->fetchAll();
            $updRel = db()->prepare("UPDATE products SET stock_reservado = GREATEST(stock_reservado - ?, 0) WHERE id=?");
            foreach ($oldItems as $it) {
              $updRel->execute([(float)$it['cant'], (int)$it['product_id']]);
            }
            
            db()->prepare("DELETE FROM production_orders WHERE order_id=? AND estado IN ('PENDIENTE','OBSERVADA')")
              ->execute([$order_id]);
        }

        db()->prepare("DELETE FROM order_items WHERE order_id=?")
          ->execute([$order_id]);

        $insItem = db()->prepare("INSERT INTO order_items (order_id, product_id, cant, precio_unit, subtotal) VALUES (?,?,?,?,?)");
        foreach ($P['items'] as $it) {
          $insItem->execute([$order_id, $it['product_id'], $it['cant'], $it['precio'], $it['subtotal']]);
        }

        // Reservas y OP nuevamente
        $estadoFinal = 'LISTO_ENTREGA';
        
        if ($isPresupuesto) {
            $estadoFinal = 'PRESUPUESTO';
        } else {
            $sqlGetProd = db()->prepare("SELECT id, tipo, stock_actual, stock_reservado FROM products WHERE id=? FOR UPDATE");
            $sqlUpdReserva = db()->prepare("UPDATE products SET stock_reservado = stock_reservado + ? WHERE id=?");
            $sqlOP = db()->prepare("INSERT INTO production_orders (order_id, product_pt_id, cantidad, estado) VALUES (?,?,?,'PENDIENTE')");

            foreach ($P['items'] as $it) {
              $pid = (int)$it['product_id'];
              $cant = (float)$it['cant'];
              $sqlGetProd->execute([$pid]);
              $prod = $sqlGetProd->fetch();
              if (!$prod) continue;

              if ($prod['tipo'] === 'PT') {
                $disponible = (float)$prod['stock_actual'] - (float)$prod['stock_reservado'];
                $aReservar = min($disponible, $cant);
                if ($aReservar > 0) {
                  $sqlUpdReserva->execute([$aReservar, $pid]);
                }
                $faltante = $cant - $aReservar;
                if ($faltante > 0) {
                  $sqlOP->execute([$order_id, $pid, $faltante]);
                  $estadoFinal = 'EN_PRODUCCION';
                }
              }
            }
        }

        $total_bruto = pedido_edit_total_bruto($P['items']);
        $descuento = (float)$ord['descuento'];
        $total_neto = $total_bruto - $descuento;
        $senia = (float)$ord['senia'];
        $saldo = max(0, $total_neto - $senia);

        db()->prepare("UPDATE orders SET total_bruto=?, total_neto=?, saldo=?, estado=? WHERE id=?")
          ->execute([$total_bruto, $total_neto, $saldo, $estadoFinal, $order_id]);

        $diff = $total_neto - (float)$ord['total_neto'];
        
        // Ledger update - ONLY if valid customer
        if ($ord['customer_id']) {
            if ($wasPresupuesto && !$isPresupuesto) {
                 // Conversion: Charge FULL amount
                 $cid = (int)$ord['customer_id'];
                 $stmtSaldo = db()->prepare("SELECT COALESCE(SUM(CASE WHEN tipo='CARGO' THEN monto ELSE -monto END),0) AS saldo
                                          FROM customer_ledger WHERE customer_id=?");
                 $stmtSaldo->execute([$cid]);
                 $saldoAnterior = (float)($stmtSaldo->fetch()['saldo'] ?? 0);
                 $saldoResult = $saldoAnterior + $total_neto;
                 
                 db()->prepare("INSERT INTO customer_ledger (customer_id, fecha, tipo, origen, referencia_id, detalle, monto, saldo_resultante)
                             VALUES (?,?,?,?,?,?,?,?)")
                ->execute([$cid, date('Y-m-d H:i:s'), 'CARGO', 'VENTA', $order_id, 'Venta pedido #'.$order_id, $total_neto, $saldoResult]);

            } elseif (!$wasPresupuesto && !$isPresupuesto && abs($diff) > 0.00001) {
              $cid = (int)$ord['customer_id'];
              $stmtSaldo = db()->prepare("SELECT COALESCE(SUM(CASE WHEN tipo='CARGO' THEN monto ELSE -monto END),0) AS saldo
                                          FROM customer_ledger WHERE customer_id=?");
              $stmtSaldo->execute([$cid]);
              $saldoAnterior = (float)($stmtSaldo->fetch()['saldo'] ?? 0);
              $saldoResult = $saldoAnterior + $diff;

              $tipo = $diff >= 0 ? 'CARGO' : 'ABONO';
              $monto = abs($diff);
              db()->prepare("INSERT INTO customer_ledger (customer_id, fecha, tipo, origen, referencia_id, detalle, monto, saldo_resultante)
                             VALUES (?,?,?,?,?,?,?,?)")
                ->execute([$cid, date('Y-m-d H:i:s'), $tipo, 'AJUSTE', $order_id, 'Ajuste pedido #'.$order_id, $monto, $saldoResult]);
            }
        }
        
        if ($convertToOrder) {
            unset($_SESSION['pedido_edit'][$order_id]); // Clear edit session
        }

        db()->commit();
        $flash_ok = 'Pedido actualizado.';
        $order = load_order($order_id);
      } catch (Throwable $e) {
        db()->rollBack();
        $flash_err = 'No se pudo actualizar: ' . $e->getMessage();
      }
    }
  }
}

$items = $P['items'] ?? [];

// Productos para selector rapido
$stmtProd = db()->query("SELECT id, codigo, nombre, precio_std FROM products WHERE tipo='PT' AND activo=1 ORDER BY nombre LIMIT 500");
$prod_list = $stmtProd->fetchAll();

include __DIR__ . '/../views/partials/header.php';
include __DIR__ . '/../views/partials/navbar.php';
?>

<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h5 class="mb-0">Editar pedido #<?= (int)$order_id ?></h5>
      <small class="text-muted">Cliente: <?= e($order['cliente']) ?> | Estado: <?= e($order['estado']) ?></small>
    </div>
    <div>
      <a class="btn btn-outline-secondary btn-sm" href="<?= url('pedidos.php') ?>">Volver</a>
      <a class="btn btn-outline-secondary btn-sm" href="<?= url('pedido_editar.php?order_id=' . (int)$order_id . '&reset=1') ?>">Restablecer</a>
    </div>
  </div>

  <?php if ($flash_ok): ?>
    <div class="alert alert-success"><?= e($flash_ok) ?></div>
  <?php endif; ?>
  <?php if ($flash_err): ?>
    <div class="alert alert-danger"><?= e($flash_err) ?></div>
  <?php endif; ?>

  <div class="row g-3">
    <div class="col-md-6">
      <div class="card">
        <div class="card-body">
          <h6 class="card-title">Agregar por codigo</h6>
          <form method="post" class="row g-2">
            <input type="hidden" name="action" value="add_item_by_code">
            <div class="col-6">
              <input class="form-control" name="codigo" placeholder="Codigo">
            </div>
            <div class="col-3">
              <input class="form-control" type="number" step="1" min="1" name="cant" value="1">
            </div>
            <div class="col-3 d-grid">
              <button class="btn btn-primary">Agregar</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="col-md-6">
      <div class="card">
        <div class="card-body">
          <h6 class="card-title">Agregar por selector</h6>
          <form method="post" class="row g-2">
            <input type="hidden" name="action" value="add_item">
            <div class="col-6">
              <select class="form-select" name="product_id">
                <?php foreach ($prod_list as $p): ?>
                  <option value="<?= (int)$p['id'] ?>"><?= e($p['codigo'] . ' - ' . $p['nombre']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-3">
              <input class="form-control" type="number" step="1" min="1" name="cant" value="1">
            </div>
            <div class="col-3 d-grid">
              <button class="btn btn-primary">Agregar</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  <div class="card mt-3">
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h6 class="mb-0">Items del pedido</h6>
        <div class="d-flex gap-2">
          <?php if ($order['estado'] === 'PRESUPUESTO'): ?>
            <form method="post" onsubmit="return confirm('¿Confirmar pedido y reservar stock?');">
              <input type="hidden" name="action" value="convert_to_order">
              <button class="btn btn-warning btn-sm">Convertir a Pedido</button>
            </form>
          <?php endif; ?>
          <form method="post">
            <input type="hidden" name="action" value="save_order">
            <button class="btn btn-success btn-sm">Guardar cambios</button>
          </form>
        </div>
      </div>

      <?php if (!$items): ?>
        <div class="text-muted">No hay items cargados.</div>
      <?php else: ?>
        <form method="post">
          <input type="hidden" name="action" value="update_items">
          <div class="table-responsive">
            <table class="table table-sm align-middle">
              <thead>
                <tr>
                  <th>Codigo</th>
                  <th>Nombre</th>
                  <th class="text-end" title="Metros Cuadrados Totales">Total m²</th>
                  <th class="text-end">Precio</th>
                  <th class="text-end">Cant</th>
                  <th class="text-end">Subtotal</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <?php $total_m2 = 0; ?>
                <?php foreach ($items as $it): 
                  $m2_unit = (float)($it['metros_cuadrados'] ?? 0);
                  $m2_row = $m2_unit * (float)$it['cant'];
                  $total_m2 += $m2_row;
                ?>
                  <tr>
                    <td><?= e($it['codigo']) ?></td>
                    <td>
                        <?= e($it['nombre']) ?>
                        <?php if ($m2_unit > 0): ?>
                            <div class="small text-muted">(<?= $m2_unit ?> m²/u)</div>
                        <?php endif; ?>
                    </td>
                    <td class="text-end"><?= $m2_row > 0 ? number_format($m2_row, 4) : '-' ?></td>
                    <td class="text-end" style="width:140px;">
                      <input class="form-control form-control-sm text-end" type="number" step="0.01" min="0" name="precio[<?= (int)$it['product_id'] ?>]" value="<?= e(number_format((float)$it['precio'], 2, '.', '')) ?>">
                    </td>
                    <td class="text-end" style="width:120px;">
                      <input class="form-control form-control-sm text-end" type="number" step="1" min="1" name="cant[<?= (int)$it['product_id'] ?>]" value="<?= (float)$it['cant'] ?>">
                    </td>
                    <td class="text-end"><?= money($it['subtotal']) ?></td>
                    <td class="text-end" style="width:90px;">
                      <form method="post" class="d-inline">
                        <input type="hidden" name="action" value="remove_item">
                        <input type="hidden" name="product_id" value="<?= (int)$it['product_id'] ?>">
                        <button class="btn btn-outline-danger btn-sm" type="submit">Quitar</button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <form method="post" class="row g-2 mb-2">
            <input type="hidden" name="action" value="set_discount">
            <div class="col-4">
              <label class="form-label mb-1">Descuento %</label>
              <input class="form-control form-control-sm text-end" type="number" step="0.01" min="0" max="100" name="descuento_pct" value="<?= (float)$P['descuento_pct'] ?>">
            </div>
            <div class="col-4">
              <label class="form-label mb-1">Descuento $</label>
              <input class="form-control form-control-sm text-end" type="number" step="0.01" min="0" name="descuento_monto" value="<?= (float)$P['descuento_monto'] ?>">
            </div>
            <div class="col-4 d-flex align-items-end">
              <button class="btn btn-outline-primary btn-sm w-100">Aplicar</button>
            </div>
          </form>
          <div class="d-flex justify-content-between align-items-center">
            <div class="text-muted">
                <span class="me-3" title="Total Metros Cuadrados"><strong><?= number_format($total_m2, 0) ?></strong> m²</span>
                <?php $total_bruto = pedido_edit_total_bruto($items); $descuento = round($total_bruto * $P['descuento_pct'] / 100, 2) + $P['descuento_monto']; $neto = max(0, $total_bruto - $descuento); ?>
                <span>Total $: <strong><?= money($total_bruto) ?></strong></span>
                <span>Descuento: <strong><?= money($descuento) ?></strong></span>
                <span>Total Neto: <strong><?= money($neto) ?></strong></span>
            </div>
            <button class="btn btn-outline-primary btn-sm">Actualizar</button>
          </div>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../views/partials/footer.php'; ?>
