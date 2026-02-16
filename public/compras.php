<?php
require_once __DIR__ . '/../app/auth.php';
require_login();
require_role('ADMIN','DEPOSITO','RRHH');
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

// Source - https://stackoverflow.com/q
// Posted by Abs, modified by community. See post 'Timeline' for change history
// Retrieved 2026-01-28, License - CC BY-SA 4.0

error_reporting(E_ALL);
ini_set('display_errors', 1);


$flash_ok = $_GET['ok'] ?? '';
$flash_err = $_GET['err'] ?? '';

$q       = trim($_GET['q'] ?? '');
$fdesde  = trim($_GET['fdesde'] ?? '');
$fhasta  = trim($_GET['fhasta'] ?? '');
$orden_fecha = $_GET['orden_fecha'] ?? 'DESC';

$where = [];
$params = [];

/* --- Filtro por búsqueda --- */
if ($q !== '') {
  $where[] = "(p.proveedor LIKE ? OR p.comp_numero LIKE ? OR p.comp_tipo LIKE ?)";
  $like = "%$q%";
  $params[] = $like;
  $params[] = $like;
  $params[] = $like;
}

/* --- Filtro por fechas --- */
if ($fdesde !== '') {
  $where[] = "p.fecha >= ?";
  $params[] = $fdesde . " 00:00:00";
}
if ($fhasta !== '') {
  $where[] = "p.fecha <= ?";
  $params[] = $fhasta . " 23:59:59";
}

$whereSQL = $where ? ("WHERE " . implode(" AND ", $where)) : "";

// Validar orden
$orden_fecha = in_array($orden_fecha, ['ASC', 'DESC']) ? $orden_fecha : 'DESC';

/* --- EXPORTAR CSV --- */
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $filename = "compras_" . date('Y-m-d_H-i') . ".csv";
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // BOM para Excel
    fputs($output, "\xEF\xBB\xBF");
    
    // Encabezados
    fputcsv($output, ['ID', 'Fecha', 'Proveedor', 'Tipo', 'Serie', 'Numero', 'Total', 'Usuario', 'Detalle']);
    
    // Query sin paginación
    $sqlExport = "SELECT p.*, u.nombre AS usuario
                  FROM purchases p
                  LEFT JOIN users u ON u.id = p.created_by
                  $whereSQL
                  ORDER BY p.fecha $orden_fecha, p.id $orden_fecha";
    $stExport = db()->prepare($sqlExport);
    $stExport->execute($params);
    
    while ($row = $stExport->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['id'],
            $row['fecha'],
            $row['proveedor'],
            $row['comp_tipo'],
            $row['comp_serie'],
            $row['comp_numero'],
            number_format((float)$row['total'], 2, ',', ''),
            $row['usuario'] ?? '',
            $row['notas'] ?? ''
        ]);
    }
    
    fclose($output);
    exit;
}

/* --- IMPRIMIR REPORTE (HTML) --- */
if (isset($_GET['print']) && $_GET['print'] === '1') {
    // Calcular total filtrado sin paginación
    $stTotal = db()->prepare("SELECT SUM(p.total) FROM purchases p $whereSQL");
    $stTotal->execute($params);
    $print_total_filtrado = (float)$stTotal->fetchColumn();

    // Query sin paginación
    $sqlPrint = "SELECT p.*, u.nombre AS usuario
                  FROM purchases p
                  LEFT JOIN users u ON u.id = p.created_by
                  $whereSQL
                  ORDER BY p.fecha $orden_fecha, p.id $orden_fecha";
    $stPrint = db()->prepare($sqlPrint);
    $stPrint->execute($params);
    $rowsPrint = $stPrint->fetchAll(PDO::FETCH_ASSOC);

    $tituloReporte = "Reporte de Compras";
    $rango = "";
    if ($fdesde && $fhasta) {
        $rango = "Desde " . date('d/m/Y', strtotime($fdesde)) . " Hasta " . date('d/m/Y', strtotime($fhasta));
    } elseif ($fdesde) {
        $rango = "Desde " . date('d/m/Y', strtotime($fdesde));
    } elseif ($fhasta) {
        $rango = "Hasta " . date('d/m/Y', strtotime($fhasta));
    } else {
        $rango = "Histórico completo";
    }

    if ($q) {
        $rango .= " (Filtro: " . e($q) . ")";
    }

    ?>
<!doctype html>
  <html lang="es">
  <head>
    <meta charset="utf-8">
    <title><?= e($tituloReporte) ?></title>
    <style>
      @page { size: A4; margin: 15mm; }
      body { font-family: Arial, sans-serif; color: #222; margin: 20px; }
      .header { display: flex; align-items: center; justify-content: space-between; border-bottom: 2px solid #0d6efd; padding-bottom: 10px; margin-bottom: 20px; }
      .header-left { display: flex; align-items: center; gap: 15px; }
      .title { font-size: 24px; font-weight: bold; color: #333; }
      .subtitle { font-size: 14px; color: #555; margin-top: 4px; }
      .meta { text-align: right; font-size: 12px; color: #666; }
      
      table { width: 100%; border-collapse: collapse; font-size: 11px; margin-bottom: 20px; }
      th, td { border-bottom: 1px solid #ddd; padding: 6px; text-align: left; vertical-align: top; }
      th { background-color: #f1f1f1; font-weight: bold; }
      .text-end { text-align: right; }
      .text-center { text-align: center; }
      
      .total-row td { border-top: 2px solid #333; font-weight: bold; background-color: #fdfdfd; font-size: 12px; }
      
      @media print {
        body { margin: 0; }
        .no-print { display: none; }
      }
    </style>
  </head>
  <body onload="window.print()">
    <div class="header">
      <div class="header-left">
        <div style="font-size:32px; font-weight:bold; color:#0d6efd;">UF</div>
        <div>
          <div class="title"><?= e($tituloReporte) ?></div>
          <div class="subtitle"><?= e($rango) ?></div>
        </div>
      </div>
      <div class="meta">
        Generado el: <?= date('d/m/Y H:i') ?><br>
        Usuario: <?= e(user()['nombre']) ?>
      </div>
    </div>

    <table>
      <thead>
        <tr>
          <th style="width: 80px;">Fecha</th>
          <th>Proveedor</th>
          <th>Comprobante</th>
          <th>Usuario</th>
          <th class="text-end" style="width: 100px;">Importe</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rowsPrint)): ?>
            <tr><td colspan="5" class="text-center" style="padding: 20px; color: #777;">No se encontraron registros.</td></tr>
        <?php else: ?>
            <?php foreach ($rowsPrint as $row): ?>
            <tr>
                <td><?= date('d/m/Y', strtotime($row['fecha'])) ?></td>
                <td><?= e($row['proveedor']) ?></td>
                <td>
                    <div style="font-weight:bold;"><?= e($row['comp_tipo']) ?></div>
                    <div style="font-size:10px; color:#555;"><?= e($row['comp_serie']) ?> <?= e($row['comp_numero']) ?></div>
                    <?php if ($row['notas']): ?>
                        <div style="font-size:10px; font-style:italic; margin-top:2px;"><?= e($row['notas']) ?></div>
                    <?php endif; ?>
                </td>
                <td><?= e($row['usuario'] ?? '-') ?></td>
                <td class="text-end">$ <?= number_format((float)$row['total'], 2, ',', '.') ?></td>
            </tr>
            <?php endforeach; ?>
            <tr class="total-row">
                <td colspan="4" class="text-end">TOTAL PERÍODO</td>
                <td class="text-end">$ <?= number_format($print_total_filtrado, 2, ',', '.') ?></td>
            </tr>
        <?php endif; ?>
      </tbody>
    </table>

  </body>
  </html>
    <?php
    exit;
}

/* --- Paginación --- */
$page  = max(1, (int)($_GET['page'] ?? 1));
$limit = 25;
$off   = ($page - 1) * $limit;

/* --- Total de registros --- */
$st = db()->prepare("SELECT COUNT(*) FROM purchases p $whereSQL");
$st->execute($params);
$total = (int)$st->fetchColumn();
$pages = max(1, (int)ceil($total / $limit));

/* --- Listado paginado --- */
$sql = "SELECT p.*, u.nombre AS usuario
        FROM purchases p
        LEFT JOIN users u ON u.id = p.created_by
        $whereSQL
        ORDER BY p.fecha $orden_fecha, p.id $orden_fecha
        LIMIT $limit OFFSET $off";
$st = db()->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

/* --- Total gastado en el rango --- */
$st = db()->prepare("SELECT SUM(p.total) 
                     FROM purchases p 
                     $whereSQL");
$st->execute($params);
$total_filtrado = (float)$st->fetchColumn();

function page_url($p){
  $qs = $_GET; $qs['page'] = $p;
  return url('compras.php') . '?' . http_build_query($qs);
}

include __DIR__ . '/../views/partials/header.php';
include __DIR__ . '/../views/partials/navbar.php';
?>
<div class="container py-4">

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">Compras de MP</h5>
    <a class="btn btn-primary" href="<?= url('compra_nueva.php') ?>">Registrar compra</a>
  </div>

  <?php if ($flash_ok): ?><div class="alert alert-success"><?= e($flash_ok) ?></div><?php endif; ?>
  <?php if ($flash_err): ?><div class="alert alert-danger"><?= e($flash_err) ?></div><?php endif; ?>

  <!-- FILTROS -->
  <form class="row g-2 mb-3" method="get">

    <div class="col-md-3">
      <label class="form-label">Buscar</label>
      <input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Proveedor / nro / tipo">
    </div>

    <div class="col-md-2">
      <label class="form-label">Desde</label>
      <input type="date" class="form-control" name="fdesde" value="<?= e($fdesde) ?>">
    </div>

    <div class="col-md-2">
      <label class="form-label">Hasta</label>
      <input type="date" class="form-control" name="fhasta" value="<?= e($fhasta) ?>">
    </div>

    <div class="col-md-2">
      <label class="form-label">Ordenar</label>
      <select name="orden_fecha" class="form-select">
        <option value="DESC" <?= $orden_fecha==='DESC'?'selected':'' ?>>Más recientes</option>
        <option value="ASC" <?= $orden_fecha==='ASC'?'selected':'' ?>>Más antiguos</option>
      </select>
    </div>

    <div class="col-md-3 d-grid gap-2 d-md-flex align-items-end">
        <button class="btn btn-primary" type="submit">Filtrar</button>
        <button class="btn btn-success" type="button" onclick="exportarCSV()">Exportar</button>
        <button class="btn btn-secondary" type="button" onclick="imprimirListado()">Imprimir</button>
        <a class="btn btn-outline-secondary" href="<?= url('compras.php') ?>">Limpiar</a>
    </div>

  </form>

  <script>
    function exportarCSV() {
      const q = document.querySelector('input[name=q]').value;
      const fdesde = document.querySelector('input[name=fdesde]').value;
      const fhasta = document.querySelector('input[name=fhasta]').value;
      const orden = document.querySelector('select[name=orden_fecha]').value;
      
      const params = new URLSearchParams({
        q: q,
        fdesde: fdesde,
        fhasta: fhasta,
        orden_fecha: orden,
        export: 'csv'
      });
      window.location.href = '<?= url("compras.php") ?>?' + params.toString();
    }

    function imprimirListado() {
      const q = document.querySelector('input[name=q]').value;
      const fdesde = document.querySelector('input[name=fdesde]').value;
      const fhasta = document.querySelector('input[name=fhasta]').value;
      const orden = document.querySelector('select[name=orden_fecha]').value;
      
      const params = new URLSearchParams({
        q: q,
        fdesde: fdesde,
        fhasta: fhasta,
        orden_fecha: orden,
        print: '1'
      });
      window.open('<?= url("compras.php") ?>?' + params.toString(), '_blank');
    }
  </script>
<?php
// --- Total histórico ---
$sth = db()->query("SELECT SUM(total) FROM purchases");
$total_historico = (float)$sth->fetchColumn();

// --- Total del periodo o total filtrado ---
$st = db()->prepare("SELECT SUM(p.total) FROM purchases p $whereSQL");
$st->execute($params);
$total_filtrado = (float)$st->fetchColumn();
?>

<!-- TOTALES -->
<div class="mb-3">
    <div class="alert alert-secondary">
        <strong>Total histórico de compras:</strong>
        $ <?= number_format($total_historico, 2, ',', '.') ?>
    </div>

    <?php if ($fdesde || $fhasta): ?>
        <div class="alert alert-info">
            <strong>Total en el período seleccionado:</strong>
            $ <?= number_format($total_filtrado, 2, ',', '.') ?>
        </div>
    <?php else: ?>
        <div class="alert alert-light border">
            <strong>Total (sin filtros de fechas):</strong>
            $ <?= number_format($total_filtrado, 2, ',', '.') ?>
        </div>
    <?php endif; ?>
</div>


  <div class="card shadow-sm">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>ID</th>
              <th>Fecha</th>
              <th>Proveedor</th>
              <th>Comprobante</th>
              <th class="text-end">Total</th>
              <th>Archivo</th>
              <th>Usuario</th>
              <th class="text-end" style="width:120px;">Acciones</th>
            </tr>
          </thead>

          <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="8" class="text-center text-muted py-4">Sin compras.</td></tr>
          <?php else: foreach ($rows as $r): ?>
            <tr>
              <td><?= (int)$r['id'] ?></td>
              <td><?= e($r['fecha']) ?></td>
              <td><?= e($r['proveedor']) ?></td>
              <td><?= e($r['comp_tipo']) ?> <?= e($r['comp_serie']) ?> <?= e($r['comp_numero']) ?></td>
              <td class="text-end">$ <?= number_format((float)$r['total'], 2, ',', '.') ?></td>

              <td>
                <?php if ($r['archivo_path']): ?>
                  <a target="_blank" href="<?= url($r['archivo_path']) ?>">Ver</a>
                <?php else: ?>—<?php endif; ?>
              </td>

              <td><?= e($r['usuario'] ?? '—') ?></td>

              <td class="text-end">
                <a class="btn btn-sm btn-outline-primary"
                   href="<?= url('compra_ver.php?id=' . (int)$r['id']) ?>">
                  Ver detalle
                </a>
              </td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>

        </table>
      </div>
      
    </div>
    
  </div>

  <!-- PAGINACIÓN -->
  <div class="d-flex justify-content-between align-items-center mt-3">

    <div class="small text-muted">
      Mostrando <?= $rows?($off+1):0 ?>–<?= $off+count($rows) ?> de <?= $total ?>
    </div>

    <ul class="pagination pagination-sm mb-0">
      <li class="page-item <?= $page<=1?'disabled':'' ?>">
        <a class="page-link" href="<?= $page>1?page_url($page-1):'#' ?>">&laquo; Anterior</a>
      </li>

      <li class="page-item disabled">
        <span class="page-link">Página <?= $page ?>/<?= $pages ?></span>
      </li>

      <li class="page-item <?= $page>=$pages?'disabled':'' ?>">
        <a class="page-link" href="<?= $page<$pages?page_url($page+1):'#' ?>">Siguiente &raquo;</a>
      </li>
    </ul>

  </div>
</div>



<?php include __DIR__ . '/../views/partials/footer.php'; ?>
