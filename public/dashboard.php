<?php
require_once __DIR__ . '/../app/auth.php';
require_login();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

include __DIR__ . '/../views/partials/header.php';
include __DIR__ . '/../views/partials/navbar.php';

$q = trim($_GET['q'] ?? '');

$where = "p.activo=1 AND p.stock_minimo > 0 AND (p.stock_actual - p.stock_reservado) <= p.stock_minimo";
$params = [];
if ($q !== '') {
  $where .= " AND (p.codigo LIKE ? OR p.nombre LIKE ?)";
  $like = "%$q%";
  $params[] = $like; $params[] = $like;
}

$sql = "
  SELECT
    p.id, p.codigo, p.nombre, p.tipo, p.unidad,
    p.stock_actual, p.stock_reservado, p.stock_minimo,
    (p.stock_actual - p.stock_reservado) AS disponible
  FROM products p
  WHERE $where
  ORDER BY (p.stock_actual - p.stock_reservado) ASC, p.nombre ASC
  LIMIT 200
";
$st = db()->prepare($sql);
$st->execute($params);
$alerts = $st->fetchAll();

// Contadores rápidos
$crit = 0; $warn = 0;
foreach ($alerts as $a) {
  $gap = (float)$a['disponible'] - (float)$a['stock_minimo'];
  if ($gap <= 0 && (float)$a['disponible'] <= 0) $crit++;
  elseif ($gap <= 0) $warn++;
}

function badge_severity($disp, $min) {
  if ($disp <= 0) return '<span class="badge bg-danger">Crítico</span>';
  return '<span class="badge bg-warning text-dark">Bajo mínimo</span>';
}
?>
<div class="container py-4">
  <h5 class="mb-3">Dashboard</h5>

  <div class="row g-3 mb-3">
    <div class="col-md-3">
      <div class="card shadow-sm">
        <div class="card-body">
          <div class="text-muted">Alertas de stock</div>
          <div class="display-6"><?= count($alerts) ?></div>
          <div class="small">
            <span class="badge bg-danger">Críticos: <?= $crit ?></span>
            <span class="badge bg-warning text-dark ms-2">Bajo mín.: <?= $warn ?></span>
          </div>
        </div>
      </div>
    </div>
    <!-- Podés sumar más KPIs acá -->
  </div>

  <div class="card shadow-sm">
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="mb-0">Alertas de stock</h6>
        <form class="d-flex" method="get" action="<?= url('index.php') ?>">
          <input class="form-control form-control-sm me-2" name="q" value="<?= e($q) ?>" placeholder="Buscar producto...">
          <button class="btn btn-sm btn-outline-secondary">Buscar</button>
        </form>
      </div>

      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th style="width:70px;">ID</th>
              <th>Código</th>
              <th>Nombre</th>
              <th class="text-center">Tipo</th>
              <th class="text-end">Stock</th>
              <th class="text-end">Reservado</th>
              <th class="text-end">Disponible</th>
              <th class="text-end">Mínimo</th>
              <th class="text-center">Severidad</th>
              <th class="text-end" style="width:220px;">Acciones</th>
            </tr>
          </thead>
          <tbody>
          <?php if (!$alerts): ?>
            <tr><td colspan="10" class="text-center text-muted py-4">Sin alertas. Tu almacén respira tranquilo.</td></tr>
          <?php else: foreach ($alerts as $r): ?>
            <tr class="<?= ((float)$r['disponible'] <= 0) ? 'table-danger' : 'table-warning' ?>">
              <td><?= (int)$r['id'] ?></td>
              <td><?= e($r['codigo']) ?></td>
              <td><?= e($r['nombre']) ?></td>
              <td class="text-center"><?= e($r['tipo']) ?></td>
              <td class="text-end"><?= (float)$r['stock_actual'] ?></td>
              <td class="text-end"><?= (float)$r['stock_reservado'] ?></td>
              <td class="text-end fw-semibold"><?= (float)$r['disponible'] ?></td>
              <td class="text-end"><?= (float)$r['stock_minimo'] ?></td>
              <td class="text-center"><?= badge_severity((float)$r['disponible'], (float)$r['stock_minimo']) ?></td>
              <td class="text-end">
                <a class="btn btn-sm btn-outline-primary" href="<?= url('producto_ver.php') . '?id=' . (int)$r['id'] ?>">Editar</a>
                <a class="btn btn-sm btn-outline-secondary" href="<?= url('stock.php') ?>?q_inv=<?= urlencode($r['codigo']) ?>">Ver stock</a>
                <!-- Si luego sumamos Compras: linkeá a OC pre-cargada -->
                <!-- <a class="btn btn-sm btn-success" href="<?= url('compras_oc_nueva.php') ?>?pref=<?= (int)$r['id'] ?>">Crear OC</a> -->
              </td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>

      <p class="small text-muted mt-2 mb-0">
        La alerta se dispara cuando <strong>disponible ≤ mínimo</strong>, donde disponible = stock actual − reservado.
      </p>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../views/partials/footer.php'; ?>
