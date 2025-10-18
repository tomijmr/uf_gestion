<?php
require_once __DIR__ . '/../app/auth.php';
require_login();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

// Borrador de pedido en sesión
if (!isset($_SESSION['pedido'])) {
  $_SESSION['pedido'] = [
    'customer_id' => null,
    'items' => [], // ['product_id','codigo','nombre','precio','cant','subtotal']
    'senia' => 0.0,
    'medio' => 'EFECTIVO',
    'observaciones' => '',
  ];
}
$P =& $_SESSION['pedido'];

function pedido_total_bruto(array $items): float {
  $t = 0.0; foreach ($items as $it) $t += (float)$it['subtotal']; return $t;
}

$step = max(1, min(3, (int)($_GET['step'] ?? 1)));

// ---------- Acciones POST ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  // Seleccionar cliente
  if (($_POST['action'] ?? '') === 'select_customer') {
    $P['customer_id'] = (int)($_POST['customer_id'] ?? 0);
    $step = 2;
  }

  // Agregar item por ID
  if (($_POST['action'] ?? '') === 'add_item') {
    $pid = (int)($_POST['product_id'] ?? 0);
    $cant = max(1, (float)($_POST['cant'] ?? 1));
    $stmt = db()->prepare("SELECT id, codigo, nombre, tipo, precio_std FROM products WHERE id=? AND activo=1");
    $stmt->execute([$pid]);
    if ($prod = $stmt->fetch()) {
      if ($prod['tipo'] !== 'PT') {
        $error = "Solo se pueden vender productos terminados (PT).";
      } else {
        $precio = (float)$prod['precio_std'];
        $subtotal = $precio * $cant;

        $found = false;
        foreach ($P['items'] as &$it) {
          if ((int)$it['product_id'] === (int)$pid) {
            $it['cant'] += $cant;
            $it['subtotal'] = $it['cant'] * $it['precio'];
            $found = true; break;
          }
        } unset($it);

        if (!$found) {
          $P['items'][] = [
            'product_id' => (int)$prod['id'],
            'codigo'     => $prod['codigo'],
            'nombre'     => $prod['nombre'],
            'precio'     => $precio,
            'cant'       => $cant,
            'subtotal'   => $subtotal,
          ];
        }
      }
    }
    $step = 2;
  }

  // Agregar item por CÓDIGO
  if (($_POST['action'] ?? '') === 'add_item_by_code') {
    $codigo = trim($_POST['codigo'] ?? '');
    $cant = max(1, (float)($_POST['cant'] ?? 1));
    if ($codigo !== '') {
      $stmt = db()->prepare("SELECT id, codigo, nombre, tipo, precio_std FROM products WHERE codigo=? AND activo=1");
      $stmt->execute([$codigo]);
      if ($prod = $stmt->fetch()) {
        if ($prod['tipo'] !== 'PT') {
          $error = "El código ingresado no corresponde a un producto terminado (PT).";
        } else {
          $precio = (float)$prod['precio_std'];
          $subtotal = $precio * $cant;

          $found = false;
          foreach ($P['items'] as &$it) {
            if ((int)$it['product_id'] === (int)$prod['id']) {
              $it['cant'] += $cant;
              $it['subtotal'] = $it['cant'] * $it['precio'];
              $found = true; break;
            }
          } unset($it);

          if (!$found) {
            $P['items'][] = [
              'product_id' => (int)$prod['id'],
              'codigo'     => $prod['codigo'],
              'nombre'     => $prod['nombre'],
              'precio'     => $precio,
              'cant'       => $cant,
              'subtotal'   => $subtotal,
            ];
          }
        }
      } else {
        $error = "No se encontró un producto activo con el código ingresado.";
      }
    }
    $step = 2;
  }

  // Quitar item
  if (($_POST['action'] ?? '') === 'remove_item') {
    $pid = (int)($_POST['product_id'] ?? 0);
    $P['items'] = array_values(array_filter($P['items'], fn($it) => (int)$it['product_id'] !== $pid));
    $step = 2;
  }

  // Actualizar items
  if (($_POST['action'] ?? '') === 'update_items') {
    $cants = $_POST['cant'] ?? [];
    $precios = $_POST['precio'] ?? [];
    foreach ($P['items'] as &$it) {
      $id = (string)$it['product_id'];
      if (isset($cants[$id], $precios[$id])) {
        $it['cant'] = max(1, (float)$cants[$id]);
        $it['precio'] = max(0, (float)$precios[$id]);
        $it['subtotal'] = $it['cant'] * $it['precio'];
      }
    } unset($it);
    $step = 2;
  }

  // Guardar pago/observaciones
  if (($_POST['action'] ?? '') === 'set_payment') {
    $P['senia'] = max(0, (float)($_POST['senia'] ?? 0));
    $P['medio'] = $_POST['medio'] ?? 'EFECTIVO';
    $P['observaciones'] = trim($_POST['observaciones'] ?? '');
    $step = 3;
  }

  // Confirmar pedido
  if (($_POST['action'] ?? '') === 'confirm_order') {
    $error = '';
    if (!$P['customer_id']) $error = 'Debes seleccionar un cliente.';
    if (empty($P['items'])) $error = 'El pedido no tiene ítems.';

    if (empty($error)) {
      try {
        db()->beginTransaction();

        $total_bruto = pedido_total_bruto($P['items']);
        $descuento   = 0.0;
        $total_neto  = $total_bruto - $descuento;
        $senia       = $P['senia'];
        $saldo       = max(0, $total_neto - $senia);

        // Crear pedido
        $sqlOrder = "INSERT INTO orders (customer_id, fecha, estado, total_bruto, descuento, total_neto, senia, saldo, observaciones)
                     VALUES (?,?,?,?,?,?,?,?,?)";
        db()->prepare($sqlOrder)->execute([
          $P['customer_id'], date('Y-m-d H:i:s'), 'CONFIRMADO',
          $total_bruto, $descuento, $total_neto, $senia, $saldo, $P['observaciones']
        ]);
        $order_id = (int)db()->lastInsertId();

        // Ítems
        $sqlItem = "INSERT INTO order_items (order_id, product_id, cant, precio_unit, subtotal) VALUES (?,?,?,?,?)";
        $stmtItem = db()->prepare($sqlItem);
        foreach ($P['items'] as $it) {
          $stmtItem->execute([$order_id, $it['product_id'], $it['cant'], $it['precio'], $it['subtotal']]);
        }

        // Reservas y OP
        $estadoFinal = 'LISTO_ENTREGA';
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

        db()->prepare("UPDATE orders SET estado=? WHERE id=?")->execute([$estadoFinal, $order_id]);

        // Ledger: CARGO total_neto
        $cid = (int)$P['customer_id'];
        $stmtSaldo = db()->prepare("SELECT COALESCE(SUM(CASE WHEN tipo='CARGO' THEN monto ELSE -monto END),0) AS saldo
                                    FROM customer_ledger WHERE customer_id=?");
        $stmtSaldo->execute([$cid]);
        $saldoAnterior = (float)($stmtSaldo->fetch()['saldo'] ?? 0);

        $saldoResult = $saldoAnterior + $total_neto;
        db()->prepare("INSERT INTO customer_ledger (customer_id, fecha, tipo, origen, referencia_id, detalle, monto, saldo_resultante)
                       VALUES (?,?,?,?,?,?,?,?)")
          ->execute([$cid, date('Y-m-d H:i:s'), 'CARGO', 'VENTA', $order_id, 'Venta pedido #'.$order_id, $total_neto, $saldoResult]);

        // Seña opcional
        if ($senia > 0) {
          db()->prepare("INSERT INTO payments (customer_id, order_id, fecha, medio, importe, referencia)
                         VALUES (?,?,?,?,?,?)")
            ->execute([$cid, $order_id, date('Y-m-d H:i:s'), $P['medio'], $senia, 'Seña']);

          $saldoResult = $saldoResult - $senia;
          db()->prepare("INSERT INTO customer_ledger (customer_id, fecha, tipo, origen, referencia_id, detalle, monto, saldo_resultante)
                         VALUES (?,?,?,?,?,?,?,?)")
            ->execute([$cid, date('Y-m-d H:i:s'), 'ABONO', 'PAGO', $order_id, 'Seña pedido #'.$order_id, $senia, $saldoResult]);
        }

        db()->commit();
        unset($_SESSION['pedido']);
        header('Location: ' . url('pedidos.php') . '?ok=1&id=' . $order_id);
        exit;

      } catch (Throwable $e) {
        db()->rollBack();
        $error = 'Error al confirmar: ' . $e->getMessage();
      }
    }
    $step = 3;
  }
}

// Utilidad
function getCliente(?int $id) {
  if (!$id) return null;
  $s = db()->prepare("SELECT id, nombre, cuit_dni, telefono FROM customers WHERE id=?");
  $s->execute([$id]);
  return $s->fetch();
}

// ------------------- UI -------------------
include __DIR__ . '/../views/partials/header.php';
include __DIR__ . '/../views/partials/navbar.php';

// Paso 1: Cliente
if ($step === 1):
  $q = trim($_GET['q'] ?? '');
  $rows = [];
  if ($q !== '') {
    $s = db()->prepare("SELECT id, nombre, cuit_dni, telefono FROM customers
                        WHERE nombre LIKE ? OR cuit_dni LIKE ? OR telefono LIKE ?
                        ORDER BY nombre LIMIT 30");
    $like = "%$q%"; $s->execute([$like,$like,$like]);
    $rows = $s->fetchAll();
  }
  ?>
  <div class="container py-4">
    <h5 class="mb-3">Nuevo Pedido — Paso 1: Seleccionar cliente</h5>

    <form class="row g-2 mb-3" method="get" action="<?= url('pedido_nuevo.php') ?>">
      <input type="hidden" name="step" value="1">
      <div class="col-md-10"><input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Buscar cliente por nombre / CUIT-DNI / Teléfono"></div>
      <div class="col-md-2 d-grid"><button class="btn btn-primary">Buscar</button></div>
    </form>

    <div class="card shadow-sm">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead class="table-light"><tr><th>ID</th><th>Nombre</th><th>CUIT/DNI</th><th>Teléfono</th><th class="text-end">Acción</th></tr></thead>
            <tbody>
            <?php if (!$rows): ?>
              <tr><td colspan="5" class="text-center text-muted py-4">Buscá un cliente para continuar.</td></tr>
            <?php else: foreach ($rows as $r): ?>
              <tr>
                <td><?= (int)$r['id'] ?></td>
                <td><?= e($r['nombre']) ?></td>
                <td><?= e($r['cuit_dni']) ?></td>
                <td><?= e($r['telefono']) ?></td>
                <td class="text-end">
                  <form method="post" class="d-inline">
                    <input type="hidden" name="action" value="select_customer">
                    <input type="hidden" name="customer_id" value="<?= (int)$r['id'] ?>">
                    <button class="btn btn-sm btn-outline-primary">Seleccionar</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="mt-3">
      <a class="btn btn-outline-secondary" href="<?= url('clientes.php') ?>" target="_blank">+ Crear cliente nuevo</a>
    </div>
  </div>
<?php endif; ?>

<?php
// Paso 2: Ítems
if ($step === 2):
  if (!$P['customer_id']) { header('Location: ' . url('pedido_nuevo.php') . '?step=1'); exit; }
  $cli = getCliente($P['customer_id']);
  $q = trim($_GET['q'] ?? '');

  // Ahora SIEMPRE mostramos productos: si no hay búsqueda, listamos los primeros 30 PT
  if ($q !== '') {
    $stmtProd = db()->prepare("SELECT id, codigo, nombre, precio_std, (stock_actual - stock_reservado) AS disponible
                               FROM products
                               WHERE activo=1 AND tipo='PT' AND (codigo LIKE ? OR nombre LIKE ?)
                               ORDER BY nombre LIMIT 30");
    $like = "%$q%"; $stmtProd->execute([$like,$like]);
  } else {
    $stmtProd = db()->query("SELECT id, codigo, nombre, precio_std, (stock_actual - stock_reservado) AS disponible
                             FROM products
                             WHERE activo=1 AND tipo='PT'
                             ORDER BY nombre LIMIT 30");
  }
  $prods = $stmtProd->fetchAll();
  $total = pedido_total_bruto($P['items']);
  ?>
  <div class="container py-4">
    <h5 class="mb-1">Nuevo Pedido — Paso 2: Ítems</h5>
    <div class="text-muted mb-3">Cliente: <strong><?= e($cli['nombre'] ?? '') ?></strong> (ID <?= (int)$P['customer_id'] ?>)</div>

    <!-- Buscar productos -->
    <form class="row g-2 mb-3" method="get" action="<?= url('pedido_nuevo.php') ?>">
      <input type="hidden" name="step" value="2">
      <div class="col-md-8"><input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Buscar producto (código o nombre)"></div>
      <div class="col-md-2 d-grid"><button class="btn btn-outline-secondary">Buscar</button></div>
      <div class="col-md-2 d-grid">
        <a class="btn btn-outline-secondary" href="<?= url('pedido_nuevo.php') ?>?step=2">Limpiar</a>
      </div>
    </form>

    <!-- Agregar por CÓDIGO -->
    <form class="row g-2 mb-3" method="post">
      <input type="hidden" name="action" value="add_item_by_code">
      <div class="col-md-4">
        <input class="form-control" name="codigo" placeholder="Código exacto (ej: PT-MAQ1)">
      </div>
      <div class="col-md-2">
        <input class="form-control" type="number" name="cant" step="1" min="1" value="1" placeholder="Cant.">
      </div>
      <div class="col-md-2 d-grid">
        <button class="btn btn-primary">Agregar por código</button>
      </div>
      <div class="col-md-4 text-muted d-flex align-items-center">
        <small>Tip: si no buscás nada, se muestran los primeros 30 productos terminados.</small>
      </div>
    </form>

    <?php if (!empty($error)): ?>
      <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <!-- Listado de productos -->
    <div class="card shadow-sm mb-4">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead class="table-light"><tr><th>Código</th><th>Nombre</th><th class="text-end">Precio</th><th class="text-center">Disp.</th><th class="text-end">Agregar</th></tr></thead>
            <tbody>
            <?php if (!$prods): ?>
              <tr><td colspan="5" class="text-center text-muted py-3">No hay productos para mostrar.</td></tr>
            <?php else: foreach ($prods as $p): ?>
              <tr>
                <td><?= e($p['codigo']) ?></td>
                <td><?= e($p['nombre']) ?></td>
                <td class="text-end"><?= money($p['precio_std']) ?></td>
                <td class="text-center"><?= (float)$p['disponible'] ?></td>
                <td class="text-end">
                  <form method="post" class="d-inline">
                    <input type="hidden" name="action" value="add_item">
                    <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                    <div class="input-group input-group-sm" style="max-width:180px; margin-left:auto;">
                      <input type="number" name="cant" step="1" min="1" value="1" class="form-control">
                      <button class="btn btn-primary">Agregar</button>
                    </div>
                  </form>
                </td>
              </tr>
            <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Carrito -->
    <div class="card shadow-sm">
      <div class="card-body p-0">
        <form method="post">
          <input type="hidden" name="action" value="update_items">
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead class="table-light"><tr>
                <th>Código</th><th>Nombre</th>
                <th class="text-end" style="width:150px;">Precio</th>
                <th class="text-end" style="width:120px;">Cant.</th>
                <th class="text-end" style="width:150px;">Subtotal</th>
                <th class="text-end" style="width:90px;">Quitar</th>
              </tr></thead>
              <tbody>
              <?php if (!$P['items']): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">Aún no agregaste productos.</td></tr>
              <?php else: foreach ($P['items'] as $it): $id=(int)$it['product_id']; ?>
                <tr>
                  <td><?= e($it['codigo']) ?></td>
                  <td><?= e($it['nombre']) ?></td>
                  <td class="text-end">
                    <input class="form-control form-control-sm text-end" type="number" step="0.01" name="precio[<?= $id ?>]" value="<?= (float)$it['precio'] ?>">
                  </td>
                  <td class="text-end">
                    <input class="form-control form-control-sm text-end" type="number" step="1" min="1" name="cant[<?= $id ?>]" value="<?= (float)$it['cant'] ?>">
                  </td>
                  <td class="text-end"><?= money($it['subtotal']) ?></td>
                  <td class="text-end">
                    <form method="post" class="d-inline">
                      <input type="hidden" name="action" value="remove_item">
                      <input type="hidden" name="product_id" value="<?= $id ?>">
                      <button class="btn btn-sm btn-outline-danger">x</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
          <div class="p-3 d-flex justify-content-between align-items-center">
            <div>
              <a class="btn btn-outline-secondary" href="<?= url('pedido_nuevo.php') ?>?step=1">« Volver a Cliente</a>
            </div>
            <div class="d-flex align-items-center gap-3">
              <div class="fw-semibold">Total: <?= money($total) ?></div>
              <button class="btn btn-outline-primary">Actualizar</button>
              <a class="btn btn-primary" href="<?= url('pedido_nuevo.php') ?>?step=3">Siguiente »</a>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
<?php endif; ?>

<?php
// Paso 3: Pago + Confirmación
if ($step === 3):
  if (!$P['customer_id']) { header('Location: ' . url('pedido_nuevo.php') . '?step=1'); exit; }
  if (!$P['items']) { header('Location: ' . url('pedido_nuevo.php') . '?step=2'); exit; }
  $cli = getCliente($P['customer_id']);
  $total = pedido_total_bruto($P['items']);
  $descuento = 0; $neto = $total - $descuento;
  $senia = (float)$P['senia']; $saldo = max(0, $neto - $senia);
  ?>
  <div class="container py-4">
    <h5 class="mb-1">Nuevo Pedido — Paso 3: Pago y Confirmación</h5>
    <div class="text-muted mb-3">Cliente: <strong><?= e($cli['nombre'] ?? '') ?></strong> (ID <?= (int)$P['customer_id'] ?>)</div>

    <?php if (!empty($error)): ?>
      <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="row g-3">
      <div class="col-lg-7">
        <div class="card shadow-sm">
          <div class="card-body">
            <h6 class="mb-3">Resumen de Ítems</h6>
            <div class="table-responsive">
              <table class="table table-sm align-middle">
                <thead class="table-light"><tr><th>Código</th><th>Nombre</th><th class="text-end">Precio</th><th class="text-end">Cant</th><th class="text-end">Subtotal</th></tr></thead>
                <tbody>
                <?php foreach ($P['items'] as $it): ?>
                  <tr>
                    <td><?= e($it['codigo']) ?></td>
                    <td><?= e($it['nombre']) ?></td>
                    <td class="text-end"><?= money($it['precio']) ?></td>
                    <td class="text-end"><?= (float)$it['cant'] ?></td>
                    <td class="text-end"><?= money($it['subtotal']) ?></td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>

            <div class="d-flex justify-content-end">
              <div class="text-end">
                <div>Subtotal: <strong><?= money($total) ?></strong></div>
                <div>Descuento: <strong><?= money($descuento) ?></strong></div>
                <div>Total Neto: <strong><?= money($neto) ?></strong></div>
                <div>Seña: <strong><?= money($senia) ?></strong></div>
                <div>Saldo: <strong><?= money($saldo) ?></strong></div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-lg-5">
        <div class="card shadow-sm mb-3">
          <div class="card-body">
            <h6 class="mb-3">Pago (opcional)</h6>
            <form method="post" class="row g-2">
              <input type="hidden" name="action" value="set_payment">
              <div class="col-6">
                <label class="form-label">Seña</label>
                <input class="form-control" type="number" step="0.01" name="senia" value="<?= (float)$P['senia'] ?>">
              </div>
              <div class="col-6">
                <label class="form-label">Medio</label>
                <select name="medio" class="form-select">
                  <?php foreach (['EFECTIVO','DEBITO','TRANSFER','CREDITO','NC'] as $m): ?>
                    <option value="<?= $m ?>" <?= $P['medio']===$m?'selected':'' ?>><?= $m ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-12">
                <label class="form-label">Observaciones</label>
                <textarea name="observaciones" class="form-control" rows="2"><?= e($P['observaciones']) ?></textarea>
              </div>
              <div class="col-12 d-flex justify-content-between">
                <a class="btn btn-outline-secondary" href="<?= url('pedido_nuevo.php') ?>?step=2">« Volver a Ítems</a>
                <button class="btn btn-outline-primary">Guardar cambios</button>
              </div>
            </form>
          </div>
        </div>

        <div class="card shadow-sm">
          <div class="card-body">
            <h6 class="mb-3">Confirmación</h6>
            <form method="post">
              <input type="hidden" name="action" value="confirm_order">
              <button class="btn btn-primary w-100">Confirmar pedido</button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
<?php endif; ?>

<?php include __DIR__ . '/../views/partials/footer.php'; ?>
