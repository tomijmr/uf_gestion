<?php
require_once __DIR__ . '/../app/auth.php';
require_login();
require_role('ADMIN','DEPOSITO');
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

$flash_ok = $_GET['ok'] ?? '';
$flash_err = $_GET['err'] ?? '';

$q = trim($_GET['q'] ?? '');
$where = '';
$params = [];
if ($q !== '') {
  $where = "WHERE (p.proveedor LIKE ? OR p.comp_numero LIKE ? OR p.comp_tipo LIKE ?)";
  $like = "%$q%";
  $params = [$like, $like, $like];
}

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 25;
$off   = ($page-1) * $limit;

$st = db()->prepare("SELECT COUNT(*) FROM purchases p $where");
$st->execute($params);
$total = (int)$st->fetchColumn();
$pages = max(1, (int)ceil($total / $limit));

$sql = "SELECT p.*, u.nombre AS usuario
        FROM purchases p
        LEFT JOIN users u ON u.id = p.created_by
        $where
        ORDER BY p.fecha DESC, p.id DESC
        LIMIT $limit OFFSET $off";
$st = db()->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

function page_url($p) {
  $qs = $_GET; $qs['page'] = $p;
  return url('compras.php') . '?' . http_build_query($qs);
}

include __DIR__ . '/../views/partials/header.php';
include __DIR__ . '/../views/partials/navbar.php';
?>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">Compras de MP</h5>
    <a class="btn btn-primary" href="<?= url('compra_nueva.php') ?>">Registrar compra</a>
  </div>

  <?php if ($flash_ok): ?><div class="alert alert-success"><?= e($flash_ok) ?></div><?php endif; ?>
  <?php if ($flash_err): ?><div class="alert alert-danger"><?= e($flash_err) ?></div><?php endif; ?>

  <form class="row g-2 mb-3" method="get">
    <div class="col-md-6">
      <input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Buscar por proveedor / tipo / número">
    </div>
    <div class="col-md-2 d-grid">
      <button class="btn btn-outline-secondary">Filtrar</button>
    </div>
    <div class="col-md-2 d-grid">
      <a class="btn btn-outline-secondary" href="<?= url('compras.php') ?>">Limpiar</a>
    </div>
  </form>

  <div class="card shadow-sm">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th style="width:90px;">ID</th>
              <th>Fecha</th>
              <th>Proveedor</th>
              <th>Comprobante</th>
              <th class="text-end">Total</th>
              <th>Archivo</th>
              <th>Usuario</th>
            </tr>
          </thead>
          <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="7" class="text-center text-muted py-4">Sin compras registradas.</td></tr>
          <?php else: foreach ($rows as $r): ?>
            <tr>
              <td><?= (int)$r['id'] ?></td>
              <td><?= e($r['fecha']) ?></td>
              <td><?= e($r['proveedor']) ?></td>
              <td><?= e($r['comp_tipo']) ?> <?= e($r['comp_serie']) ?> <?= e($r['comp_numero']) ?></td>
              <td class="text-end">$ <?= number_format((float)$r['total'], 2, ',', '.') ?></td>
              <td>
                <?php if ($r['archivo_path']): ?>
                  <a target="_blank" href="<?= url($r['archivo_path']) ?>">Ver</a>
                <?php else: ?>—<?php endif; ?>
              </td>
              <td><?= e($r['usuario'] ?? '—') ?></td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="d-flex justify-content-between align-items-center mt-3">
    <div class="small text-muted">Mostrando <?= $rows?($off+1):0 ?>–<?= $off+count($rows) ?> de <?= $total ?></div>
    <ul class="pagination pagination-sm mb-0">
      <li class="page-item <?= $page<=1?'disabled':'' ?>"><a class="page-link" href="<?= $page>1?page_url($page-1):'#' ?>">&laquo; Anterior</a></li>
      <li class="page-item disabled"><span class="page-link">Página <?= $page ?>/<?= $pages ?></span></li>
      <li class="page-item <?= $page>=$pages?'disabled':'' ?>"><a class="page-link" href="<?= $page<$pages?page_url($page+1):'#' ?>">Siguiente &raquo;</a></li>
    </ul>
  </div>
</div>
<?php include __DIR__ . '/../views/partials/footer.php'; ?>
