<?php
require_once __DIR__ . '/../app/proveedor.php';
require_once __DIR__ . '/../app/helpers.php';

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

$proveedores = proveedor_all();
?><!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Proveedores</title>
  <link rel="icon" href="/favicon.ico">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>
<?php include __DIR__ . '/../views/partials/navbar.php'; ?>
<div class="container mt-4">
  <h1 class="mb-4">Proveedores</h1>
  <a href="proveedor_nuevo.php" class="btn btn-primary mb-3">Agregar proveedor</a>
  <table class="table table-bordered table-hover">
    <thead>
      <tr>
        <th>Nombre</th>
        <th>Contacto</th>
        <th>Teléfono</th>
        <th>Email</th>
        <th>Facturación</th>
        <th>Acciones</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($proveedores)): ?>
        <tr><td colspan="6" class="text-center">No hay proveedores registrados.</td></tr>
      <?php else: ?>
        <?php foreach ($proveedores as $p): ?>
          <tr>
            <td><?= e($p['nombre']) ?></td>
            <td><?= e($p['contacto']) ?></td>
            <td><?= e($p['telefono']) ?></td>
            <td><?= e($p['email']) ?></td>
            <td><?= e($p['condicion_iva']) ?></td>
            <td>
              <a href="proveedor_ver.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-info">Ver</a>
              <a href="proveedor_editar.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-warning">Editar</a>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>
</body>
</html>
