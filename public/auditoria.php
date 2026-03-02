<?php
require_once __DIR__ . '/../app/auth.php';
require_login();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

// ---------- Filtros de Fecha ----------
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date   = $_GET['end_date'] ?? date('Y-m-t');

// Calcular período anterior para comparar (mismo rango de días pero mes/año anterior o offset)
$d1 = new DateTime($start_date);
$d2 = new DateTime($end_date);
$diff = $d1->diff($d2);
// Ajuste para incluir el día final
$days = $diff->days + 1;

// Periodo anterior exacto (misma cantidad de días hacia atrás)
$prev_end_dt = (clone $d1)->modify('-1 day');
$prev_end = $prev_end_dt->format('Y-m-d');
$prev_start = (clone $prev_end_dt)->modify("-" . ($days - 1) . " days")->format('Y-m-d');

// Helper para obtener datos de un rango
function get_audit_data($start, $end) {
    $db = db();
    $data = [
        'ventas' => 0,
        'cobros' => 0, // Cobros reales en caja
        'compras' => 0, // Materia prima
        'gastos' => 0,  // Gastos generales + caja chica
        'gastos_categorias' => [],
        'top_productos' => [],
        'timeline' => [] // [fecha => [ingreso, egreso]]
    ];

    // 1. VENTAS (Orders confirmados/entregados/cerrados)
    // Excluir PRESUPUESTO, BORRADOR, CANCELADO
    $sqlVentas = "SELECT SUM(total_neto) as total FROM orders 
                  WHERE fecha BETWEEN ? AND ? 
                  AND estado NOT IN ('BORRADOR','PRESUPUESTO','CANCELADO')";
    $stmt = $db->prepare($sqlVentas);
    $stmt->execute([$start . ' 00:00:00', $end . ' 23:59:59']);
    $data['ventas'] = (float)($stmt->fetchColumn() ?? 0);

    // 1b. COBROS REALES (Payments) - Para conciliación de caja
    $sqlCobros = "SELECT SUM(importe) as total FROM payments 
                  WHERE fecha BETWEEN ? AND ?";
    $stmt = $db->prepare($sqlCobros);
    $stmt->execute([$start . ' 00:00:00', $end . ' 23:59:59']);
    $data['cobros'] = (float)($stmt->fetchColumn() ?? 0);

    // 2. COMPRAS (Purchases - Materia Prima)
    $sqlCompras = "SELECT SUM(total) as total FROM purchases 
                   WHERE fecha BETWEEN ? AND ?";
    $stmt = $db->prepare($sqlCompras);
    $stmt->execute([$start . ' 00:00:00', $end . ' 23:59:59']);
    $data['compras'] = (float)($stmt->fetchColumn() ?? 0);

    // 3. GASTOS VARIOS (Expenses table)
    $stmt = $db->prepare("SELECT SUM(importe) as total, categoria FROM expenses 
                  WHERE fecha BETWEEN ? AND ? GROUP BY categoria");
    $stmt->execute([$start . ' 00:00:00', $end . ' 23:59:59']);
    $rows = $stmt->fetchAll();
    foreach ($rows as $r) {
        $cat = $r['categoria'] ?: 'Sin Categoría';
        $amount = (float)$r['total'];
        $data['gastos'] += $amount;
        $data['gastos_categorias'][$cat] = ($data['gastos_categorias'][$cat] ?? 0) + $amount;
    }

    // 4. CAJA CHICA (Cash Expenses) - Asumimos que son gastos también
    $stmt = $db->prepare("SELECT SUM(importe) as total, categoria FROM cash_expenses 
                WHERE fecha BETWEEN ? AND ? GROUP BY categoria");
    $stmt->execute([$start . ' 00:00:00', $end . ' 23:59:59']);
    $rows = $stmt->fetchAll();
    foreach ($rows as $r) {
        $cat = 'Caja: ' . ($r['categoria'] ?: 'Varios');
        $amount = (float)$r['total'];
        $data['gastos'] += $amount;
        $data['gastos_categorias'][$cat] = ($data['gastos_categorias'][$cat] ?? 0) + $amount;
    }

    // 5. TOP PRODUCTOS VENDIDOS
    $sqlTop = "SELECT p.nombre, SUM(oi.cant) as cantidad, SUM(oi.subtotal) as total_vendido
               FROM order_items oi
               JOIN orders o ON o.id = oi.order_id
               JOIN products p ON p.id = oi.product_id
               WHERE o.fecha BETWEEN ? AND ?
               AND o.estado NOT IN ('BORRADOR','PRESUPUESTO','CANCELADO')
               GROUP BY p.id
               ORDER BY total_vendido DESC
               LIMIT 5";
    $stmt = $db->prepare($sqlTop);
    $stmt->execute([$start . ' 00:00:00', $end . ' 23:59:59']);
    $data['top_productos'] = $stmt->fetchAll();

    return $data;
}

$current = get_audit_data($start_date, $end_date);
$prev    = get_audit_data($prev_start, $prev_end);

// Variaciones
function calc_diff($curr, $prev) {
    if ($prev == 0) return $curr > 0 ? 100 : 0;
    return (($curr - $prev) / $prev) * 100;
}

$var_ventas  = calc_diff($current['ventas'], $prev['ventas']);
$var_compras = calc_diff($current['compras'], $prev['compras']);
$var_gastos  = calc_diff($current['gastos'], $prev['gastos']);

// Gráfico Timeline (Día a día del periodo actual)
$chart_ventas = [];
$chart_egresos = [];
$chart_labels = [];

// -- OPTIMIZACIÓN: Fetch grouped data first (4 queries total instead of 4 * days) --

// 1. Grouped Ventas
$sql_daily_ventas = "SELECT DATE(fecha) as dia, SUM(total_neto) as total 
                     FROM orders 
                     WHERE fecha BETWEEN ? AND ? 
                     AND estado NOT IN ('BORRADOR','PRESUPUESTO','CANCELADO')
                     GROUP BY DATE(fecha)";
$stmt = db()->prepare($sql_daily_ventas);
$stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
$daily_ventas = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // [ '2023-01-01' => 1500, ... ]

// 2. Grouped Compras
$sql_daily_purchases = "SELECT DATE(fecha) as dia, SUM(total) as total 
                        FROM purchases 
                        WHERE fecha BETWEEN ? AND ? 
                        GROUP BY DATE(fecha)";
$stmt = db()->prepare($sql_daily_purchases);
$stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
$daily_purchases = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// 3. Grouped Expenses
$sql_daily_expenses = "SELECT DATE(fecha) as dia, SUM(importe) as total 
                       FROM expenses 
                       WHERE fecha BETWEEN ? AND ? 
                       GROUP BY DATE(fecha)";
$stmt = db()->prepare($sql_daily_expenses);
$stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
$daily_expenses = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// 4. Grouped Cash Expenses
$sql_daily_cash = "SELECT DATE(fecha) as dia, SUM(importe) as total 
                   FROM cash_expenses 
                   WHERE fecha BETWEEN ? AND ? 
                   GROUP BY DATE(fecha)";
$stmt = db()->prepare($sql_daily_cash);
$stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
$daily_cash = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Prepare timeline loop purely in PHP
$begin = new DateTime($start_date);
$end   = new DateTime($end_date);
$end = $end->modify('+1 day'); // Include end date

$interval = DateInterval::createFromDateString('1 day');
$period = new DatePeriod($begin, $interval, $end);

foreach ($period as $dt) {
    $chart_labels[] = $dt->format("d/m");
    $d = $dt->format("Y-m-d");
    
    // Ventas
    $val_ventas = isset($daily_ventas[$d]) ? (float)$daily_ventas[$d] : 0;
    $chart_ventas[] = $val_ventas;
    
    // Egresos (Sum of all 3 sources)
    $val_purchases = isset($daily_purchases[$d]) ? (float)$daily_purchases[$d] : 0;
    $val_expenses  = isset($daily_expenses[$d]) ? (float)$daily_expenses[$d] : 0;
    $val_cash      = isset($daily_cash[$d]) ? (float)$daily_cash[$d] : 0;
    
    $chart_egresos[] = $val_purchases + $val_expenses + $val_cash;
}

// Categorías para chart
$cat_labels = array_keys($current['gastos_categorias']);
$cat_values = array_values($current['gastos_categorias']);

// Include View
include __DIR__ . '/../views/partials/header.php';
include __DIR__ . '/../views/partials/navbar.php';
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="container-fluid py-4">
    <!-- Print Header -->
    <div class="print-header">
        <h3 class="text-center mb-0">Reporte de Auditoría Financiera</h3>
        <p class="text-center mb-0">Período: <?= date('d/m/Y', strtotime($start_date)) ?> al <?= date('d/m/Y', strtotime($end_date)) ?></p>
        <div class="text-center small text-muted mb-3">Universal Fitness - Generado: <?= date('d/m/Y H:i') ?></div>
    </div>

    <!-- Header y Filtros (Hidden on Print via CSS) -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mb-4 gap-3 no-print">
        <h2 class="mb-0 fw-bold text-primary"><i class="bi bi-bar-chart-line-fill"></i> Auditoría</h2>
        
        <div class="d-flex flex-column flex-sm-row gap-2 w-100 w-md-auto">
            <form class="d-flex flex-column flex-sm-row gap-2 bg-white p-2 rounded shadow-sm align-items-center w-100" method="get">
                <div class="d-flex align-items-center gap-2 w-100">
                    <span class="text-muted small fw-bold d-none d-sm-inline">Del:</span>
                    <input type="date" name="start_date" class="form-control form-control-sm" value="<?= $start_date ?>">
                    <span class="text-muted small fw-bold d-none d-sm-inline">Al:</span>
                    <input type="date" name="end_date" class="form-control form-control-sm" value="<?= $end_date ?>">
                </div>
                <div class="d-flex gap-2 w-100 w-sm-auto">
                    <button class="btn btn-primary btn-sm flex-fill">Actualizar</button>
                    <a href="auditoria.php" class="btn btn-outline-secondary btn-sm" title="Mes actual">
                        <i class="bi bi-arrow-clockwise"></i>
                    </a>
                </div>
            </form>
            
            <button onclick="window.print()" class="btn btn-dark btn-sm d-flex align-items-center justify-content-center gap-2">
                <i class="bi bi-printer-fill"></i> <span class="d-none d-md-inline">Imprimir</span>
            </button>
        </div>
    </div>

    <!-- KPIs Principales -->
    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-4 g-3 mb-4">
        <!-- Ventas (Pedidos) -->
        <div class="col">
            <div class="card border-0 shadow-sm h-100 border-start border-4 border-primary">
                <div class="card-body p-3">
                    <div class="text-muted small text-uppercase fw-bold mb-1">Ventas (Facturado)</div>
                    <h3 class="fw-bold text-dark mb-0"><?= money($current['ventas']) ?></h3>
                    <div class="small mt-2 <?= $var_ventas >= 0 ? 'text-success' : 'text-danger' ?>">
                        <i class="bi bi-arrow-<?= $var_ventas >= 0 ? 'up' : 'down' ?>"></i> <?= number_format(abs($var_ventas), 1) ?>% vs periodo anterior
                    </div>
                </div>
            </div>
        </div>

        <!-- Ingresos Reales (Caja) -->
        <div class="col">
            <div class="card border-0 shadow-sm h-100 border-start border-4 border-success">
                <div class="card-body p-3">
                    <div class="text-muted small text-uppercase fw-bold mb-1">Ingresos Reales (Caja)</div>
                    <h3 class="fw-bold text-dark mb-0"><?= money($current['cobros']) ?></h3>
                    <div class="small mt-2 text-muted">
                        Pagos recibidos en el período
                    </div>
                </div>
            </div>
        </div>

        <!-- Compras MP -->
        <div class="col">
            <div class="card border-0 shadow-sm h-100 border-start border-4 border-warning">
                <div class="card-body p-3">
                    <div class="text-muted small text-uppercase fw-bold mb-1">Compras (Materia Prima)</div>
                    <h3 class="fw-bold text-dark mb-0"><?= money($current['compras']) ?></h3>
                    <div class="small mt-2 <?= $var_compras <= 0 ? 'text-success' : 'text-danger' ?>">
                        <i class="bi bi-arrow-<?= $var_compras > 0 ? 'up' : 'down' ?>"></i> <?= number_format(abs($var_compras), 1) ?>% vs periodo anterior
                    </div>
                </div>
            </div>
        </div>

        <!-- Gastos Operativos -->
        <div class="col">
            <div class="card border-0 shadow-sm h-100 border-start border-4 border-danger">
                <div class="card-body p-3">
                    <div class="text-muted small text-uppercase fw-bold mb-1">Gastos Operativos</div>
                    <h3 class="fw-bold text-dark mb-0"><?= money($current['gastos']) ?></h3>
                    <div class="small mt-2 <?= $var_gastos <= 0 ? 'text-success' : 'text-danger' ?>">
                        <i class="bi bi-arrow-<?= $var_gastos > 0 ? 'up' : 'down' ?>"></i> <?= number_format(abs($var_gastos), 1) ?>% vs periodo anterior
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <!-- Resultado Neto -->
        <?php 
            // Neto operativo = Ventas - (Compras + Gastos)
            $neto = $current['ventas'] - ($current['compras'] + $current['gastos']);
            // Neto financiero (Cash Flow) = Cobros - (Compras + Gastos)
            $cash_flow = $current['cobros'] - ($current['compras'] + $current['gastos']);
            
            $margen = $current['ventas'] > 0 ? ($neto / $current['ventas']) * 100 : 0;
        ?>
        <div class="col-12 col-md-6">
            <div class="card border-0 shadow-sm h-100 bg-primary text-white">
                <div class="card-body p-3 d-flex flex-column flex-sm-row justify-content-between align-items-center gap-3">
                    <div class="text-center text-sm-start">
                        <div class="text-white-50 small text-uppercase fw-bold mb-1">Resultado Operativo (Ventas - Gastos)</div>
                        <h3 class="fw-bold mb-0"><?= money($neto) ?></h3>
                        <div class="small mt-1 text-white-50">Margen: <strong><?= number_format($margen, 1) ?>%</strong></div>
                    </div>
                    <div class="text-center text-sm-end border-top border-sm-start pt-3 pt-sm-0 ps-sm-4 border-light w-100 w-sm-auto">
                         <div class="text-white-50 small text-uppercase fw-bold mb-1">Flujo de Caja Real</div>
                         <h3 class="fw-bold mb-0" style="color: #6affad;"><?= money($cash_flow) ?></h3>
                         <div class="small mt-1 text-white-50">Cobros - Pagos</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Balance General (Actualizado para usar Cobros en lugar de Ventas si se desea ver flujo de caja, pero mantenemos ventas para balance economico) -->
        <div class="col-12 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex flex-column justify-content-center p-3">
                     <div class="row text-center align-items-center h-100 g-2">
                        <div class="col-4 border-end">
                            <small class="text-muted d-block mb-1" style="font-size: 0.75rem;">Total Entradas (Caja)</small>
                            <span class="fw-bold text-success fs-6"><?= money($current['cobros']) ?></span>
                        </div>
                         <div class="col-4 border-end">
                            <small class="text-muted d-block mb-1" style="font-size: 0.75rem;">Total Salidas</small>
                            <span class="fw-bold text-danger fs-6"><?= money($current['compras'] + $current['gastos']) ?></span>
                        </div>
                        <div class="col-4">
                            <small class="text-muted d-block mb-1" style="font-size: 0.75rem;">Resultado Financiero</small>
                             <span class="fw-bold fs-6 <?= $cash_flow >= 0 ? 'text-primary' : 'text-danger' ?>"><?= money($cash_flow) ?></span>
                        </div>
                     </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Gráficos -->
    <div class="row g-3 mb-4">
        <!-- Comparativa General -->
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold">Balance General</h6>
                    <span class="badge bg-light text-muted border">Devengado</span>
                </div>
                <div class="card-body d-flex flex-column justify-content-center">
                    <?php 
                        $total_egresos = $current['compras'] + $current['gastos'];
                        $total_balance = $current['ventas'] + $total_egresos;
                        $p_ingresos = $total_balance > 0 ? ($current['ventas'] / $total_balance) * 100 : 0;
                        $p_egresos = $total_balance > 0 ? ($total_egresos / $total_balance) * 100 : 0;
                    ?>
                    <div class="mb-4">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="fw-bold text-success">Ingresos</span>
                            <span class="fw-bold"><?= number_format($p_ingresos, 1) ?>%</span>
                        </div>
                        <div class="progress" style="height: 25px;">
                            <div class="progress-bar bg-success" style="width: <?= $p_ingresos ?>%"><?= money($current['ventas']) ?></div>
                        </div>
                    </div>
                    
                    <div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="fw-bold text-danger">Egresos</span>
                            <span class="fw-bold"><?= number_format($p_egresos, 1) ?>%</span>
                        </div>
                        <div class="progress" style="height: 25px;">
                            <div class="progress-bar bg-danger" style="width: <?= $p_egresos ?>%"><?= money($total_egresos) ?></div>
                        </div>
                    </div>

                    <hr>
                    <div class="text-center">
                        <small class="text-muted">Ratio de Eficiencia</small>
                        <h4 class="mb-0 fw-bold <?= $current['ventas'] > $total_egresos ? 'text-primary' : 'text-warning' ?>">
                            <?= $total_egresos > 0 ? number_format($current['ventas'] / $total_egresos, 2) : 'N/A' ?>x
                        </h4>
                        <small class="text-muted">Por cada $1 de egreso, ingresan $<?= $total_egresos > 0 ? number_format($current['ventas'] / $total_egresos, 2) : '0' ?></small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Timeline -->
        <div class="col-md-8">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3">
                    <h6 class="mb-0 fw-bold">Evolución Diaria (Ingresos vs Egresos)</h6>
                </div>
                <div class="card-body">
                    <div style="height: 300px; position: relative;">
                        <canvas id="chartTimeline"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Listado Detalles -->
    <div class="row g-3 mb-4">
        <!-- Top Productos -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3">
                    <h6 class="mb-0 fw-bold">Top 5 Productos Vendidos</h6>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 50%;">Producto</th>
                                <th class="text-center" style="width: 25%;">Cant.</th>
                                <th class="text-end" style="width: 25%;">Total Generado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($current['top_productos'])): ?>
                                <tr><td colspan="3" class="text-center text-muted py-3">Sin ventas en este periodo</td></tr>
                            <?php else: ?>
                                <?php foreach ($current['top_productos'] as $p): ?>
                                <tr>
                                    <td><?= e($p['nombre']) ?></td>
                                    <td class="text-center badge bg-light text-dark mx-auto d-block mt-2" style="width:fit-content"><?= (float)$p['cantidad'] ?></td>
                                    <td class="text-end fw-bold text-success"><?= money($p['total_vendido']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Resumen General Data Table -->
         <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3">
                    <h6 class="mb-0 fw-bold">Resumen Financiero</h6>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Ingresos Totales (Ventas Netas)
                            <span class="fw-bold"><?= money($current['ventas']) ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center text-danger">
                            (-) Compras (Insumos/Stock)
                            <span class="fw-bold"><?= money($current['compras']) ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center text-danger">
                            (-) Gastos Operativos
                            <span class="fw-bold"><?= money($current['gastos']) ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center bg-light fw-bold mt-2">
                            = Resultado Operativo
                            <span class="<?= $neto >= 0 ? 'text-success' : 'text-danger' ?>"><?= money($neto) ?></span>
                        </li>
                    </ul>
                    <div class="alert alert-info mt-3 mb-0 small">
                        <i class="bi bi-info-circle"></i> Los datos mostrados no incluyen movimientos pendientes de facturación o pagos no registrados. Se basan en fechas de emisión de orden/gasto.
                    </div>
                </div>
            </div>
         </div>
    </div>

        <!-- Detalle de Gastos por Categoría -->
        <div class="row g-3 mt-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white py-3">
                         <h6 class="mb-0 fw-bold">Detalle de Gastos por Categoría</h6>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 60%;">Categoría</th>
                                    <th class="text-end" style="width: 20%;">Monto Total</th>
                                    <th class="text-end" style="width: 20%;">% del Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $total_gastos = $current['gastos'];
                                arsort($current['gastos_categorias']);
                                if ($total_gastos > 0):
                                    foreach ($current['gastos_categorias'] as $cat => $val): 
                                        $porcentaje = ($val / $total_gastos) * 100;
                                ?>
                                <tr>
                                    <td><?= e($cat) ?></td>
                                    <td class="text-end fw-bold"><?= money($val) ?></td>
                                    <td class="text-end text-muted"><?= number_format($porcentaje, 1) ?>%</td>
                                </tr>
                                <?php endforeach; 
                                else: ?>
                                <tr><td colspan="3" class="text-center text-muted py-3">No hay gastos registrados en este período.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detalle de Transacciones (Ingresos y Egresos) -->
        <div class="col-12 mt-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                     <h6 class="mb-0 fw-bold">Reporte Detallado de Movimientos</h6>
                     <span class="badge bg-light text-muted border">Caja y Bancos</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 12%;">Fecha</th>
                                <th style="width: 12%;">Tipo</th>
                                <th style="width: 28%;">Categoría / Cliente / Prov.</th>
                                <th style="width: 28%;">Detalle / Medio</th>
                                <th style="width: 10%; text-align: right;">Ingreso</th>
                                <th style="width: 10%; text-align: right;">Egreso</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            // Fetch all movements for the detailed report
                            $movements = [];
                            $db = db();

                            // 1. Ingresos (Payments) - Usando rango fecha completo para asegurar index usage
                            $sqlPay = "SELECT p.fecha, 'INGRESO' as tipo, c.nombre as tercero, p.medio, p.referencia, p.importe 
                                       FROM payments p 
                                       LEFT JOIN customers c ON c.id = p.customer_id 
                                       WHERE p.fecha >= ? AND p.fecha <= ?";
                            $stmt = $db->prepare($sqlPay);
                            $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
                            while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                $movements[] = [
                                    'fecha' => $row['fecha'],
                                    'tipo'  => 'INGRESO',
                                    'cat'   => $row['tercero'] ?: 'Cliente Final',
                                    'det'   => $row['medio'] . ($row['referencia'] ? " (Ref: {$row['referencia']})" : ''),
                                    'in'    => (float)$row['importe'],
                                    'out'   => 0
                                ];
                            }

                            // 2. Compras (Purchases)
                            $sqlPur = "SELECT fecha, 'EGRESO' as tipo, proveedor as tercero, comp_tipo, comp_numero, total 
                                       FROM purchases 
                                       WHERE fecha >= ? AND fecha <= ?";
                            $stmt = $db->prepare($sqlPur);
                            $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
                            while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                $movements[] = [
                                    'fecha' => $row['fecha'],
                                    'tipo'  => 'COMPRA',
                                    'cat'   => $row['tercero'] ?: 'Proveedor',
                                    'det'   => "{$row['comp_tipo']} {$row['comp_numero']}",
                                    'in'    => 0,
                                    'out'   => (float)$row['total']
                                ];
                            }

                            // 3. Gastos (Expenses - Cash & General)
                            // General - 'expenses' usa 'detalle', 'cash_expenses' usa 'descripcion'
                            $sqlExp = "SELECT fecha, 'GASTO' as tipo, categoria, detalle as descripcion, importe FROM expenses WHERE fecha >= ? AND fecha <= ?";
                            $stmt = $db->prepare($sqlExp);
                            $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
                            while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                $movements[] = [
                                    'fecha' => $row['fecha'],
                                    'tipo'  => 'GASTO',
                                    'cat'   => $row['categoria'],
                                    'det'   => $row['descripcion'],
                                    'in'    => 0,
                                    'out'   => (float)$row['importe']
                                ];
                            }
                            // Cash
                            $sqlCash = "SELECT fecha, 'GASTO CAJA' as tipo, categoria, descripcion, importe FROM cash_expenses WHERE fecha >= ? AND fecha <= ?";
                            $stmt = $db->prepare($sqlCash);
                            $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
                            while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                $movements[] = [
                                    'fecha' => $row['fecha'],
                                    'tipo'  => 'GASTO (Caja)',
                                    'cat'   => $row['categoria'],
                                    'det'   => $row['descripcion'],
                                    'in'    => 0,
                                    'out'   => (float)$row['importe']
                                ];
                            }

                            // Sort by date DESC
                            usort($movements, function($a, $b) {
                                return strtotime($b['fecha']) - strtotime($a['fecha']);
                            });

                            $total_in = 0;
                            $total_out = 0;

                            if (count($movements) > 0):
                                foreach ($movements as $m): 
                                    $total_in += $m['in'];
                                    $total_out += $m['out'];
                            ?>
                            <tr>
                                <td class="text-muted"><?= date('d/m/Y H:i', strtotime($m['fecha'])) ?></td>
                                <td>
                                    <?php if ($m['in'] > 0): ?>
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success">Ingreso</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger bg-opacity-10 text-danger border border-danger"><?= $m['tipo'] ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?= e($m['cat']) ?></td>
                                <td><?= e($m['det']) ?></td>
                                <td class="text-end fw-bold text-success"><?= $m['in'] > 0 ? money($m['in']) : '-' ?></td>
                                <td class="text-end fw-bold text-danger"><?= $m['out'] > 0 ? money($m['out']) : '-' ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <tr class="table-secondary fw-bold">
                                <td colspan="4" class="text-end">TOTALES DEL PERÍODO</td>
                                <td class="text-end text-success"><?= money($total_in) ?></td>
                                <td class="text-end text-danger"><?= money($total_out) ?></td>
                            </tr>
                            <?php else: ?>
                            <tr><td colspan="6" class="text-center text-muted py-3">No hay movimientos en este período.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

</div>

<!-- Print Styles -->
<style>
@media print {
    @page {
        size: A4 landscape;
        margin: 8mm 10mm;
    }
    
    * {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
        color-adjust: exact;
    }

    html, body {
        width: 100%;
        height: 100%;
        margin: 0;
        padding: 0;
        font-size: 11pt;
        line-height: 1.5;
        background-color: white !important;
    }

    /* Hide non-essential UI */
    nav, .navbar, header, footer, .no-print { 
        display: none !important; 
    }
    
    .btn, form, button, a[href] {
        display: none !important;
    }
    
    /* Hide bi icons */
    .bi {
        display: none !important;
    }

    /* Remove shadows and borders that clutter print */
    .shadow-sm { 
        box-shadow: none !important; 
    }
    
    .card { 
        border: 1px solid #999 !important;
        break-inside: avoid;
        page-break-inside: avoid;
        margin-bottom: 4pt !important;
    }
    
    /* Layout Adjustments */
    .container-fluid {
        width: 100% !important;
        max-width: none !important;
        padding: 0 !important;
        margin: 0 !important;
    }

    /* Row and Column Fixes */
    .row {
        display: flex !important;
        flex-wrap: wrap !important;
        width: 100%;
        margin: 0 !important;
        gap: 6pt !important;
    }

    .col-12 { width: 100% !important; }
    .col-md-2 { width: 16.666% !important; }
    .col-md-3 { width: 25% !important; }
    .col-md-4 { width: 33.333% !important; }
    .col-md-6 { width: 49% !important; }
    .col-md-8 { width: 66.666% !important; }
    .col-md-12 { width: 100% !important; }
    
    .col {
        flex: 1 1 auto;
        max-width: 24%;
        padding: 0 !important;
    }
    
    .col > .card {
        margin-bottom: 3pt !important;
    }

    .g-3 {
        gap: 6pt !important;
    }

    .mb-4 { margin-bottom: 8pt !important; }
    .mb-3 { margin-bottom: 6pt !important; }
    .mb-0 { margin-bottom: 0 !important; }
    .mx-auto { margin: 0 auto !important; }
    .py-4 { padding-top: 4pt !important; padding-bottom: 4pt !important; }
    .p-3 { padding: 6pt !important; }

    /* Card Styles */
    .card-header {
        background-color: #e8e8e8 !important;
        border-bottom: 1px solid #999 !important;
        padding: 4pt 6pt !important;
        margin: 0 !important;
    }

    .card-header h6 {
        font-size: 11pt;
        margin: 0 !important;
        font-weight: bold;
    }

    .card-body { 
        padding: 6pt !important;
        font-size: 11pt;
    }

    .card-body h3,
    .card-body h4 {
        font-size: 14pt;
        margin: 3pt 0 !important;
    }

    .card-body .small {
        font-size: 9pt;
    }

    /* Print Header */
    .print-header {
        display: block !important;
        text-align: center;
        margin-bottom: 10pt;
        border-bottom: 2px solid #333;
        padding-bottom: 6pt;
        page-break-after: avoid;
    }

    .print-header h3 {
        font-size: 16pt;
        margin: 0 0 3pt 0;
        font-weight: bold;
    }

    .print-header p {
        font-size: 11pt;
        margin: 1pt 0;
    }

    /* Text positioning fixes */
    .d-flex {
        display: flex !important;
    }

    .justify-content-between {
        justify-content: space-between !important;
    }

    .align-items-center {
        align-items: center !important;
    }

    /* Table Optimizations */
    .table-responsive { 
        overflow: visible !important;
        width: 100%;
    }

    table {
        width: 100% !important;
        font-size: 10pt;
        border-collapse: collapse;
        margin: 0;
        page-break-inside: avoid;
    }

    table thead {
        display: table-header-group;
    }

    table tbody tr {
        page-break-inside: avoid;
    }

    th, td {
        padding: 4pt 5pt !important;
        border: 1px solid #ccc;
        vertical-align: top;
        line-height: 1.3;
        word-wrap: break-word;
        overflow-wrap: break-word;
        hyphens: auto;
        white-space: normal !important;
    }

    th {
        background-color: #f0f0f0 !important;
        font-weight: bold;
        font-size: 10pt;
    }

    /* Text utilities */
    .text-center { text-align: center !important; }
    .text-end { text-align: right !important; }
    .text-start { text-align: left !important; }
    .text-muted { color: #666 !important; font-size: 10pt; }
    .fw-bold { font-weight: bold !important; }

    /* Progress bars */
    .progress {
        height: 18pt !important;
        font-size: 10pt;
    }

    .progress-bar {
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: bold;
        font-size: 11pt;
    }

    /* Badges and colors */
    .badge {
        padding: 2pt 3pt;
        font-size: 9pt;
        border-radius: 2px;
        display: inline-block;
        border: 1px solid !important;
    }

    .bg-primary { background-color: #0d6efd !important; color: white !important; }
    .bg-success { background-color: #198754 !important; color: white !important; }
    .bg-danger { background-color: #dc3545 !important; color: white !important; }
    .bg-warning { background-color: #ffc107 !important; color: #000 !important; }
    .bg-light { background-color: #f8f9fa !important; }
    .bg-opacity-10 { opacity: 1 !important; }
    
    .text-primary { color: #0d6efd !important; }
    .text-success { color: #198754 !important; }
    .text-danger { color: #dc3545 !important; }
    .text-warning { color: #d39e00 !important; }
    .text-white { color: white !important; }
    .text-white-50 { color: rgba(255,255,255,0.7) !important; }

    .border-0 { border: none !important; }
    .border-start { border-left: 4px solid !important; }
    .border-end { border-right: 1px solid #ccc !important; }
    .border-top { border-top: 1px solid #ccc !important; }
    .border-light { border-color: #ccc !important; }
    .border-primary { border-color: #0d6efd !important; }
    .border-success { border-color: #198754 !important; }
    .border-danger { border-color: #dc3545 !important; }
    .border-warning { border-color: #ffc107 !important; }

    /* List group */
    .list-group {
        display: flex !important;
        flex-direction: column;
    }

    .list-group-item {
        padding: 6pt !important;
        border: 1px solid #ddd !important;
        font-size: 10pt;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .list-group-item.list-group-flush {
        border-left: none !important;
        border-right: none !important;
    }

    .list-group-item.bg-light {
        background-color: #f0f0f0 !important;
    }

    /* Alert */
    .alert {
        padding: 6pt !important;
        margin: 6pt 0 !important;
        border: 1px solid #bbb !important;
        font-size: 10pt;
        border-radius: 3px;
    }

    .alert-info {
        background-color: #d1ecf1 !important;
        border-color: #bee5eb !important;
        color: #0c5460 !important;
    }

    /* Charts */
    canvas {
        max-height: 180pt !important;
        width: 100% !important;
        page-break-inside: avoid;
    }

    /* Text nowrap fix - allow wrapping for print */
    .text-nowrap {
        white-space: normal !important;
    }

    /* Ensure text is readable */
    h1 { font-size: 16pt; }
    h2 { font-size: 14pt; }
    h3 { font-size: 12pt; }
    h4 { font-size: 11pt; }
    h5 { font-size: 10pt; }
    h6 { font-size: 10pt; }

    .small { font-size: 9pt; }

    /* MtD specific styling */
    .mt-2 { margin-top: 4pt !important; }
    .mt-1 { margin-top: 2pt !important; }
    .pt-3 { padding-top: 4pt !important; }
    .ps-4 { padding-left: 8pt !important; }

    /* HR */
    hr {
        border: none;
        border-top: 1px solid #ccc;
        margin: 8pt 0;
    }
}

/* Helper for Print Header (Hidden on Screen) */
.print-header { display: none; }

/* Hide elements on screen marked for print only */
.no-print { display: inline; }

@media screen {
    .no-print { display: none; }
}
</style>


<!-- Scripts ChartJS -->
<script>
    // Config global currency format ish
    const moneyFormat = (value) => {
        return new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(value);
    };

    // Timeline Chart
    const ctxTimeline = document.getElementById('chartTimeline').getContext('2d');
    new Chart(ctxTimeline, {
        type: 'line',
        data: {
            labels: <?= json_encode($chart_labels) ?>,
            datasets: [
                {
                    label: 'Ingresos',
                    data: <?= json_encode($chart_ventas) ?>,
                    borderColor: '#198754',
                    backgroundColor: 'rgba(25, 135, 84, 0.1)',
                    tension: 0.3,
                    fill: true
                },
                {
                    label: 'Egresos',
                    data: <?= json_encode($chart_egresos) ?>,
                    borderColor: '#dc3545',
                    backgroundColor: 'rgba(220, 53, 69, 0.1)',
                    tension: 0.3,
                    fill: true
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom' },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': ' + moneyFormat(context.raw);
                        }
                    }
                }
            },
            scales: {
                y: { beginAtZero: true }
            }
        }
    });

    // Doughnut Chart removed as per request
</script>

<?php include __DIR__ . '/../views/partials/footer.php'; ?>
