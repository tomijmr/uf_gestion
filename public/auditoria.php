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
    <!-- Header y Filtros -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <h2 class="mb-0 fw-bold text-primary"><i class="bi bi-bar-chart-line-fill"></i> Auditoría y Reportes</h2>
        
        <form class="d-flex gap-2 bg-white p-2 rounded shadow-sm align-items-center" method="get">
            <span class="text-muted small fw-bold">Período:</span>
            <input type="date" name="start_date" class="form-control form-control-sm" value="<?= $start_date ?>">
            <span class="text-muted">–</span>
            <input type="date" name="end_date" class="form-control form-control-sm" value="<?= $end_date ?>">
            <button class="btn btn-primary btn-sm px-3">Actualizar</button>
            <a href="auditoria.php" class="btn btn-outline-secondary btn-sm" title="Mes actual">
                <i class="bi bi-arrow-clockwise"></i> Mes Actual
            </a>
        </form>
        
        <button onclick="window.print()" class="btn btn-dark btn-sm d-flex align-items-center gap-2">
            <i class="bi bi-printer-fill"></i> Imprimir Reporte
        </button>
    </div>

    <!-- KPIs Principales -->
    <div class="row g-3 mb-4">
        <!-- Ventas (Pedidos) -->
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100 border-start border-4 border-primary">
                <div class="card-body">
                    <div class="text-muted small text-uppercase fw-bold mb-1">Ventas (Facturado)</div>
                    <h3 class="fw-bold text-dark mb-0"><?= money($current['ventas']) ?></h3>
                    <div class="small mt-2 <?= $var_ventas >= 0 ? 'text-success' : 'text-danger' ?>">
                        <i class="bi bi-arrow-<?= $var_ventas >= 0 ? 'up' : 'down' ?>"></i> <?= number_format(abs($var_ventas), 1) ?>% vs periodo anterior
                    </div>
                </div>
            </div>
        </div>

        <!-- Ingresos Reales (Caja) -->
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100 border-start border-4 border-success">
                <div class="card-body">
                    <div class="text-muted small text-uppercase fw-bold mb-1">Ingresos Reales (Caja)</div>
                    <h3 class="fw-bold text-dark mb-0"><?= money($current['cobros']) ?></h3>
                    <div class="small mt-2 text-muted">
                        Pagos recibidos en el período
                    </div>
                </div>
            </div>
        </div>

        <!-- Compras MP -->
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100 border-start border-4 border-warning">
                <div class="card-body">
                    <div class="text-muted small text-uppercase fw-bold mb-1">Compras (Materia Prima)</div>
                    <h3 class="fw-bold text-dark mb-0"><?= money($current['compras']) ?></h3>
                    <div class="small mt-2 <?= $var_compras <= 0 ? 'text-success' : 'text-danger' ?>">
                        <i class="bi bi-arrow-<?= $var_compras > 0 ? 'up' : 'down' ?>"></i> <?= number_format(abs($var_compras), 1) ?>% vs periodo anterior
                    </div>
                </div>
            </div>
        </div>

        <!-- Gastos Operativos -->
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100 border-start border-4 border-danger">
                <div class="card-body">
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
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100 bg-primary text-white">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-white-50 small text-uppercase fw-bold mb-1">Resultado Operativo (Ventas - Gastos)</div>
                        <h3 class="fw-bold mb-0"><?= money($neto) ?></h3>
                        <div class="small mt-1 text-white-50">Margen: <strong><?= number_format($margen, 1) ?>%</strong></div>
                    </div>
                    <div class="text-end border-start ps-4 border-light">
                         <div class="text-white-50 small text-uppercase fw-bold mb-1">Flujo de Caja Real</div>
                         <h3 class="fw-bold mb-0" style="color: #6affad;"><?= money($cash_flow) ?></h3>
                         <div class="small mt-1 text-white-50">Cobros - Pagos</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Balance General (Actualizado para usar Cobros en lugar de Ventas si se desea ver flujo de caja, pero mantenemos ventas para balance economico) -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex flex-column justify-content-center pt-2 pb-2">
                     <div class="row text-center align-items-center h-100">
                        <div class="col-4 border-end">
                            <small class="text-muted d-block mb-1">Total Entradas (Caja)</small>
                            <span class="fw-bold text-success fs-5"><?= money($current['cobros']) ?></span>
                        </div>
                         <div class="col-4 border-end">
                            <small class="text-muted d-block mb-1">Total Salidas</small>
                            <span class="fw-bold text-danger fs-5"><?= money($current['compras'] + $current['gastos']) ?></span>
                        </div>
                        <div class="col-4">
                            <small class="text-muted d-block mb-1">Resultado Financiero</small>
                             <span class="fw-bold fs-5 <?= $cash_flow >= 0 ? 'text-primary' : 'text-danger' ?>"><?= money($cash_flow) ?></span>
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
                                <th>Producto</th>
                                <th class="text-center">Cant.</th>
                                <th class="text-end">Total Generado</th>
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
                                    <th>Categoría</th>
                                    <th class="text-end" style="width: 200px;">Monto Total</th>
                                    <th class="text-end" style="width: 150px;">% del Total</th>
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
                    <table class="table table-hover align-middle mb-0 text-nowrap">
                        <thead class="table-light">
                            <tr>
                                <th>Fecha</th>
                                <th>Tipo</th>
                                <th>Categoría / Cliente / Prov.</th>
                                <th>Detalle / Medio</th>
                                <th class="text-end">Ingreso</th>
                                <th class="text-end">Egreso</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            // Fetch all movements for the detailed report
                            $movements = [];

                            // 1. Ingresos (Payments) - Usando DATE(fecha) igual que caja.php para consistencia
                            $sqlPay = "SELECT p.fecha, 'INGRESO' as tipo, c.nombre as tercero, p.medio, p.referencia, p.importe 
                                       FROM payments p 
                                       LEFT JOIN customers c ON c.id = p.customer_id 
                                       WHERE DATE(p.fecha) BETWEEN ? AND ?";
                            $stmt = $db->prepare($sqlPay);
                            $stmt->execute([$start_date, $end_date]);
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
                                       WHERE DATE(fecha) BETWEEN ? AND ?";
                            $stmt = $db->prepare($sqlPur);
                            $stmt->execute([$start_date, $end_date]);
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
                            // General
                            $sqlExp = "SELECT fecha, 'GASTO' as tipo, categoria, descripcion, importe FROM expenses WHERE DATE(fecha) BETWEEN ? AND ?";
                            $stmt = $db->prepare($sqlExp);
                            $stmt->execute([$start_date, $end_date]);
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
                            $sqlCash = "SELECT fecha, 'GASTO CAJA' as tipo, categoria, descripcion, importe FROM cash_expenses WHERE DATE(fecha) BETWEEN ? AND ?";
                            $stmt = $db->prepare($sqlCash);
                            $stmt->execute([$start_date, $end_date]);
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
                                <td class="text-muted small"><?= date('d/m/Y H:i', strtotime($m['fecha'])) ?></td>
                                <td>
                                    <?php if ($m['in'] > 0): ?>
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success">Ingreso</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger bg-opacity-10 text-danger border border-danger"><?= $m['tipo'] ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?= e(substr($m['cat'], 0, 40)) ?></td>
                                <td class="small text-muted"><?= e(substr($m['det'], 0, 50)) ?></td>
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
        margin: 1cm;
    }
    
    /* Hide non-essential elements */
    nav, .btn, form, footer, .bi-arrow-clockwise, button, a {
        display: none !important;
    }
    /* Expand container */
    .container-fluid {
        width: 100% !important;
        padding: 0 !important;
        margin: 0 !important;
    }
    /* Card borders for print clarity */
    .card {
        border: 1px solid #ddd !important;
        box-shadow: none !important;
        break-inside: avoid;
    }
    /* Colors */
    .bg-primary { background-color: #0d6efd !important; color: white !important; -webkit-print-color-adjust: exact; }
    .text-success { color: #198754 !important; -webkit-print-color-adjust: exact; }
    .text-danger { color: #dc3545 !important; -webkit-print-color-adjust: exact; }
    
    /* Ensure charts print */
    canvas {
        max-width: 100% !important;
        max-height: 100% !important;
    }
    
    /* Font sizes */
    body { font-size: 10pt; }
    h2, h3 { color: #000 !important; }
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
