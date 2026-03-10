<?php
require_once __DIR__ . '/../app/proveedor.php';
require_once __DIR__ . '/../app/helpers.php';

$id = (int)($_GET['id'] ?? 0);
$proveedor = proveedor_get($id);
if (!$proveedor) {
    header('Location: proveedores.php');
    exit;
}
$errores = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'nombre' => trim($_POST['nombre'] ?? ''),
        'contacto' => trim($_POST['contacto'] ?? ''),
        'telefono' => trim($_POST['telefono'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'direccion' => trim($_POST['direccion'] ?? ''),
        'cuit' => trim($_POST['cuit'] ?? ''),
        'condicion_iva' => trim($_POST['condicion_iva'] ?? ''),
        'observaciones' => trim($_POST['observaciones'] ?? ''),
    ];
    if ($data['nombre'] === '') {
        $errores[] = 'El nombre es obligatorio.';
    }
    if (!$errores) {
        proveedor_update($id, $data);
        header('Location: proveedor_ver.php?id=' . $id);
        exit;
    }
}
?><!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Editar Proveedor</title>
  <link rel="icon" href="/favicon.ico">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body>
<?php include __DIR__ . '/../views/partials/navbar.php'; ?>
<div class="container mt-4">
  <h1>Editar Proveedor</h1>
  <?php if ($errores): ?>
    <div class="alert alert-danger">
      <ul><?php foreach ($errores as $e) echo "<li>".e($e)."</li>"; ?></ul>
    </div>
  <?php endif; ?>
  <form method="post">
    <div class="mb-3">
      <label>Nombre *</label>
      <input type="text" name="nombre" class="form-control" value="<?= e($proveedor['nombre']) ?>" required>
    </div>
    <div class="mb-3">
      <label>Contacto</label>
      <input type="text" name="contacto" class="form-control" value="<?= e($proveedor['contacto']) ?>">
    </div>
    <div class="mb-3">
      <label>Teléfono</label>
      <input type="text" name="telefono" class="form-control" value="<?= e($proveedor['telefono']) ?>">
    </div>
    <div class="mb-3">
      <label>Email</label>
      <input type="email" name="email" class="form-control" value="<?= e($proveedor['email']) ?>">
    </div>
    <div class="mb-3">
      <label>Dirección</label>
      <input type="text" name="direccion" class="form-control" value="<?= e($proveedor['direccion']) ?>">
    </div>
    <div class="mb-3">
      <label>CUIT</label>
      <input type="text" name="cuit" class="form-control" value="<?= e($proveedor['cuit']) ?>">
    </div>
    <div class="mb-3">
      <label>Condición IVA</label>
      <input type="text" name="condicion_iva" class="form-control" value="<?= e($proveedor['condicion_iva']) ?>">
    </div>
    <div class="mb-3">
      <label>Observaciones</label>
      <textarea name="observaciones" class="form-control"><?= e($proveedor['observaciones']) ?></textarea>
    </div>
    <button type="submit" class="btn btn-success">Guardar cambios</button>
    <a href="proveedor_ver.php?id=<?= $id ?>" class="btn btn-secondary">Cancelar</a>
  </form>
</div>
</body>
</html>
