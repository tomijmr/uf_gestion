<?php
require_once __DIR__ . '/../app/auth.php';
require_login();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/compra.php';
require_once __DIR__ . '/../app/pago_proveedor.php';
require_once __DIR__ . '/../app/proveedor.php';

$compra_id = (int)($_GET['compra_id'] ?? $_POST['compra_id'] ?? 0);
$proveedor_id = (int)($_GET['proveedor_id'] ?? $_POST['proveedor_id'] ?? 0);
$compra = null;
$proveedor = null;
$flash_err = '';

if ($compra_id && $proveedor_id) {
    $proveedor = proveedor_get($proveedor_id);
    $compras = compra_all_by_proveedor($proveedor_id);
    foreach ($compras as $c) {
        if ($c['id'] == $compra_id) {
            $compra = $c;
            break;
        }
    }
}
if (!$compra || !$proveedor) {
    header('Location: proveedores.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $monto = floatval($_POST['pago_monto'] ?? 0);
    $fecha = $_POST['pago_fecha'] ?? date('Y-m-d');
    $comprobante = trim($_POST['pago_comprobante'] ?? '');
    $obs = trim($_POST['pago_observaciones'] ?? '');
    $archivo_path = null;
    if (!empty($_FILES['pago_archivo']['name'])) {
        $dir = __DIR__ . '/../storage/comprobantes';
        if (!is_dir($dir)) mkdir($dir, 0775, true);
        $fname = date('YmdHis') . '_' . preg_replace('/[^A-Za-z0-9.\-_]/', '_', $_FILES['pago_archivo']['name']);
        $destFS = $dir . '/' . $fname;
        if (move_uploaded_file($_FILES['pago_archivo']['tmp_name'], $destFS)) {
            $archivo_path = 'storage/comprobantes/' . $fname;
        }
    }
    if ($monto > 0) {
        $pago_id = pago_proveedor_create([
            'proveedor_id' => $proveedor_id,
            'fecha' => $fecha,
            'monto' => $monto,
            'comprobante' => $comprobante,
            'observaciones' => $obs,
            'archivo' => $archivo_path
        ]);
        compra_consolidar($compra_id, $pago_id);
        header('Location: proveedor_ver.php?id=' . $proveedor_id);
        exit;
    } else {
        $flash_err = 'El monto debe ser mayor a 0.';
    }
}

include __DIR__ . '/../views/partials/header.php';
include __DIR__ . '/../views/partials/navbar.php';
?>
<div class="container py-4">
  <h2>Consolidar pago de compra</h2>
  <div class="mb-3">
    <strong>Proveedor:</strong> <?= e($proveedor['nombre']) ?><br>
    <strong>Compra:</strong> <?= e($compra['comp_tipo']) ?> <?= e($compra['comp_serie']) ?> <?= e($compra['comp_numero']) ?> | Fecha: <?= e($compra['fecha']) ?> | Monto: $<?= number_format((float)($compra['total'] ?? 0),2,',','.') ?>
  </div>
  <?php if ($flash_err): ?><div class="alert alert-danger"><?= e($flash_err) ?></div><?php endif; ?>
  <form method="post" enctype="multipart/form-data" class="card shadow-sm p-3">
    <input type="hidden" name="compra_id" value="<?= $compra_id ?>">
    <input type="hidden" name="proveedor_id" value="<?= $proveedor_id ?>">
    <div class="mb-3">
      <label class="form-label">Monto del pago *</label>
      <input type="number" name="pago_monto" step="0.01" min="0" class="form-control" required>
    </div>
    <div class="mb-3">
      <label class="form-label">Fecha del pago</label>
      <input type="date" name="pago_fecha" value="<?= date('Y-m-d') ?>" class="form-control">
    </div>
    <div class="mb-3">
      <label class="form-label">Comprobante</label>
      <input type="text" name="pago_comprobante" class="form-control">
    </div>
    <div class="mb-3">
      <label class="form-label">Observaciones</label>
      <input type="text" name="pago_observaciones" class="form-control">
    </div>
    <div class="mb-3">
      <label class="form-label">Archivo (PDF o imagen)</label>
      <input type="file" name="pago_archivo" accept="application/pdf,image/*" class="form-control">
    </div>
    <button type="submit" class="btn btn-success">Consolidar pago</button>
    <a href="proveedor_ver.php?id=<?= $proveedor_id ?>" class="btn btn-secondary ms-2">Cancelar</a>
  </form>
</div>
<?php include __DIR__ . '/../views/partials/footer.php'; ?>
