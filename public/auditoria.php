<?php
require_once __DIR__ . '/../app/auth.php';
require_login();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

// --------- RANGO DE FECHAS (PERÍODO ACTUAL) ----------
$hoy = date('Y-m-d');
$desde = $_GET['desde'] ?? date('Y-m-01');
$hasta = $_GET['hasta'] ?? $hoy;

// Normalización básica
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) $desde = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) $hasta = $hoy;

// --------- PERÍODO ANTERIOR (MISMA DURACIÓN) ----------
$dtDesde = new DateTime($desde);
$dtHasta = new DateTime($hasta);
$diffDays = $dtHasta->diff($dtDesde)->days + 1; // inclusive

$dtPrevHasta = clone $dtDesde;
$dtPrevHasta->modify('-1 day');
$dtPrevDesde = clone $dtPrevHasta;
$dtPrevDesde->modify('-' . ($diffDays - 1) . ' day');

$desdePrev = $dtPrevDesde->format('Y-m-d');
$hastaPrev = $dtPrevHasta->format('Y-m-d');

// ---------- INGRESOS POR PEDIDOS (PERÍODO ACTUAL / ANTERIOR) ----------
$sqlOrdersBase = "
  SELECT 
    COALESCE(SUM(o.total_neto),0) AS total,
    COUNT(*) AS cant
  FROM orders o
  WHERE DATE(o.fecha) BETWEEN :desde AND :hasta
    AND o.estado <> 'BORRADOR'
";

$st = db()->prepare($sqlOrdersBase);
$st->execute([':desde' => $desde, ':hasta' => $hasta]);
$ordersAct = $st->fetch(PDO::FETCH_ASSOC);
$ingPedidosAct = (float)$ordersAct['total'];
$cantPedidosAct = (int)$ordersAct['cant'];

$st->execute([':desde' => $desdePrev, ':hasta' => $hastaPrev]);
$ordersPrev = $st->fetch(PDO::FETCH_ASSOC);
$ingPedidosPrev = (float)$ordersPrev['total'];
$cantPedidosPrev = (int)$ordersPrev['cant'];

// ---------- INGRESOS POR PAGOS (PERÍODO ACTUAL / ANTERIOR) ----------
$sqlPagos = "
  SELECT COALESCE(SUM(importe),0) AS total
  FROM payments
  WHERE DATE(fecha) BETWEEN :desde AND :hasta
";
$stP = db()->prepare($sqlPagos);
$stP->execute([':desde' => $desde, ':hasta' => $hasta]);
$ingPagosAct = (float)$stP->fetchColumn();

$stP->execute([':desde' => $desdePrev, ':hasta' => $hastaPrev]);
$ingPagosPrev = (float)$stP->fetchColumn();

// ---------- GASTOS (CASH_EXPENSES) POR CATEGORÍA (ACTUAL / ANTERIOR) ----------
$sqlGastosCat = "
  SELECT categoria, COALESCE(SUM(importe),0) AS total
  FROM cash_expenses
  WHERE DATE(fecha) BETWEEN :desde AND :hasta
  GROUP BY categoria
  ORDER BY total DESC
";
$stG = db()->prepare($sqlGastosCat);
$stG->execute([':desde' => $desde, ':hasta' => $hasta]);
$gastosCatAct = $stG->fetchAll(PDO::FETCH_ASSOC);

$stG->execute([':desde' => $desdePrev, ':hasta' => $hastaPrev]);
$gastosCatPrev = $stG->fetchAll(PDO::FETCH_ASSOC);

// Totales de gasto (actual / anterior)
$sqlGastosTotal = "
  SELECT COALESCE(SUM(importe),0) AS total
  FROM cash_expenses
  WHERE DATE(fecha) BETWEEN :desde AND :hasta
";
$stGT = db()->prepare($sqlGastosTotal);
$stGT->execute([':desde' => $desde, ':hasta' => $hasta]);
$gastosTotalAct = (float)$stGT->fetchColumn();

$stGT->execute([':desde' => $desdePrev, ':hasta' => $hastaPrev]);
$gastosTotalPrev = (float)$stGT->fetchColumn();

// ---------- TOP 5 MÁQUINAS (PT) VENDIDAS EN PERÍODO ACTUAL ----------
$sqlTopPT = "
  SELECT 
    p.id,
    p.codigo,
    p.nombre,
    SUM(oi.cant) AS unidades,
    COALESCE(SUM(oi.subtotal),0) AS total_ventas
  FROM order_items oi
  JOIN orders o   ON o.id = oi.order_id
  JOIN products p ON p.id = oi.product_id
  WHERE 
    DATE(o.fecha) BETWEEN :desde AND :hasta
    AND o.estado <> 'BORRADOR'
    AND p.tipo = 'PT'
  GROUP BY p.id, p.codigo, p.nombre
  ORDER BY unidades DESC
  LIMIT 5
";
$stTop = db()->prepare($sqlTopPT);
$stTop->execute([':desde' => $desde, ':hasta' => $hasta]);
$topMaquinas = $stTop->fetchAll(PDO::FETCH_ASSOC);

// Total máquinas vendidas (PT) en el período actual
$sqlTotalPT = "
  SELECT COALESCE(SUM(oi.cant),0) AS unidades
  FROM order_items oi
  JOIN orders o   ON o.id = oi.order_id
  JOIN products p ON p.id = oi.product_id
  WHERE 
    DATE(o.fecha) BETWEEN :desde AND :hasta
    AND o.estado <> 'BORRADOR'
    AND p.tipo = 'PT'
";
$stTotPT = db()->prepare($sqlTotalPT);
$stTotPT->execute([':desde' => $desde, ':hasta' => $hasta]);
$totalMaquinasVendidas = (float)$stTotPT->fetchColumn();

// ---------- RANKING TOP 5 CLIENTES POR VENTAS (PERÍODO ACTUAL) ----------
$sqlTopClientes = "
  SELECT 
    c.id,
    c.nombre,
    COUNT(o.id) AS cant_pedidos,
    COALESCE(SUM(o.total_neto),0) AS total_vendido
  FROM orders o
  JOIN customers c ON c.id = o.customer_id
  WHERE 
    DATE(o.fecha) BETWEEN :desde AND :hasta
    AND o.estado <> 'BORRADOR'
  GROUP BY c.id, c.nombre
  ORDER BY total_vendido DESC
  LIMIT 5
";
$stTC = db()->prepare($sqlTopClientes);
$stTC->execute([':desde' => $desde, ':hasta' => $hasta]);
$topClientes = $stTC->fetchAll(PDO::FETCH_ASSOC);

// ---------- KPI: TICKET PROMEDIO ----------
$ticketPromAct = $cantPedidosAct > 0 ? ($ingPedidosAct / $cantPedidosAct) : 0;
$ticketPromPrev = $cantPedidosPrev > 0 ? ($ingPedidosPrev / $cantPedidosPrev) : 0;

// ---------- KPI: ROTACIÓN “SIMPLE” DE STOCK PT ----------
$sqlStockPT = "
  SELECT COALESCE(SUM(stock_actual),0) AS stock_actual_pt
  FROM products
  WHERE tipo = 'PT'
";
$stockPTActual = (float)db()->query($sqlStockPT)->fetchColumn();
$rotacionPT = $stockPTActual > 0 ? ($totalMaquinasVendidas / $stockPTActual) : null;

// ---------- TOTAL HISTÓRICO (INGRESOS / GASTOS) ----------
$totalHistPedidos = (float)db()->query("
  SELECT COALESCE(SUM(total_neto),0) FROM orders WHERE estado <> 'BORRADOR'
")->fetchColumn();

$totalHistPagos = (float)db()->query("
  SELECT COALESCE(SUM(importe),0) FROM payments
")->fetchColumn();

$totalHistGastos = (float)db()->query("
  SELECT COALESCE(SUM(importe),0) FROM cash_expenses
")->fetchColumn();

// ---------- PREPARAR DATOS PARA GRÁFICOS (Chart.js) ----------
$chartIngresosGastos = [
  'labels' => ['Periodo actual', 'Periodo anterior'],
  'ingresos_pagos' => [$ingPagosAct, $ingPagosPrev],
  'gastos'          => [$gastosTotalAct, $gastosTotalPrev],
];

$chartGastosCatLabels = [];
$chartGastosCatData   = [];
foreach ($gastosCatAct as $g) {
  $chartGastosCatLabels[] = $g['categoria'];
  $chartGastosCatData[]   = (float)$g['total'];
}

$chartTopPTLabels = [];
$chartTopPTUnits  = [];
foreach ($topMaquinas as $m) {
  $chartTopPTLabels[] = $m['codigo'] . ' - ' . $m['nombre'];
  $chartTopPTUnits[]  = (float)$m['unidades'];
}

include __DIR__ . '/../views/partials/header.php';
include __DIR__ . '/../views/partials/navbar.php';
?>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">Auditoría & Analytics</h5>
  </div>

  <form class="row g-2 mb-3" method="get" action="<?= url('auditoria.php') ?>">
    <div class="col-md-3">
      <label class="form-label">Desde</label>
      <input type="date" name="desde" class="form-control" value="<?= e($desde) ?>">
    </div>
    <div class="col-md-3">
      <label class="form-label">Hasta</label>
      <input type="date" name="hasta" class="form-control" value="<?= e($hasta) ?>">
    </div>
    <div class="col-md-3 d-grid">
      <label class="form-label">&nbsp;</label>
      <button class="btn btn-outline-secondary">Aplicar</button>
    </div>
    <div class="col-md-3 d-grid">
      <label class="form-label">&nbsp;</label>
      <a class="btn btn-outline-secondary" href="<?= url('auditoria.php') ?>">Reset</a>
    </div>
  </form>

  <div class="row g-3 mb-3">
    <!-- Ingresos por pedidos -->
    <div class="col-md-3">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <div class="small text-muted">Ingresos por pedidos (período)</div>
          <div class="fs-5 fw-semibold mb-1"><?= money($ingPedidosAct) ?></div>
          <div class="small text-muted">
            <?= (int)$cantPedidosAct ?> pedidos<br>
            vs período anterior: <?= money($ingPedidosPrev) ?>
          </div>
        </div>
      </div>
    </div>
    <!-- Ingresos por pagos -->
    <div class="col-md-3">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <div class="small text-muted">Ingresos por pagos (caja)</div>
          <div class="fs-5 fw-semibold mb-1"><?= money($ingPagosAct) ?></div>
          <div class="small text-muted">
            vs período anterior: <?= money($ingPagosPrev) ?>
          </div>
        </div>
      </div>
    </div>
    <!-- Gastos -->
    <div class="col-md-3">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <div class="small text-muted">Gastos (período)</div>
          <div class="fs-5 fw-semibold mb-1"><?= money($gastosTotalAct) ?></div>
          <div class="small text-muted">
            vs período anterior: <?= money($gastosTotalPrev) ?>
          </div>
        </div>
      </div>
    </div>
    <!-- Ticket promedio -->
    <div class="col-md-3">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <div class="small text-muted">Ticket promedio (por pedido)</div>
          <div class="fs-5 fw-semibold mb-1"><?= money($ticketPromAct) ?></div>
          <div class="small text-muted">
            vs período anterior: <?= money($ticketPromPrev) ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- KPIs extra -->
  <div class="row g-3 mb-3">
    <div class="col-md-4">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <div class="small text-muted">Máquinas (PT) vendidas en el período</div>
          <div class="fs-5 fw-semibold mb-1"><?= number_format($totalMaquinasVendidas, 2, ',', '.') ?></div>
          <div class="small text-muted">Incluye todos los productos tipo PT en pedidos no BORRADOR.</div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <div class="small text-muted">Stock actual de Maquinas Terminadas</div>
          <div class="fs-5 fw-semibold mb-1"><?= number_format($stockPTActual, 2, ',', '.') ?></div>
          <div class="small text-muted">Suma de stock_actual de productos tipo PT.</div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <div class="small text-muted">Rotación simple de PT (período)</div>
          <div class="fs-5 fw-semibold mb-1">
            <?php if ($rotacionPT === null): ?>
              N/A
            <?php else: ?>
              <?= number_format($rotacionPT, 2, ',', '.') ?> veces
            <?php endif; ?>
          </div>
          <div class="small text-muted">
            Aproximación: unidades PT vendidas en el período / stock actual PT.
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Total histórico -->
  <div class="row g-3 mb-4">
    <div class="col-md-4">
      <div class="card border-0 bg-light h-100">
        <div class="card-body">
          <div class="small text-muted">Total histórico de pedidos (no BORRADOR)</div>
          <div class="fs-5 fw-semibold"><?= money($totalHistPedidos) ?></div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card border-0 bg-light h-100">
        <div class="card-body">
          <div class="small text-muted">Total histórico de pagos</div>
          <div class="fs-5 fw-semibold"><?= money($totalHistPagos) ?></div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card border-0 bg-light h-100">
        <div class="card-body">
          <div class="small text-muted">Total histórico de gastos</div>
          <div class="fs-5 fw-semibold"><?= money($totalHistGastos) ?></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Gráficos -->
  <div class="row g-3 mb-4">
    <div class="col-md-6">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <h6 class="card-title">Ingresos vs Gastos (comparativa períodos)</h6>
          <canvas id="chartIngresosGastos" style="max-height:260px;"></canvas>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <h6 class="card-title">Gastos por categoría (período actual)</h6>
          <?php if (!$gastosCatAct): ?>
            <p class="text-muted small mb-0">No hay gastos registrados en el período.</p>
          <?php else: ?>
            <canvas id="chartGastosCategoria" style="max-height:260px;"></canvas>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Top máquinas y ranking de clientes -->
  <div class="row g-3 mb-4">
    <div class="col-md-6">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <h6 class="card-title">Top 5 máquinas (PT) vendidas</h6>
          <?php if (!$topMaquinas): ?>
            <p class="text-muted small mb-0">No hay ventas de PT en el período.</p>
          <?php else: ?>
            <canvas id="chartTopMaquinas" style="max-height:260px;"></canvas>
            <div class="table-responsive mt-3">
              <table class="table table-sm mb-0">
                <thead class="table-light">
                  <tr>
                    <th>Producto</th>
                    <th class="text-end">Unidades</th>
                    <th class="text-end">Total ventas</th>
                  </tr>
                </thead>
                <tbody>
                <?php foreach ($topMaquinas as $m): ?>
                  <tr>
                    <td><?= e($m['codigo'] . ' - ' . $m['nombre']) ?></td>
                    <td class="text-end"><?= number_format($m['unidades'], 2, ',', '.') ?></td>
                    <td class="text-end"><?= money($m['total_ventas']) ?></td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="col-md-6">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <h6 class="card-title">Top 5 clientes por ventas (período)</h6>
          <?php if (!$topClientes): ?>
            <p class="text-muted small mb-0">No hay pedidos en el período.</p>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table table-sm mb-0">
                <thead class="table-light">
                  <tr>
                    <th>Cliente</th>
                    <th class="text-end">Pedidos</th>
                    <th class="text-end">Total vendido</th>
                  </tr>
                </thead>
                <tbody>
                <?php foreach ($topClientes as $c): ?>
                  <tr>
                    <td><?= e($c['nombre']) ?></td>
                    <td class="text-end"><?= (int)$c['cant_pedidos'] ?></td>
                    <td class="text-end"><?= money($c['total_vendido']) ?></td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <p class="small text-muted">
    Tip: este panel está pensado para jugar con rangos de fechas y ver rápidamente 
    cómo se comportan ventas, cobros y gastos. Ideal para decisiones de precios, stock y marketing.
  </p>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const chartIngresosGastosData = <?= json_encode($chartIngresosGastos, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
const gastosCatLabels = <?= json_encode($chartGastosCatLabels, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
const gastosCatData   = <?= json_encode($chartGastosCatData, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
const topPTLabels     = <?= json_encode($chartTopPTLabels, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
const topPTUnits      = <?= json_encode($chartTopPTUnits, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;

function makeChartIngresosGastos() {
  const ctx = document.getElementById('chartIngresosGastos');
  if (!ctx) return;
  new Chart(ctx, {
    type: 'bar',
    data: {
      labels: chartIngresosGastosData.labels,
      datasets: [
        {
          label: 'Ingresos (pagos)',
          data: chartIngresosGastosData.ingresos_pagos,
        },
        {
          label: 'Gastos',
          data: chartIngresosGastosData.gastos,
        }
      ]
    },
    options: {
      responsive: true,
      plugins: {
        legend: { position: 'bottom' }
      },
      scales: {
        y: { beginAtZero: true }
      }
    }
  });
}

function makeChartGastosCategoria() {
  const ctx = document.getElementById('chartGastosCategoria');
  if (!ctx || !gastosCatLabels.length) return;
  new Chart(ctx, {
    type: 'pie',
    data: {
      labels: gastosCatLabels,
      datasets: [{
        data: gastosCatData,
      }]
    },
    options: {
      responsive: true,
      plugins: {
        legend: { position: 'bottom' }
      }
    }
  });
}

function makeChartTopMaquinas() {
  const ctx = document.getElementById('chartTopMaquinas');
  if (!ctx || !topPTLabels.length) return;
  new Chart(ctx, {
    type: 'bar',
    data: {
      labels: topPTLabels,
      datasets: [{
        label: 'Unidades vendidas',
        data: topPTUnits,
      }]
    },
    options: {
      indexAxis: 'y',
      responsive: true,
      plugins: {
        legend: { display: false }
      },
      scales: {
        x: { beginAtZero: true }
      }
    }
  });
}

document.addEventListener('DOMContentLoaded', () => {
  makeChartIngresosGastos();
  makeChartGastosCategoria();
  makeChartTopMaquinas();
});
</script>

<?php include __DIR__ . '/../views/partials/footer.php'; ?>
