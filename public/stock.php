<?php
require_once __DIR__ . '/../app/auth.php';
require_login();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

// -----------------------------------------------------------
// Acciones: Ajuste de stock (Entrada/Salida, motivo, observa.)
// -----------------------------------------------------------
$flash_ok = '';
$flash_err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'ajuste') {
  $product_id = (int)($_POST['product_id'] ?? 0);
  $signo      = $_POST['signo'] ?? 'ENTRADA'; // ENTRADA|SALIDA
  $motivo     = $_POST['motivo'] ?? 'AJUSTE'; // AJUSTE por defecto
  $cantidad   = max(0, (float)($_POST['cantidad'] ?? 0));
  $obs        = trim($_POST['observaciones'] ?? '');

  try {
    if ($product_id <= 0 || $cantidad <= 0) {
      throw new Exception('Producto y cantidad son obligatorios.');
    }
    if (!in_array($signo, ['ENTRADA','SALIDA'], true)) {
      throw new Exception('Signo inválido.');
    }
    $motivosValid = ['AJUSTE','COMPRA','VENTA','PROD_CONSUMO','PROD_ALTA','RESERVA','LIBERACION','ENTREGA'];
    if (!in_array($motivo, $motivosValid, true)) {
      throw new Exception('Motivo inválido.');
    }

    db()->beginTransaction();

    // Lock producto
    $sp = db()->prepare("SELECT id, codigo, nombre, stock_actual, stock_reservado FROM products WHERE id=? FOR UPDATE");
    $sp->execute([$product_id]);
    $p = $sp->fetch();
    if (!$p) throw new Exception('Producto no encontrado.');

    // Aplicar
    $delta = ($signo === 'ENTRADA') ? +$cantidad : -$cantidad;

    // No permitir stock negativo
    if ($p['stock_actual'] + $delta < 0) {
      throw new Exception('El ajuste dejaría el stock negativo.');
    }

    // Actualizar stock
    $up = db()->prepare("UPDATE products SET stock_actual = stock_actual + ? WHERE id=?");
    $up->execute([$delta, $product_id]);

    // Registrar movimiento
    $ins = db()->prepare("INSERT INTO stock_moves (fecha, tipo, motivo, product_id, cantidad, referencia_tipo, referencia_id, observaciones)
                          VALUES (NOW(), ?, ?, ?, ?, 'AJUSTE', NULL, ?)");
    $ins->execute([$signo, $motivo, $product_id, $cantidad, $obs]);

    db()->commit();
    $flash_ok = "Ajuste aplicado: {$signo} {$cantidad} al producto #{$product_id}.";
  } catch (Throwable $e) {
    db()->rollBack();
    $flash_err = 'No se pudo aplicar el ajuste: ' . $e->getMessage();
  }
}

// -----------------------------------------------------------
// Filtros y datos: Inventario
// -----------------------------------------------------------
$q_inv = trim($_GET['q_inv'] ?? '');
$tipo  = trim($_GET['tipo'] ?? ''); // MP|PT|''

$whereInv = [];
$paramsInv = [];
if ($q_inv !== '') {
  $whereInv[] = "(p.codigo LIKE ? OR p.nombre LIKE ?)";
  $paramsInv[] = "%$q_inv%";
  $paramsInv[] = "%$q_inv%";
}
if ($tipo !== '' && in_array($tipo, ['MP','PT'], true)) {
  $whereInv[] = "p.tipo = ?";
  $paramsInv[] = $tipo;
}
$invWhereSql = $whereInv ? ('WHERE ' . implode(' AND ', $whereInv)) : '';

$sqlInv = "
  SELECT p.id, p.codigo, p.nombre, p.tipo, p.unidad,
         p.stock_actual, p.stock_reservado,
         (p.stock_actual - p.stock_reservado) AS stock_disponible
  FROM products p
  $invWhereSql
  ORDER BY p.tipo, p.nombre
  LIMIT 200
";
$stInv = db()->prepare($sqlInv);
$stInv->execute($paramsInv);
$invRows = $stInv->fetchAll();

// -----------------------------------------------------------
// Filtros y datos: Movimientos
// -----------------------------------------------------------
$q_mov      = trim($_GET['q_mov'] ?? '');
$motivo_mov = trim($_GET['motivo_mov'] ?? ''); // COMPRA|VENTA|...|AJUSTE
$tipo_mov   = trim($_GET['tipo_mov'] ?? '');   // ENTRADA|SALIDA
$desde      = trim($_GET['desde'] ?? '');
$hasta      = trim($_GET['hasta'] ?? '');

$mvWhere = [];
$paramsMv = [];

if ($tipo_mov !== '' && in_array($tipo_mov, ['ENTRADA','SALIDA'], true)) {
  $mvWhere[] = "m.tipo = ?";
  $paramsMv[] = $tipo_mov;
}
$motivosValid = ['COMPRA','VENTA','PROD_CONSUMO','PROD_ALTA','AJUSTE','RESERVA','LIBERACION','ENTREGA'];
if ($motivo_mov !== '' && in_array($motivo_mov, $motivosValid, true)) {
  $mvWhere[] = "m.motivo = ?";
  $paramsMv[] = $motivo_mov;
}
if ($q_mov !== '') {
  $mvWhere[] = "(p.codigo LIKE ? OR p.nombre LIKE ? OR m.referencia_tipo LIKE ?)";
  $paramsMv[] = "%$q_mov%";
  $paramsMv[] = "%$q_mov%";
  $paramsMv[] = "%$q_mov%";
}
if ($desde !== '') { $mvWhere[] = "DATE(m.fecha) >= ?"; $paramsMv[] = $desde; }
if ($hasta !== '') { $mvWhere[] = "DATE(m.fecha) <= ?"; $paramsMv[] = $hasta; }

$mvWhereSql = $mvWhere ? ('WHERE ' . implode(' AND ', $mvWhere)) : '';

$page  = max(1, (int)($_GET['page'] ?? 1));
$limit = 50;
$off   = ($page - 1) * $limit;

$sqlCount = "SELECT COUNT(*) total
             FROM stock_moves m
             JOIN products p ON p.id=m.product_id
             $mvWhereSql";
$stCount = db()->prepare($sqlCount);
$stCount->execute($paramsMv);
$total = (int)$stCount->fetch()['total'];
$pages = max(1, (int)ceil($total / $limit));

$sqlMv = "
  SELECT m.id, m.fecha, m.tipo, m.motivo, m.product_id, m.cantidad,
         m.referencia_tipo, m.referencia_id, m.observaciones,
         p.codigo, p.nombre, p.unidad
  FROM stock_moves m
  JOIN products p ON p.id=m.product_id
  $mvWhereSql
  ORDER BY m.fecha DESC, m.id DESC
  LIMIT $limit OFFSET $off";
$stMv = db()->prepare($sqlMv);
$stMv->execute($paramsMv);
$mvRows = $stMv->fetchAll();

function page_url(int $p): string {
  $qs = $_GET; $qs['page'] = $p;
  return url('stock.php') . '?' . http_build_query($qs);
}

// -----------------------------------------------------------
// UI
// -----------------------------------------------------------
include __DIR__ . '/../views/partials/header.php';
include __DIR__ . '/../views/partials/navbar.php';
?>
<div class="container py-4">
  <h5 class="mb-3">Stock</h5>

  <?php if ($flash_ok): ?><div class="alert alert-success"><?= e($flash_ok) ?></div><?php endif; ?>
  <?php if ($flash_err): ?><div class="alert alert-danger"><?= e($flash_err) ?></div><?php endif; ?>

  <ul class="nav nav-tabs" id="tabs" role="tablist">
    <li class="nav-item" role="presentation">
      <button class="nav-link active" id="inv-tab" data-bs-toggle="tab" data-bs-target="#inv" type="button" role="tab">Inventario</button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="mov-tab" data-bs-toggle="tab" data-bs-target="#mov" type="button" role="tab">Movimientos</button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="ajuste-tab" data-bs-toggle="tab" data-bs-target="#ajuste" type="button" role="tab">Ajuste</button>
    </li>
  </ul>

  <div class="tab-content border-bottom border-start border-end p-3 bg-white shadow-sm">

    <!-- Inventario -->
    <div class="tab-pane fade show active" id="inv" role="tabpanel" aria-labelledby="inv-tab">
      <form class="row g-2 mb-3" method="get" action="<?= url('stock.php') ?>">
        <div class="col-md-6">
          <input class="form-control" name="q_inv" value="<?= e($q_inv) ?>" placeholder="Buscar por Código / Nombre">
        </div>
        <div class="col-md-2">
          <select name="tipo" class="form-select">
            <option value="">MP y PT</option>
            <option value="MP" <?= $tipo==='MP'?'selected':'' ?>>Materia Prima (MP)</option>
            <option value="PT" <?= $tipo==='PT'?'selected':'' ?>>Producto Terminado (PT)</option>
          </select>
        </div>
        <div class="col-md-2 d-grid">
          <button class="btn btn-outline-secondary">Filtrar</button>
        </div>
        <div class="col-md-2 d-grid">
          <a class="btn btn-outline-secondary" href="<?= url('stock.php') ?>">Limpiar</a>
        </div>
      </form>

      <div class="table-responsive">
        <table class="table table-sm align-middle">
          <thead class="table-light">
          <tr>
            <th style="width:80px;">ID</th>
            <th>Código</th>
            <th>Nombre</th>
            <th class="text-center">Tipo</th>
            <th class="text-end">Stock</th>
            <th class="text-end">Reservado</th>
            <th class="text-end">Disponible</th>
            <th>Unidad</th>
          </tr>
          </thead>
          <tbody>
          <?php if (!$invRows): ?>
            <tr><td colspan="8" class="text-center text-muted py-4">No hay productos para mostrar.</td></tr>
          <?php else: foreach ($invRows as $r): ?>
            <tr>
              <td><?= (int)$r['id'] ?></td>
              <td><?= e($r['codigo']) ?></td>
              <td><?= e($r['nombre']) ?></td>
              <td class="text-center"><?= e($r['tipo']) ?></td>
              <td class="text-end"><?= (float)$r['stock_actual'] ?></td>
              <td class="text-end"><?= (float)$r['stock_reservado'] ?></td>
              <td class="text-end fw-semibold"><?= (float)$r['stock_disponible'] ?></td>
              <td><?= e($r['unidad']) ?></td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
      <p class="small text-muted">* “Disponible” = Stock actual − Reservado.</p>
    </div>

    <!-- Movimientos -->
    <div class="tab-pane fade" id="mov" role="tabpanel" aria-labelledby="mov-tab">
      <form class="row g-2 mb-3" method="get" action="<?= url('stock.php') ?>">
        <input type="hidden" name="tab" value="mov">
        <div class="col-md-3">
          <input class="form-control" name="q_mov" value="<?= e($q_mov) ?>" placeholder="Producto / referencia">
        </div>
        <div class="col-md-2">
          <select name="tipo_mov" class="form-select">
            <option value="">ENTRADA/SALIDA</option>
            <option value="ENTRADA" <?= $tipo_mov==='ENTRADA'?'selected':'' ?>>ENTRADA</option>
            <option value="SALIDA"  <?= $tipo_mov==='SALIDA'?'selected':''  ?>>SALIDA</option>
          </select>
        </div>
        <div class="col-md-2">
          <select name="motivo_mov" class="form-select">
            <option value="">Todos los motivos</option>
            <?php foreach (['COMPRA','VENTA','PROD_CONSUMO','PROD_ALTA','AJUSTE','RESERVA','LIBERACION','ENTREGA'] as $m): ?>
              <option value="<?= $m ?>" <?= $motivo_mov===$m?'selected':'' ?>><?= $m ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <input type="date" name="desde" class="form-control" value="<?= e($desde) ?>">
        </div>
        <div class="col-md-2">
          <input type="date" name="hasta" class="form-control" value="<?= e($hasta) ?>">
        </div>
        <div class="col-md-1 d-grid">
          <button class="btn btn-outline-secondary">Filtrar</button>
        </div>
      </form>

      <div class="table-responsive">
        <table class="table table-sm align-middle">
          <thead class="table-light">
          <tr>
            <th style="width:90px;">#</th>
            <th>Fecha</th>
            <th>Tipo</th>
            <th>Motivo</th>
            <th>Código</th>
            <th>Producto</th>
            <th class="text-end">Cantidad</th>
            <th>Unidad</th>
            <th>Ref</th>
            <th>Obs</th>
          </tr>
          </thead>
          <tbody>
          <?php if (!$mvRows): ?>
            <tr><td colspan="10" class="text-center text-muted py-4">No hay movimientos.</td></tr>
          <?php else: foreach ($mvRows as $m): ?>
            <tr>
              <td><?= (int)$m['id'] ?></td>
              <td><?= e($m['fecha']) ?></td>
              <td><?= e($m['tipo']) ?></td>
              <td><?= e($m['motivo']) ?></td>
              <td><?= e($m['codigo']) ?></td>
              <td><?= e($m['nombre']) ?></td>
              <td class="text-end"><?= (float)$m['cantidad'] ?></td>
              <td><?= e($m['unidad']) ?></td>
              <td><?= e(trim(($m['referencia_tipo'] ?? '').' #'.($m['referencia_id'] ?? ''), ' #')) ?></td>
              <td><?= e($m['observaciones']) ?></td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Paginación -->
      <div class="d-flex justify-content-between align-items-center mt-3">
        <div class="small text-muted">
          Mostrando <?= $mvRows ? ($off + 1) : 0 ?>–<?= $off + count($mvRows) ?> de <?= $total ?>
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

    <!-- Ajuste -->
    <div class="tab-pane fade" id="ajuste" role="tabpanel" aria-labelledby="ajuste-tab">
      <div class="row g-3">
        <div class="col-lg-6">
          <div class="card shadow-sm">
            <div class="card-body">
              <h6 class="mb-3">Nuevo ajuste de stock</h6>
              <form method="post">
                <input type="hidden" name="action" value="ajuste">
                <div class="mb-3">
                  <label class="form-label">Producto</label>
                  <select name="product_id" class="form-select" required>
                    <option value="">— Seleccionar —</option>
                    <?php
                    $prodsSel = db()->query("SELECT id, codigo, nombre FROM products WHERE activo=1 ORDER BY nombre LIMIT 500")->fetchAll();
                    foreach ($prodsSel as $p):
                    ?>
                      <option value="<?= (int)$p['id'] ?>"><?= e($p['codigo'].' — '.$p['nombre']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="row">
                  <div class="col-md-4 mb-3">
                    <label class="form-label">Tipo</label>
                    <select name="signo" class="form-select">
                      <option value="ENTRADA">ENTRADA (+)</option>
                      <option value="SALIDA">SALIDA (−)</option>
                    </select>
                  </div>
                  <div class="col-md-4 mb-3">
                    <label class="form-label">Cantidad</label>
                    <input type="number" step="0.001" min="0.001" name="cantidad" class="form-control" required>
                  </div>
                  <div class="col-md-4 mb-3">
                    <label class="form-label">Motivo</label>
                    <select name="motivo" class="form-select">
                      <?php foreach (['AJUSTE','COMPRA','VENTA','PROD_CONSUMO','PROD_ALTA','RESERVA','LIBERACION','ENTREGA'] as $m): ?>
                        <option value="<?= $m ?>"><?= $m ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>
                <div class="mb-3">
                  <label class="form-label">Observaciones</label>
                  <input name="observaciones" class="form-control" placeholder="Detalle del ajuste">
                </div>
                <div class="d-grid">
                  <button class="btn btn-primary">Aplicar ajuste</button>
                </div>
              </form>
              <p class="small text-muted mt-2">Los ajustes registran un movimiento en la bitácora e impactan el stock actual.</p>
            </div>
          </div>
        </div>

        <div class="col-lg-6">
          <div class="alert alert-info">
            <strong>Recordatorio del flujo:</strong><br>
            <ul class="mb-0">
              <li>La <em>reserva</em> se hace al confirmar el pedido si hay stock de PT; se descuenta recién en la <em>entrega</em>.</li>
              <li>Las OP <em>consumen</em> MP al iniciar y <em>ingresan</em> PT al finalizar.</li>
              <li>Los <em>ajustes</em> son excepciones para corregir diferencias físicas vs. sistema.</li>
            </ul>
          </div>
        </div>
      </div>
    </div>

  </div> <!-- tab-content -->
</div>
<?php include __DIR__ . '/../views/partials/footer.php'; ?>
