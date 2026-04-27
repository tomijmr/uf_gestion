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
} catch (Throwable $e) {
    $hasMonthAdjustments = false;
}

$period_month = trim($_GET['period_month'] ?? date('Y-m'));
if (!preg_match('/^\d{4}-\d{2}$/', $period_month)) {
    $period_month = date('Y-m');
}
$period_start = $period_month . '-01';
$period_start_ts = strtotime($period_start);
if ($period_start_ts === false) {
    $period_start = date('Y-m-01');
    $period_start_ts = strtotime($period_start);
}
$period_start = date('Y-m-01', $period_start_ts);
$period_end = date('Y-m-t', strtotime($period_start));

$days = [];
$days_count = (int)date('t', strtotime($period_start));
for ($i = 0; $i < $days_count; $i++) {
    $d = date('Y-m-d', strtotime($period_start . " +$i day"));
    $days[] = $d;
}

$attendanceCols = [];
try {
    $cols = db()->query("SHOW COLUMNS FROM employee_attendance")->fetchAll();
    foreach ($cols as $c) {
        $attendanceCols[(string)$c['Field']] = true;
    }
} catch (Throwable $e) {
    $attendanceCols = [];
}

$hasIngresoManana = isset($attendanceCols['ingreso_manana']);
$hasIngresoTarde = isset($attendanceCols['ingreso_tarde']);
$hasHorasTrabajadas = isset($attendanceCols['horas_trabajadas']);
$hasHorasExtras = isset($attendanceCols['horas_extras']);
$hasTurno = isset($attendanceCols['turno']);
$hasHorarioEntrada = isset($attendanceCols['horario_entrada']);
$hasHoraEntrada = isset($attendanceCols['hora_entrada']);
$hasHoraSalida = isset($attendanceCols['hora_salida']);

$hoursByShiftSql = "((CASE WHEN ingreso_manana IS NOT NULL THEN 4 ELSE 0 END) + (CASE WHEN ingreso_tarde IS NOT NULL THEN 4 ELSE 0 END) + " . ($hasHorasExtras ? 'COALESCE(horas_extras,0)' : '0') . ")";
$hoursByClockSql = "((CASE WHEN hora_entrada IS NOT NULL AND hora_salida IS NOT NULL THEN GREATEST((TIMESTAMPDIFF(MINUTE, CONCAT(fecha,' ',hora_entrada), CONCAT(fecha,' ',hora_salida))/60) - 1,0) ELSE 0 END) + " . ($hasHorasExtras ? 'COALESCE(horas_extras,0)' : '0') . ")";

if ($hasHorasTrabajadas) {
    $hoursExprSql = "(CASE
      WHEN DAYOFWEEK(fecha)=1 THEN 0
      WHEN COALESCE(horas_trabajadas,0) > 0 THEN COALESCE(horas_trabajadas,0)
      WHEN (" . ($hasIngresoManana ? "ingreso_manana IS NOT NULL" : "0=1") . " OR " . ($hasIngresoTarde ? "ingreso_tarde IS NOT NULL" : "0=1") . ") THEN $hoursByShiftSql
      WHEN " . (($hasHoraEntrada && $hasHoraSalida) ? "(hora_entrada IS NOT NULL AND hora_salida IS NOT NULL)" : "0=1") . " THEN $hoursByClockSql
      WHEN presente=1 THEN 8
      ELSE 0
    END)";
} else {
    $hoursExprSql = "(CASE
      WHEN DAYOFWEEK(fecha)=1 THEN 0
      WHEN (" . ($hasIngresoManana ? "ingreso_manana IS NOT NULL" : "0=1") . " OR " . ($hasIngresoTarde ? "ingreso_tarde IS NOT NULL" : "0=1") . ") THEN $hoursByShiftSql
      WHEN " . (($hasHoraEntrada && $hasHoraSalida) ? "(hora_entrada IS NOT NULL AND hora_salida IS NOT NULL)" : "0=1") . " THEN $hoursByClockSql
      WHEN presente=1 THEN 8
      ELSE 0
    END)";
}

$extraExprSql = $hasHorasExtras ? 'COALESCE(horas_extras,0)' : '0';

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
        $horas_extras = max(0, (float)($_POST['horas_extras'] ?? 0));
        $notas = trim($_POST['notas'] ?? '');

        try {
            if ($employee_id <= 0) throw new Exception('Empleado invalido.');
            if ($fecha === '' || !strtotime($fecha)) throw new Exception('Fecha invalida.');

            $ingreso_manana = $turno_manana ? '08:00:00' : null;
            $ingreso_tarde = $turno_tarde ? '13:00:00' : null;
            $presente = ($turno_manana || $turno_tarde) ? 1 : 0;
            $horas_trabajadas = ($turno_manana ? 4 : 0) + ($turno_tarde ? 4 : 0) + $horas_extras;
            $turno = 'AUSENTE';
            if ($turno_manana && $turno_tarde) $turno = 'COMPLETO';
            if ($turno_manana && !$turno_tarde) $turno = 'MANANA';
            if (!$turno_manana && $turno_tarde) $turno = 'TARDE';
            $horario_entrada = $turno_manana ? '08:00' : ($turno_tarde ? '13:00' : null);

            $insertCols = ['employee_id', 'fecha', 'presente', 'justificado', 'notas'];
            $insertVals = [$employee_id, $fecha, $presente, 0, $notas ?: null];

            if ($hasIngresoManana) {
                $insertCols[] = 'ingreso_manana';
                $insertVals[] = $ingreso_manana;
            }
            if ($hasIngresoTarde) {
                $insertCols[] = 'ingreso_tarde';
                $insertVals[] = $ingreso_tarde;
            }
            if ($hasHorasExtras) {
                $insertCols[] = 'horas_extras';
                $insertVals[] = $horas_extras;
            }
            if ($hasHorasTrabajadas) {
                $insertCols[] = 'horas_trabajadas';
                $insertVals[] = $horas_trabajadas;
            }
            if ($hasTurno) {
                $insertCols[] = 'turno';
                $insertVals[] = $turno;
            }
            if ($hasHorarioEntrada) {
                $insertCols[] = 'horario_entrada';
                $insertVals[] = $horario_entrada;
            }
            if ($hasHoraEntrada) {
                $insertCols[] = 'hora_entrada';
                $insertVals[] = $horario_entrada ? ($horario_entrada . ':00') : null;
            }
            if ($hasHoraSalida) {
                $horaSalida = null;
                if ($turno_manana && $turno_tarde) $horaSalida = '17:00:00';
                elseif ($turno_manana) $horaSalida = '12:00:00';
                elseif ($turno_tarde) $horaSalida = '17:00:00';
                $insertCols[] = 'hora_salida';
                $insertVals[] = $horaSalida;
            }

            db()->prepare("DELETE FROM employee_attendance WHERE employee_id=? AND fecha=?")
              ->execute([$employee_id, $fecha]);

            $placeholders = implode(',', array_fill(0, count($insertCols), '?'));
            $updates = [];
            foreach ($insertCols as $col) {
                if ($col === 'employee_id' || $col === 'fecha') continue;
                $updates[] = $col . '=VALUES(' . $col . ')';
            }

            $sqlAttendance = 'INSERT INTO employee_attendance (' . implode(', ', $insertCols) . ') '
              . 'VALUES (' . $placeholders . ') '
              . 'ON DUPLICATE KEY UPDATE ' . implode(', ', $updates);

            db()->prepare($sqlAttendance)->execute($insertVals);

            $flash_ok = 'Asistencia guardada.';
        } catch (Throwable $e) {
            $flash_err = 'No se pudo guardar la asistencia: ' . $e->getMessage();
        }
    }

    if ($action === 'set_month_hours_discount') {
        $employee_id = (int)($_POST['employee_id'] ?? 0);
        $hours_discount = max(0, (float)($_POST['hours_discount'] ?? 0));
        $reason = trim($_POST['discount_reason'] ?? '');

        try {
            if (!$hasMonthAdjustments) throw new Exception('La funcionalidad de descuentos mensuales no esta disponible en esta base de datos.');
            if ($employee_id <= 0) throw new Exception('Empleado invalido.');

            $stmtEmp = db()->prepare("SELECT id FROM employees WHERE id=? AND activo=1 LIMIT 1");
            $stmtEmp->execute([$employee_id]);
            if (!$stmtEmp->fetch()) throw new Exception('Empleado no encontrado.');

            $userId = (int)(user()['id'] ?? 0);
            db()->prepare("INSERT INTO employee_month_adjustments (employee_id, month_start, month_end, hours_discount, reason, created_by, updated_at)
                           VALUES (?, ?, ?, ?, ?, ?, NOW())
                           ON DUPLICATE KEY UPDATE hours_discount=VALUES(hours_discount), reason=VALUES(reason), created_by=VALUES(created_by), updated_at=NOW()")
                ->execute([$employee_id, $period_start, $period_end, $hours_discount, ($reason !== '' ? $reason : null), $userId ?: null]);

            $flash_ok = 'Descuento mensual de horas actualizado.';
        } catch (Throwable $e) {
            $flash_err = 'No se pudo guardar el descuento mensual: ' . $e->getMessage();
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

    if ($action === 'liquidar_mes') {
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

            $stmtH = db()->prepare("SELECT COALESCE(SUM($hoursExprSql),0)
                                    FROM employee_attendance a
                                    INNER JOIN (
                                      SELECT MAX(id) AS id
                                      FROM employee_attendance
                                      WHERE employee_id=? AND fecha BETWEEN ? AND ?
                                      GROUP BY fecha
                                    ) latest ON latest.id = a.id");
            $stmtH->execute([$employee_id, $period_start, $period_end]);
            $hours = (float)$stmtH->fetchColumn();
            if ($hours <= 0) throw new Exception('No hay horas registradas en el mes.');

            $hours_discount = 0.0;
            if ($hasMonthAdjustments) {
                $stmtAdj = db()->prepare("SELECT COALESCE(hours_discount,0) FROM employee_month_adjustments
                                         WHERE employee_id=? AND month_start=? AND month_end=?");
                $stmtAdj->execute([$employee_id, $period_start, $period_end]);
                $hours_discount = max(0, (float)$stmtAdj->fetchColumn());
            }
            $hours_net = max(0, $hours - $hours_discount);
            if ($hours_net <= 0) throw new Exception('Las horas netas a liquidar quedaron en 0. Revise el descuento mensual de horas.');

            $gross = round($hours_net * $rate, 2);

            $stmtAdv = db()->prepare("SELECT COALESCE(SUM(monto),0)
                                     FROM employee_advances
                                     WHERE employee_id=? AND estado='APROBADO' AND fecha_solicitud BETWEEN ? AND ?");
            $stmtAdv->execute([$employee_id, $period_start, $period_end]);
            $advances_total = (float)$stmtAdv->fetchColumn();

            $base_to_pay = max(0, round($gross - $advances_total, 2));

            $stmtPaid = db()->prepare("SELECT COALESCE(SUM(sueldo_neto),0)
                                      FROM employee_payroll
                                      WHERE employee_id=? AND semana_inicio=? AND semana_fin=? AND estado <> 'ANULADO'");
            $stmtPaid->execute([$employee_id, $period_start, $period_end]);
            $already_paid = (float)$stmtPaid->fetchColumn();

            $pending = max(0, round($base_to_pay - $already_paid, 2));
            if ($pending <= 0) throw new Exception('El mes ya se encuentra liquidado para este empleado.');

            db()->beginTransaction();

            db()->prepare("INSERT INTO employee_payroll
                (employee_id, fecha_pago, semana_inicio, semana_fin, sueldo_base, descuentos_total, adelantos_total, prestamos_cuota, sueldo_neto, medio_pago, estado, notas)
                VALUES (?, ?, ?, ?, ?, 0, ?, 0, ?, ?, 'PAGADO', ?)")
                ->execute([
                    $employee_id,
                    $fecha_pago,
                    $period_start,
                    $period_end,
                    $gross,
                    $advances_total,
                    $pending,
                    $medio_pago,
                    'Liquidacion mensual por horas'
                ]);

            db()->prepare("UPDATE employee_advances
                           SET estado='DESCONTADO'
                           WHERE employee_id=? AND estado='APROBADO' AND fecha_solicitud BETWEEN ? AND ?")
                ->execute([$employee_id, $period_start, $period_end]);

            $userId = (int)(user()['id'] ?? 0);
            $descripcion = 'Pago mensual por horas - ' . $emp['nombre'] . ' ' . $emp['apellido']
                . ' (' . $period_start . ' a ' . $period_end . ')'
                . ' - Horas netas: ' . number_format($hours_net, 2, '.', '')
                . ($advances_total > 0 ? (' - Adelantos: ' . number_format($advances_total, 2, '.', '')) : '');

            db()->prepare("INSERT INTO cash_expenses (fecha, categoria, descripcion, medio, importe, created_by)
                           VALUES (?, 'SUELDOS', ?, ?, ?, ?)")
                ->execute([$fecha_pago . ' ' . date('H:i:s'), $descripcion, $medio_pago, $pending, $userId]);

            db()->commit();
            $flash_ok = 'Liquidacion mensual registrada correctamente.';
        } catch (Throwable $e) {
            if (db()->inTransaction()) db()->rollBack();
            $flash_err = 'No se pudo liquidar: ' . $e->getMessage();
        }
    }

    if ($action === 'reset_month_hours') {
        $employee_id = (int)($_POST['employee_id'] ?? 0);
        try {
            if ($employee_id <= 0) throw new Exception('Empleado invalido.');

            $stmtEmp = db()->prepare("SELECT nombre, apellido FROM employees WHERE id=? LIMIT 1");
            $stmtEmp->execute([$employee_id]);
            $emp = $stmtEmp->fetch();
            if (!$emp) throw new Exception('Empleado no encontrado.');

            $stmtDel = db()->prepare("DELETE FROM employee_attendance WHERE employee_id=? AND fecha BETWEEN ? AND ?");
            $stmtDel->execute([$employee_id, $period_start, $period_end]);
            $deleted = (int)$stmtDel->rowCount();

            $flash_ok = 'Mes reiniciado para ' . $emp['nombre'] . ' ' . $emp['apellido'] . '. Registros eliminados: ' . $deleted . '.';
        } catch (Throwable $e) {
            $flash_err = 'No se pudo resetear el mes: ' . $e->getMessage();
        }
    }
}

$employees = db()->query("SELECT id, nombre, apellido, pago_por_hora, suspendido, en_licencia_medica
                          FROM employees WHERE activo=1 ORDER BY nombre, apellido")->fetchAll();

$attendanceMap = [];
$monthlySummary = [];

$monthlyAdjustments = [];
if ($hasMonthAdjustments) {
    try {
        $stmtAdjMonth = db()->prepare("SELECT employee_id, hours_discount, reason
                                       FROM employee_month_adjustments
                                       WHERE month_start=? AND month_end=?");
        $stmtAdjMonth->execute([$period_start, $period_end]);
        foreach ($stmtAdjMonth->fetchAll() as $adj) {
            $monthlyAdjustments[(int)$adj['employee_id']] = [
                'hours_discount' => (float)($adj['hours_discount'] ?? 0),
                'reason' => (string)($adj['reason'] ?? ''),
            ];
        }
    } catch (Throwable $e) {
        $hasMonthAdjustments = false;
        $monthlyAdjustments = [];
    }
}

foreach ($employees as $e) {
    $eid = (int)$e['id'];

    $latestByDaySql = "FROM employee_attendance a
              INNER JOIN (
                SELECT MAX(id) AS id
                FROM employee_attendance
                WHERE employee_id=? AND fecha BETWEEN ? AND ?
                GROUP BY fecha
              ) latest ON latest.id = a.id";

    $selIngresoManana = $hasIngresoManana ? 'ingreso_manana' : 'NULL AS ingreso_manana';
    $selIngresoTarde = $hasIngresoTarde ? 'ingreso_tarde' : 'NULL AS ingreso_tarde';
    $selHorasExtras = $hasHorasExtras ? 'COALESCE(horas_extras,0) AS horas_extras' : '0 AS horas_extras';
    $selHorasTrabajadas = $hoursExprSql . ' AS horas_trabajadas';

    $stmtA = db()->prepare("SELECT fecha, presente, $selIngresoManana, $selIngresoTarde, $selHorasExtras, $selHorasTrabajadas, notas
                            $latestByDaySql");
    $stmtA->execute([$eid, $period_start, $period_end]);
    $rowsA = $stmtA->fetchAll();

    $attendanceMap[$eid] = [];
    foreach ($rowsA as $ra) {
        $attendanceMap[$eid][$ra['fecha']] = $ra;
    }

    $stmtExtra = db()->prepare("SELECT COALESCE(SUM($extraExprSql),0)
                                $latestByDaySql");
    $stmtExtra->execute([$eid, $period_start, $period_end]);
    $extras = (float)$stmtExtra->fetchColumn();

    $stmtH = db()->prepare("SELECT COALESCE(SUM($hoursExprSql),0)
                            $latestByDaySql");
    $stmtH->execute([$eid, $period_start, $period_end]);
    $hours = (float)$stmtH->fetchColumn();

    $hoursDiscount = (float)($monthlyAdjustments[$eid]['hours_discount'] ?? 0);
    $hoursNet = max(0, $hours - $hoursDiscount);

    $rate = (float)($e['pago_por_hora'] ?? 0);
    $gross = round($hoursNet * $rate, 2);

    $stmtAdv = db()->prepare("SELECT COALESCE(SUM(monto),0)
                             FROM employee_advances
                             WHERE employee_id=? AND estado='APROBADO' AND fecha_solicitud BETWEEN ? AND ?");
    $stmtAdv->execute([$eid, $period_start, $period_end]);
    $advances = (float)$stmtAdv->fetchColumn();

    $baseToPay = max(0, round($gross - $advances, 2));

    $stmtPaid = db()->prepare("SELECT COALESCE(SUM(sueldo_neto),0) FROM employee_payroll
                               WHERE employee_id=? AND semana_inicio=? AND semana_fin=? AND estado <> 'ANULADO'");
    $stmtPaid->execute([$eid, $period_start, $period_end]);
    $paid = (float)$stmtPaid->fetchColumn();

    $monthlySummary[$eid] = [
        'hours' => $hours,
        'extras' => $extras,
        'hours_discount' => $hoursDiscount,
        'hours_net' => $hoursNet,
        'discount_reason' => (string)($monthlyAdjustments[$eid]['reason'] ?? ''),
        'rate' => $rate,
        'gross' => $gross,
        'advances' => $advances,
        'to_pay' => $baseToPay,
        'paid' => $paid,
        'pending' => round(max(0, $baseToPay - $paid), 2),
    ];
}

$totalCols = 12;

include __DIR__ . '/../views/partials/header.php';
include __DIR__ . '/../views/partials/navbar.php';
?>

<style>
  .attendance-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    max-width: 420px;
  }
  .attendance-chip {
    font-size: 11px;
    line-height: 1;
    padding: 4px 6px;
    border-radius: 6px;
    border: 1px solid #dee2e6;
    background: #f8f9fa;
    white-space: nowrap;
  }
  .attendance-chip.has-att {
    background: #e8f5e9;
    border-color: #b7dfbf;
  }
  .attendance-chip.no-att {
    background: #fff;
    color: #6c757d;
  }
</style>

<div class="container-fluid py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">Empleados2 - Asistencia y liquidacion mensual por hora</h5>
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
          <label class="form-label">Mes de liquidacion</label>
          <input type="month" name="period_month" class="form-control" value="<?= e($period_month) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Rango</label>
          <input type="text" class="form-control" value="<?= e($period_start . ' a ' . $period_end) ?>" readonly>
        </div>
        <div class="col-md-2 d-grid">
          <button class="btn btn-outline-primary">Ver mes</button>
        </div>
        <div class="col-md-4 small text-muted">
          Control de asistencia diario con liquidacion mensual.
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
        <div class="col-md-1">
          <label class="form-label">H. extra</label>
          <input type="number" step="0.25" min="0" name="horas_extras" class="form-control" value="0">
        </div>
        <div class="col-md-1">
          <label class="form-label">Notas</label>
          <input type="text" name="notas" class="form-control" placeholder="Opc.">
        </div>
        <div class="col-md-1 d-grid">
          <button class="btn btn-primary">Guardar</button>
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
    <div class="card-header">Resumen mensual y liquidacion</div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-sm table-bordered align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Empleado</th>
              <th>Valor hora</th>
              <th>Asistencia del mes</th>
              <th class="text-end">H. extra</th>
              <th class="text-end">Horas</th>
              <th class="text-end">Desc. hs</th>
              <th class="text-end">Horas netas</th>
              <th class="text-end">Adelantos</th>
              <th class="text-end">A liquidar</th>
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
                  $sum = $monthlySummary[$eid] ?? ['hours'=>0,'extras'=>0,'hours_discount'=>0,'hours_net'=>0,'discount_reason'=>'','rate'=>0,'gross'=>0,'advances'=>0,'to_pay'=>0,'paid'=>0,'pending'=>0];
                  $disabledPay = ((int)$e['suspendido'] === 1 || (int)$e['en_licencia_medica'] === 1 || $sum['rate'] <= 0 || $sum['hours_net'] <= 0 || $sum['pending'] <= 0);
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
                  <td>
                    <div class="attendance-grid">
                      <?php foreach ($days as $d): ?>
                        <?php $a = $attendanceMap[$eid][$d] ?? null; ?>
                        <?php if (!$a): ?>
                          <span class="attendance-chip no-att"><?= e(date('d', strtotime($d))) ?>:-</span>
                        <?php else: ?>
                          <?php
                            $turnoTxt = ($a['ingreso_manana'] ? 'M' : '')
                              . (($a['ingreso_manana'] && $a['ingreso_tarde']) ? '+' : '')
                              . ($a['ingreso_tarde'] ? 'T' : '');
                            if ($turnoTxt === '') $turnoTxt = ((int)($a['presente'] ?? 0) === 1 ? 'P' : 'A');
                            $horasTxt = number_format((float)$a['horas_trabajadas'], 1, '.', '') . 'h';
                            $extraTxt = ((float)($a['horas_extras'] ?? 0) > 0)
                              ? (' | +' . number_format((float)$a['horas_extras'], 1, '.', '') . 'h')
                              : '';
                          ?>
                          <span class="attendance-chip has-att" title="<?= e(date('d/m/Y', strtotime($d)) . ' - ' . $horasTxt . $extraTxt) ?>">
                            <?= e(date('d', strtotime($d))) ?>:<?= e($turnoTxt) ?>
                          </span>
                        <?php endif; ?>
                      <?php endforeach; ?>
                    </div>
                  </td>
                  <td class="text-end text-primary fw-semibold"><?= e(number_format((float)$sum['extras'], 2, ',', '.')) ?></td>
                  <td class="text-end fw-semibold"><?= e(number_format((float)$sum['hours'], 2, ',', '.')) ?></td>
                  <td class="text-end text-danger fw-semibold" title="<?= e($sum['discount_reason']) ?>"><?= e(number_format((float)$sum['hours_discount'], 2, ',', '.')) ?></td>
                  <td class="text-end fw-semibold"><?= e(number_format((float)$sum['hours_net'], 2, ',', '.')) ?></td>
                  <td class="text-end text-warning fw-semibold"><?= e(money($sum['advances'])) ?></td>
                  <td class="text-end"><?= e(money($sum['to_pay'])) ?></td>
                  <td class="text-end"><?= e(money($sum['paid'])) ?></td>
                  <td class="text-end <?= $sum['pending'] > 0 ? 'text-danger fw-semibold' : 'text-success' ?>"><?= e(money($sum['pending'])) ?></td>
                  <td style="min-width:270px;">
                    <form method="post" class="row g-1">
                      <input type="hidden" name="action" value="liquidar_mes">
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
                        <button class="btn btn-sm btn-success" <?= $disabledPay ? 'disabled' : '' ?>>Pagar</button>
                      </div>
                    </form>
                    <form method="post" class="mt-1" onsubmit="return confirm('¿Resetear horas/asistencia de este mes para este empleado?');">
                      <input type="hidden" name="action" value="reset_month_hours">
                      <input type="hidden" name="employee_id" value="<?= $eid ?>">
                      <button class="btn btn-sm btn-outline-danger w-100" <?= ((float)$sum['hours'] <= 0) ? 'disabled' : '' ?>>Reset mes</button>
                    </form>
                    <form method="post" class="row g-1 mt-1">
                      <input type="hidden" name="action" value="set_month_hours_discount">
                      <input type="hidden" name="employee_id" value="<?= $eid ?>">
                      <div class="col-4">
                        <input type="number" step="0.25" min="0" name="hours_discount" class="form-control form-control-sm" value="<?= e(number_format((float)$sum['hours_discount'], 2, '.', '')) ?>" title="Horas a descontar en el mes">
                      </div>
                      <div class="col-5">
                        <input type="text" name="discount_reason" class="form-control form-control-sm" value="<?= e($sum['discount_reason']) ?>" placeholder="Motivo">
                      </div>
                      <div class="col-3 d-grid">
                        <button class="btn btn-sm btn-outline-secondary">Desc.</button>
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
