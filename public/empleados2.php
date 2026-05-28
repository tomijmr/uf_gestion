<?php
require_once __DIR__ . '/../app/auth.php';
require_login();
require_role('ADMIN', 'CAJA', 'RRHH');
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

$flash_ok = '';
$flash_err = '';

$hasMonthAdjustments = true;
try {
    db()->exec("CREATE TABLE IF NOT EXISTS employee_month_adjustments (
      id INT AUTO_INCREMENT PRIMARY KEY,
      employee_id INT NOT NULL,
      month_start DATE NOT NULL,
      month_end DATE NOT NULL,
      hours_discount DECIMAL(8,2) NOT NULL DEFAULT 0,
      reason VARCHAR(255) NULL,
      created_by INT NULL,
      updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      UNIQUE KEY uniq_employee_month (employee_id, month_start, month_end),
      KEY idx_month (month_start, month_end),
      CONSTRAINT fk_ema_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    db()->exec("CREATE TABLE IF NOT EXISTS employee_weekly_hours (
      id INT AUTO_INCREMENT PRIMARY KEY,
      employee_id INT NOT NULL,
      week_start DATE NOT NULL,
      week_end DATE NOT NULL,
      hours DECIMAL(8,2) NOT NULL DEFAULT 0,
      notes VARCHAR(255) NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      UNIQUE KEY uniq_employee_week (employee_id, week_start, week_end),
      KEY idx_week (week_start, week_end),
      CONSTRAINT fk_ewh_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Throwable $e) {
    $hasMonthAdjustments = false;
}

$period_week = trim($_REQUEST['period_week'] ?? '');
if (!preg_match('/^\d{4}-W\d{2}$/', $period_week)) {
    $period_week = date('o-\WW');
}
list($period_year, $period_week_number) = explode('-W', $period_week) + [0, 0];
$period_year = max(2000, (int)$period_year);
$period_week_number = max(1, min(53, (int)$period_week_number));
$week_start_dt = new DateTime();
$week_start_dt->setISODate($period_year, $period_week_number);
$week_start = $week_start_dt->format('Y-m-d');
$week_end = date('Y-m-d', strtotime($week_start . ' +6 days'));
$week_label = date('d/m/Y', strtotime($week_start)) . ' - ' . date('d/m/Y', strtotime($week_end));

$weeklyHoursMap = [];
try {
    $stmtWeekly = db()->prepare("SELECT employee_id, hours, notes FROM employee_weekly_hours WHERE week_start=? AND week_end=?");
    $stmtWeekly->execute([$week_start, $week_end]);
    foreach ($stmtWeekly->fetchAll() as $row) {
        $weeklyHoursMap[(int)$row['employee_id']] = [
            'hours' => (float)$row['hours'],
            'notes' => (string)$row['notes'],
        ];
    }
} catch (Throwable $e) {
    $weeklyHoursMap = [];
}

$extraExprSql = '0';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'set_hourly_rate') {
        $employee_id = (int)($_POST['employee_id'] ?? 0);
        $hourly_rate = max(0, (float)($_POST['hourly_rate'] ?? 0));

        try {
            if ($employee_id <= 0) throw new Exception('Empleado invalido.');
            if ($hourly_rate <= 0) throw new Exception('El valor por hora debe ser mayor a 0.');

            db()->prepare("UPDATE employees SET pago_por_hora=? WHERE id=?")
                ->execute([$hourly_rate, $employee_id]);

            $flash_ok = 'Valor por hora actualizado.';
        } catch (Throwable $e) {
            $flash_err = 'No se pudo actualizar el valor por hora: ' . $e->getMessage();
        }
    }

    if ($action === 'save_week_hours') {
        $employee_id = (int)($_POST['employee_id'] ?? 0);
        $hours_week = max(0, (float)($_POST['hours_week'] ?? 0));
        $notes = trim($_POST['notes'] ?? '');

        try {
            if ($employee_id <= 0) throw new Exception('Empleado invalido.');
            if ($hours_week <= 0) throw new Exception('Ingrese horas de la semana mayor a 0.');

            db()->prepare("INSERT INTO employee_weekly_hours (employee_id, week_start, week_end, hours, notes)
                           VALUES (?, ?, ?, ?, ?)
                           ON DUPLICATE KEY UPDATE hours=VALUES(hours), notes=VALUES(notes), updated_at=NOW()")
                ->execute([$employee_id, $week_start, $week_end, $hours_week, $notes !== '' ? $notes : null]);

            $weeklyHoursMap[$employee_id] = ['hours' => $hours_week, 'notes' => $notes];
            $flash_ok = 'Horas semanales guardadas.';
        } catch (Throwable $e) {
            $flash_err = 'No se pudo guardar las horas de la semana: ' . $e->getMessage();
        }
    }

    if ($action === 'registrar_adelanto') {
        $employee_id = (int)($_POST['employee_id'] ?? 0);
        $monto = max(0, (float)($_POST['monto'] ?? 0));
        $razon = trim($_POST['razon'] ?? '');
        $medio_pago = trim($_POST['medio_pago'] ?? 'EFECTIVO');
        $fecha_adelanto = trim($_POST['fecha_adelanto'] ?? date('Y-m-d'));

        try {
            if ($employee_id <= 0) throw new Exception('Empleado invalido.');
            if ($monto <= 0) throw new Exception('El monto del adelanto debe ser mayor a 0.');
            if ($fecha_adelanto === '' || !strtotime($fecha_adelanto)) throw new Exception('Fecha de adelanto invalida.');

            $stmtEmp = db()->prepare("SELECT nombre, apellido FROM employees WHERE id=? AND activo=1 LIMIT 1");
            $stmtEmp->execute([$employee_id]);
            $emp = $stmtEmp->fetch();
            if (!$emp) throw new Exception('Empleado no encontrado.');

            db()->beginTransaction();

            db()->prepare("INSERT INTO employee_advances (employee_id, monto, fecha_solicitud, fecha_aprobacion, razon, estado)
                           VALUES (?, ?, ?, ?, ?, 'APROBADO')")
                ->execute([$employee_id, $monto, $fecha_adelanto, $fecha_adelanto, ($razon !== '' ? $razon : null)]);

            $userId = (int)(user()['id'] ?? 0);
            $descripcion = 'Adelanto de sueldo - ' . $emp['nombre'] . ' ' . $emp['apellido'];
            if ($razon !== '') $descripcion .= ' (' . $razon . ')';

            db()->prepare("INSERT INTO cash_expenses (fecha, categoria, descripcion, medio, importe, created_by)
                           VALUES (?, 'SUELDOS', ?, ?, ?, ?)")
                ->execute([$fecha_adelanto . ' ' . date('H:i:s'), $descripcion, $medio_pago, $monto, $userId]);

            db()->commit();
            $flash_ok = 'Adelanto registrado correctamente.';
        } catch (Throwable $e) {
            if (db()->inTransaction()) db()->rollBack();
            $flash_err = 'No se pudo registrar el adelanto: ' . $e->getMessage();
        }
    }

    if ($action === 'liquidar_semana') {
        $employee_id = (int)($_POST['employee_id'] ?? 0);
        $medio_pago = trim($_POST['medio_pago'] ?? 'EFECTIVO');
        $fecha_pago = trim($_POST['fecha_pago'] ?? date('Y-m-d'));

        try {
            if ($employee_id <= 0) throw new Exception('Empleado invalido.');

            $stmtEmp = db()->prepare("SELECT id, nombre, apellido, pago_por_hora, suspendido, en_licencia_medica
                                      FROM employees WHERE id=? AND activo=1");
            $stmtEmp->execute([$employee_id]);
            $emp = $stmtEmp->fetch();
            if (!$emp) throw new Exception('Empleado no encontrado.');
            if ((int)$emp['suspendido'] === 1 || (int)$emp['en_licencia_medica'] === 1) {
                throw new Exception('El empleado esta suspendido o con licencia.');
            }

            $rate = (float)($emp['pago_por_hora'] ?? 0);
            if ($rate <= 0) throw new Exception('Debe cargar valor por hora antes de liquidar.');

            $hours = (float)($weeklyHoursMap[$employee_id]['hours'] ?? 0);
            if ($hours <= 0) throw new Exception('No hay horas registradas para esta semana.');

            $gross = round($hours * $rate, 2);

            $stmtAdv = db()->prepare("SELECT COALESCE(SUM(monto),0)
                                     FROM employee_advances
                                     WHERE employee_id=? AND estado='APROBADO' AND fecha_solicitud BETWEEN ? AND ?");
            $stmtAdv->execute([$employee_id, $week_start, $week_end]);
            $advances_total = (float)$stmtAdv->fetchColumn();

            $base_to_pay = max(0, round($gross - $advances_total, 2));

            $stmtPaid = db()->prepare("SELECT COALESCE(SUM(sueldo_neto),0)
                                      FROM employee_payroll
                                      WHERE employee_id=? AND semana_inicio=? AND semana_fin=? AND estado <> 'ANULADO'");
            $stmtPaid->execute([$employee_id, $week_start, $week_end]);
            $already_paid = (float)$stmtPaid->fetchColumn();

            $pending = max(0, round($base_to_pay - $already_paid, 2));
            if ($pending <= 0) throw new Exception('La semana ya se encuentra liquidada para este empleado.');

            db()->beginTransaction();

            db()->prepare("INSERT INTO employee_payroll
                (employee_id, fecha_pago, semana_inicio, semana_fin, sueldo_base, descuentos_total, adelantos_total, prestamos_cuota, sueldo_neto, medio_pago, estado, notas)
                VALUES (?, ?, ?, ?, ?, 0, ?, 0, ?, ?, 'PAGADO', ?)")
                ->execute([
                    $employee_id,
                    $fecha_pago,
                    $week_start,
                    $week_end,
                    $gross,
                    $advances_total,
                    $pending,
                    $medio_pago,
                    'Liquidacion semanal por horas'
                ]);

            db()->prepare("UPDATE employee_advances
                           SET estado='DESCONTADO'
                           WHERE employee_id=? AND estado='APROBADO' AND fecha_solicitud BETWEEN ? AND ?")
                ->execute([$employee_id, $week_start, $week_end]);

            $userId = (int)(user()['id'] ?? 0);
            $descripcion = 'Pago semanal por horas - ' . $emp['nombre'] . ' ' . $emp['apellido']
                . ' (' . $week_start . ' a ' . $week_end . ')'
                . ' - Horas: ' . number_format($hours, 2, '.', '')
                . ' - Valor hora: ' . number_format($rate, 2, '.', '')
                . ($advances_total > 0 ? (' - Adelantos: ' . number_format($advances_total, 2, '.', '')) : '');

            db()->prepare("INSERT INTO cash_expenses (fecha, categoria, descripcion, medio, importe, created_by)
                           VALUES (?, 'SUELDOS', ?, ?, ?, ?)")
                ->execute([$fecha_pago . ' ' . date('H:i:s'), $descripcion, $medio_pago, $pending, $userId]);

            db()->commit();
            $flash_ok = 'Liquidacion semanal registrada correctamente.';
        } catch (Throwable $e) {
            if (db()->inTransaction()) db()->rollBack();
            $flash_err = 'No se pudo liquidar: ' . $e->getMessage();
        }
    }

    if ($action === 'reset_week_hours') {
        $employee_id = (int)($_POST['employee_id'] ?? 0);
        try {
            if ($employee_id <= 0) throw new Exception('Empleado invalido.');

            $stmtEmp = db()->prepare("SELECT nombre, apellido FROM employees WHERE id=? LIMIT 1");
            $stmtEmp->execute([$employee_id]);
            $emp = $stmtEmp->fetch();
            if (!$emp) throw new Exception('Empleado no encontrado.');

            $stmtDel = db()->prepare("DELETE FROM employee_weekly_hours WHERE employee_id=? AND week_start=? AND week_end=?");
            $stmtDel->execute([$employee_id, $week_start, $week_end]);
            $deleted = (int)$stmtDel->rowCount();

            unset($weeklyHoursMap[$employee_id]);
            $flash_ok = 'Semana reiniciada para ' . $emp['nombre'] . ' ' . $emp['apellido'] . '. Registros eliminados: ' . $deleted . '.';
        } catch (Throwable $e) {
            $flash_err = 'No se pudo resetear la semana: ' . $e->getMessage();
        }
    }
}

$employees = db()->query("SELECT id, nombre, apellido, pago_por_hora, suspendido, en_licencia_medica
                          FROM employees WHERE activo=1 ORDER BY nombre, apellido")->fetchAll();

$weeklySummary = [];
foreach ($employees as $e) {
    $eid = (int)$e['id'];
    $hours = max(0, (float)($weeklyHoursMap[$eid]['hours'] ?? 0));
    $notes = (string)($weeklyHoursMap[$eid]['notes'] ?? '');

    $rate = (float)($e['pago_por_hora'] ?? 0);
    $gross = round($hours * $rate, 2);

    $stmtAdv = db()->prepare("SELECT COALESCE(SUM(monto),0)
                             FROM employee_advances
                             WHERE employee_id=? AND estado='APROBADO' AND fecha_solicitud BETWEEN ? AND ?");
    $stmtAdv->execute([$eid, $week_start, $week_end]);
    $advances = (float)$stmtAdv->fetchColumn();

    $baseToPay = max(0, round($gross - $advances, 2));

    $stmtPaid = db()->prepare("SELECT COALESCE(SUM(sueldo_neto),0) FROM employee_payroll
                               WHERE employee_id=? AND semana_inicio=? AND semana_fin=? AND estado <> 'ANULADO'");
    $stmtPaid->execute([$eid, $week_start, $week_end]);
    $paid = (float)$stmtPaid->fetchColumn();

    $weeklySummary[$eid] = [
        'hours' => $hours,
        'notes' => $notes,
        'rate' => $rate,
        'gross' => $gross,
        'advances' => $advances,
        'to_pay' => $baseToPay,
        'paid' => $paid,
        'pending' => round(max(0, $baseToPay - $paid), 2),
    ];
}

$totalCols = 8;

include __DIR__ . '/../views/partials/header.php';
include __DIR__ . '/../views/partials/navbar.php';
?>


<div class="container-fluid py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">Empleados2 - Horas semanales y liquidación por hora</h5>
  </div>

  <?php if ($flash_ok): ?>
    <div class="alert alert-success"><?= e($flash_ok) ?></div>
  <?php endif; ?>
  <?php if ($flash_err): ?>
    <div class="alert alert-danger"><?= e($flash_err) ?></div>
  <?php endif; ?>
  <?php if (!$hasMonthAdjustments): ?>
    <div class="alert alert-warning">No se pudo inicializar la tabla de descuentos mensuales. Se muestran empleados y liquidaciones sin descuentos de horas.</div>
  <?php endif; ?>

  <div class="card mb-3 shadow-sm">
    <div class="card-body">
      <form method="get" class="row g-2 align-items-end">
        <div class="col-md-3">
          <label class="form-label">Semana de liquidación</label>
          <input type="week" name="period_week" class="form-control" value="<?= e($period_week) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Rango de la semana</label>
          <input type="text" class="form-control" value="<?= e($week_label) ?>" readonly>
        </div>
        <div class="col-md-2 d-grid">
          <button class="btn btn-outline-primary">Ver semana</button>
        </div>
        <div class="col-md-4 small text-muted">
          Ingresa las horas trabajadas por semana y calcula el pago según el valor por hora.
        </div>
      </form>
    </div>
  </div>

  <div class="card mb-3 shadow-sm">
    <div class="card-header">Registrar horas de la semana</div>
    <div class="card-body">
      <form method="post" class="row g-2 align-items-end">
        <input type="hidden" name="action" value="save_week_hours">
        <input type="hidden" name="period_week" value="<?= e($period_week) ?>">
        <div class="col-md-3">
          <label class="form-label">Empleado</label>
          <select name="employee_id" class="form-select" required>
            <option value="">Seleccionar...</option>
            <?php foreach ($employees as $e): ?>
              <option value="<?= (int)$e['id'] ?>"><?= e($e['nombre'] . ' ' . $e['apellido']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label">Horas</label>
          <input type="number" step="0.25" min="0" name="hours_week" class="form-control" value="0" required>
        </div>
        <div class="col-md-4">
          <label class="form-label">Notas</label>
          <input type="text" name="notes" class="form-control" placeholder="Opcional">
        </div>
        <div class="col-md-3 d-grid">
          <button class="btn btn-primary">Guardar semana</button>
        </div>
      </form>
    </div>
  </div>

  <div class="card mb-3 shadow-sm">
    <div class="card-header">Registrar adelanto de sueldo</div>
    <div class="card-body">
      <form method="post" class="row g-2 align-items-end">
        <input type="hidden" name="action" value="registrar_adelanto">
        <div class="col-md-3">
          <label class="form-label">Empleado</label>
          <select name="employee_id" class="form-select" required>
            <option value="">Seleccionar...</option>
            <?php foreach ($employees as $e): ?>
              <option value="<?= (int)$e['id'] ?>"><?= e($e['nombre'] . ' ' . $e['apellido']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label">Fecha</label>
          <input type="date" name="fecha_adelanto" class="form-control" value="<?= e(date('Y-m-d')) ?>" required>
        </div>
        <div class="col-md-2">
          <label class="form-label">Monto</label>
          <input type="number" step="0.01" min="0.01" name="monto" class="form-control" required>
        </div>
        <div class="col-md-2">
          <label class="form-label">Medio</label>
          <select name="medio_pago" class="form-select">
            <option value="EFECTIVO">Efectivo</option>
            <option value="TRANSFERENCIA">Transferencia</option>
            <option value="CHEQUE">Cheque</option>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label">Motivo</label>
          <input type="text" name="razon" class="form-control" placeholder="Opcional">
        </div>
        <div class="col-md-1 d-grid">
          <button class="btn btn-warning">Adelantar</button>
        </div>
      </form>
    </div>
  </div>

  <div class="card shadow-sm">
    <div class="card-header">Resumen semanal y liquidacion</div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-sm table-bordered align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Empleado</th>
              <th>Valor hora</th>
              <th>Horas semana</th>
              <th>Notas</th>
              <th class="text-end">A liquidar</th>
              <th class="text-end">Adelantos</th>
              <th class="text-end">Pagado</th>
              <th class="text-end">Pendiente</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$employees): ?>
              <tr><td colspan="<?= (int)$totalCols ?>" class="text-center text-muted py-4">No hay empleados activos.</td></tr>
            <?php else: ?>
              <?php foreach ($employees as $e): ?>
                <?php
                  $eid = (int)$e['id'];
                  $sum = $weeklySummary[$eid] ?? ['hours'=>0,'notes'=>'','rate'=>0,'gross'=>0,'advances'=>0,'to_pay'=>0,'paid'=>0,'pending'=>0];
                  $disabledPay = ((int)$e['suspendido'] === 1 || (int)$e['en_licencia_medica'] === 1 || $sum['rate'] <= 0 || $sum['hours'] <= 0 || $sum['pending'] <= 0);
                ?>
                <tr>
                  <td>
                    <div class="fw-semibold"><?= e($e['nombre'] . ' ' . $e['apellido']) ?></div>
                    <?php if ((int)$e['suspendido'] === 1): ?><span class="badge bg-danger">Suspendido</span><?php endif; ?>
                    <?php if ((int)$e['en_licencia_medica'] === 1): ?><span class="badge bg-warning text-dark">Licencia</span><?php endif; ?>
                  </td>
                  <td style="min-width:220px;">
                    <form method="post" class="d-flex gap-1">
                      <input type="hidden" name="action" value="set_hourly_rate">
                      <input type="hidden" name="employee_id" value="<?= $eid ?>">
                      <input type="number" step="0.01" min="0" name="hourly_rate" class="form-control form-control-sm" value="<?= e(number_format((float)$sum['rate'], 2, '.', '')) ?>" required>
                      <button class="btn btn-sm btn-outline-primary">OK</button>
                    </form>
                  </td>
                  <td class="text-end fw-semibold">
                    <form method="post" class="d-flex gap-1 justify-content-end">
                      <input type="hidden" name="action" value="save_week_hours">
                      <input type="hidden" name="employee_id" value="<?= $eid ?>">
                      <input type="hidden" name="period_week" value="<?= e($period_week) ?>">
                      <input type="number" step="0.25" min="0" name="hours_week" class="form-control form-control-sm text-end" style="width:110px;" value="<?= e(number_format((float)$sum['hours'], 2, '.', '')) ?>">
                      <button class="btn btn-sm btn-outline-primary">OK</button>
                    </form>
                  </td>
                  <td><?= e($sum['notes']) ?></td>
                  <td class="text-end"><?= e(money($sum['to_pay'])) ?></td>
                  <td class="text-end text-warning fw-semibold"><?= e(money($sum['advances'])) ?></td>
                  <td class="text-end"><?= e(money($sum['paid'])) ?></td>
                  <td class="text-end <?= $sum['pending'] > 0 ? 'text-danger fw-semibold' : 'text-success' ?>"><?= e(money($sum['pending'])) ?></td>
                  <td style="min-width:270px;">
                    <form method="post" class="row g-1">
                      <input type="hidden" name="action" value="liquidar_semana">
                      <input type="hidden" name="employee_id" value="<?= $eid ?>">
                      <input type="hidden" name="period_week" value="<?= e($period_week) ?>">
                      <div class="col-5">
                        <input type="date" name="fecha_pago" class="form-control form-control-sm" value="<?= e(date('Y-m-d')) ?>" required>
                      </div>
                      <div class="col-4">
                        <select name="medio_pago" class="form-select form-select-sm">
                          <option value="EFECTIVO">Efectivo</option>
                          <option value="TRANSFERENCIA">Transfer</option>
                          <option value="CHEQUE">Cheque</option>
                        </select>
                      </div>
                      <div class="col-3 d-grid">
                        <button class="btn btn-sm btn-success" <?= $disabledPay ? 'disabled' : '' ?>>Pagar</button>
                      </div>
                    </form>
                    <form method="post" class="mt-1" onsubmit="return confirm('¿Resetear las horas de esta semana para este empleado?');">
                      <input type="hidden" name="action" value="reset_week_hours">
                      <input type="hidden" name="employee_id" value="<?= $eid ?>">
                      <input type="hidden" name="period_week" value="<?= e($period_week) ?>">
                      <button class="btn btn-sm btn-outline-danger w-100" <?= ((float)$sum['hours'] <= 0) ? 'disabled' : '' ?>>Reset semana</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../views/partials/footer.php'; ?>
