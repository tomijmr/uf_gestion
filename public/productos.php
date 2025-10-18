<?php
require_once __DIR__ . '/../app/auth.php';
require_login();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

include __DIR__ . '/../views/partials/header.php';
include __DIR__ . '/../views/partials/navbar.php';

// --------- Acciones rápidas ----------
$flash_ok = '';
$flash_err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  if ($action === 'toggle_activo') {
    try {
      $id = (int)($_POST['id'] ?? 0);
      $st = db()->prepare("UPDATE products SET activo = 1 - activo WHERE id=?");
      $st->execute([$id]);
      $flash_ok = "Producto #$id actualizado (activo/inactivo).";
    } catch (Throwable $e) {
      $flash_err = "No se pudo actualizar: " . $e->getMessage();
    }
  }

  if ($action === 'alta_rapida') {
    try {
      $codigo = trim($_POST['codigo'] ?? '');
      $nombre = trim($_POST['nombre'] ?? '');
      $tipo   = $_POST['tipo'] ?? 'MP';
      $unidad = trim($_POST['unidad'] ?? 'UN');
      $precio = (float)($_POST['precio_std'] ?? 0);
      $stock_minimo = isset($_POST['stock_minimo']) ? (float)$_POST['stock_minimo'] : 0;

      if ($codigo === '' || $nombre === '') throw new Exception('Código y Nombre son obligatorios.');
      if (!in_array($tipo, ['MP','PT'], true)) throw new Exception('Tipo inválido.');

      $sql = "INSERT INTO products (codigo, nombre, tipo, unidad, precio_std, stock_actual, stock_reservado, activo, margen_pct, stock_minimo)
              VALUES (?,?,?,?,?,0,0,1,30.00,?)";
      db()->prepare($sql)->execute([$codigo, $nombre, $tipo, $unidad, $precio, $stock_minimo]);

      $flash_ok = "Producto creado correctamente.";
    } catch (Throwable $e) {
      $flash_err = "No se pudo crear: " . $e->getMessage();
    }
  }
}

// --------- Filtros / listado ----------
$q    = trim($_GET['q'] ?? '');
$tipo = trim($_GET['tipo'] ?? '');

$where = [];
$params = [];
if ($q !== '') {
  $where[] = "(p.codigo LIKE ? OR p.nombre LIKE ?)";
  $params[] = "%$q%";
  $params[] = "%$q%";
}
if ($tipo !== '' && in_array($tipo, ['MP','PT'], true)) {
  $where[] = "p.tipo = ?";
  $params[] = $tipo;
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$page  = max(1, (int)($_GET['page'] ?? 1));
$limit = 50;
$off   = ($page - 1) * $limit;

// Contador
$sqlCount = "SELECT COUNT(*) total FROM products p $whereSql";
$st = db()->prepare($sqlCount);
$st->execute($params);
$total = (int)$st->fetch()['total'];
$pages = max(1, (int)ceil($total / $limit));

/**
 * Traemos productos + costo_bom (para PT), margen_pct y disponibles/min.
 */
$sql = "
  SELECT
    p.id, p.codigo, p.nombre, p.tipo, p.unidad, p.precio_std,
    p.stock_actual, p.stock_reservado, p.stock_minimo,
    (p.stock_actual - p.stock_reservado) AS stock_disponible,
    p.activo, p.margen_pct,
    COALESCE(bc.costo_bom, 0) AS costo_bom
  FROM products p
  LEFT JOIN (
    SELECT b.product_pt_id AS pt_id, SUM(b.cant_por_unidad * mp.precio_std) AS costo_bom
    FROM product_bom b
    JOIN products mp ON mp.id = b.component_id
    GROUP BY b.product_pt_id
  ) bc ON bc.pt_id = p.id
  $whereSql
  ORDER BY p.tipo, p.nombre
  LIMIT $limit OFFSET $off
";
$st = db()->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

function page_url(int $p): string {
  $qs = $_GET; $qs['page'] = $p;
  return url('productos.php') . '?' . http_build_query($qs);
}

function badge_margen(?float $m): string {
  if ($m === null) return '<span class="badge bg-secondary">—</span>';
  if ($m < 20) return '<span class="badge bg-danger">'.number_format($m, 2, ',', '.').'%</span>';
  if ($m < 30) return '<span class="badge bg-warning text-dark">'.number_format($m, 2, ',', '.').'%</span>';
  return '<span class="badge bg-success">'.number_format($m, 2, ',', '.').'%</span>';
}
?>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">Productos</h5>
    <a class="btn btn-primary" href="<?= url('producto_ver.php') ?>">Nuevo Producto</a>
  </div>

  <?php if ($flash_ok): ?><div class="alert alert-success"><?= e($flash_ok) ?></div><?php endif; ?>
  <?php if ($flash_err): ?><div class="alert alert-danger"><?= e($flash_err) ?></div><?php endif; ?>

  <form class="row g-2 mb-3" method="get" action="<?= url('productos.php') ?>">
    <div class="col-md-6">
      <input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Buscar por Código / Nombre">
    </div>
    <div class="col-md-2">
      <select name="tipo" class="form-select">
        <option value="">MP y PT</option>
        <option value="MP" <?= $tipo==='MP'?'selected':'' ?>>Materia Prima</option>
        <option value="PT" <?= $tipo==='PT'?'selected':'' ?>>Producto Terminado</option>
      </select>
    </div>
    <div class="col-md-2 d-grid">
      <button class="btn btn-outline-secondary">Filtrar</button>
    </div>
    <div class="col-md-2 d-grid">
      <a class="btn btn-outline-secondary" href="<?= url('productos.php') ?>">Limpiar</a>
    </div>
  </form>

  <!-- Alta rápida -->
  <div class="card shadow-sm mb-4">
    <div class="card-body">
      <h6 class="mb-3">Alta rápida</h6>
      <form class="row g-2" method="post">
        <input type="hidden" name="action" value="alta_rapida">
        <div class="col-md-2">
          <input class="form-control" name="codigo" placeholder="Código *" required>
        </div>
        <div class="col-md-4">
          <input class="form-control" name="nombre" placeholder="Nombre *" required>
        </div>
        <div class="col-md-2">
          <select name="tipo" class="form-select">
            <option value="MP">MP</option>
            <option value="PT">PT</option>
          </select>
        </div>
        <div class="col-md-1">
          <input class="form-control" name="unidad" value="UN">
        </div>
        <div class="col-md-1">
          <input class="form-control" type="number" step="0.01" name="precio_std" placeholder="$ (MP)">
        </div>
        <div class="col-md-2">
          <input class="form-control" type="number" step="0.001" name="stock_minimo" placeholder="Stock mín.">
        </div>
        <div class="col-12 d-grid d-md-block mt-2">
          <button class="btn btn-primary">Crear</button>
        </div>
      </form>
    </div>
  </div>

  <div class="card shadow-sm">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th style="width:70px;">ID</th>
              <th>Código</th>
              <th>Nombre</th>
              <th class="text-center">Tipo</th>
              <th>Unidad</th>
              <th class="text-end" title="Precio de venta estándar">Precio</th>
              <th class="text-end" title="Costo BOM (solo PT)">Costo BOM</th>
              <th class="text-center" title="Margen efectivo desde BOM">Margen</th>
              <th class="text-end">Stock</th>
              <th class="text-end">Reservado</th>
              <th class="text-end">Disponible</th>
              <th class="text-end">Mínimo</th>
              <th class="text-center">Activo</th>
              <th class="text-end" style="width:260px;">Acciones</th>
            </tr>
          </thead>
          <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="14" class="text-center text-muted py-4">No hay productos.</td></tr>
          <?php else: foreach ($rows as $r):
            $isPT = ($r['tipo'] === 'PT');
            $costoBOM = $isPT ? (float)$r['costo_bom'] : null;
            $precio   = (float)$r['precio_std'];
            $disp     = (float)$r['stock_disponible'];
            $min      = (float)$r['stock_minimo'];
            $rowClass = ($disp <= $min && $min > 0) ? 'table-warning' : '';
            $margenEf = null;
            if ($isPT) {
              if ($costoBOM > 0) {
                $margenEf = (($precio - $costoBOM) / $costoBOM) * 100.0;
              } elseif ($precio > 0) {
                $margenEf = 9999.0; // ∞
              }
            }
          ?>
            <tr class="<?= $rowClass ?>">
              <td><?= (int)$r['id'] ?></td>
              <td><?= e($r['codigo']) ?></td>
              <td><?= e($r['nombre']) ?></td>
              <td class="text-center"><?= e($r['tipo']) ?></td>
              <td><?= e($r['unidad']) ?></td>
              <td class="text-end"><?= money($precio) ?></td>
              <td class="text-end"><?= $isPT ? money($costoBOM) : '—' ?></td>
              <td class="text-center">
                <?php
                  if (!$isPT) {
                    echo '<span class="badge bg-secondary">—</span>';
                  } else {
                    if ($margenEf === null) echo '<span class="badge bg-secondary">—</span>';
                    elseif ($margenEf >= 9999) echo '<span class="badge bg-success">∞</span>';
                    else echo badge_margen($margenEf);
                  }
                ?>
              </td>
              <td class="text-end"><?= (float)$r['stock_actual'] ?></td>
              <td class="text-end"><?= (float)$r['stock_reservado'] ?></td>
              <td class="text-end fw-semibold"><?= $disp ?></td>
              <td class="text-end"><?= $min ?></td>
              <td class="text-center">
                <span class="badge bg-<?= $r['activo']?'success':'secondary' ?>"><?= $r['activo']?'Sí':'No' ?></span>
              </td>
              <td class="text-end">
                <a class="btn btn-sm btn-outline-primary" href="<?= url('producto_ver.php') . '?id=' . (int)$r['id'] ?>">Editar</a>
                <?php if ($r['tipo']==='PT'): ?>
                  <a class="btn btn-sm btn-outline-secondary" href="<?= url('producto_ver.php') . '?id=' . (int)$r['id'] . '#bom' ?>">BOM</a>
                <?php endif; ?>
                <form method="post" class="d-inline">
                  <input type="hidden" name="action" value="toggle_activo">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <button class="btn btn-sm <?= $r['activo']?'btn-outline-danger':'btn-outline-success' ?>">
                    <?= $r['activo']?'Desactivar':'Activar' ?>
                  </button>
                </form>
              </td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Leyenda margen -->
  <div class="mt-3 small text-muted">
    <span class="badge bg-danger">Margen &lt; 20%</span>
    <span class="badge bg-warning text-dark ms-2">20% – 30%</span>
    <span class="badge bg-success ms-2">&ge; 30%</span>
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
