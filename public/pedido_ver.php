<?php
// Mostrar errores para debug
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

$pedido_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$pedido_id) {
    echo '<div class="alert alert-danger">ID de pedido inválido.</div>';
    exit;
}
$pedido = db()->query("SELECT pc.*, p.nombre AS proveedor, u.nombre AS usuario FROM pedidos_compra pc JOIN proveedores p ON p.id = pc.proveedor_id JOIN users u ON u.id = pc.usuario_id WHERE pc.id = $pedido_id")->fetch();
if (!$pedido) {
    echo '<div class="alert alert-danger">Pedido no encontrado.</div>';
    exit;
}
$items = db()->prepare("SELECT i.*, pr.nombre, pr.unidad FROM pedidos_compra_items i JOIN products pr ON pr.id = i.producto_id WHERE i.pedido_id = ?");
$items->execute([$pedido_id]);
$items = $items->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ver Pedido de Compra</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body>
<div class="container py-4">
    <h2>Pedido de Compra #<?= e($pedido['id']) ?></h2>
    <p><strong>Proveedor:</strong> <?= e($pedido['proveedor']) ?></p>
    <p><strong>Usuario:</strong> <?= e($pedido['usuario']) ?></p>
    <p><strong>Descripción:</strong> <?= e($pedido['descripcion']) ?></p>
    <p><strong>Estado:</strong> <?= e($pedido['estado']) ?></p>
    <p><strong>Fecha:</strong> <?= e($pedido['fecha']) ?></p>
    <h4>Ítems solicitados</h4>
    <table class="table table-bordered">
        <thead><tr><th>Materia Prima</th><th>Cantidad</th><th>Unidad</th></tr></thead>
        <tbody>
        <?php foreach ($items as $it): ?>
            <tr>
                <td><?= e($it['nombre']) ?></td>
                <td><?= e($it['cantidad']) ?></td>
                <td><?= e($it['unidad']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <a href="pedidos_compra.php" class="btn btn-secondary">Volver</a>
    <button onclick="window.print()" class="btn btn-primary ms-2">Imprimir PDF</button>
</div>
</body>
</html>
