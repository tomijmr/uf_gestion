<?php
require_once __DIR__ . '/../app/auth.php';
require_login();
require_role('ADMIN','DEPOSITO');
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) die("ID inválido");

$sql = "SELECT p.*, u.nombre AS usuario
        FROM purchases p
        LEFT JOIN users u ON u.id = p.created_by
        WHERE p.id = ?";
$st = db()->prepare($sql);
$st->execute([$id]);
$purchase = $st->fetch(PDO::FETCH_ASSOC);

if (!$purchase) die("Compra no encontrada");

$st = db()->prepare("SELECT * FROM purchase_items WHERE purchase_id = ? ORDER BY id ASC");
$st->execute([$id]);
$items = $st->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/../views/partials/header.php';
include __DIR__ . '/../views/partials/navbar.php';
?>
<div class="container py-4">

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">Detalle de compra #<?= $id ?></h5>
    <a class="btn btn-outline-secondary" href="<?= url('compras.php') ?>">Volver</a>
  </div>

  <div class="card shadow-sm mb-3">
    <div class="card-body">
      <p><strong>Fecha:</strong> <?= e($purchase['fecha']) ?></p>
      <p><strong>Proveedor:</strong> <?= e($purchase['proveedor']) ?></p>
      <p><strong>Comprobante:</strong>
        <?= e($purchase['comp_tipo']) . ' ' . e($purchase['comp_serie']) . ' ' . e($purchase['comp_numero']) ?>
      </p>
      <p><strong>Usuario:</strong> <?= e($purchase['usuario'] ?? '—') ?></p>
      <p><strong>Total:</strong> $ <?= number_format((float)$purchase['total'], 2, ',', '.') ?></p>

      <?php if ($purchase['archivo_path']): ?>
        <p><strong>Comprobante:</strong> 
          <a href="<?= url($purchase['archivo_path']) ?>" target="_blank">Ver archivo</a>
        </p>
      <?php endif; ?>

      <?php if ($purchase['notas']): ?>
        <p><strong>Notas:</strong> <?= e($purchase['notas']) ?></p>
      <?php endif; ?>
    </div>
  </div>

  <div class="card shadow-sm">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Código</th>
              <th>Nombre</th>
              <th>Unidad</th>
              <th class="text-end">Cantidad</th>
              <th class="text-end">Costo unit.</th>
              <th class="text-end">Subtotal</th>
              <th>Notas</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($items as $it): ?>
            <tr>
              <td><?= e($it['codigo']) ?></td>
              <td><?= e($it['nombre']) ?></td>
              <td><?= e($it['unidad']) ?></td>
              <td class="text-end"><?= (float)$it['cantidad'] ?></td>
              <td class="text-end">$ <?= number_format((float)$it['costo_unit'], 2, ',', '.') ?></td>
              <td class="text-end">$ <?= number_format((float)$it['subtotal'], 2, ',', '.') ?></td>
              <td><?= e($it['notas']) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div>
<?php include __DIR__ . '/../views/partials/footer.php'; ?>
