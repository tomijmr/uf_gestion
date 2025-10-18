<?php
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
        if ($it['tipo'] !== 'PT') continue; // sólo PT generan entrega de stock
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
        // salida de stock y baja de reserva (mismo valor)
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
$q        = trim($_GET['q'] ?? ''); // cliente/ID
$estado   = trim($_GET['estado'] ?? '');

$page  = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$off   = ($page - 1) * $limit;

// Build WHERE
$where = [];
$params = [];
if ($estado !== '' && in_array($estado, $estados, true)) {
  $where[] = "o.estado = ?";
  $params[] = $estado;
}
if ($q !== '') {
  // match por ID exacto o por nombre cliente LIKE
  if (ctype_digit($q)) {
    $where[] = "(o.id = ? OR c.nombre LIKE ?)";
    $params[] = (int)$q;
    $params[] = '%' . $q . '%';
  } else {
    $where[] = "(c.nombre LIKE ?)";
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

// Totales
$sqlCount = "SELECT COUNT(*) total
             FROM orders o
             JOIN customers c ON c.id=o.customer_id
             $whereSql";
$st = db()->prepare($sqlCount);
$st->execute($params);
$total = (int)$st->fetch()['total'];
$pages = max(1, (int)ceil($total / $limit));

// Datos
$sql = "SELECT o.id, o.fecha, o.estado, o.total_neto, o.saldo, c.nombre AS cliente
        FROM orders o
        JOIN customers c ON c.id=o.customer_id
        $whereSql
        ORDER BY o.fecha DESC, o.id DESC
        LIMIT $limit OFFSET $off";
$st = db()->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

// Items por pedido (para vista rápida)
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

// Links paginación
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
    <a class="btn btn-primary" href="<?= url('pedido_nuevo.php') ?>">Nuevo Pedido</a>
  </div>

  <?php if ($flash_ok): ?>
    <div class="alert alert-success"><?= e($flash_ok) ?></div>
  <?php endif; ?>
  <?php if ($flash_err): ?>
    <div class="alert alert-danger"><?= e($flash_err) ?></div>
  <?php endif; ?>

  <form class="row g-2 mb-3" method="get" action="<?= url('pedidos.php') ?>">
    <div class="col-md-3">
      <input class="form-control" name="q" value="<?= e($q) ?>" placeholder="ID Pedido o Cliente">
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
      <input type="date" name="desde" class="form-control" value="<?= e($fe_desde) ?>">
    </div>
    <div class="col-md-2">
      <input type="date" name="hasta" class="form-control" value="<?= e($fe_hasta) ?>">
    </div>
    <div class="col-md-3 d-grid">
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
              <th>Cliente</th>
              <th class="text-end">Total</th>
              <th class="text-end">Saldo</th>
              <th class="text-center">Estado</th>
              <th class="text-end" style="width:220px;">Acciones</th>
            </tr>
          </thead>
          <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="7" class="text-center text-muted py-4">No hay pedidos.</td></tr>
          <?php else: ?>
            <?php foreach ($rows as $r): ?>
              <?php $items = getItems((int)$r['id']); ?>
              <tr>
                <td>#<?= (int)$r['id'] ?></td>
                <td><?= e($r['fecha']) ?></td>
                <td><?= e($r['cliente']) ?></td>
                <td class="text-end"><?= money($r['total_neto']) ?></td>
                <td class="text-end"><?= money($r['saldo']) ?></td>
                <td class="text-center"><?= badge_estado($r['estado']) ?></td>
                <td class="text-end">
                  <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#it<?= (int)$r['id'] ?>">Ver</button>
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
                <td colspan="7" class="bg-light">
                  <div class="p-3">
                    <div class="fw-semibold mb-2">Ítems del pedido #<?= (int)$r['id'] ?></div>
                    <div class="table-responsive">
                      <table class="table table-sm mb-0">
                        <thead><tr><th>Código</th><th>Nombre</th><th class="text-center">Tipo</th><th class="text-end">Precio</th><th class="text-end">Cant</th><th class="text-end">Subtotal</th></tr></thead>
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
