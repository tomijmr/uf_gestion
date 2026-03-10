<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../app/proveedor.php';
require_once __DIR__ . '/../app/compra.php';
require_once __DIR__ . '/../app/pago_proveedor.php';
require_once __DIR__ . '/../app/helpers.php';

$id = (int)($_GET['id'] ?? 0);
$proveedor = proveedor_get($id);
if (!$proveedor) {
    header('Location: proveedores.php');
    exit;
}
$compras = compra_all_by_proveedor($id);
$pagos = pagos_by_proveedor($id);

// Consolidar compra con archivo
if (isset($_POST['consolidar_compra_id'], $_POST['pago_monto'])) {
    $compra_id = (int)$_POST['consolidar_compra_id'];
    $monto = floatval($_POST['pago_monto']);
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
            'proveedor_id' => $id,
            'fecha' => $fecha,
            'monto' => $monto,
            'comprobante' => $comprobante,
            'observaciones' => $obs,
            'archivo' => $archivo_path
        ]);
        compra_consolidar($compra_id, $pago_id);
        header('Location: proveedor_ver.php?id=' . $id);
        exit;
    }
}
?><!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Proveedor: <?= e($proveedor['nombre']) ?></title>
  <link rel="icon" href="/favicon.ico">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <style>
    .table th, .table td { vertical-align: middle; }
    .form-consolidar input, .form-consolidar button, .form-consolidar label { margin-bottom: 0.2rem; }
    .form-consolidar { display: flex; flex-wrap: wrap; gap: 0.3rem; align-items: center; }
    .comprobante-thumb { max-width: 60px; max-height: 60px; object-fit: contain; border: 1px solid #ccc; border-radius: 4px; }
  </style>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>
<?php include __DIR__ . '/../views/partials/navbar.php'; ?>
<div class="container mt-4">
  <h1 class="mb-3">Proveedor: <?= e($proveedor['nombre']) ?></h1>
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
  <h2 class="mt-4">Compras realizadas</h2>
  <a href="compra_nueva1.php?proveedor_id=<?= $proveedor['id'] ?>" class="btn btn-primary mb-2">Registrar nueva compra</a>
  <table class="table table-bordered table-hover">
    <thead class="table-light">
      <tr>
        <th>Fecha</th>
        <th>N° Factura</th>
        <th>Monto</th>
        <th>Detalle</th>
        <th>Estado</th>
        <th>Acción</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($compras)): ?>
        <tr><td colspan="6" class="text-center">No hay compras registradas.</td></tr>
      <?php else: ?>
        <?php foreach ($compras as $c): ?>
          <tr>
            <td><?= e($c['fecha']) ?></td>
            <td><?= e($c['comp_numero'] ?? '') ?></td>
            <td>$<?= number_format((float)($c['total'] ?? 0),2,',','.') ?></td>
            <td><?= e($c['notas'] ?? '') ?></td>
            <td><?= e($c['estado'] ?? 'PENDIENTE') ?></td>
            <td>
              <?php
              $esPendiente = ($c['estado'] ?? '') === 'PENDIENTE';
              $esConsolidada = ($c['estado'] ?? '') === 'CONSOLIDADA' && !empty($c['pago_id']);
              ?>
              <?php if ($esPendiente): ?>
                <a href="consolidar_pago.php?compra_id=<?= $c['id'] ?>&proveedor_id=<?= $id ?>" class="btn btn-success btn-sm">Consolidar</a>
              <?php elseif ($esConsolidada): ?>
                <span class="text-success">Consolidada</span>
              <?php else: ?>
                <span class="text-secondary">Sin consolidar</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
  <h2 class="mt-4">Pagos realizados</h2>
  <table class="table table-bordered table-hover">
    <thead class="table-light">
      <tr>
        <th>Fecha</th>
        <th>Monto</th>
        <th>Comprobante</th>
        <th>Archivo</th>
        <th>Observaciones</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($pagos)): ?>
        <tr><td colspan="5" class="text-center">No hay pagos registrados.</td></tr>
      <?php else: ?>
        <?php foreach ($pagos as $p): ?>
          <tr>
            <td><?= e($p['fecha']) ?></td>
            <td>$<?= number_format($p['monto'],2,',','.') ?></td>
            <td><?= e($p['comprobante']) ?></td>
            <td>
              <?php if (!empty($p['archivo'])): ?>
                <?php if (preg_match('/\\.(jpg|jpeg|png|gif)$/i', $p['archivo'])): ?>
                  <a href="../<?= e($p['archivo']) ?>" target="_blank"><img src="../<?= e($p['archivo']) ?>" class="comprobante-thumb"></a>
                <?php elseif (preg_match('/\\.pdf$/i', $p['archivo'])): ?>
                  <a href="../<?= e($p['archivo']) ?>" target="_blank" class="btn btn-outline-secondary btn-sm">Ver PDF</a>
                <?php else: ?>
                  <a href="../<?= e($p['archivo']) ?>" target="_blank">Descargar</a>
                <?php endif; ?>
              <?php endif; ?>
            </td>
            <td><?= e($p['observaciones']) ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
  <h2 class="mt-4">Cuenta corriente</h2>
  <?php
    $saldo = 0;
    $movimientos = [];
    foreach ($compras as $c) {
      $movimientos[] = [
        'fecha' => $c['fecha'],
        'tipo' => 'Compra',
        'detalle' => 'Compra ' . ($c['comp_numero'] ?? $c['id']),
        'monto' => -floatval($c['total'] ?? 0),
        'estado' => $c['estado'] ?? 'PENDIENTE',
      ];
    }
    foreach ($pagos as $p) {
      $movimientos[] = [
        'fecha' => $p['fecha'],
        'tipo' => 'Pago',
        'detalle' => 'Pago ' . ($p['comprobante'] ?: $p['id']),
        'monto' => floatval($p['monto']),
        'estado' => 'APLICADO',
      ];
    }
    usort($movimientos, function($a, $b) {
      return strcmp($a['fecha'], $b['fecha']);
    });
  ?>
  <table class="table table-bordered table-hover">
    <thead class="table-light">
      <tr>
        <th>Fecha</th>
        <th>Tipo</th>
        <th>Detalle</th>
        <th>Estado</th>
        <th class="text-end">Importe</th>
        <th class="text-end">Saldo</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($movimientos as $m): ?>
        <?php $saldo += $m['monto']; ?>
        <tr>
          <td><?= e($m['fecha']) ?></td>
          <td><?= e($m['tipo']) ?></td>
          <td><?= e($m['detalle']) ?></td>
          <td><?= e($m['estado']) ?></td>
          <td class="text-end">
            <?= $m['monto'] < 0 ? '<span class="text-danger">$'.number_format(-$m['monto'],2,',','.').'</span>' : '<span class="text-success">$'.number_format($m['monto'],2,',','.').'</span>' ?>
          </td>
          <td class="text-end">$<?= number_format($saldo,2,',','.') ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr>
        <th colspan="5" class="text-end">Saldo actual</th>
        <th class="text-end">$<?= number_format($saldo,2,',','.') ?></th>
      </tr>
    </tfoot>
  </table>
</div>
</body>
</html>
