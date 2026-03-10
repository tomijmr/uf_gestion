<?php
require_once __DIR__ . '/../app/proveedor.php';
require_once __DIR__ . '/../app/compra.php';
require_once __DIR__ . '/../app/helpers.php';

$id = (int)($_GET['id'] ?? 0);
$proveedor = proveedor_get($id);
if (!$proveedor) {
    header('Location: proveedores.php');
    exit;
}
$compras = compra_all_by_proveedor($id);
?><!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Proveedor: <?= e($proveedor['nombre']) ?></title>
  <link rel="icon" href="/favicon.ico">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body>
<?php include __DIR__ . '/../views/partials/navbar.php'; ?>
<div class="container mt-4">
  <h1><?= e($proveedor['nombre']) ?></h1>
  <div class="mb-3">
    <strong>Contacto:</strong> <?= e($proveedor['contacto']) ?><br>
    <strong>Teléfono:</strong> <?= e($proveedor['telefono']) ?><br>
    <strong>Email:</strong> <?= e($proveedor['email']) ?><br>
    <strong>Dirección:</strong> <?= e($proveedor['direccion']) ?><br>
    <strong>CUIT:</strong> <?= e($proveedor['cuit']) ?><br>
    <strong>Condición IVA:</strong> <?= e($proveedor['condicion_iva']) ?><br>
    <strong>Observaciones:</strong> <?= e($proveedor['observaciones']) ?><br>
  </div>
  <a href="proveedor_editar.php?id=<?= $proveedor['id'] ?>" class="btn btn-warning mb-3">Editar proveedor</a>
  <h2>Compras realizadas</h2>
  <a href="compra_nueva.php?proveedor_id=<?= $proveedor['id'] ?>" class="btn btn-primary mb-2">Registrar nueva compra</a>
  <table class="table table-bordered table-hover">
    <thead>
      <tr>
        <th>Fecha</th>
        <th>N° Factura</th>
        <th>Monto</th>
        <th>Detalle</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($compras)): ?>
        <tr><td colspan="4" class="text-center">No hay compras registradas.</td></tr>
      <?php else: ?>
        <?php foreach ($compras as $c): ?>
          <tr>
            <td><?= e($c['fecha']) ?></td>
            <td><?= e($c['numero_factura']) ?></td>
            <td>$<?= number_format($c['monto'],2,',','.') ?></td>
            <td><?= e($c['detalle']) ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>
</body>
</html>
