<?php
// Mostrar errores para debug
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../app/auth.php';
require_login();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

// ---------- Acciones (POST) ----------
$flash_ok = '';
$flash_err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  if ($action === 'deliver') {
    $order_id = (int)($_POST['order_id'] ?? 0);
    try {
      db()->beginTransaction();

      // Traer pedido
      $sOrder = db()->prepare("SELECT id, customer_id, estado, saldo FROM orders WHERE id=? FOR UPDATE");
      $sOrder->execute([$order_id]);
      $order = $sOrder->fetch();
      if (!$order) throw new Exception("Pedido no encontrado");
      if ($order['estado'] !== 'LISTO_ENTREGA') throw new Exception("El pedido no está listo para entregar");

      // Ítems del pedido
      $sItems = db()->prepare("SELECT oi.product_id, oi.cant, p.tipo, p.stock_actual, p.stock_reservado
                               FROM order_items oi
                               JOIN products p ON p.id = oi.product_id
                               WHERE oi.order_id=? FOR UPDATE");
      $sItems->execute([$order_id]);
      $items = $sItems->fetchAll();
      if (!$items) throw new Exception("El pedido no tiene ítems");

      // Validar reservas suficientes
      foreach ($items as $it) {
        if ($it['tipo'] !== 'PT') continue;
        $reservado = (float)$it['stock_reservado'];
        if ($reservado < (float)$it['cant']) {
          throw new Exception("Reserva insuficiente para product_id {$it['product_id']}");
        }
      }

      // Ejecutar entrega
      $updProd = db()->prepare("UPDATE products 
                                SET stock_actual = stock_actual - ?, 
                                    stock_reservado = stock_reservado - ?
                                WHERE id=?");
      $insMove = db()->prepare("INSERT INTO stock_moves (fecha, tipo, motivo, product_id, cantidad, referencia_tipo, referencia_id, observaciones)
                                VALUES (NOW(), 'SALIDA', 'ENTREGA', ?, ?, 'ORDER', ?, 'Entrega de pedido')");

      foreach ($items as $it) {
        if ($it['tipo'] !== 'PT') continue;
        $cant = (float)$it['cant'];
        $updProd->execute([$cant, $cant, (int)$it['product_id']]);
        $insMove->execute([(int)$it['product_id'], $cant, $order_id]);
      }

      // Estado del pedido
      $nuevoEstado = 'ENTREGADO';
      db()->prepare("UPDATE orders SET estado=? WHERE id=?")->execute([$nuevoEstado, $order_id]);

      // Si el saldo quedó en 0, cerrar
      $saldo = (float)$order['saldo'];
      if ($saldo <= 0.00001) {
        db()->prepare("UPDATE orders SET estado='CERRADO' WHERE id=?")->execute([$order_id]);
        $nuevoEstado = 'CERRADO';
      }

      db()->commit();
      $flash_ok = "Pedido #$order_id marcado como $nuevoEstado.";
    } catch (Throwable $e) {
      db()->rollBack();
      $flash_err = 'No se pudo entregar el pedido: ' . $e->getMessage();
    }
  }
}

// ---------- Filtros y paginación ----------
$estados = ['BORRADOR','CONFIRMADO','EN_PRODUCCION','LISTO_ENTREGA','ENTREGADO','CERRADO'];
$fe_desde = trim($_GET['desde'] ?? '');
$fe_hasta = trim($_GET['hasta'] ?? '');
$q        = trim($_GET['q'] ?? '');
$estado   = trim($_GET['estado'] ?? '');
$orden_fecha = $_GET['orden_fecha'] ?? 'DESC';

$page  = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$off   = ($page - 1) * $limit;

// Build WHERE
$where = ["o.estado != 'PRESUPUESTO'"]; // Excluir presupuestos
$params = [];

if ($estado !== '' && in_array($estado, $estados, true)) {
  $where[] = "o.estado = ?";
  $params[] = $estado;
}
if ($q !== '') {
  if (ctype_digit($q)) {
    $where[] = "(o.id = ? OR c.nombre LIKE ? OR o.cliente_manual LIKE ?)";
    $params[] = (int)$q;
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
  } else {
    $where[] = "(c.nombre LIKE ? OR o.cliente_manual LIKE ?)";
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
  }
}
if ($fe_desde !== '') {
  $where[] = "DATE(o.fecha) >= ?";

  $params[] = $fe_desde;
}
if ($fe_hasta !== '') {
  $where[] = "DATE(o.fecha) <= ?";
  $params[] = $fe_hasta;
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// Validar orden
$orden_fecha = in_array($orden_fecha, ['ASC', 'DESC']) ? $orden_fecha : 'DESC';

// Totales
$sqlCount = "SELECT COUNT(*) total
             FROM orders o
             LEFT JOIN customers c ON c.id=o.customer_id
             $whereSql";
$st = db()->prepare($sqlCount);
$st->execute($params);
$total = (int)($st->fetch()['total'] ?? 0);
$pages = max(1, (int)ceil($total / $limit));

// Datos
$sql = "SELECT o.id, o.fecha, o.fecha_entrega, o.estado, o.total_neto, o.saldo, o.senia, o.observaciones, o.transporte_bonificado, o.empresa_transporte, COALESCE(c.nombre, o.cliente_manual) AS cliente
        FROM orders o
        LEFT JOIN customers c ON c.id=o.customer_id
        $whereSql
        ORDER BY o.fecha $orden_fecha, o.id $orden_fecha
        LIMIT $limit OFFSET $off";
$st = db()->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

// Items por pedido
function getItems(int $order_id): array {
  $s = db()->prepare("SELECT oi.product_id, p.codigo, p.nombre, p.tipo, oi.cant, oi.precio_unit, oi.subtotal
                      FROM order_items oi
                      JOIN products p ON p.id=oi.product_id
                      WHERE oi.order_id=?");
  $s->execute([$order_id]);
  return $s->fetchAll();
}

// Badges
function badge_estado(string $estado): string {
  $map = [
    'BORRADOR'       => 'secondary',
    'CONFIRMADO'     => 'info',
    'EN_PRODUCCION'  => 'warning',
    'LISTO_ENTREGA'  => 'primary',
    'ENTREGADO'      => 'success',
    'CERRADO'        => 'dark',
  ];
  $cls = $map[$estado] ?? 'secondary';
  return '<span class="badge bg-' . $cls . '">' . e($estado) . '</span>';
}

function page_url(int $p): string {
  $qs = $_GET;
  $qs['page'] = $p;
  return url('pedidos.php') . '?' . http_build_query($qs);
}

include __DIR__ . '/../views/partials/header.php';
include __DIR__ . '/../views/partials/navbar.php';
?>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">Pedidos</h5>
    <a class="btn btn-primary" href="<?= url('pedido_nuevo.php?type=pedido') ?>">Nuevo Pedido</a>
  </div>

  <?php if ($flash_ok): ?>
    <div class="alert alert-success"><?= e($flash_ok) ?></div>
  <?php endif; ?>
  <?php if ($flash_err): ?>
    <div class="alert alert-danger"><?= e($flash_err) ?></div>
  <?php endif; ?>

  <form class="row g-2 mb-3" method="get" action="<?= url('pedidos.php') ?>">
    <div class="col-md-2">
      <input class="form-control" name="q" value="<?= e($q) ?>" placeholder="ID o Cliente">
    </div>
    <div class="col-md-2">
      <select name="estado" class="form-select">
        <option value="">Todos los estados</option>
        <?php foreach ($estados as $e): ?>
          <option value="<?= $e ?>" <?= $estado===$e?'selected':'' ?>><?= $e ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <input type="date" name="desde" class="form-control" value="<?= e($fe_desde) ?>" placeholder="Desde">
    </div>
    <div class="col-md-2">
      <input type="date" name="hasta" class="form-control" value="<?= e($fe_hasta) ?>" placeholder="Hasta">
    </div>
    <div class="col-md-2">
      <select name="orden_fecha" class="form-select">
        <option value="DESC" <?= $orden_fecha==='DESC'?'selected':'' ?>>Más recientes</option>
        <option value="ASC" <?= $orden_fecha==='ASC'?'selected':'' ?>>Más antiguos</option>
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
              <th style="width:90px;">#</th>
              <th>Fecha</th>
              <th>Entrega</th>
              <th>Cliente</th>
              <th class="text-end">Total</th>
              <th class="text-end">Saldo</th>
              <th class="text-center">Estado</th>
              <th class="text-end" style="width:220px;">Acciones</th>
            </tr>
          </thead>
          <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="8" class="text-center text-muted py-4">No hay pedidos.</td></tr>
          <?php else: ?>
            <?php foreach ($rows as $r): ?>
              <?php $items = getItems((int)$r['id']); ?>
              <tr>
                <td>#<?= (int)$r['id'] ?></td>
                <td><?= e($r['fecha']) ?></td>
                <td><?= $r['fecha_entrega'] ? e($r['fecha_entrega']) : '-' ?></td>
                <td><?= e($r['cliente']) ?></td>
                <td class="text-end"><?= money($r['total_neto']) ?></td>
                <td class="text-end"><?= money($r['saldo']) ?></td>
                <td class="text-center"><?= badge_estado($r['estado']) ?></td>
                <td class="text-end">
                  <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#it<?= (int)$r['id'] ?>">Ver</button>
                  <a class="btn btn-sm btn-outline-success" href="<?= url('remito_pedido.php?id=' . (int)$r['id']) ?>" target="_blank" title="Generar Remito"><i class="bi bi-truck"></i> Remito</a>
                  <?php if (!in_array($r['estado'], ['ENTREGADO','CERRADO'], true)): ?>
                    <a class="btn btn-sm btn-outline-primary" href="<?= url('pedido_editar.php?order_id=' . (int)$r['id']) ?>">Editar</a>
                  <?php endif; ?>
                  <?php if ($r['estado'] === 'LISTO_ENTREGA'): ?>
                    <form method="post" class="d-inline" onsubmit="return confirm('¿Entregar el pedido #<?= (int)$r['id'] ?>?');">
                      <input type="hidden" name="action" value="deliver">
                      <input type="hidden" name="order_id" value="<?= (int)$r['id'] ?>">
                      <button class="btn btn-sm btn-primary">Entregar</button>
                    </form>
                  <?php endif; ?>
                </td>
              </tr>

              <tr class="collapse" id="it<?= (int)$r['id'] ?>">
                <td colspan="8" class="bg-light">
                  <div class="p-3">
                    <div class="row mb-3">
                      <div class="col-md-6">
                        <div class="fw-semibold mb-2">Ítems del pedido #<?= (int)$r['id'] ?></div>
                      </div>
                      <div class="col-md-6 text-end">
                        <div class="small">
                          <span class="badge <?= $r['senia'] > 0 ? 'bg-success' : 'bg-secondary' ?>">
                            <?= $r['senia'] > 0 ? 'Señado: ' . money($r['senia']) : 'Sin seña' ?>
                          </span>
                          <span class="badge <?= $r['transporte_bonificado'] ? 'bg-info' : 'bg-secondary' ?> ms-1">
                            <?= $r['transporte_bonificado'] ? 'Transporte bonificado' : 'Transporte no bonificado' ?>
                          </span>
                          <?php if ($r['empresa_transporte']): ?>
                            <span class="badge bg-primary ms-1">
                              <?= e($r['empresa_transporte']) ?>
                            </span>
                          <?php endif; ?>
                        </div>
                      </div>
                    </div>
                    <div class="table-responsive">
                      <table class="table table-sm mb-0">
                        <thead>
                          <tr>
                            <th>Código</th>
                            <th>Nombre</th>
                            <th class="text-center">Tipo</th>
                            <th class="text-end">Precio</th>
                            <th class="text-end">Cant</th>
                            <th class="text-end">Subtotal</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php foreach ($items as $it): ?>
                            <tr>
                              <td><?= e($it['codigo']) ?></td>
                              <td><?= e($it['nombre']) ?></td>
                              <td class="text-center"><?= e($it['tipo']) ?></td>
                              <td class="text-end"><?= money($it['precio_unit']) ?></td>
                              <td class="text-end"><?= (float)$it['cant'] ?></td>
                              <td class="text-end"><?= money($it['subtotal']) ?></td>
                            </tr>
                          <?php endforeach; ?>
                        </tbody>
                      </table>
                    </div>

                    <?php if (!empty($r['observaciones'])): ?>
                      <div class="mt-3 p-2 bg-warning bg-opacity-10 border border-warning rounded">
                        <strong>Notas:</strong> <?= nl2br(e($r['observaciones'])) ?>
                      </div>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>

            <?php endforeach; ?>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

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
