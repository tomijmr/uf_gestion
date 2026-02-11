<?php
require_once __DIR__ . '/../app/auth.php';
require_login();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

$flash_ok = '';
$flash_err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'ajustar_mp') {
  $product_id = (int)($_POST['product_id'] ?? 0);
  $signo = $_POST['signo'] ?? 'ENTRADA';
  $cantidad = max(0, (float)($_POST['cantidad'] ?? 0));
  $obs = trim($_POST['observaciones'] ?? '');

  try {
    if ($product_id <= 0 || $cantidad <= 0) {
      throw new Exception('Producto y cantidad son obligatorios.');
    }
    if (!in_array($signo, ['ENTRADA', 'SALIDA'], true)) {
      throw new Exception('Movimiento invalido.');
    }

    db()->beginTransaction();

    $sp = db()->prepare("SELECT id, nombre, stock_actual FROM products WHERE id=? AND tipo='MP' FOR UPDATE");
    $sp->execute([$product_id]);
    $p = $sp->fetch();
    if (!$p) throw new Exception('Materia prima no encontrada.');

    $delta = ($signo === 'ENTRADA') ? $cantidad : -$cantidad;
    if ($p['stock_actual'] + $delta < 0) {
      throw new Exception('El ajuste dejaria stock negativo.');
    }

    db()->prepare("UPDATE products SET stock_actual = stock_actual + ? WHERE id=?")
      ->execute([$delta, $product_id]);

    db()->prepare("INSERT INTO stock_moves (fecha, tipo, motivo, product_id, cantidad, referencia_tipo, referencia_id, observaciones)
                   VALUES (NOW(), ?, 'AJUSTE', ?, ?, 'AJUSTE_MP', NULL, ?)")
      ->execute([$signo, $product_id, $cantidad, $obs]);

    db()->commit();
    $flash_ok = 'Stock actualizado.';
  } catch (Throwable $e) {
    db()->rollBack();
    $flash_err = 'No se pudo actualizar: ' . $e->getMessage();
  }
}

$q = trim($_GET['q'] ?? '');
$params = [];
$where = "WHERE p.tipo='MP' AND p.activo=1";
if ($q !== '') {
  $where .= " AND (p.codigo LIKE ? OR p.nombre LIKE ?)";
  $params[] = "%$q%";
  $params[] = "%$q%";
}

$sql = "SELECT p.id, p.codigo, p.nombre, p.unidad, p.stock_actual
        FROM products p
        $where
        ORDER BY p.nombre
        LIMIT 500";
$st = db()->prepare($sql);
$st->execute($params);
$items = $st->fetchAll();

include __DIR__ . '/../views/partials/header.php';
include __DIR__ . '/../views/partials/navbar.php';
?>

<div class="container py-3">
  <div class="d-flex align-items-center justify-content-between mb-2">
    <h5 class="mb-0">Stock de Materias Primas</h5>
    <small class="text-muted">Uso rapido desde celular</small>
  </div>

  <?php if ($flash_ok): ?><div class="alert alert-success py-2"><?= e($flash_ok) ?></div><?php endif; ?>
  <?php if ($flash_err): ?><div class="alert alert-danger py-2"><?= e($flash_err) ?></div><?php endif; ?>

  <form class="mb-3" method="get" action="<?= url('materias_primas_stock.php') ?>">
    <div class="input-group">
      <input type="text" class="form-control" name="q" value="<?= e($q) ?>" placeholder="Buscar materia prima">
      <button class="btn btn-outline-secondary" type="submit">Buscar</button>
    </div>
  </form>

  <?php if (!$items): ?>
    <div class="alert alert-warning">Sin materias primas.</div>
  <?php else: ?>
    <div class="row g-3">
      <?php foreach ($items as $it): ?>
        <div class="col-12 col-md-6 col-lg-4">
          <div class="card h-100">
            <div class="card-body">
              <div class="d-flex justify-content-between">
                <div>
                  <div class="fw-semibold"><?= e($it['nombre']) ?></div>
                  <div class="text-muted small"><?= e($it['codigo']) ?><?= $it['unidad'] ? ' · ' . e($it['unidad']) : '' ?></div>
                </div>
                <div class="text-end">
                  <div class="small text-muted">Stock</div>
                  <div class="fs-5 fw-semibold"><?= e(number_format((float)$it['stock_actual'], 2, '.', '')) ?></div>
                </div>
              </div>

              <form method="post" class="mt-3">
                <input type="hidden" name="action" value="ajustar_mp">
                <input type="hidden" name="product_id" value="<?= (int)$it['id'] ?>">
                <div class="row g-2">
                  <div class="col-5">
                    <input type="number" step="0.01" min="0" name="cantidad" class="form-control" placeholder="Cantidad" required>
                  </div>
                  <div class="col-4">
                    <select name="signo" class="form-select">
                      <option value="ENTRADA">Sumar</option>
                      <option value="SALIDA">Restar</option>
                    </select>
                  </div>
                  <div class="col-3 d-grid">
                    <button type="submit" class="btn btn-primary">OK</button>
                  </div>
                </div>
                <input type="text" name="observaciones" class="form-control form-control-sm mt-2" placeholder="Nota (opcional)">
              </form>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../views/partials/footer.php'; ?>
