<?php
require_once __DIR__ . '/../app/auth.php';
require_login();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

// ================== IMPRESIONES/REPORTES ==================
if (isset($_GET['print']) && isset($_GET['tipo'])) {
  $tipo = $_GET['tipo'] ?? '';
  $fecha_desde = $_GET['fecha_desde'] ?? '';
  $fecha_hasta = $_GET['fecha_hasta'] ?? '';
  $q = trim($_GET['q'] ?? '');

  if ($tipo === 'stock_actual') {
    $params = [];
    $where = "tipo='MP' AND activo=1";
    
    if ($q !== '') {
      $where .= " AND (codigo LIKE ? OR nombre LIKE ?)";
      $params[] = "%$q%";
      $params[] = "%$q%";
    }

    $sql = "SELECT id, codigo, nombre, unidad, stock_actual
            FROM products
            WHERE $where
            ORDER BY nombre";
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll();

    $logo = url('favicon-96x96.png');
    $fecha = date('d/m/Y H:i');
    $titulo = 'Stock Actual de Materias Primas';
    if ($q !== '') {
      $titulo .= ' - Búsqueda: ' . $q;
    }
    ?>
    <!doctype html>
    <html lang="es">
    <head>
      <meta charset="utf-8">
      <title>Reporte Stock Actual</title>
      <style>
        @page { size: A4; margin: 15mm; }
        body { font-family: Arial, sans-serif; color: #222; }
        .header { display: flex; align-items: center; gap: 12px; border-bottom: 2px solid #0d6efd; padding-bottom: 8px; margin-bottom: 12px; }
        .header img { width: 40px; height: 40px; }
        .title { font-size: 16px; font-weight: bold; }
        .sub { color: #555; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; font-size: 12px; }
        th, td { border-bottom: 1px solid #ddd; padding: 6px; text-align: left; }
        th { background: #f0f0f0; font-weight: bold; }
        .text-right { text-align: right; }
        .footer { margin-top: 20px; text-align: center; color: #666; font-size: 10px; }
        @media print { body { margin: 0; padding: 0; } }
      </style>
    </head>
    <body>
      <div class="header">
        <img src="<?= e($logo) ?>" alt="Logo">
        <div>
          <div class="title"><?= e($titulo) ?></div>
          <div class="sub">Universal Fitness SA</div>
        </div>
        <div style="margin-left:auto; text-align:right;" class="sub"><?= e($fecha) ?></div>
      </div>

      <table>
        <thead>
          <tr>
            <th>Código</th>
            <th>Nombre</th>
            <th>Unidad</th>
            <th class="text-right">Stock Actual</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($items as $item): ?>
            <tr>
              <td><?= e($item['codigo']) ?></td>
              <td><?= e($item['nombre']) ?></td>
              <td><?= e($item['unidad']) ?></td>
              <td class="text-right"><?= number_format((float)$item['stock_actual'], 2, '.', '') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <div class="footer">
        <p>Reporte generado automáticamente el <?= e($fecha) ?></p>
      </div>
    </body>
    </html>
    <?php
    exit;
  }

  elseif ($tipo === 'movimientos') {
    $params = [];
    $where = "1=1";

    if ($fecha_desde) {
      $where .= " AND sm.fecha >= ?";
      $params[] = $fecha_desde . " 00:00:00";
    }
    if ($fecha_hasta) {
      $where .= " AND sm.fecha <= ?";
      $params[] = $fecha_hasta . " 23:59:59";
    }

    $sql = "SELECT sm.fecha, sm.tipo, p.codigo, p.nombre, p.unidad, sm.cantidad, sm.observaciones
            FROM stock_moves sm
            LEFT JOIN products p ON sm.product_id = p.id
            WHERE $where AND sm.motivo='AJUSTE'
            ORDER BY sm.fecha DESC";
    
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll();

    $logo = url('favicon-96x96.png');
    $fecha = date('d/m/Y H:i');
    $rango = '';
    if ($fecha_desde && $fecha_hasta) {
      $rango = " (desde " . date_create($fecha_desde)->format('d/m/Y') . " hasta " . date_create($fecha_hasta)->format('d/m/Y') . ")";
    }
    ?>
    <!doctype html>
    <html lang="es">
    <head>
      <meta charset="utf-8">
      <title>Reporte Movimientos Stock</title>
      <style>
        @page { size: A4; margin: 15mm; }
        body { font-family: Arial, sans-serif; color: #222; }
        .header { display: flex; align-items: center; gap: 12px; border-bottom: 2px solid #0d6efd; padding-bottom: 8px; margin-bottom: 12px; }
        .header img { width: 40px; height: 40px; }
        .title { font-size: 16px; font-weight: bold; }
        .sub { color: #555; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; font-size: 11px; }
        th, td { border-bottom: 1px solid #ddd; padding: 5px; text-align: left; }
        th { background: #f0f0f0; font-weight: bold; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .badge { display: inline-block; padding: 2px 6px; border-radius: 3px; font-size: 10px; font-weight: bold; }
        .entrada { background: #d4edda; color: #155724; }
        .salida { background: #f8d7da; color: #721c24; }
        .footer { margin-top: 20px; text-align: center; color: #666; font-size: 10px; }
        @media print { body { margin: 0; padding: 0; } }
      </style>
    </head>
    <body>
      <div class="header">
        <img src="<?= e($logo) ?>" alt="Logo">
        <div>
          <div class="title">Movimientos de Stock<?= e($rango) ?></div>
          <div class="sub">Universal Fitness SA</div>
        </div>
        <div style="margin-left:auto; text-align:right;" class="sub"><?= e($fecha) ?></div>
      </div>

      <table>
        <thead>
          <tr>
            <th>Fecha</th>
            <th>Tipo</th>
            <th>Código</th>
            <th>Nombre</th>
            <th>Unidad</th>
            <th class="text-right">Cantidad</th>
            <th>Observaciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($items as $item): 
            $badge_class = $item['tipo'] === 'ENTRADA' ? 'entrada' : 'salida';
            $tipo_text = $item['tipo'] === 'ENTRADA' ? 'Entrada' : 'Salida';
          ?>
            <tr>
              <td><?= date_create($item['fecha'])->format('d/m/Y H:i') ?></td>
              <td><span class="badge <?= $badge_class ?>"><?= e($tipo_text) ?></span></td>
              <td><?= e($item['codigo']) ?></td>
              <td><?= e($item['nombre']) ?></td>
              <td><?= e($item['unidad']) ?></td>
              <td class="text-right"><?= number_format((float)$item['cantidad'], 2, '.', '') ?></td>
              <td><small><?= e($item['observaciones']) ?></small></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <div class="footer">
        <p>Total de movimientos: <?= count($items) ?></p>
        <p>Reporte generado automáticamente el <?= e($fecha) ?></p>
      </div>
    </body>
    </html>
    <?php
    exit;
  }

  http_response_code(400);
  echo "Tipo de reporte inválido";
  exit;
}

// ================== PÁGINA DE REPORTES ==================
include __DIR__ . '/../views/partials/header.php';
include __DIR__ . '/../views/partials/navbar.php';
?>

<div class="container py-4">
  <h5 class="mb-4">Reportes de Stock</h5>

  <div class="row">
    <!-- Stock Actual -->
    <div class="col-md-6 mb-4">
      <div class="card">
        <div class="card-body">
          <h6 class="card-title">Stock Actual</h6>
          <p class="card-text text-muted small">Listado completo de materias primas con stock actual.</p>
          <form method="get" target="_blank" action="<?= url('stock_reportes.php') ?>">
            <input type="hidden" name="print" value="1">
            <input type="hidden" name="tipo" value="stock_actual">
            <div class="input-group input-group-sm">
              <input type="text" class="form-control" name="q" placeholder="Filtrar por nombre o código (opcional)">
              <button class="btn btn-primary" type="submit">Generar A4</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Movimientos -->
    <div class="col-md-6 mb-4">
      <div class="card">
        <div class="card-body">
          <h6 class="card-title">Movimientos de Stock</h6>
          <p class="card-text text-muted small">Historial de entradas y salidas en un rango de fechas.</p>
          <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalMovimientos">
            Generar Reporte A4
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal Movimientos -->
<div class="modal fade" id="modalMovimientos" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Movimientos por Período</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="get" target="_blank" action="<?= url('stock_reportes.php') ?>">
        <input type="hidden" name="print" value="1">
        <input type="hidden" name="tipo" value="movimientos">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Desde</label>
            <input type="date" class="form-control" name="fecha_desde">
          </div>
          <div class="mb-3">
            <label class="form-label">Hasta</label>
            <input type="date" class="form-control" name="fecha_hasta">
          </div>
          <small class="text-muted">Dejar vacío para incluir todos los movimientos.</small>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
          <button type="submit" class="btn btn-primary">Generar Reporte A4</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../views/partials/footer.php'; ?>
