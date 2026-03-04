<?php
require_once __DIR__ . '/../app/auth.php';
require_login();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// Obtener parámetros
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date   = $_GET['end_date'] ?? date('Y-m-t');
$is_print   = isset($_GET['print']) && $_GET['print'] === '1';
$is_export  = isset($_GET['export']) && $_GET['export'] === 'csv';

// Validar fechas
try {
    new DateTime($start_date);
    new DateTime($end_date);
} catch (Exception $e) {
    http_response_code(400);
    die('Fechas inválidas');
}

$db = db();

// Obtener todos los gastos
$sql = "
    SELECT 
        'Gastos Generales' as tipo_gasto,
        CAST(categoria AS CHAR) COLLATE utf8mb4_unicode_ci as categoria,
        DATE(fecha) as fecha,
        CAST(detalle AS CHAR) COLLATE utf8mb4_unicode_ci as descripcion,
        importe
    FROM expenses 
    WHERE DATE(fecha) BETWEEN ? AND ?
    
    UNION ALL
    
    SELECT 
        'Caja Chica' as tipo_gasto,
        CAST(categoria AS CHAR) COLLATE utf8mb4_unicode_ci as categoria,
        DATE(fecha) as fecha,
        CAST(descripcion AS CHAR) COLLATE utf8mb4_unicode_ci as descripcion,
        importe
    FROM cash_expenses 
    WHERE DATE(fecha) BETWEEN ? AND ?
    
    UNION ALL
    
    SELECT 
        'Compras' as tipo_gasto,
        CAST('Insumos y Servicios' AS CHAR) COLLATE utf8mb4_unicode_ci as categoria,
        DATE(fecha) as fecha,
        CAST(CONCAT(proveedor, ' - ', comp_tipo, ' ', comp_numero) AS CHAR) COLLATE utf8mb4_unicode_ci as descripcion,
        total as importe
    FROM purchases 
    WHERE DATE(fecha) BETWEEN ? AND ?
    
    ORDER BY categoria ASC, fecha DESC
";

$stmt = $db->prepare($sql);
$stmt->execute([$start_date, $end_date, $start_date, $end_date, $start_date, $end_date]);
$gastos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Agrupar por categoría
$gastos_agrupados = [];
$total_general = 0;

foreach ($gastos as $gasto) {
    $cat = $gasto['categoria'];
    if (!isset($gastos_agrupados[$cat])) {
        $gastos_agrupados[$cat] = [];
    }
    $gastos_agrupados[$cat][] = $gasto;
    $total_general += (float)$gasto['importe'];
}

// ----- EXPORTAR CSV -----
if ($is_export) {
    $filename = 'Reporte_Gastos_Categoria_' . date('Y-m-d_His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM para UTF-8

    // Título
    fputcsv($output, ['REPORTE DE GASTOS POR CATEGORÍA'], ';');
    fputcsv($output, [], ';');
    fputcsv($output, ['Período:', date('d/m/Y', strtotime($start_date)) . ' al ' . date('d/m/Y', strtotime($end_date))], ';');
    fputcsv($output, ['Generado:', date('d/m/Y H:i:s')], ';');
    fputcsv($output, [], ';');

    // Para cada categoría
    foreach ($gastos_agrupados as $categoria => $items) {
        fputcsv($output, [''], ';');
        fputcsv($output, ['CATEGORÍA: ' . strtoupper($categoria)], ';');
        fputcsv($output, ['Fecha', 'Tipo de Gasto', 'Descripción', 'Monto ($)'], ';');
        
        $subtotal = 0;
        foreach ($items as $item) {
            fputcsv($output, [
                date('d/m/Y', strtotime($item['fecha'])),
                $item['tipo_gasto'],
                mb_substr($item['descripcion'], 0, 50),
                number_format((float)$item['importe'], 2, ',', '.')
            ], ';');
            $subtotal += (float)$item['importe'];
        }
        
        fputcsv($output, ['SUBTOTAL ' . $categoria, '', '', number_format($subtotal, 2, ',', '.')], ';');
    }

    fputcsv($output, [], ';');
    fputcsv($output, ['TOTAL GENERAL', '', '', number_format($total_general, 2, ',', '.')], ';');

    fclose($output);
    exit;
}

// ----- IMPRIMIR HTML -----
if ($is_print) {
    $logo = 'https://ui-avatars.com/api/?name=UF&background=0D8ABC&color=fff&size=128';
    $fecha = date('d/m/Y H:i');
    $user = user();
    ?>
    <!doctype html>
    <html lang="es">
    <head>
        <meta charset="utf-8">
        <title>Reporte de Gastos por Categoría</title>
        <style>
            @page { size: A4; margin: 15mm; }
            body { font-family: Arial, sans-serif; color: #222; margin: 20px; }
            .header { 
                display: flex; 
                align-items: center; 
                justify-content: space-between; 
                border-bottom: 2px solid #0d6efd; 
                padding-bottom: 10px; 
                margin-bottom: 20px; 
            }
            .header-left { display: flex; align-items: center; gap: 15px; }
            .title { font-size: 24px; font-weight: bold; color: #333; }
            .subtitle { font-size: 14px; color: #555; margin-top: 4px; }
            .meta { text-align: right; font-size: 12px; color: #666; }
            
            .category-section {
                margin-top: 20px;
                margin-bottom: 20px;
                page-break-inside: avoid;
            }
            
            .category-title {
                background-color: #f8f9fa;
                padding: 10px 12px;
                font-weight: bold;
                border-left: 4px solid #0d6efd;
                margin-bottom: 10px;
                font-size: 14px;
            }
            
            table { 
                width: 100%; 
                border-collapse: collapse; 
                font-size: 12px; 
                margin-bottom: 15px; 
            }
            th, td { 
                border-bottom: 1px solid #ddd; 
                padding: 8px; 
                text-align: left; 
            }
            th { background-color: #f1f1f1; font-weight: bold; }
            .text-end { text-align: right; }
            .fw-bold { font-weight: bold; }
            .text-success { color: #198754; }
            
            .subtotal-row td { 
                border-top: 2px solid #333; 
                border-bottom: 2px solid #333;
                font-weight: bold; 
                background-color: #fdfdfd; 
            }
            
            .total-row td {
                border-top: 3px solid #333;
                font-weight: bold;
                font-size: 14px;
                background-color: #f0f0f0;
                padding: 12px;
            }
            
            .total-amount {
                text-align: right;
                color: #198754;
                font-weight: bold;
            }

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
                    <div class="title">Reporte de Gastos por Categoría</div>
                    <div class="subtitle">Desde <?= date('d/m/Y', strtotime($start_date)) ?> Hasta <?= date('d/m/Y', strtotime($end_date)) ?></div>
                </div>
            </div>
            <div class="meta">
                Generado el: <?= $fecha ?><br>
                Usuario: <?= e($user['nombre']) ?>
            </div>
        </div>

        <?php if (empty($gastos_agrupados)): ?>
            <p style="text-align:center; color:#777; font-style:italic; padding: 30px;">No hay gastos registrados en este período.</p>
        <?php else: ?>
            <?php foreach ($gastos_agrupados as $categoria => $items): ?>
                <div class="category-section">
                    <div class="category-title"><?= e(strtoupper($categoria)) ?></div>
                    <table>
                        <thead>
                            <tr>
                                <th style="width: 100px;">Fecha</th>
                                <th style="width: 100px;">Tipo</th>
                                <th>Descripción</th>
                                <th class="text-end" style="width: 120px;">Monto</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $subtotal = 0;
                            foreach ($items as $item): 
                                $subtotal += (float)$item['importe'];
                            ?>
                                <tr>
                                    <td><?= date('d/m/Y', strtotime($item['fecha'])) ?></td>
                                    <td><?= e($item['tipo_gasto']) ?></td>
                                    <td><?= e($item['descripcion']) ?></td>
                                    <td class="text-end">$ <?= number_format((float)$item['importe'], 2, ',', '.') ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="subtotal-row">
                                <td colspan="3" class="text-end">SUBTOTAL <?= e($categoria) ?></td>
                                <td class="text-end">$ <?= number_format($subtotal, 2, ',', '.') ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            <?php endforeach; ?>

            <div style="margin-top: 30px; padding: 20px; background-color: #f0f0f0; border: 2px solid #333;">
                <table>
                    <tr class="total-row">
                        <td colspan="3" class="text-end">TOTAL GENERAL</td>
                        <td class="total-amount">$ <?= number_format($total_general, 2, ',', '.') ?></td>
                    </tr>
                </table>
            </div>
        <?php endif; ?>

    </body>
    </html>
    <?php
    exit;
}

// Si llegamos aquí, es una solicitud normal (no debería pasar)
http_response_code(400);
die('Parámetros inválidos');
?>
