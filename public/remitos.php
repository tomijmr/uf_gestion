
<?php
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';
// Mostrar errores en desarrollo
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

$stmt = db()->query("SELECT * FROM remitos ORDER BY fecha DESC, id DESC");
$remitos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Remitos Generados</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <style>
    body { background: #f6f7fb; }
    .remitos-header {
      background: linear-gradient(90deg, #181818 0%, #4e54c8 100%);
      color: #fff;
      border-radius: 0 0 18px 18px;
      box-shadow: 0 2px 12px #0002;
      padding: 32px 0 24px 0;
      margin-bottom: 32px;
      text-align: center;
    }
    .remitos-header h2 { font-weight: 700; font-size: 2.2em; letter-spacing: 1px; }
    .remitos-header p { font-size: 1.1em; color: #e0e0e0; }
    .table-remitos th { background: #181818; color: #fff; font-weight: 700; }
    .table-remitos td, .table-remitos th { vertical-align: middle; }
    .btn-ver { background: #4e54c8; color: #fff; border: none; }
    .btn-ver:hover { background: #181818; color: #fff; }
    .table-remitos tr { transition: background 0.2s; }
    .table-remitos tr:hover { background: #f0f3fa; }
  </style>
</head>
<body>
<?php include __DIR__ . '/../views/partials/navbar.php'; ?>
<div class="remitos-header">
  <h2>Remitos Generados</h2>
  <p>Listado de todos los remitos emitidos. Puedes buscar, filtrar y acceder a cada remito generado.</p>
</div>
<div class="container mb-5">
  <div class="table-responsive">
    <table class="table table-bordered table-hover table-remitos align-middle">
      <thead>
        <tr>
          <th>N° Remito</th>
          <th>Fecha</th>
          <th>Cliente</th>
          <th>Pedido N°</th>
          <th>Transporte</th>
          <th>Bultos</th>
          <th>Tipo Envío</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($remitos as $r): ?>
        <tr>
          <td><?= e($r['numero']) ?></td>
          <td><?= date('d/m/Y', strtotime($r['fecha'])) ?></td>
          <td><?= e($r['cliente_nombre']) ?></td>
          <td><?= e($r['order_id']) ?></td>
          <td><?= e($r['transporte']) ?></td>
          <td><?= e($r['bultos']) ?></td>
          <td><?= e($r['tipo_envio']) ?></td>
          <td>
            <a href="remito_pedido.php?id=<?= e($r['order_id']) ?>" class="btn btn-sm btn-ver" target="_blank">Ver PDF</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
