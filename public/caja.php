<?php
require_once __DIR__ . '/../app/auth.php';
require_login();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

$flash_ok = '';
$flash_err = '';

$MEDIOS = ['EFECTIVO','DEBITO','TRANSFER','CREDITO','NC'];
$GASTO_CATEGORIAS = ['SERVICIOS','SUELDOS','ALQUILER','IMPUESTOS','INSUMOS','OTROS'];

// --------------------
// Tab activo por GET
// --------------------
$validTabs = ['cobrar','recientes','cc','resumen','gastos'];
$tab = $_GET['tab'] ?? 'cobrar';
if (!in_array($tab, $validTabs, true)) $tab = 'cobrar';

// -------------------------------
// POST: Registrar pago (cobranza)
// -------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  // --- COBRO ---
  if ($action === 'registrar_pago') {
    $customer_id = (int)($_POST['customer_id'] ?? 0);
    $order_id    = (int)($_POST['order_id'] ?? 0);
    $medio       = $_POST['medio'] ?? 'EFECTIVO';
    $importe     = max(0, (float)($_POST['importe'] ?? 0));
    $referencia  = trim($_POST['referencia'] ?? '');

    try {
      if ($customer_id <= 0) throw new Exception('Debe seleccionar un cliente.');
      if (!in_array($medio, $MEDIOS, true)) throw new Exception('Medio de pago inválido.');
      if ($importe <= 0) throw new Exception('Importe inválido.');

      db()->beginTransaction();

      if ($order_id > 0) {
        $so = db()->prepare("SELECT id, estado, saldo FROM orders WHERE id=? AND customer_id=? FOR UPDATE");
        $so->execute([$order_id, $customer_id]);
        $o = $so->fetch();
        if (!$o) throw new Exception('El pedido no existe o no pertenece al cliente.');
      }

      $sp = db()->prepare("INSERT INTO payments (customer_id, order_id, fecha, medio, importe, referencia)
                           VALUES (?, ?, NOW(), ?, ?, ?)");
      $sp->execute([$customer_id, $order_id ?: null, $medio, $importe, $referencia]);

      // Ledger ABONO
      $ss = db()->prepare("SELECT COALESCE(SUM(CASE WHEN tipo='CARGO' THEN monto ELSE -monto END),0) AS saldo
                           FROM customer_ledger WHERE customer_id=?");
      $ss->execute([$customer_id]);
      $saldoAnterior = (float)($ss->fetch()['saldo'] ?? 0);

      $saldoResultante = $saldoAnterior - $importe;
      $sl = db()->prepare("INSERT INTO customer_ledger (customer_id, fecha, tipo, origen, referencia_id, detalle, monto, saldo_resultante)
                           VALUES (?, NOW(), 'ABONO', 'PAGO', ?, ?, ?, ?)");
      $sl->execute([$customer_id, $order_id ?: null, 'Pago registrado en caja', $importe, $saldoResultante]);

      if ($order_id > 0) {
        $newSaldo = max(0, (float)$o['saldo'] - $importe);
        db()->prepare("UPDATE orders SET saldo=? WHERE id=?")->execute([$newSaldo, $order_id]);
        if ($newSaldo <= 0.00001 && $o['estado'] === 'ENTREGADO') {
          db()->prepare("UPDATE orders SET estado='CERRADO' WHERE id=?")->execute([$order_id]);
        }
      }

      db()->commit();
      $flash_ok = "Pago registrado correctamente.";
      $tab = 'cobrar';
    } catch (Throwable $e) {
      db()->rollBack();
      $flash_err = 'No se pudo registrar el pago: ' . $e->getMessage();
      $tab = 'cobrar';
    }
  }

  // --- GASTO ---
  if ($action === 'registrar_gasto') {
    $fechaG      = trim($_POST['fecha'] ?? '');
    $categoria   = trim($_POST['categoria'] ?? '');
    $medioG      = $_POST['medio'] ?? 'EFECTIVO';
    $importeG    = max(0, (float)($_POST['importe'] ?? 0));
    $descripcion = trim($_POST['descripcion'] ?? '');

    try {
      if ($fechaG === '') {
        $fechaG = date('Y-m-d H:i:s');
      } else {
        // Viene como datetime-local: 2025-11-30T14:30
        $fechaG = str_replace('T', ' ', $fechaG);
      }

      if ($categoria === '') {
        throw new Exception('Debe seleccionar una categoría de gasto.');
      }
      if ($importeG <= 0) {
        throw new Exception('Importe de gasto inválido.');
      }

      // Permitimos los mismos medios + eventualmente OTRO
      if (!in_array($medioG, array_merge($MEDIOS, ['OTRO']), true)) {
        throw new Exception('Medio de pago de gasto inválido.');
      }

      $userId = (int)user()['id'];

      $sg = db()->prepare("INSERT INTO cash_expenses (fecha, categoria, descripcion, medio, importe, created_by)
                           VALUES (?, ?, ?, ?, ?, ?)");
      $sg->execute([$fechaG, $categoria, $descripcion, $medioG, $importeG, $userId]);

      $flash_ok = "Gasto registrado correctamente.";
      $tab = 'gastos';
    } catch (Throwable $e) {
      $flash_err = 'No se pudo registrar el gasto: ' . $e->getMessage();
      $tab = 'gastos';
    }
  }
}

// ------------------------------------
// Datos para selects (clientes/pedidos)
// ------------------------------------
$clientes = db()->query("SELECT id, nombre FROM customers WHERE activo=1 ORDER BY nombre LIMIT 500")->fetchAll();

$pref_customer_id = (int)($_GET['customer_id'] ?? 0);
$pedidos_cliente = [];
if ($pref_customer_id > 0) {
  $spc = db()->prepare("SELECT id, estado, saldo, total_neto FROM orders WHERE customer_id=? AND saldo>0 ORDER BY id DESC LIMIT 200");
  $spc->execute([$pref_customer_id]);
  $pedidos_cliente = $spc->fetchAll();
}

// --------------------
// Filtros P. Recientes
// --------------------
$desde = $_GET['desde'] ?? date('Y-m-d');
$hasta = $_GET['hasta'] ?? date('Y-m-d');
$f_customer = (int)($_GET['f_customer'] ?? 0);

$wherePay = [];
$paramsPay = [];
$wherePay[] = "DATE(p.fecha) BETWEEN ? AND ?";
$paramsPay[] = $desde; 
$paramsPay[] = $hasta;
if ($f_customer > 0) {
  $wherePay[] = "p.customer_id = ?";
  $paramsPay[] = $f_customer;
}
$wherePaySql = 'WHERE ' . implode(' AND ', $wherePay);

$sqlPays = "SELECT p.id, p.fecha, p.medio, p.importe, p.referencia, c.nombre AS cliente, p.order_id
            FROM payments p
            JOIN customers c ON c.id=p.customer_id
            $wherePaySql
            ORDER BY p.fecha DESC, p.id DESC
            LIMIT 200";
$stPays = db()->prepare($sqlPays);
$stPays->execute($paramsPay);
$pays = $stPays->fetchAll();

// --------------------
// Filtro de GASTOS
// --------------------
$g_desde    = $_GET['g_desde'] ?? date('Y-m-d');
$g_hasta    = $_GET['g_hasta'] ?? date('Y-m-d');
$g_categoria = $_GET['g_categoria'] ?? '';

$whereG = [];
$paramsG = [];
$whereG[] = "DATE(e.fecha) BETWEEN ? AND ?";
$paramsG[] = $g_desde;
$paramsG[] = $g_hasta;

if ($g_categoria !== '') {
  $whereG[] = "e.categoria = ?";
  $paramsG[] = $g_categoria;
}

$whereGSql = 'WHERE ' . implode(' AND ', $whereG);

$sqlG = "SELECT e.id, e.fecha, e.categoria, e.descripcion, e.medio, e.importe, u.nombre AS usuario
         FROM cash_expenses e
         LEFT JOIN users u ON u.id = e.created_by
         $whereGSql
         ORDER BY e.fecha DESC, e.id DESC
         LIMIT 200";
$stG = db()->prepare($sqlG);
$stG->execute($paramsG);
$gastos = $stG->fetchAll();

// ---------------------
// Cuenta Corriente (CC)
// ---------------------
$cc_customer = (int)($_GET['cc_customer'] ?? 0);
$cc_rows = [];
$cc_saldo = null;
if ($cc_customer > 0) {
  $stCc = db()->prepare("SELECT id, fecha, tipo, origen, referencia_id, detalle, monto, saldo_resultante
                         FROM customer_ledger
                         WHERE customer_id=?
                         ORDER BY fecha DESC, id DESC
                         LIMIT 300");
  $stCc->execute([$cc_customer]);
  $cc_rows = $stCc->fetchAll();

  $sSaldo = db()->prepare("SELECT COALESCE(SUM(CASE WHEN tipo='CARGO' THEN monto ELSE -monto END),0) AS saldo
                           FROM customer_ledger WHERE customer_id=?");
  $sSaldo->execute([$cc_customer]);
  $cc_saldo = (float)$sSaldo->fetch()['saldo'];
}

// --------------
// Resumen diario
// --------------
$hoy = date('Y-m-d');

// Cobros por medio
$stRes = db()->prepare("SELECT medio, SUM(importe) AS total 
                        FROM payments 
                        WHERE DATE(fecha)=? 
                        GROUP BY medio 
                        ORDER BY medio");
$stRes->execute([$hoy]);
$resumenHoy = $stRes->fetchAll();

// Gastos por categoría
$stResG = db()->prepare("SELECT categoria, SUM(importe) AS total
                         FROM cash_expenses
                         WHERE DATE(fecha)=?
                         GROUP BY categoria
                         ORDER BY categoria");
$stResG->execute([$hoy]);
$resumenGastosHoy = $stResG->fetchAll();

function money0($n) { return '$ ' . number_format((float)$n, 0, ',', '.'); }

include __DIR__ . '/../views/partials/header.php';
include __DIR__ . '/../views/partials/navbar.php';

// helpers para clases de pestañas
function tabActive($t, $tab) { return $t===$tab ? 'active' : ''; }
function paneActive($t, $tab) { return $t===$tab ? 'show active' : ''; }
?>
<div class="container py-4">
  <h5 class="mb-3">Caja</h5>

  <?php if ($flash_ok): ?><div class="alert alert-success"><?= e($flash_ok) ?></div><?php endif; ?>
  <?php if ($flash_err): ?><div class="alert alert-danger"><?= e($flash_err) ?></div><?php endif; ?>

  <ul class="nav nav-tabs" id="tabs" role="tablist">
    <li class="nav-item" role="presentation">
      <button class="nav-link <?= tabActive('cobrar',$tab) ?>" id="cobrar-tab" data-bs-toggle="tab" data-bs-target="#cobrar" type="button" role="tab">Cobrar</button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link <?= tabActive('recientes',$tab) ?>" id="recientes-tab" data-bs-toggle="tab" data-bs-target="#recientes" type="button" role="tab">Pagos recientes</button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link <?= tabActive('gastos',$tab) ?>" id="gastos-tab" data-bs-toggle="tab" data-bs-target="#gastos" type="button" role="tab">Gastos</button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link <?= tabActive('cc',$tab) ?>" id="cc-tab" data-bs-toggle="tab" data-bs-target="#cc" type="button" role="tab">Cuenta Corriente</button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link <?= tabActive('resumen',$tab) ?>" id="resumen-tab" data-bs-toggle="tab" data-bs-target="#resumen" type="button" role="tab">Resumen diario</button>
    </li>
  </ul>

  <div class="tab-content border-bottom border-start border-end p-3 bg-white shadow-sm">

    <!-- COBRAR -->
    <div class="tab-pane fade <?= paneActive('cobrar',$tab) ?>" id="cobrar" role="tabpanel" aria-labelledby="cobrar-tab">
      <form class="row g-3" method="post" action="<?= url('caja.php') ?>?tab=cobrar">
        <input type="hidden" name="action" value="registrar_pago">

        <div class="col-md-6">
          <label class="form-label">Cliente</label>
          <select name="customer_id" class="form-select" required onchange="location.href='<?= url('caja.php') ?>?tab=cobrar&customer_id='+this.value">
            <option value="">— Seleccionar —</option>
            <?php foreach ($clientes as $c): ?>
              <option value="<?= (int)$c['id'] ?>" <?= $pref_customer_id===(int)$c['id']?'selected':'' ?>>
                <?= e($c['nombre']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <div class="form-text">Opcional: precargá pedidos al elegir cliente.</div>
        </div>

        <div class="col-md-6">
          <label class="form-label">Pedido (opcional)</label>
          <select name="order_id" class="form-select" <?= $pref_customer_id>0?'':'disabled' ?>>
            <option value="">— Sin pedido —</option>
            <?php foreach ($pedidos_cliente as $p): ?>
              <option value="<?= (int)$p['id'] ?>">#<?= (int)$p['id'] ?> — <?= e($p['estado']) ?> — Saldo <?= money($p['saldo']) ?></option>
            <?php endforeach; ?>
          </select>
          <div class="form-text">Si no seleccionás, el pago impacta solo en la cuenta corriente.</div>
        </div>

        <div class="col-md-3">
          <label class="form-label">Medio</label>
          <select name="medio" class="form-select">
            <?php foreach ($MEDIOS as $m): ?>
              <option value="<?= $m ?>"><?= $m ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-3">
          <label class="form-label">Importe</label>
          <input type="number" step="0.01" min="0.01" name="importe" class="form-control" required>
        </div>

        <div class="col-md-6">
          <label class="form-label">Referencia / Observación</label>
          <input name="referencia" class="form-control" placeholder="Comprobante, banco, últimos 4, etc.">
        </div>

        <div class="col-12 d-grid">
          <button class="btn btn-primary">Registrar pago</button>
        </div>
      </form>
    </div>

    <!-- PAGOS RECIENTES -->
    <div class="tab-pane fade <?= paneActive('recientes',$tab) ?>" id="recientes" role="tabpanel" aria-labelledby="recientes-tab">
      <form class="row g-2 mb-3" method="get" action="<?= url('caja.php') ?>">
        <input type="hidden" name="tab" value="recientes">
        <div class="col-md-3">
          <label class="form-label">Desde</label>
          <input type="date" name="desde" class="form-control" value="<?= e($desde) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Hasta</label>
          <input type="date" name="hasta" class="form-control" value="<?= e($hasta) ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Cliente</label>
          <select name="f_customer" class="form-select">
            <option value="0">Todos</option>
            <?php foreach ($clientes as $c): ?>
              <option value="<?= (int)$c['id'] ?>" <?= $f_customer===(int)$c['id']?'selected':'' ?>>
                <?= e($c['nombre']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2 d-grid">
          <label class="form-label">&nbsp;</label>
          <button class="btn btn-outline-secondary">Filtrar</button>
        </div>
      </form>

      <div class="table-responsive">
        <table class="table table-sm align-middle">
          <thead class="table-light">
          <tr>
            <th style="width:90px;">#</th>
            <th>Fecha</th>
            <th>Cliente</th>
            <th>Medio</th>
            <th class="text-end">Importe</th>
            <th>Pedido</th>
            <th>Referencia</th>
          </tr>
          </thead>
          <tbody>
          <?php if (!$pays): ?>
            <tr><td colspan="7" class="text-center text-muted py-4">No hay pagos en el rango seleccionado.</td></tr>
          <?php else: foreach ($pays as $p): ?>
            <tr>
              <td><?= (int)$p['id'] ?></td>
              <td><?= e($p['fecha']) ?></td>
              <td><?= e($p['cliente']) ?></td>
              <td><?= e($p['medio']) ?></td>
              <td class="text-end"><?= money($p['importe']) ?></td>
              <td><?= $p['order_id'] ? '#'.(int)$p['order_id'] : '-' ?></td>
              <td><?= e($p['referencia']) ?></td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- GASTOS -->
    <div class="tab-pane fade <?= paneActive('gastos',$tab) ?>" id="gastos" role="tabpanel" aria-labelledby="gastos-tab">
      <div class="row g-3 mb-4">
        <div class="col-md-5">
          <h6>Registrar gasto</h6>
          <form method="post" action="<?= url('caja.php') ?>?tab=gastos" class="border rounded p-3 bg-light">
            <input type="hidden" name="action" value="registrar_gasto">
            <div class="mb-2">
              <label class="form-label">Fecha</label>
              <input type="datetime-local" name="fecha" class="form-control" value="<?= date('Y-m-d\TH:i') ?>">
            </div>
            <div class="mb-2">
              <label class="form-label">Categoría</label>
              <select name="categoria" class="form-select" required>
                <option value="">— Seleccionar —</option>
                <?php foreach ($GASTO_CATEGORIAS as $cat): ?>
                  <option value="<?= e($cat) ?>"><?= e($cat) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="mb-2">
              <label class="form-label">Medio</label>
              <select name="medio" class="form-select">
                <?php foreach (array_merge($MEDIOS,['OTRO']) as $m): ?>
                  <option value="<?= $m ?>"><?= $m ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="mb-2">
              <label class="form-label">Importe</label>
              <input type="number" step="0.01" min="0.01" name="importe" class="form-control" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Descripción</label>
              <input name="descripcion" class="form-control" placeholder="Ej: Luz, agua, sueldo Juan, etc.">
            </div>
            <div class="d-grid">
              <button class="btn btn-danger">Registrar gasto</button>
            </div>
          </form>
        </div>

        <div class="col-md-7">
          <h6>Gastos registrados</h6>
          <form class="row g-2 mb-2" method="get" action="<?= url('caja.php') ?>">
            <input type="hidden" name="tab" value="gastos">
            <div class="col-md-3">
              <label class="form-label">Desde</label>
              <input type="date" name="g_desde" class="form-control" value="<?= e($g_desde) ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label">Hasta</label>
              <input type="date" name="g_hasta" class="form-control" value="<?= e($g_hasta) ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">Categoría</label>
              <select name="g_categoria" class="form-select">
                <option value="">Todas</option>
                <?php foreach ($GASTO_CATEGORIAS as $cat): ?>
                  <option value="<?= e($cat) ?>" <?= $g_categoria===$cat?'selected':'' ?>><?= e($cat) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-2 d-grid">
              <label class="form-label">&nbsp;</label>
              <button class="btn btn-outline-secondary">Filtrar</button>
            </div>
          </form>

          <div class="table-responsive">
            <table class="table table-sm align-middle">
              <thead class="table-light">
              <tr>
                <th style="width:70px;">#</th>
                <th>Fecha</th>
                <th>Categoría</th>
                <th>Medio</th>
                <th class="text-end">Importe</th>
                <th>Descripción</th>
                <th>Usuario</th>
              </tr>
              </thead>
              <tbody>
              <?php if (!$gastos): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">No hay gastos en el rango seleccionado.</td></tr>
              <?php else: foreach ($gastos as $g): ?>
                <tr>
                  <td><?= (int)$g['id'] ?></td>
                  <td><?= e($g['fecha']) ?></td>
                  <td><?= e($g['categoria']) ?></td>
                  <td><?= e($g['medio']) ?></td>
                  <td class="text-end"><?= money($g['importe']) ?></td>
                  <td><?= e($g['descripcion']) ?></td>
                  <td><?= e($g['usuario'] ?? '—') ?></td>
                </tr>
              <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- CUENTA CORRIENTE -->
    <div class="tab-pane fade <?= paneActive('cc',$tab) ?>" id="cc" role="tabpanel" aria-labelledby="cc-tab">
      <form class="row g-2 mb-3" method="get" action="<?= url('caja.php') ?>">
        <input type="hidden" name="tab" value="cc">
        <div class="col-md-8">
          <label class="form-label">Cliente</label>
          <select name="cc_customer" class="form-select" onchange="this.form.submit()">
            <option value="0">— Seleccionar —</option>
            <?php foreach ($clientes as $c): ?>
              <option value="<?= (int)$c['id'] ?>" <?= $cc_customer===(int)$c['id']?'selected':'' ?>><?= e($c['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4 d-grid">
          <label class="form-label">&nbsp;</label>
          <button class="btn btn-outline-secondary">Ver movimientos</button>
        </div>
      </form>

      <?php if ($cc_customer > 0): ?>
        <div class="d-flex justify-content-between align-items-center mb-2">
          <div class="fw-semibold">Saldo actual:</div>
          <div class="fs-5"><?= money($cc_saldo) ?></div>
        </div>

        <div class="table-responsive">
          <table class="table table-sm align-middle">
            <thead class="table-light">
            <tr>
              <th style="width:90px;">#</th>
              <th>Fecha</th>
              <th>Tipo</th>
              <th>Origen</th>
              <th>Ref</th>
              <th>Detalle</th>
              <th class="text-end">Monto</th>
              <th class="text-end">Saldo</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$cc_rows): ?>
              <tr><td colspan="8" class="text-center text-muted py-4">No hay movimientos.</td></tr>
            <?php else: foreach ($cc_rows as $m): ?>
              <tr>
                <td><?= (int)$m['id'] ?></td>
                <td><?= e($m['fecha']) ?></td>
                <td><?= e($m['tipo']) ?></td>
                <td><?= e($m['origen']) ?></td>
                <td><?= e($m['referencia_id']) ?></td>
                <td><?= e($m['detalle']) ?></td>
                <td class="text-end"><?= money($m['monto']) ?></td>
                <td class="text-end"><?= money($m['saldo_resultante']) ?></td>
              </tr>
            <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <div class="alert alert-info">Elegí un cliente para ver su cuenta corriente.</div>
      <?php endif; ?>
    </div>

    <!-- RESUMEN DIARIO -->
    <div class="tab-pane fade <?= paneActive('resumen',$tab) ?>" id="resumen" role="tabpanel" aria-labelledby="resumen-tab">
      <div class="mb-3">
        <div class="fw-semibold">Hoy (<?= e($hoy) ?>)</div>
      </div>

      <div class="row g-3">
        <div class="col-md-6">
          <h6>Cobros por medio</h6>
          <div class="table-responsive">
            <table class="table table-sm align-middle">
              <thead class="table-light"><tr><th>Medio</th><th class="text-end">Total</th></tr></thead>
              <tbody>
              <?php
                $totCobros = 0;
                if (!$resumenHoy): ?>
                  <tr><td colspan="2" class="text-center text-muted py-3">Sin cobros hoy.</td></tr>
                <?php else:
                  foreach ($resumenHoy as $r):
                    $totCobros += (float)$r['total']; ?>
                    <tr>
                      <td><?= e($r['medio']) ?></td>
                      <td class="text-end"><?= money($r['total']) ?></td>
                    </tr>
                  <?php endforeach; ?>
                  <tr class="table-light">
                    <td class="fw-semibold">TOTAL COBROS</td>
                    <td class="text-end fw-semibold"><?= money($totCobros) ?></td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <div class="col-md-6">
          <h6>Gastos por categoría</h6>
          <div class="table-responsive">
            <table class="table table-sm align-middle">
              <thead class="table-light"><tr><th>Categoría</th><th class="text-end">Total</th></tr></thead>
              <tbody>
              <?php
                $totGastos = 0;
                if (!$resumenGastosHoy): ?>
                  <tr><td colspan="2" class="text-center text-muted py-3">Sin gastos hoy.</td></tr>
                <?php else:
                  foreach ($resumenGastosHoy as $g):
                    $totGastos += (float)$g['total']; ?>
                    <tr>
                      <td><?= e($g['categoria']) ?></td>
                      <td class="text-end text-danger">-<?= money($g['total']) ?></td>
                    </tr>
                  <?php endforeach; ?>
                  <tr class="table-light">
                    <td class="fw-semibold">TOTAL GASTOS</td>
                    <td class="text-end fw-semibold text-danger">-<?= money($totGastos) ?></td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <?php
        $neto = $totCobros - $totGastos;
      ?>
      <div class="mt-3">
        <div class="alert <?= $neto >= 0 ? 'alert-success' : 'alert-danger' ?> d-flex justify-content-between align-items-center">
          <span class="fw-semibold">Total neto del día (Cobros - Gastos):</span>
          <span class="fs-5"><?= money($neto) ?></span>
        </div>
      </div>

      <p class="small text-muted">Tip: Podés usar “Pagos recientes” y “Gastos” con rango de fechas para cortes no diarios.</p>
    </div>

  </div> <!-- tab-content -->
</div>
<?php include __DIR__ . '/../views/partials/footer.php'; ?>
