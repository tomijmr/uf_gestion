<?php
require_once __DIR__ . '/../app/auth.php';
require_login();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

// ---------- Utilidades ----------
function fetchBom(int $pt_id): array {
  $s = db()->prepare("SELECT b.component_id, p.codigo, p.nombre, p.unidad, b.cant_por_unidad,
                             p.stock_actual, p.stock_reservado
                      FROM product_bom b
                      JOIN products p ON p.id=b.component_id
                      WHERE b.product_pt_id=?");
  $s->execute([$pt_id]);
  return $s->fetchAll();
}

function reserveForOrdersIfPossible(int $pt_id): void {
  // Objetivo MVP: para cada pedido EN_PRODUCCION que tenga este PT,
  // intentamos reservar cantidades faltantes; si el pedido queda totalmente reservado,
  // pasarlo a LISTO_ENTREGA.
  // Nota: reserva "global", no por pedido; suficiente para MVP.

  // Buscar órdenes que estén EN_PRODUCCION y tengan ítems de este PT
  $sOrders = db()->prepare("
    SELECT DISTINCT o.id
    FROM orders o
    JOIN order_items oi ON oi.order_id=o.id
    WHERE o.estado='EN_PRODUCCION' AND oi.product_id=? 
    ORDER BY o.fecha ASC, o.id ASC
  ");
  $sOrders->execute([$pt_id]);
  $orderIds = array_map(fn($r) => (int)$r['id'], $sOrders->fetchAll());

  if (!$orderIds) return;

  // Traer stock disponible del PT (con lock)
  $sProd = db()->prepare("SELECT stock_actual, stock_reservado FROM products WHERE id=? FOR UPDATE");
  $sProd->execute([$pt_id]);
  $prod = $sProd->fetch();
  if (!$prod) return;
  $disponible = (float)$prod['stock_actual'] - (float)$prod['stock_reservado'];
  if ($disponible <= 0) return;

  $updReserva = db()->prepare("UPDATE products SET stock_reservado = stock_reservado + ? WHERE id=?");
  $getItemsForOrder = db()->prepare("
      SELECT p.id as product_id, p.tipo, oi.cant,
             (p.stock_actual - p.stock_reservado) AS disponible_global
      FROM order_items oi
      JOIN products p ON p.id=oi.product_id
      WHERE oi.order_id=? AND p.tipo='PT'
  ");

  foreach ($orderIds as $oid) {
    if ($disponible <= 0) break;
    // Para el pedido, evaluar si TODOS los PTs quedarían reservados con lo que hay
    $getItemsForOrder->execute([$oid]);
    $its = $getItemsForOrder->fetchAll();
    if (!$its) continue;

    $faltantesPorProd = [];
    foreach ($its as $it) {
      $need = (float)$it['cant'];
      // estimamos "reservado" como total pedido - disponible_global negativo? No tenemos por-pedido;
      // MVP: si disponible_global >= cant, consideramos que "puede reservarse".
      $falt = max(0, $need - max(0, (float)$it['disponible_global']));
      if ($falt > 0) $faltantesPorProd[(int)$it['product_id']] = $falt;
    }

    if (!$faltantesPorProd) {
      // Ya estaba posible reservar todo (o ya reservado). Marcar LISTO_ENTREGA.
      db()->prepare("UPDATE orders SET estado='LISTO_ENTREGA' WHERE id=?")->execute([$oid]);
      continue;
    }

    // Intentar reservar al menos para el PT actual
    if (isset($faltantesPorProd[$pt_id]) && $disponible > 0) {
      $aRes = min($disponible, $faltantesPorProd[$pt_id]);
      if ($aRes > 0) {
        $updReserva->execute([$aRes, $pt_id]);
        $disponible -= $aRes;
        // Re-evaluar si TODOS los PTs quedarían "cubiertos" (heurística simple: volver a consultar)
        $getItemsForOrder->execute([$oid]);
        $its2 = $getItemsForOrder->fetchAll();
        $aunFalta = false;
        foreach ($its2 as $it2) {
          $need2 = (float)$it2['cant'];
          $disp2 = (float)$it2['disponible_global']; // ya incluye nuestra reserva en el pt actual porque leímos sin lock; asumido suficiente en MVP
          if ($disp2 < $need2) { $aunFalta = true; break; }
        }
        if (!$aunFalta) {
          db()->prepare("UPDATE orders SET estado='LISTO_ENTREGA' WHERE id=?")->execute([$oid]);
        }
      }
    }
  }
}

// ---------- Acciones ----------
$flash_ok = '';
$flash_err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  $op_id  = (int)($_POST['op_id'] ?? 0);

  if ($action === 'start') {
    try {
      db()->beginTransaction();

      // Traer OP y lockear
      $s = db()->prepare("SELECT po.id, po.product_pt_id, po.cantidad, po.estado, p.nombre AS pt_nombre
                          FROM production_orders po
                          JOIN products p ON p.id=po.product_pt_id
                          WHERE po.id=? FOR UPDATE");
      $s->execute([$op_id]);
      $op = $s->fetch();
      if (!$op) throw new Exception("OP no encontrada");
      if ($op['estado'] !== 'PENDIENTE') throw new Exception("La OP no está en estado PENDIENTE");

      // Verificar y consumir BOM
      $bom = fetchBom((int)$op['product_pt_id']);
      if (!$bom) throw new Exception("El PT no tiene BOM definido");
      $updMP  = db()->prepare("UPDATE products SET stock_actual = stock_actual - ? WHERE id=?");
      $insMov = db()->prepare("INSERT INTO stock_moves (fecha, tipo, motivo, product_id, cantidad, referencia_tipo, referencia_id, observaciones)
                               VALUES (NOW(), 'SALIDA', 'PROD_CONSUMO', ?, ?, 'OP', ?, 'Consumo OP')");

      // Chequear disponibilidad
      foreach ($bom as $row) {
        $need = (float)$row['cant_por_unidad'] * (float)$op['cantidad'];
        if ($need <= 0) continue;
        if ((float)$row['stock_actual'] < $need) {
          throw new Exception("Stock insuficiente de {$row['codigo']} ({$row['nombre']}). Necesario: $need, Actual: {$row['stock_actual']}");
        }
      }

      // Consumir
      foreach ($bom as $row) {
        $need = (float)$row['cant_por_unidad'] * (float)$op['cantidad'];
        if ($need <= 0) continue;
        $updMP->execute([$need, (int)$row['component_id'] ?? (int)$row['component_id']]);
        $insMov->execute([(int)$row['component_id'] ?? (int)$row['component_id'], $need, $op_id]);
      }

      // Cambiar estado
      db()->prepare("UPDATE production_orders SET estado='EN_CURSO', fecha_ini=NOW() WHERE id=?")->execute([$op_id]);

      db()->commit();
      $flash_ok = "OP #{$op_id} iniciada. Se consumió BOM.";
    } catch (Throwable $e) {
      db()->rollBack();
      $flash_err = "No se pudo iniciar la OP: " . $e->getMessage();
    }
  }

  if ($action === 'finish') {
    try {
      db()->beginTransaction();

      // Traer OP y lockear
      $s = db()->prepare("SELECT po.id, po.product_pt_id, po.cantidad, po.estado, p.nombre AS pt_nombre
                          FROM production_orders po
                          JOIN products p ON p.id=po.product_pt_id
                          WHERE po.id=? FOR UPDATE");
      $s->execute([$op_id]);
      $op = $s->fetch();
      if (!$op) throw new Exception("OP no encontrada");
      if ($op['estado'] !== 'EN_CURSO') throw new Exception("La OP debe estar EN_CURSO para finalizar");

      // Ingresar PT terminado
      $updPT  = db()->prepare("UPDATE products SET stock_actual = stock_actual + ? WHERE id=?");
      $insMov = db()->prepare("INSERT INTO stock_moves (fecha, tipo, motivo, product_id, cantidad, referencia_tipo, referencia_id, observaciones)
                               VALUES (NOW(), 'ENTRADA', 'PROD_ALTA', ?, ?, 'OP', ?, 'Alta PT de OP')");
      $updPT->execute([(float)$op['cantidad'], (int)$op['product_pt_id']]);
      $insMov->execute([(int)$op['product_pt_id'], (float)$op['cantidad'], $op_id]);

      // Cerrar OP
      db()->prepare("UPDATE production_orders SET estado='FINALIZADA', fecha_fin=NOW() WHERE id=?")->execute([$op_id]);

      // Intentar auto-reservar para pedidos EN_PRODUCCION que esperen este PT
      reserveForOrdersIfPossible((int)$op['product_pt_id']);

      db()->commit();
      $flash_ok = "OP #{$op_id} finalizada. PT ingresado a stock y reservas actualizadas.";
    } catch (Throwable $e) {
      db()->rollBack();
      $flash_err = "No se pudo finalizar la OP: " . $e->getMessage();
    }
  }
}

// ---------- Filtros / listado ----------
$estado = trim($_GET['estado'] ?? '');
$q      = trim($_GET['q'] ?? '');

$validEstados = ['PENDIENTE','EN_CURSO','FINALIZADA','OBSERVADA'];
$where = [];
$params = [];

if ($estado !== '' && in_array($estado, $validEstados, true)) {
  $where[] = "po.estado = ?";
  $params[] = $estado;
}
if ($q !== '') {
  $where[] = "(p.codigo LIKE ? OR p.nombre LIKE ? OR po.id = ?)";
  $params[] = "%$q%";
  $params[] = "%$q%";
  $params[] = ctype_digit($q) ? (int)$q : 0;
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$page  = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$off   = ($page - 1) * $limit;

$sqlCount = "SELECT COUNT(*) total
             FROM production_orders po
             JOIN products p ON p.id=po.product_pt_id
             $whereSql";
$st = db()->prepare($sqlCount);
$st->execute($params);
$total = (int)$st->fetch()['total'];
$pages = max(1, (int)ceil($total / $limit));

$sql = "SELECT po.id, po.order_id, po.product_pt_id, po.cantidad, po.estado, po.fecha_ini, po.fecha_fin,
               p.codigo, p.nombre
        FROM production_orders po
        JOIN products p ON p.id=po.product_pt_id
        $whereSql
        ORDER BY FIELD(po.estado, 'PENDIENTE','EN_CURSO','FINALIZADA','OBSERVADA'), po.id DESC
        LIMIT $limit OFFSET $off";
$st = db()->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

function page_url(int $p): string {
  $qs = $_GET; $qs['page'] = $p;
  return url('op.php') . '?' . http_build_query($qs);
}

function badge_op(string $s): string {
  $map = [
    'PENDIENTE'  => 'secondary',
    'EN_CURSO'   => 'warning',
    'FINALIZADA' => 'success',
    'OBSERVADA'  => 'danger',
  ];
  $cls = $map[$s] ?? 'secondary';
  return '<span class="badge bg-'.$cls.'">'.e($s).'</span>';
}

// ---------- UI ----------
include __DIR__ . '/../views/partials/header.php';
include __DIR__ . '/../views/partials/navbar.php';
?>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">Órdenes de Producción</h5>
    <a class="btn btn-outline-secondary" href="<?= url('pedidos.php') ?>">Ir a Pedidos</a>
  </div>

  <?php if ($flash_ok): ?><div class="alert alert-success"><?= e($flash_ok) ?></div><?php endif; ?>
  <?php if ($flash_err): ?><div class="alert alert-danger"><?= e($flash_err) ?></div><?php endif; ?>

  <form class="row g-2 mb-3" method="get" action="<?= url('op.php') ?>">
    <div class="col-md-4">
      <input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Buscar por código/nombre PT o #OP">
    </div>
    <div class="col-md-3">
      <select name="estado" class="form-select">
        <option value="">Todos los estados</option>
        <?php foreach ($validEstados as $e): ?>
          <option value="<?= $e ?>" <?= $estado===$e?'selected':'' ?>><?= $e ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2 d-grid">
      <button class="btn btn-outline-secondary">Filtrar</button>
    </div>
  </form>

  <div class="card shadow-sm">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th style="width:90px;">#OP</th>
              <th>Producto PT</th>
              <th class="text-end">Cant.</th>
              <th class="text-center">Estado</th>
              <th>Pedido Origen</th>
              <th style="width:260px;" class="text-end">Acciones</th>
            </tr>
          </thead>
          <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="6" class="text-center text-muted py-4">No hay OPs.</td></tr>
          <?php else: foreach ($rows as $r): ?>
            <tr>
              <td>#<?= (int)$r['id'] ?></td>
              <td><?= e($r['codigo']) ?> — <?= e($r['nombre']) ?></td>
              <td class="text-end"><?= (float)$r['cantidad'] ?></td>
              <td class="text-center"><?= badge_op($r['estado']) ?></td>
              <td><?= $r['order_id'] ? '#'.(int)$r['order_id'] : '-' ?></td>
              <td class="text-end">
                <button class="btn btn-sm btn-outline-secondary" type="button"
                        data-bs-toggle="collapse" data-bs-target="#bom<?= (int)$r['id'] ?>">
                  Ver BOM
                </button>
                <?php if ($r['estado']==='PENDIENTE'): ?>
                  <form method="post" class="d-inline" onsubmit="return confirm('¿Iniciar OP #<?= (int)$r['id'] ?>?');">
                    <input type="hidden" name="action" value="start">
                    <input type="hidden" name="op_id" value="<?= (int)$r['id'] ?>">
                    <button class="btn btn-sm btn-primary">Iniciar</button>
                  </form>
                <?php elseif ($r['estado']==='EN_CURSO'): ?>
                  <form method="post" class="d-inline" onsubmit="return confirm('¿Finalizar OP #<?= (int)$r['id'] ?>?');">
                    <input type="hidden" name="action" value="finish">
                    <input type="hidden" name="op_id" value="<?= (int)$r['id'] ?>">
                    <button class="btn btn-sm btn-success">Finalizar</button>
                  </form>
                <?php else: ?>
                  <span class="text-muted small">Sin acciones</span>
                <?php endif; ?>
              </td>
            </tr>
            <tr class="collapse" id="bom<?= (int)$r['id'] ?>">
              <td colspan="6" class="bg-light">
                <div class="p-3">
                  <div class="fw-semibold mb-2">BOM (x <?= (float)$r['cantidad'] ?>)</div>
                  <?php $bom = fetchBom((int)$r['product_pt_id']); ?>
                  <div class="table-responsive">
                    <table class="table table-sm mb-0">
                      <thead><tr><th>Código</th><th>Nombre</th><th>Unidad</th><th class="text-end">Por unidad</th><th class="text-end">Necesario</th><th class="text-end">Stock</th></tr></thead>
                      <tbody>
                        <?php foreach ($bom as $b): 
                          $need = (float)$b['cant_por_unidad'] * (float)$r['cantidad']; ?>
                          <tr>
                            <td><?= e($b['codigo']) ?></td>
                            <td><?= e($b['nombre']) ?></td>
                            <td><?= e($b['unidad']) ?></td>
                            <td class="text-end"><?= (float)$b['cant_por_unidad'] ?></td>
                            <td class="text-end"><?= $need ?></td>
                            <td class="text-end"><?= (float)$b['stock_actual'] ?></td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                </div>
              </td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Paginación -->
  <div class="d-flex justify-content-between align-items-center mt-3">
    <div class="small text-muted">
      Mostrando <?= $rows ? ($off + 1) : 0 ?>–<?= $off + count($rows) ?> de <?= $total ?>
    </div>
    <nav>
      <ul class="pagination pagination-sm mb-0">
        <li class="page-item <?= $page<=1 ? 'disabled' : '' ?>">
          <a class="page-link" href="<?= $page>1 ? page_url($page-1) : '#' ?>">&laquo; Anterior</a>
        </li>
        <li class="page-item disabled"><span class="page-link">Página <?= $page ?> / <?= $pages ?></span></li>
        <li class="page-item <?= $page>=$pages ? 'disabled' : '' ?>">
          <a class="page-link" href="<?= $page<$pages ? page_url($page+1) : '#' ?>">Siguiente &raquo;</a>
        </li>
      </ul>
    </nav>
  </div>
</div>
<?php include __DIR__ . '/../views/partials/footer.php'; ?>
