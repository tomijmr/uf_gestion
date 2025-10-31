<?php
require_once __DIR__ . '/../app/auth.php';
require_login();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

include __DIR__ . '/../views/partials/header.php';
include __DIR__ . '/../views/partials/navbar.php';

// Parámetros
$q     = trim($_GET['q'] ?? '');
$page  = max(1, (int)($_GET['page'] ?? 1));
$limit = 50;
$off   = ($page - 1) * $limit;

// WHERE y parámetros
$where  = '';
$params = [];

if ($q !== '') {
  $where = "WHERE c.nombre LIKE ? OR c.cuit_dni LIKE ? OR c.telefono LIKE ? OR c.gym LIKE ?";
  $like  = "%$q%";
  $params = [$like, $like, $like, $like];
}

// Total de registros
$sqlCount = "SELECT COUNT(*) AS total FROM customers c $where";
$stmt = db()->prepare($sqlCount);
$stmt->execute($params);
$total = (int)$stmt->fetch()['total'];
$pages = max(1, (int)ceil($total / $limit));

// Consulta de datos
$sql = "SELECT 
          c.id, 
          c.nombre, 
          c.gym, 
          c.cuit_dni, 
          c.telefono, 
          COALESCE(v.saldo, 0) AS saldo
        FROM customers c
        LEFT JOIN v_cc_cliente v ON v.customer_id = c.id
        $where
        ORDER BY c.nombre ASC
        LIMIT $limit OFFSET $off";

$stmt = db()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Helper para links de paginación
function page_url(int $p, string $q): string {
  $qs = http_build_query(['q' => $q, 'page' => $p]);
  return url('clientes.php') . '?' . $qs;
}
?>

<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">Clientes</h5>
    <a class="btn btn-primary" href="<?= url('cliente_ver.php') ?>">Nuevo Cliente</a>
  </div>

  <form class="row g-2 mb-3" method="get" action="<?= url('clientes.php') ?>">
    <div class="col-md-10">
      <input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Buscar por Nombre / CUIT-DNI / Teléfono / Gym">
    </div>
    <div class="col-md-2 d-grid">
      <button class="btn btn-outline-secondary">Buscar</button>
    </div>
  </form>

  <div class="card shadow-sm">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th style="width: 70px;">ID</th>
              <th>Nombre / Razón Social</th>
              <th>Gym</th>
              <th>CUIT/DNI</th>
              <th>Teléfono</th>
              <th class="text-end">Saldo</th>
              <th class="text-end" style="width:120px;">Acciones</th>
            </tr>
          </thead>
          <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="7" class="text-center text-muted py-4">No se encontraron clientes.</td></tr>
          <?php else: ?>
            <?php foreach ($rows as $r): ?>
              <tr>
                <td><?= (int)$r['id'] ?></td>
                <td><?= e($r['nombre']) ?></td>
                <td><?= e($r['gym']) ?></td>
                <td><?= e($r['cuit_dni']) ?></td>
                <td><?= e($r['telefono']) ?></td>
                <td class="text-end"><?= money($r['saldo']) ?></td>
                <td class="text-end">
                  <a class="btn btn-sm btn-outline-primary" href="<?= url('cliente_ver.php') . '?id=' . (int)$r['id'] ?>">Ver</a>
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
        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
          <a class="page-link" href="<?= $page > 1 ? page_url($page-1, $q) : '#' ?>">&laquo; Anterior</a>
        </li>
        <li class="page-item disabled"><span class="page-link">Página <?= $page ?> / <?= $pages ?></span></li>
        <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>">
          <a class="page-link" href="<?= $page < $pages ? page_url($page+1, $q) : '#' ?>">Siguiente &raquo;</a>
        </li>
      </ul>
    </nav>
  </div>
</div>

<?php include __DIR__ . '/../views/partials/footer.php'; ?>
