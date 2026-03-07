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
  <link rel="icon" type="image/svg+xml" href="favicon.svg">
  <link rel="icon" type="image/png" sizes="96x96" href="favicon-96x96.png">
  <style>
    @page { size: A4 portrait; margin: 25mm 18mm 25mm 18mm; }
    body {
      font-family: 'Segoe UI', Arial, sans-serif;
      margin: 0;
      background: #fff;
      color: #181818;
      min-height: 100vh;
      font-size: 1.5em;
      text-align: justify;
      text-justify: inter-word;
    }
    .header, .footer { text-align: center; }
    .header-logo { display: none !important; }
    .remito-title {
      font-size: 2em;
      font-weight: bold;
      margin: 0 0 8px 0;
      text-transform: uppercase;
      color: #181818;
      text-align: center;
    }
    .empresa-info, .footer {
      font-size: 1em;
      margin: 0 0 4px 0;
      color: #181818;
      text-align: center;
    }
    .remito-container, .datos, .footer {
      background: #fff !important;
      color: #181818 !important;
      max-width: 700px;
      margin: 0 auto 8px auto !important;
      padding: 0 !important;
      border-radius: 0 !important;
      box-shadow: none !important;
    }
    .remito-container {
      border: none !important;
      padding: 0 0 0 0 !important;
    }
    .datos th, .datos td {
      font-size: 1em;
      padding: 2px 6px 2px 0;
      color: #181818;
    }
    table.items {
      font-size: 1em;
      margin: 8px 0 0 0;
      border-collapse: collapse;
      background: #fff;
      color: #181818;
      width: 100%;
    }
    table.items th {
      background: #181818;
      color: #fff;
      font-weight: bold;
      border: 1.5px solid #181818;
      padding: 8px 6px;
    }
    table.items td {
      border: 1.5px solid #181818;
      padding: 8px 6px;
      color: #181818;
      background: #fff;
    }
    hr { display: none; }
    @media print {
      body, .remito-container, .datos, .footer {
        background: #fff !important;
        color: #181818 !important;
      }
      table.items th {
        background: #181818 !important;
        color: #fff !important;
      }
      table.items td {
        background: #fff !important;
        color: #181818 !important;
      }
    }
  </style>
</head>
<body>
<div class="remito-container">
  <div class="header">
    <img src="favicon.svg" alt="Logo" class="header-logo">
    <div class="remito-title">Remito de Despacho</div>
    <div style="font-size:1.2em; font-weight:600; margin-bottom:6px;">N° Remito: <?= $remito_numero ?> &nbsp; | &nbsp; Fecha: <?= date('d/m/Y') ?></div>
    <div class="empresa-info">
      <strong><?= e($empresa['nombre']) ?></strong><br>
      <?= e($empresa['direccion']) ?> | CUIT: <?= e($empresa['cuit']) ?><br>
      Tel: <?= e($empresa['telefono']) ?> | Email: <?= e($empresa['email']) ?>
    </div>
  </div>
  <hr style="width:100%; margin:18px 0 18px 0; border: none; border-top: 2px solid var(--bordo-soft);">
  <div class="datos">
    <form id="remitoForm" style="width:100%;">
      <table style="width:100%;">
        <tr><th>Cliente:</th><td><?= e($order['cliente_nombre']) ?></td></tr>
        <tr><th>CUIT/DNI:</th><td><?= e($order['cuit_dni']) ?></td></tr>
        <tr><th>Teléfono:</th><td><?= e($order['telefono']) ?></td></tr>
        <tr><th>Dirección:</th><td><?= e($order['cliente_direccion']) ?></td></tr>
        <tr><th>Pedido N°:</th><td><?= $order_id ?></td></tr>
        <tr><th>Fecha Pedido:</th><td><?= date('d/m/Y', strtotime($order['fecha'])) ?></td></tr>
        <tr><th>Fecha Pactada:</th><td><?= $order['fecha_entrega'] ? date('d/m/Y', strtotime($order['fecha_entrega'])) : '-' ?></td></tr>
        <tr>
          <th>Transporte:</th>
          <td><input type="text" name="transporte" id="transporte" value="<?= e($order['empresa_transporte'] ?? '') ?>" style="width:220px; font-size:1em; padding:2px 6px;" placeholder="Transporte"></td>
        </tr>
        <tr>
          <th>Cant. de Bultos:</th>
          <td><input type="number" min="1" max="999" name="bultos" id="bultos" style="width:80px; font-size:1em; padding:2px 6px;" required></td>
        </tr>
        <tr>
          <th>Tipo de Envío:</th>
          <td>
            <label><input type="radio" name="envio" value="Sucursal" required onchange="toggleSucursalFields()"> Sucursal</label>
            &nbsp;&nbsp;
            <label><input type="radio" name="envio" value="Domicilio" onchange="toggleSucursalFields()"> Domicilio</label>
          </td>
        </tr>
        <tr id="sucursalFields" style="display:none;">
          <th>Nombre Sucursal:</th>
          <td><input type="text" name="nombre_sucursal" id="nombre_sucursal" style="width:220px; font-size:1em; padding:2px 6px;" placeholder="Nombre de la sucursal"></td>
        </tr>
        <tr id="sucursalDireccionFields" style="display:none;">
          <th>Dirección Sucursal:</th>
          <td><input type="text" name="direccion_sucursal" id="direccion_sucursal" style="width:320px; font-size:1em; padding:2px 6px;" placeholder="Dirección de la sucursal"></td>
        </tr>
      </table>
    </form>
  </div>
<script>
function toggleSucursalFields() {
  var radios = document.getElementsByName('envio');
  var show = false;
  for (var i = 0; i < radios.length; i++) {
    if (radios[i].checked && radios[i].value === 'Sucursal') {
      show = true;
      break;
    }
  }
  document.getElementById('sucursalFields').style.display = show ? '' : 'none';
  document.getElementById('sucursalDireccionFields').style.display = show ? '' : 'none';
}

// Mostrar los datos de bultos, tipo de envío y sucursal en el remito al imprimir
function beforePrint() {
  var bultos = document.getElementById('bultos').value;
  var envio = '';
  var radios = document.getElementsByName('envio');
  for (var i = 0; i < radios.length; i++) {
    if (radios[i].checked) { envio = radios[i].value; break; }
  }
  var nombreSucursal = document.getElementById('nombre_sucursal').value;
  var direccionSucursal = document.getElementById('direccion_sucursal').value;
  var datosTable = document.querySelector('.datos table');
  if (!document.getElementById('rowBultos')) {
    var rowBultos = datosTable.insertRow(-1);
    rowBultos.id = 'rowBultos';
    rowBultos.innerHTML = '<th>Cant. de Bultos:</th><td>' + (bultos ? bultos : '-') + '</td>';
  } else {
    datosTable.querySelector('#rowBultos td').textContent = bultos ? bultos : '-';
  }
  if (!document.getElementById('rowEnvio')) {
    var rowEnvio = datosTable.insertRow(-1);
    rowEnvio.id = 'rowEnvio';
    rowEnvio.innerHTML = '<th>Tipo de Envío:</th><td>' + (envio ? envio : '-') + '</td>';
  } else {
    datosTable.querySelector('#rowEnvio td').textContent = envio ? envio : '-';
  }
  // Sucursal fields
  if (!document.getElementById('rowSucursalNombre')) {
    var rowSucursalNombre = datosTable.insertRow(-1);
    rowSucursalNombre.id = 'rowSucursalNombre';
    rowSucursalNombre.innerHTML = '<th>Nombre Sucursal:</th><td>' + (envio === 'Sucursal' && nombreSucursal ? nombreSucursal : '-') + '</td>';
  } else {
    datosTable.querySelector('#rowSucursalNombre td').textContent = (envio === 'Sucursal' && nombreSucursal ? nombreSucursal : '-');
  }
  if (!document.getElementById('rowSucursalDireccion')) {
    var rowSucursalDireccion = datosTable.insertRow(-1);
    rowSucursalDireccion.id = 'rowSucursalDireccion';
    rowSucursalDireccion.innerHTML = '<th>Dirección Sucursal:</th><td>' + (envio === 'Sucursal' && direccionSucursal ? direccionSucursal : '-') + '</td>';
  } else {
    datosTable.querySelector('#rowSucursalDireccion td').textContent = (envio === 'Sucursal' && direccionSucursal ? direccionSucursal : '-');
  }
  // Ocultar inputs
  document.getElementById('bultos').style.display = 'none';
  radios.forEach(function(r){ r.style.display = 'none'; });
  document.getElementById('nombre_sucursal').style.display = 'none';
  document.getElementById('direccion_sucursal').style.display = 'none';
}

function afterPrint() {
  // Volver a mostrar los inputs
  document.getElementById('bultos').style.display = '';
  var radios = document.getElementsByName('envio');
  radios.forEach(function(r){ r.style.display = ''; });
  document.getElementById('nombre_sucursal').style.display = '';
  document.getElementById('direccion_sucursal').style.display = '';
}

window.onbeforeprint = beforePrint;
window.onafterprint = afterPrint;

// Inicializar visibilidad al cargar
window.addEventListener('DOMContentLoaded', function() {
  toggleSucursalFields();
});
function guardarDatosRemito() {
  var bultos = document.getElementById('bultos').value;
  var envio = '';
  var radios = document.getElementsByName('envio');
  for (var i = 0; i < radios.length; i++) {
    if (radios[i].checked) { envio = radios[i].value; break; }
  }
  var nombreSucursal = document.getElementById('nombre_sucursal').value;
  var direccionSucursal = document.getElementById('direccion_sucursal').value;
  var transporte = document.getElementById('transporte').value;
  var xhr = new XMLHttpRequest();
  xhr.open('POST', 'remito_update.php', true);
  xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
  xhr.send('numero=<?= $remito_numero ?>'
    + '&bultos=' + encodeURIComponent(bultos)
    + '&tipo_envio=' + encodeURIComponent(envio)
    + '&nombre_sucursal=' + encodeURIComponent(nombreSucursal)
    + '&direccion_sucursal=' + encodeURIComponent(direccionSucursal)
    + '&transporte=' + encodeURIComponent(transporte));
}

document.getElementById('remitoForm').addEventListener('change', guardarDatosRemito);
window.onbeforeprint = function() { beforePrint(); guardarDatosRemito(); };
window.onafterprint = afterPrint;
</script>
<div style="text-align:right; margin: 18px 0 0 0;">
  <button onclick="window.print()" style="font-size:1.1em; padding:7px 18px; background:#181818; color:#fff; border:none; border-radius:5px; cursor:pointer;">Imprimir Remito</button>
</div>
<table class="items">
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

<?php if (!empty($order['observaciones'])): ?>
<div style="max-width:700px; margin:28px auto 0 auto; padding:18px 22px; background:#fff; border:2px solid #181818; border-radius:8px; font-size:1.08em; color:#181818;">
  <strong>Notas del pedido:</strong><br>
  <?= nl2br(e($order['observaciones'])) ?>
</div>
<?php endif; ?>

<div class="footer">
  <hr>
  <small>Remito generado automáticamente - Universal Fitness</small>
</div>
</body>
</html>