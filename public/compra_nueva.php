<?php
require_once __DIR__ . '/../app/compra.php';
require_once __DIR__ . '/../app/helpers.php';

$proveedor_id = (int)($_GET['proveedor_id'] ?? 0);
$errores = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'proveedor_id' => $proveedor_id,
        'fecha' => $_POST['fecha'] ?? date('Y-m-d'),
        'numero_factura' => trim($_POST['numero_factura'] ?? ''),
        'monto' => floatval($_POST['monto'] ?? 0),
        'detalle' => trim($_POST['detalle'] ?? ''),
    ];
    if ($data['monto'] <= 0) {
        $errores[] = 'El monto debe ser mayor a cero.';
    }
    if (!$errores) {
        compra_create($data);
        header('Location: proveedor_ver.php?id=' . $proveedor_id);
        exit;
    }
}
?><!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Registrar compra</title>
  <link rel="icon" href="/favicon.ico">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body>
<?php include __DIR__ . '/../views/partials/navbar.php'; ?>
<div class="container mt-4">
  <h1>Registrar compra</h1>
  <?php if ($errores): ?>
    <div class="alert alert-danger">
      <ul><?php foreach ($errores as $e) echo "<li>".e($e)."</li>"; ?></ul>
    </div>
  <?php endif; ?>
  <form method="post">
    <div class="mb-3">
      <label>Fecha</label>
      <input type="date" name="fecha" class="form-control" value="<?= date('Y-m-d') ?>">
    </div>
    <div class="mb-3">
      <label>N° Factura</label>
      <input type="text" name="numero_factura" class="form-control">
    </div>
    <div class="mb-3">
      <label>Monto *</label>
      <input type="number" name="monto" class="form-control" step="0.01" required>
    </div>
    <div class="mb-3">
      <label>Detalle</label>
      <textarea name="detalle" class="form-control"></textarea>
    </div>
    <button type="submit" class="btn btn-success">Registrar</button>
    <a href="proveedor_ver.php?id=<?= $proveedor_id ?>" class="btn btn-secondary">Cancelar</a>
  </form>
</div>
</body>
</html>
