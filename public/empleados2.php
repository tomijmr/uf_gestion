<?php
require_once __DIR__ . '/../app/auth.php';
require_login();
require_role('ADMIN', 'CAJA', 'RRHH');
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

$flash_ok = '';
$flash_err = '';

$week_start = trim($_GET['week_start'] ?? '');
if ($week_start === '') {
    $week_start = date('Y-m-d', strtotime('monday this week'));
}
$week_ts = strtotime($week_start);
if ($week_ts === false) {
    $week_start = date('Y-m-d', strtotime('monday this week'));
    $week_ts = strtotime($week_start);
}
$week_start = date('Y-m-d', $week_ts);
$week_end = date('Y-m-d', strtotime($week_start . ' +6 day'));

$days = [];
for ($i = 0; $i < 7; $i++) {
    $d = date('Y-m-d', strtotime($week_start . " +$i day"));
    $days[] = $d;
}

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

    if ($action === 'save_attendance') {
        $employee_id = (int)($_POST['employee_id'] ?? 0);
        $fecha = trim($_POST['fecha'] ?? '');
        $turno_manana = isset($_POST['turno_manana']) ? 1 : 0;
        $turno_tarde = isset($_POST['turno_tarde']) ? 1 : 0;
        $notas = trim($_POST['notas'] ?? '');

        try {
            if ($employee_id <= 0) throw new Exception('Empleado invalido.');
            if ($fecha === '' || !strtotime($fecha)) throw new Exception('Fecha invalida.');

            $ingreso_manana = $turno_manana ? '08:00:00' : null;
            $ingreso_tarde = $turno_tarde ? '13:00:00' : null;
            $presente = ($turno_manana || $turno_tarde) ? 1 : 0;
            $horas_trabajadas = ($turno_manana ? 4 : 0) + ($turno_tarde ? 4 : 0);
            $turno = 'AUSENTE';
            if ($turno_manana && $turno_tarde) $turno = 'COMPLETO';
            if ($turno_manana && !$turno_tarde) $turno = 'MANANA';
            if (!$turno_manana && $turno_tarde) $turno = 'TARDE';
            $horario_entrada = $turno_manana ? '08:00' : ($turno_tarde ? '13:00' : null);

            db()->prepare("INSERT INTO employee_attendance
                (employee_id, fecha, ingreso_manana, ingreso_tarde, horas_extras, horas_trabajadas, presente, justificado, notas, turno, horario_entrada)
                VALUES (?, ?, ?, ?, 0, ?, ?, 0, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                ingreso_manana=VALUES(ingreso_manana),
                ingreso_tarde=VALUES(ingreso_tarde),
                horas_extras=0,
                horas_trabajadas=VALUES(horas_trabajadas),
                presente=VALUES(presente),
                justificado=0,
                notas=VALUES(notas),
                turno=VALUES(turno),
                horario_entrada=VALUES(horario_entrada)")
                ->execute([
                    $employee_id,
                    $fecha,
                    $ingreso_manana,
                    $ingreso_tarde,
                    $horas_trabajadas,
                    $presente,
                    $notas ?: null,
                    $turno,
                    $horario_entrada
                ]);

            $flash_ok = 'Asistencia guardada.';
        } catch (Throwable $e) {
            $flash_err = 'No se pudo guardar la asistencia: ' . $e->getMessage();
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

            $stmtDup = db()->prepare("SELECT COUNT(*) FROM employee_payroll
                                      WHERE employee_id=? AND semana_inicio=? AND semana_fin=? AND estado <> 'ANULADO'");
            $stmtDup->execute([$employee_id, $week_start, $week_end]);
            if ((int)$stmtDup->fetchColumn() > 0) {
                throw new Exception('Ya existe una liquidacion para este empleado en esa semana.');
            }

            $stmtH = db()->prepare("SELECT COALESCE(SUM(horas_trabajadas),0) FROM employee_attendance
                                    WHERE employee_id=? AND fecha BETWEEN ? AND ?");
            $stmtH->execute([$employee_id, $week_start, $week_end]);
            $hours = (float)$stmtH->fetchColumn();
            if ($hours <= 0) throw new Exception('No hay horas registradas en la semana.');

            $gross = round($hours * $rate, 2);

            db()->beginTransaction();
            db()->prepare("INSERT INTO employee_payroll
                (employee_id, fecha_pago, semana_inicio, semana_fin, sueldo_base, descuentos_total, adelantos_total, prestamos_cuota, sueldo_neto, medio_pago, estado, notas)
                VALUES (?, ?, ?, ?, ?, 0, 0, 0, ?, ?, 'PAGADO', ?)")
                ->execute([
                    $employee_id,
                    $fecha_pago,
                    $week_start,
                    $week_end,
                    $gross,
                    $gross,
                    $medio_pago,
                    'Liquidacion semanal por horas'
                ]);

            $userId = (int)(user()['id'] ?? 0);
            try {
                db()->prepare("INSERT INTO cash_expenses (fecha, categoria, descripcion, medio, importe, created_by)
                               VALUES (NOW(), 'SUELDOS', ?, ?, ?, ?)")
                    ->execute([
                        'Pago semanal por horas - ' . $emp['nombre'] . ' ' . $emp['apellido'] . ' (' . $week_start . ' a ' . $week_end . ')',
                        $medio_pago,
                        $gross,
                        $userId
                    ]);
            } catch (Throwable $ignored) {
            }

            db()->commit();
            $flash_ok = 'Liquidacion semanal registrada correctamente.';
        } catch (Throwable $e) {
            if (db()->inTransaction()) db()->rollBack();
            $flash_err = 'No se pudo liquidar: ' . $e->getMessage();
        }
    }
}

$employees = db()->query("SELECT id, nombre, apellido, pago_por_hora, suspendido, en_licencia_medica
                          FROM employees WHERE activo=1 ORDER BY nombre, apellido")->fetchAll();

$attendanceMap = [];
$weeklySummary = [];

foreach ($employees as $e) {
    $eid = (int)$e['id'];

    $stmtA = db()->prepare("SELECT fecha, ingreso_manana, ingreso_tarde, horas_trabajadas, notas
                            FROM employee_attendance WHERE employee_id=? AND fecha BETWEEN ? AND ?");
    $stmtA->execute([$eid, $week_start, $week_end]);
    $rowsA = $stmtA->fetchAll();

    $attendanceMap[$eid] = [];
    foreach ($rowsA as $ra) {
        $attendanceMap[$eid][$ra['fecha']] = $ra;
    }

    $stmtH = db()->prepare("SELECT COALESCE(SUM(horas_trabajadas),0) FROM employee_attendance
                            WHERE employee_id=? AND fecha BETWEEN ? AND ?");
    $stmtH->execute([$eid, $week_start, $week_end]);
    $hours = (float)$stmtH->fetchColumn();

    $rate = (float)($e['pago_por_hora'] ?? 0);
    $gross = round($hours * $rate, 2);

    $stmtPaid = db()->prepare("SELECT COALESCE(SUM(sueldo_neto),0) FROM employee_payroll
                               WHERE employee_id=? AND semana_inicio=? AND semana_fin=? AND estado <> 'ANULADO'");
    $stmtPaid->execute([$eid, $week_start, $week_end]);
    $paid = (float)$stmtPaid->fetchColumn();

    $weeklySummary[$eid] = [
        'hours' => $hours,
        'rate' => $rate,
        'gross' => $gross,
        'paid' => $paid,
        'pending' => round(max(0, $gross - $paid), 2),
    ];
}

include __DIR__ . '/../views/partials/header.php';
include __DIR__ . '/../views/partials/navbar.php';
?>

<div class="container-fluid py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">Empleados2 - Asistencia y pagos semanales por hora</h5>
  </div>

  <?php if ($flash_ok): ?>
    <div class="alert alert-success"><?= e($flash_ok) ?></div>
  <?php endif; ?>
  <?php if ($flash_err): ?>
    <div class="alert alert-danger"><?= e($flash_err) ?></div>
  <?php endif; ?>

  <div class="card mb-3 shadow-sm">
    <div class="card-body">
      <form method="get" class="row g-2 align-items-end">
        <div class="col-md-3">
          <label class="form-label">Semana (inicio)</label>
          <input type="date" name="week_start" class="form-control" value="<?= e($week_start) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Semana (fin)</label>
          <input type="text" class="form-control" value="<?= e($week_end) ?>" readonly>
        </div>
        <div class="col-md-2 d-grid">
          <button class="btn btn-outline-primary">Ver semana</button>
        </div>
        <div class="col-md-4 small text-muted">
          Turnos fijos: manana 08:00-12:00 (4h) y tarde 13:00-17:00 (4h).
        </div>
      </form>
    </div>
  </div>

  <div class="card mb-3 shadow-sm">
    <div class="card-header">Carga de asistencia diaria</div>
    <div class="card-body">
      <form method="post" class="row g-2 align-items-end">
        <input type="hidden" name="action" value="save_attendance">
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
          <input type="date" name="fecha" class="form-control" value="<?= e(date('Y-m-d')) ?>" required>
        </div>
        <div class="col-md-2">
          <label class="form-label d-block">Turno manana</label>
          <input type="checkbox" name="turno_manana" value="1"> 08-12
        </div>
        <div class="col-md-2">
          <label class="form-label d-block">Turno tarde</label>
          <input type="checkbox" name="turno_tarde" value="1"> 13-17
        </div>
        <div class="col-md-2">
          <label class="form-label">Notas</label>
          <input type="text" name="notas" class="form-control" placeholder="Opcional">
        </div>
        <div class="col-md-1 d-grid">
          <button class="btn btn-primary">Guardar</button>
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
              <?php foreach ($days as $d): ?>
                <th class="text-center"><?= e(date('d/m', strtotime($d))) ?></th>
              <?php endforeach; ?>
              <th class="text-end">Horas</th>
              <th class="text-end">A liquidar</th>
              <th class="text-end">Pagado</th>
              <th class="text-end">Pendiente</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$employees): ?>
              <tr><td colspan="13" class="text-center text-muted py-4">No hay empleados activos.</td></tr>
            <?php else: ?>
              <?php foreach ($employees as $e): ?>
                <?php
                  $eid = (int)$e['id'];
                  $sum = $weeklySummary[$eid] ?? ['hours'=>0,'rate'=>0,'gross'=>0,'paid'=>0,'pending'=>0];
                  $disabled = ((int)$e['suspendido'] === 1 || (int)$e['en_licencia_medica'] === 1 || $sum['rate'] <= 0 || $sum['hours'] <= 0 || $sum['pending'] <= 0);
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
                  <?php foreach ($days as $d): ?>
                    <?php $a = $attendanceMap[$eid][$d] ?? null; ?>
                    <td class="text-center small">
                      <?php if (!$a): ?>
                        -
                      <?php else: ?>
                        <?= $a['ingreso_manana'] ? 'M' : '' ?><?= ($a['ingreso_manana'] && $a['ingreso_tarde']) ? '+' : '' ?><?= $a['ingreso_tarde'] ? 'T' : '' ?>
                        <div class="text-muted"><?= e(number_format((float)$a['horas_trabajadas'], 1, '.', '')) ?>h</div>
                      <?php endif; ?>
                    </td>
                  <?php endforeach; ?>
                  <td class="text-end fw-semibold"><?= e(number_format((float)$sum['hours'], 2, ',', '.')) ?></td>
                  <td class="text-end"><?= e(money($sum['gross'])) ?></td>
                  <td class="text-end"><?= e(money($sum['paid'])) ?></td>
                  <td class="text-end <?= $sum['pending'] > 0 ? 'text-danger fw-semibold' : 'text-success' ?>"><?= e(money($sum['pending'])) ?></td>
                  <td style="min-width:240px;">
                    <form method="post" class="row g-1">
                      <input type="hidden" name="action" value="liquidar_semana">
                      <input type="hidden" name="employee_id" value="<?= $eid ?>">
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
                        <button class="btn btn-sm btn-success" <?= $disabled ? 'disabled' : '' ?>>Pagar</button>
                      </div>
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
