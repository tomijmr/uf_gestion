<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// DEBUG LOGGING
// echo "DEBUG: Starting script<br>";

require_once __DIR__ . '/../app/auth.php';
// echo "DEBUG: After auth<br>";
require_login();
// echo "DEBUG: After login check<br>";
require_once __DIR__ . '/../app/db.php';
// echo "DEBUG: After DB include<br>";
require_once __DIR__ . '/../app/helpers.php';

// ---------- Filtros y paginación ----------
$fe_desde = trim($_GET['desde'] ?? '');
$fe_hasta = trim($_GET['hasta'] ?? '');
$q        = trim($_GET['q'] ?? '');
$orden_fecha = $_GET['orden_fecha'] ?? 'DESC';

$page  = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$off   = ($page - 1) * $limit;

if ($orden_fecha !== 'ASC' && $orden_fecha !== 'DESC') $orden_fecha = 'DESC';
// Build WHERE
$where = ["o.estado = 'PRESUPUESTO'"]; // SOLO PRESUPUESTOS
$params = [];

// DEBUG DATABASE STATUS
try {
    $test = db()->query("SHOW TABLES LIKE 'orders'")->fetch();
    if (!$test) die("Table 'orders' not found!");
} catch (Exception $e) {
    die("DB Connection Error: " . $e->getMessage());
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

$whereSql = implode(' AND ', $where);

// Orden
$ordenSql = ($orden_fecha === 'ASC') ? 'ASC' : 'DESC';

// Count
$sqlCount = "SELECT COUNT(*) FROM orders o 
             LEFT JOIN customers c ON c.id=o.customer_id 
             WHERE $whereSql";
$stmtC = db()->prepare($sqlCount);
$stmtC->execute($params);
$total_rows = $stmtC->fetchColumn();
$total_pages = ceil($total_rows / $limit);

// Data
$sql = "SELECT o.*, COALESCE(c.nombre, o.cliente_manual) as cliente 
        FROM orders o
        LEFT JOIN customers c ON c.id=o.customer_id
        WHERE $whereSql
        ORDER BY o.fecha $ordenSql
        LIMIT $limit OFFSET $off";

$stmt = db()->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

include __DIR__ . '/../views/partials/header.php';
include __DIR__ . '/../views/partials/navbar.php';
?>

<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Presupuestos</h1>
    <a href="<?= url('pedido_nuevo.php?type=presupuesto') ?>" class="btn btn-primary">
      <i class="bi bi-plus-lg"></i> Nuevo Presupuesto
    </a>
  </div>

  <?php if (isset($_GET['ok'])): ?>
    <div class="alert alert-success fs-5">
      <i class="bi bi-check-circle"></i> Acción realizada con éxito.
    </div>
  <?php endif; ?>

  <!-- Filtros -->
  <div class="card mb-4 shadow-sm">
    <div class="card-body">
      <form class="row g-3" method="get">
        <div class="col-md-3">
          <label class="form-label">Buscar</label>
          <input type="text" name="q" class="form-control" placeholder="Cliente o ID..." value="<?= e($q) ?>">
        </div>
        <div class="col-md-2">
          <label class="form-label">Desde</label>
          <input type="date" name="desde" class="form-control" value="<?= e($fe_desde) ?>">
        </div>
        <div class="col-md-2">
          <label class="form-label">Hasta</label>
          <input type="date" name="hasta" class="form-control" value="<?= e($fe_hasta) ?>">
        </div>
        <div class="col-md-2">
          <label class="form-label">Orden</label>
          <select name="orden_fecha" class="form-select">
            <option value="DESC" <?= $orden_fecha==='DESC'?'selected':'' ?>>Más recientes</option>
            <option value="ASC" <?= $orden_fecha==='ASC'?'selected':'' ?>>Más antiguos</option>
          </select>
        </div>
        <div class="col-md-3 align-self-end d-flex gap-2">
          <button class="btn btn-primary flex-grow-1">Filtrar</button>
          <a href="<?= url('presupuestos.php') ?>" class="btn btn-outline-secondary">Limpiar</a>
        </div>
      </form>
    </div>
  </div>

  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead class="table-light">
        <tr>
          <th>ID</th>
          <th>Fecha</th>
          <th>Cliente</th>
          <th class="text-end">Total</th>
          <th class="text-center">Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php if (count($orders) === 0): ?>
          <tr><td colspan="6" class="text-center py-4 text-muted">No se encontraron presupuestos.</td></tr>
        <?php else: ?>
          <?php foreach ($orders as $o): ?>
            <tr>
              <td>#<?= (int)$o['id'] ?></td>
              <td><?= date('d/m/Y H:i', strtotime($o['fecha'])) ?></td>
              <td><?= e($o['cliente']) ?></td>
              <td class="text-end fw-bold">$<?= number_format((float)$o['total_neto'], 2, ',', '.') ?></td>
              <td class="text-center">
                <a href="<?= url('pedido_editar.php?order_id=' . $o['id']) ?>" class="btn btn-sm btn-outline-primary" title="Editar / Ver">
                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-pencil" viewBox="0 0 16 16"><path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168l10-10zM11.207 2.5 13.5 4.793 14.793 3.5 12.5 1.207 11.207 2.5zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.293l6.5-6.5zm-9.761 5.175-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325z"/></svg> Editar
                </a>
                <a href="<?= url('pedido_nuevo.php?export_presupuesto=1&order_id=' . $o['id']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="Imprimir PDF">
                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-printer" viewBox="0 0 16 16"><path d="M2.5 8a.5.5 0 1 0 0-1 .5.5 0 0 0 0 1z"/><path d="M5 1a2 2 0 0 0-2 2v2H2a2 2 0 0 0-2 2v3a2 2 0 0 0 2 2h1v1a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2v-1h1a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-1V3a2 2 0 0 0-2-2H5zM4 3a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2H4V3zm1 5a2 2 0 0 0-2 2v1H2a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1h-1v-1a2 2 0 0 0-2-2H5zm7 2v3a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-3a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1z"/></svg> PDF
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Paginación -->
  <?php if ($total_pages > 1): ?>
    <nav>
      <ul class="pagination justify-content-center">
        <?php for ($i=1; $i<=$total_pages; $i++): ?>
          <li class="page-item <?= ($i==$page)?'active':'' ?>">
            <a class="page-link" href="<?= url('presupuestos.php?page='.$i.'&q='.urlencode($q).'&desde='.$fe_desde.'&hasta='.$fe_hasta.'&orden_fecha='.$orden_fecha) ?>">
              <?= $i ?>
            </a>
          </li>
        <?php endfor; ?>
      </ul>
    </nav>
  <?php endif; ?>

</div>

<?php include __DIR__ . '/../views/partials/footer.php'; ?>
