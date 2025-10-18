<?php
require_once __DIR__ . '/../app/auth.php';
require_login();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

$flash_ok = '';
$flash_err = '';

$MEDIOS = ['EFECTIVO','DEBITO','TRANSFER','CREDITO','NC'];

// --------------------
// Tab activo por GET
// --------------------
$validTabs = ['cobrar','recientes','cc','resumen'];
$tab = $_GET['tab'] ?? 'cobrar';
if (!in_array($tab, $validTabs, true)) $tab = 'cobrar';

// -------------------------------
// POST: Registrar pago (cobranza)
// -------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'registrar_pago') {
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
    // permanecer en el tab cobrar después de registrar
    $tab = 'cobrar';
  } catch (Throwable $e) {
    db()->rollBack();
    $flash_err = 'No se pudo registrar el pago: ' . $e->getMessage();
    $tab = 'cobrar';
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
$paramsPay[] = $desde; $paramsPay[] = $hasta;
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
$stRes = db()->prepare("SELECT medio, SUM(importe) total FROM payments WHERE DATE(fecha)=? GROUP BY medio ORDER BY medio");
$stRes->execute([$hoy]);
$resumenHoy = $stRes->fetchAll();

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
      <div class="d-flex justify-content-between align-items-center mb-2">
        <div class="fw-semibold">Hoy (<?= e($hoy) ?>)</div>
      </div>
      <div class="table-responsive">
        <table class="table table-sm align-middle">
          <thead class="table-light"><tr><th>Medio</th><th class="text-end">Total</th></tr></thead>
          <tbody>
          <?php
            $tot = 0;
            if (!$resumenHoy): ?>
              <tr><td colspan="2" class="text-center text-muted py-3">Sin cobros hoy.</td></tr>
            <?php else:
              foreach ($resumenHoy as $r):
                $tot += (float)$r['total']; ?>
                <tr>
                  <td><?= e($r['medio']) ?></td>
                  <td class="text-end"><?= money($r['total']) ?></td>
                </tr>
              <?php endforeach; ?>
              <tr class="table-light">
                <td class="fw-semibold">TOTAL</td>
                <td class="text-end fw-semibold"><?= money($tot) ?></td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      <p class="small text-muted">Tip: podés filtrar “Pagos recientes” por rango y cliente para cortes no diarios.</p>
    </div>

  </div> <!-- tab-content -->
</div>
<?php include __DIR__ . '/../views/partials/footer.php'; ?>
