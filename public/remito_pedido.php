<?php
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

$order_id = (int)($_GET['id'] ?? 0);
if (!$order_id) die('Pedido no especificado');

// Traer pedido y cliente
$stmt = db()->prepare("SELECT o.*, c.nombre as cliente_nombre, c.cuit_dni, c.telefono, c.direccion as cliente_direccion FROM orders o LEFT JOIN customers c ON c.id=o.customer_id WHERE o.id=?");
$stmt->execute([$order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$order) die('Pedido no encontrado');

// Traer ítems (la unidad está en products)
$stmt = db()->prepare("SELECT p.nombre, oi.cant, p.unidad FROM order_items oi JOIN products p ON p.id=oi.product_id WHERE oi.order_id=?");
$stmt->execute([$order_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Datos empresa
$empresa = require __DIR__ . '/../app/empresa.php';

// Generar número de remito autoincremental simple (por día)
$remito_fecha = date('Ymd');
$remito_counter_file = sys_get_temp_dir() . '/remito_counter_' . $remito_fecha . '.txt';
if (file_exists($remito_counter_file)) {
  $remito_num = (int)file_get_contents($remito_counter_file) + 1;
} else {
  $remito_num = 1;
}
file_put_contents($remito_counter_file, $remito_num);
$remito_numero = $remito_fecha . '-' . str_pad($remito_num, 3, '0', STR_PAD_LEFT);

// Guardar remito en la base de datos si no existe ya para este número
$remito_guardado = false;
if (isset($remito_numero) && !$remito_guardado) {
  $existe = db()->prepare("SELECT id FROM remitos WHERE numero=?");
  $existe->execute([$remito_numero]);
  if (!$existe->fetch()) {
    // Tomar datos del pedido y del remito
    $sql = "INSERT INTO remitos (numero, fecha, order_id, cliente_nombre, cuit_dni, telefono, direccion, fecha_pedido, fecha_pactada, transporte, bultos, tipo_envio, nombre_sucursal, direccion_sucursal, observaciones) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
    $stmt = db()->prepare($sql);
    $stmt->execute([
      $remito_numero,
      date('Y-m-d'),
      $order_id,
      $order['cliente_nombre'] ?? 'SIN NOMBRE',
      $order['cuit_dni'] ?? '',
      $order['telefono'] ?? '',
      $order['cliente_direccion'] ?? '',
      $order['fecha'] ?? null,
      $order['fecha_entrega'] ?? null,
      $order['empresa_transporte'] ?? null,
      null, // bultos (se puede actualizar luego)
      null, // tipo_envio (se puede actualizar luego)
      null, // nombre_sucursal (se puede actualizar luego)
      null, // direccion_sucursal (se puede actualizar luego)
      $order['observaciones'] ?? null
    ]);
    $remito_guardado = true;
  }
}
?><!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Remito Pedido #<?= $order_id ?></title>
  <style>
    @page { size: A4 landscape; margin: 10mm; }
    body {
      font-family: Arial, sans-serif;
      font-size: 20px;
      margin: 0;
      background: #fff;
      color: #222;
    }
    .remitos-grid {
      display: flex;
      flex-wrap: wrap;
      width: 100%;
      gap: 0;
      justify-content: space-between;
      align-items: flex-start;
      page-break-inside: avoid;
    }
    .remito-box {
      width: 98%;
      min-width: 600px;
      max-width: 700px;
      border: 2.5px solid #222;
      border-radius: 16px;
      margin-bottom: 24px;
      padding: 32px 36px 28px 36px;
      box-sizing: border-box;
      background: #fafafa;
      font-size: 1.45em;
      page-break-inside: avoid;
    }
    @media print {
      body {
        font-size: 20px;
      }
      .remito-box {
        width: 48%;
        min-width: 420px;
        max-width: 480px;
        font-size: 1.2em;
        margin-bottom: 18px;
        padding: 22px 24px 18px 24px;
      }
    }
    .remito-header {
      text-align: center;
      margin-bottom: 8px;
    }
    .remito-title {
      font-size: 1.2em;
      font-weight: bold;
      margin-bottom: 2px;
      text-transform: uppercase;
      color: #222;
    }
    .empresa-info {
      font-size: 0.98em;
      color: #444;
      margin-bottom: 6px;
    }
    .remito-datos {
      margin-bottom: 6px;
    }
    .remito-datos table {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.98em;
    }
    .remito-datos th {
      text-align: left;
      font-weight: 600;
      color: #333;
      width: 38%;
      padding: 2px 4px 2px 0;
    }
    .remito-datos td {
      color: #222;
      padding: 2px 0 2px 2px;
    }
    .remito-items {
      margin-top: 6px;
      margin-bottom: 6px;
    }
    .remito-items table {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.97em;
    }
    .remito-items th, .remito-items td {
      border-bottom: 1px solid #e0e0e0;
      padding: 2px 4px;
      text-align: left;
    }
    .remito-items th {
      background: #f0f0f0;
      font-weight: 600;
      color: #222;
    }
    .remito-obs {
      font-size: 0.95em;
      color: #444;
      margin-top: 6px;
      margin-bottom: 2px;
    }
    .remito-footer {
      text-align: right;
      font-size: 0.92em;
      color: #888;
      margin-top: 8px;
    }
    @media print {
      body {
        background: #fff;
        color: #222;
      }
      .remito-box {
        page-break-inside: avoid;
      }
    }
  </style>
</head>
<body>
<div class="remitos-grid">
<?php for ($i = 0; $i < 4; $i++): ?>
  <div class="remito-box">
    <div class="remito-header">
      <div class="remito-title">Remito de Despacho</div>
      <div class="empresa-info">
        <strong><?= e($empresa['nombre']) ?></strong> | CUIT: <?= e($empresa['cuit']) ?><br>
        <?= e($empresa['direccion']) ?> | Tel: <?= e($empresa['telefono']) ?>
      </div>
      <div style="font-size:0.98em; margin-bottom:2px;">N° Remito: <?= $remito_numero ?> &nbsp; | &nbsp; Fecha: <?= date('d/m/Y') ?></div>
    </div>
    <div class="remito-datos">
      <table>
        <tr><th>Cliente:</th><td><?= e($order['cliente_nombre']) ?></td></tr>
        <tr><th>CUIT/DNI:</th><td><?= e($order['cuit_dni']) ?></td></tr>
        <tr><th>Teléfono:</th><td><?= e($order['telefono']) ?></td></tr>
        <tr><th>Dirección:</th><td><?= e($order['cliente_direccion']) ?></td></tr>
        <tr><th>Pedido N°:</th><td><?= $order_id ?></td></tr>
        <tr><th>Fecha Pedido:</th><td><?= date('d/m/Y', strtotime($order['fecha'])) ?></td></tr>
        <tr><th>Fecha Pactada:</th><td><?= $order['fecha_entrega'] ? date('d/m/Y', strtotime($order['fecha_entrega'])) : '-' ?></td></tr>
        <tr><th>Transporte:</th><td><?= e($order['empresa_transporte'] ?? '-') ?></td></tr>
      </table>
    </div>
    <div class="remito-items">
      <table>
        <thead>
          <tr><th>Producto</th><th>Cantidad</th><th>Unidad</th></tr>
        </thead>
        <tbody>
        <?php foreach ($items as $it): ?>
          <tr>
            <td><?= e($it['nombre']) ?></td>
            <td><?= (float)$it['cant'] ?></td>
            <td><?= e($it['unidad']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if (!empty($order['observaciones'])): ?>
    <div class="remito-obs">
      <strong>Notas:</strong> <?= nl2br(e($order['observaciones'])) ?>
    </div>
    <?php endif; ?>
    <div class="remito-footer">
      Remito generado automáticamente - Universal Fitness
    </div>
  </div>
<?php endfor; ?>
</div>
<div style="text-align:right; margin: 18px 0 0 0;">
  <button onclick="window.print()" style="font-size:1.1em; padding:7px 18px; background:#181818; color:#fff; border:none; border-radius:5px; cursor:pointer;">Imprimir Remitos</button>
</div>
</body>
</html>