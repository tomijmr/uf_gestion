<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../app/auth.php';
require_login();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

$flash_ok = '';
$flash_err = '';

// Validar tabs
$validTabs = ['empleados','asistencia','movimientos','resumen','nomina'];
$tab = $_GET['tab'] ?? 'empleados';
if (!in_array($tab, $validTabs, true)) $tab = 'empleados';

// Empleado seleccionado en movimientos/resumen
$selected_emp_id = (int)($_GET['emp_id'] ?? 0);

// ========== POST: Acciones ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  // --- CREAR/EDITAR EMPLEADO ---
  if ($action === 'guardar_empleado') {
    $emp_id = (int)($_POST['employee_id'] ?? 0);
    $nombre = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $dni = trim($_POST['dni'] ?? '');
    $fecha_nacimiento = trim($_POST['fecha_nacimiento'] ?? '');
    $domicilio = trim($_POST['domicilio'] ?? '');
    $ciudad = trim($_POST['ciudad'] ?? '');
    $provincia = trim($_POST['provincia'] ?? '');
    $codigo_postal = trim($_POST['codigo_postal'] ?? '');
    $fecha_contratacion = trim($_POST['fecha_contratacion'] ?? '');
    $sueldo_base_semanal = max(0, (float)($_POST['sueldo_base_semanal'] ?? 0));
    $puesto = trim($_POST['puesto'] ?? '');

    try {
      if ($nombre === '') throw new Exception('El nombre es obligatorio.');
      if ($apellido === '') throw new Exception('El apellido es obligatorio.');
      if ($sueldo_base_semanal <= 0) throw new Exception('El sueldo base semanal debe ser mayor a 0.');
      if ($fecha_contratacion === '') throw new Exception('La fecha de contratación es obligatoria.');

      if ($emp_id > 0) {
        // Actualizar
        $stmt = db()->prepare("UPDATE employees SET nombre=?, apellido=?, email=?, telefono=?, dni=?, fecha_nacimiento=?, 
                               domicilio=?, ciudad=?, provincia=?, codigo_postal=?, fecha_contratacion=?, 
                               sueldo_base_semanal=?, puesto=? WHERE id=?");
        $stmt->execute([$nombre, $apellido, $email ?: null, $telefono, $dni, $fecha_nacimiento ?: null, $domicilio, 
                        $ciudad, $provincia, $codigo_postal, $fecha_contratacion, $sueldo_base_semanal, $puesto, $emp_id]);
        $flash_ok = "Empleado actualizado correctamente.";
      } else {
        // Crear
        $stmt = db()->prepare("INSERT INTO employees (nombre, apellido, email, telefono, dni, fecha_nacimiento, 
                               domicilio, ciudad, provincia, codigo_postal, fecha_contratacion, sueldo_base_semanal, puesto)
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$nombre, $apellido, $email ?: null, $telefono, $dni, $fecha_nacimiento ?: null, $domicilio, 
                        $ciudad, $provincia, $codigo_postal, $fecha_contratacion, $sueldo_base_semanal, $puesto]);
        $flash_ok = "Empleado creado correctamente.";
      }
      $tab = 'empleados';
    } catch (Throwable $e) {
      $flash_err = 'Error al guardar empleado: ' . $e->getMessage();
    }
  }

  // --- REGISTRAR ASISTENCIA ---
  if ($action === 'registrar_asistencia') {
    $emp_id = (int)($_POST['employee_id'] ?? 0);
    $fecha = trim($_POST['fecha'] ?? '');
    $presente = (int)($_POST['presente'] ?? 1);
    $hora_entrada = trim($_POST['hora_entrada'] ?? '') ?: null;
    $hora_salida = trim($_POST['hora_salida'] ?? '') ?: null;
    $justificado = (int)($_POST['justificado'] ?? 0);
    $notas = trim($_POST['notas'] ?? '');

    try {
      if ($emp_id <= 0) throw new Exception('Selecciona un empleado.');
      if ($fecha === '') throw new Exception('Selecciona una fecha.');

      db()->prepare("INSERT INTO employee_attendance (employee_id, fecha, hora_entrada, hora_salida, presente, justificado, notas)
                     VALUES (?, ?, ?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE hora_entrada=?, hora_salida=?, presente=?, justificado=?, notas=?")
        ->execute([$emp_id, $fecha, $hora_entrada, $hora_salida, $presente, $justificado, $notas, 
                   $hora_entrada, $hora_salida, $presente, $justificado, $notas]);
      $flash_ok = "Asistencia registrada.";
      $tab = 'asistencia';
    } catch (Throwable $e) {
      $flash_err = 'Error: ' . $e->getMessage();
    }
  }

  // --- REGISTRAR DESCUENTO ---
  if ($action === 'registrar_descuento') {
    $emp_id = (int)($_POST['employee_id'] ?? 0);
    $tipo = $_POST['tipo'] ?? 'FALTA';
    $fecha = trim($_POST['fecha'] ?? '');
    $minutos = max(0, (int)($_POST['minutos_descuento'] ?? 0));
    $monto = max(0, (float)($_POST['monto_descuento'] ?? 0));
    $razon = trim($_POST['razon'] ?? '');

    try {
      if ($emp_id <= 0) throw new Exception('Selecciona un empleado.');
      if ($fecha === '') throw new Exception('Selecciona una fecha.');

      db()->prepare("INSERT INTO employee_discounts (employee_id, tipo, fecha, minutos_descuento, monto_descuento, razon)
                     VALUES (?, ?, ?, ?, ?, ?)")
        ->execute([$emp_id, $tipo, $fecha, $minutos, $monto, $razon]);
      $flash_ok = "Descuento registrado.";
      $tab = 'descuentos';
    } catch (Throwable $e) {
      $flash_err = 'Error: ' . $e->getMessage();
    }
  }

  // --- SOLICITAR PRÉSTAMO ---
  if ($action === 'solicitar_prestamo') {
    $emp_id = (int)($_POST['employee_id'] ?? 0);
    $monto = max(0, (float)($_POST['monto_solicitado'] ?? 0));
    $cuotas = max(1, (int)($_POST['cuotas_cantidad'] ?? 1));
    $razon = trim($_POST['razon'] ?? '');

    try {
      if ($emp_id <= 0) throw new Exception('Selecciona un empleado.');
      if ($monto <= 0) throw new Exception('Monto debe ser mayor a 0.');

      db()->prepare("INSERT INTO employee_loans (employee_id, monto_solicitado, fecha_solicitud, cuotas_cantidad, razon)
                     VALUES (?, ?, NOW(), ?, ?)")
        ->execute([$emp_id, $monto, $cuotas, $razon]);
      $flash_ok = "Préstamo solicitado.";
      $tab = 'prestamos';
    } catch (Throwable $e) {
      $flash_err = 'Error: ' . $e->getMessage();
    }
  }

  // --- APROBAR PRÉSTAMO ---
  if ($action === 'aprobar_prestamo') {
    $loan_id = (int)($_POST['loan_id'] ?? 0);
    $monto_aprobado = max(0, (float)($_POST['monto_aprobado'] ?? 0));

    try {
      if ($loan_id <= 0 || $monto_aprobado <= 0) throw new Exception('Datos inválidos.');

      db()->prepare("UPDATE employee_loans SET monto_aprobado=?, fecha_aprobacion=NOW(), estado='APROBADO' WHERE id=?")
        ->execute([$monto_aprobado, $loan_id]);
      $flash_ok = "Préstamo aprobado.";
      $tab = 'prestamos';
    } catch (Throwable $e) {
      $flash_err = 'Error: ' . $e->getMessage();
    }
  }

  // --- SOLICITAR ADELANTO ---
  if ($action === 'solicitar_adelanto') {
    $emp_id = (int)($_POST['employee_id'] ?? 0);
    $monto = max(0, (float)($_POST['monto'] ?? 0));
    $razon = trim($_POST['razon'] ?? '');

    try {
      if ($emp_id <= 0) throw new Exception('Selecciona un empleado.');
      if ($monto <= 0) throw new Exception('Monto debe ser mayor a 0.');

      db()->prepare("INSERT INTO employee_advances (employee_id, monto, fecha_solicitud, razon)
                     VALUES (?, ?, NOW(), ?)")
        ->execute([$emp_id, $monto, $razon]);
      $flash_ok = "Adelanto solicitado.";
      $tab = 'adelantos';
    } catch (Throwable $e) {
      $flash_err = 'Error: ' . $e->getMessage();
    }
  }

  // --- APROBAR ADELANTO ---
  if ($action === 'aprobar_adelanto') {
    $adv_id = (int)($_POST['advance_id'] ?? 0);

    try {
      if ($adv_id <= 0) throw new Exception('Adelanto no encontrado.');

      db()->prepare("UPDATE employee_advances SET fecha_aprobacion=NOW(), estado='APROBADO' WHERE id=?")
        ->execute([$adv_id]);
      $flash_ok = "Adelanto aprobado.";
      $tab = 'adelantos';
    } catch (Throwable $e) {
      $flash_err = 'Error: ' . $e->getMessage();
    }
  }

  // --- REGISTRAR PAGO DE NÓMINA ---
  if ($action === 'registrar_nomina') {
    $emp_id = (int)($_POST['employee_id'] ?? 0);
    $fecha_pago = trim($_POST['fecha_pago'] ?? '');
    $semana_inicio = trim($_POST['semana_inicio'] ?? '');
    $semana_fin = trim($_POST['semana_fin'] ?? '');
    $sueldo_base = max(0, (float)($_POST['sueldo_base'] ?? 0));
    $descuentos = max(0, (float)($_POST['descuentos_total'] ?? 0));
    $adelantos = max(0, (float)($_POST['adelantos_total'] ?? 0));
    $prestamos_cuota = max(0, (float)($_POST['prestamos_cuota'] ?? 0));
    $sueldo_neto = max(0, (float)($_POST['sueldo_neto'] ?? 0));
    $medio_pago = $_POST['medio_pago'] ?? 'EFECTIVO';
    $notas = trim($_POST['notas'] ?? '');

    try {
      if ($emp_id <= 0) throw new Exception('Selecciona un empleado.');
      if ($fecha_pago === '') throw new Exception('Selecciona fecha de pago.');
      if ($sueldo_neto <= 0) throw new Exception('Sueldo neto debe ser mayor a 0.');

      db()->prepare("INSERT INTO employee_payroll (employee_id, fecha_pago, semana_inicio, semana_fin, sueldo_base, 
                     descuentos_total, adelantos_total, prestamos_cuota, sueldo_neto, medio_pago, estado, notas)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'PAGADO', ?)")
        ->execute([$emp_id, $fecha_pago, $semana_inicio ?: null, $semana_fin ?: null, $sueldo_base, $descuentos, 
                   $adelantos, $prestamos_cuota, $sueldo_neto, $medio_pago, $notas]);
      
      // Marcar adelantos como descontados
      db()->prepare("UPDATE employee_advances SET estado='DESCONTADO' WHERE employee_id=? AND estado='APROBADO' LIMIT 1")
        ->execute([$emp_id]);
      
      $flash_ok = "Nómina registrada.";
      $tab = 'nomina';
    } catch (Throwable $e) {
      $flash_err = 'Error: ' . $e->getMessage();
    }
  }
}

// ========== DATOS PARA VISTAS ==========

// Obtener todos los empleados
$employees = [];
$employees_list = [];
try {
  $employees = db()->query("SELECT * FROM employees WHERE activo=1 ORDER BY nombre, apellido LIMIT 500")->fetchAll();
  $employees_list = db()->query("SELECT id, CONCAT(nombre,' ',apellido) AS nombre_completo FROM employees WHERE activo=1 ORDER BY nombre")->fetchAll();
} catch (Throwable $e) {
  $flash_err = 'Error al cargar empleados: ' . $e->getMessage();
}

// Obtener empleado seleccionado
$selected_employee = null;
if ($selected_emp_id > 0) {
  $stmt = db()->prepare("SELECT * FROM employees WHERE id=?");
  $stmt->execute([$selected_emp_id]);
  $selected_employee = $stmt->fetch();
}

// Movimientos del empleado seleccionado (descuentos, adelantos, préstamos)
$movimientos_empleado = [];
if ($selected_emp_id > 0) {
  try {
    // Obtener todos los movimientos combinados
    $query = "SELECT 
                'd'::varchar AS tipo_registro, 
                d.id, 
                d.fecha, 
                'DESCUENTO' AS tipo_movimiento, 
                d.monto_descuento AS monto, 
                d.razon AS detalle,
                NULL AS cuotas,
                NULL AS estado_aprob
              FROM employee_discounts d
              WHERE d.employee_id = ?
              UNION ALL
              SELECT 
                'a'::varchar AS tipo_registro,
                a.id,
                a.fecha_solicitud AS fecha,
                'ADELANTO' AS tipo_movimiento,
                a.monto,
                a.razon AS detalle,
                NULL AS cuotas,
                a.estado AS estado_aprob
              FROM employee_advances a
              WHERE a.employee_id = ?
              UNION ALL
              SELECT 
                'p'::varchar AS tipo_registro,
                l.id,
                l.fecha_solicitud AS fecha,
                'PRESTAMO' AS tipo_movimiento,
                l.monto_solicitado AS monto,
                l.razon AS detalle,
                l.cuotas_cantidad AS cuotas,
                l.estado AS estado_aprob
              FROM employee_loans l
              WHERE l.employee_id = ?
              ORDER BY fecha DESC";
    
    // Alternativa sin UNION (compatible con más bases de datos)
    $discuentos = db()->prepare("SELECT 'DESCUENTO' AS tipo, d.id, d.fecha, d.monto_descuento AS monto, d.razon AS detalle, NULL AS estado FROM employee_discounts d WHERE d.employee_id=? ORDER BY d.fecha DESC")->execute([$selected_emp_id])->fetchAll();
    $adelantos = db()->prepare("SELECT 'ADELANTO' AS tipo, a.id, a.fecha_solicitud AS fecha, a.monto, a.razon AS detalle, a.estado FROM employee_advances a WHERE a.employee_id=? ORDER BY a.fecha_solicitud DESC")->execute([$selected_emp_id])->fetchAll();
    $prestamos = db()->prepare("SELECT 'PRESTAMO' AS tipo, l.id, l.fecha_solicitud AS fecha, l.monto_solicitado AS monto, l.razon AS detalle, l.estado FROM employee_loans l WHERE l.employee_id=? ORDER BY l.fecha_solicitud DESC")->execute([$selected_emp_id])->fetchAll();
    
    // Combinar todos
    $movimientos_empleado = array_merge($discuentos, $adelantos, $prestamos);
    usort($movimientos_empleado, function($a, $b) { return strtotime($b['fecha']) - strtotime($a['fecha']); });
  } catch (Throwable $e) {}
}

// Resumen de sueldos: todos los empleados con su saldo a pagar
$resumen_empleados = [];
try {
  $query_resumen = "SELECT 
                      e.id,
                      CONCAT(e.nombre, ' ', e.apellido) AS nombre_completo,
                      e.sueldo_base_semanal,
                      COALESCE(SUM(CASE WHEN d.tipo='DESCUENTO' THEN d.monto_descuento ELSE 0 END), 0) AS descuentos,
                      COALESCE(SUM(CASE WHEN a.estado='APROBADO' THEN a.monto ELSE 0 END), 0) AS adelantos,
                      COALESCE(SUM(CASE WHEN l.estado='APROBADO' THEN (l.monto_aprobado / l.cuotas_cantidad) ELSE 0 END), 0) AS cuota_prestamo
                    FROM employees e
                    LEFT JOIN employee_discounts d ON d.employee_id = e.id AND DATE(d.fecha) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                    LEFT JOIN employee_advances a ON a.employee_id = e.id AND a.estado='APROBADO'
                    LEFT JOIN employee_loans l ON l.employee_id = e.id AND l.estado='APROBADO'
                    WHERE e.activo=1
                    GROUP BY e.id, e.nombre, e.apellido, e.sueldo_base_semanal
                    ORDER BY e.nombre, e.apellido";
  
  $resumen_empleados = db()->query($query_resumen)->fetchAll();
} catch (Throwable $e) {}

// Asistencias recientes
$attendance_list = [];
try {
  $attendance_list = db()->query("SELECT a.*, CONCAT(e.nombre,' ',e.apellido) AS empleado
                                  FROM employee_attendance a
                                  JOIN employees e ON e.id=a.employee_id
                                  ORDER BY a.fecha DESC, a.id DESC
                                  LIMIT 100")->fetchAll();
} catch (Throwable $e) {}

// Nóminas
$payroll_list = [];
try {
  $payroll_list = db()->query("SELECT p.*, CONCAT(e.nombre,' ',e.apellido) AS empleado
                               FROM employee_payroll p
                               JOIN employees e ON e.id=p.employee_id
                               ORDER BY p.fecha_pago DESC
                               LIMIT 100")->fetchAll();
} catch (Throwable $e) {}

function tabActive($t, $tab) { return $t===$tab ? 'active' : ''; }
function paneActive($t, $tab) { return $t===$tab ? 'show active' : ''; }

include __DIR__ . '/../views/partials/header.php';
include __DIR__ . '/../views/partials/navbar.php';
?>

<div class="container py-4">
  <h5 class="mb-3">Gestión de Empleados</h5>

  <?php if ($flash_ok): ?><div class="alert alert-success"><?= e($flash_ok) ?></div><?php endif; ?>
  <?php if ($flash_err): ?><div class="alert alert-danger"><?= e($flash_err) ?></div><?php endif; ?>

  <ul class="nav nav-tabs" id="tabs" role="tablist">
    <li class="nav-item"><button class="nav-link <?= tabActive('empleados',$tab) ?>" id="empleados-tab" data-bs-toggle="tab" data-bs-target="#empleados" type="button">Empleados</button></li>
    <li class="nav-item"><button class="nav-link <?= tabActive('asistencia',$tab) ?>" id="asistencia-tab" data-bs-toggle="tab" data-bs-target="#asistencia" type="button">Asistencia</button></li>
    <li class="nav-item"><button class="nav-link <?= tabActive('movimientos',$tab) ?>" id="movimientos-tab" data-bs-toggle="tab" data-bs-target="#movimientos" type="button">Movimientos y Detalles</button></li>
    <li class="nav-item"><button class="nav-link <?= tabActive('resumen',$tab) ?>" id="resumen-tab" data-bs-toggle="tab" data-bs-target="#resumen" type="button">Resumen de Sueldos</button></li>
    <li class="nav-item"><button class="nav-link <?= tabActive('nomina',$tab) ?>" id="nomina-tab" data-bs-toggle="tab" data-bs-target="#nomina" type="button">Nómina</button></li>
  </ul>

  <div class="tab-content border-bottom border-start border-end p-3 bg-white shadow-sm">

    <!-- EMPLEADOS -->
    <div class="tab-pane fade <?= paneActive('empleados',$tab) ?>" id="empleados">
      <div class="row g-3">
        <div class="col-md-5">
          <h6>Nuevo/Editar Empleado</h6>
          <form method="post" class="border rounded p-3 bg-light">
            <input type="hidden" name="action" value="guardar_empleado">
            <?php if ($employee): ?>
              <input type="hidden" name="employee_id" value="<?= (int)$employee['id'] ?>">
            <?php endif; ?>

            <div class="mb-2">
              <label class="form-label">Nombre *</label>
              <input type="text" name="nombre" class="form-control" value="<?= e($employee['nombre'] ?? '') ?>" required>
            </div>
            <div class="mb-2">
              <label class="form-label">Apellido *</label>
              <input type="text" name="apellido" class="form-control" value="<?= e($employee['apellido'] ?? '') ?>" required>
            </div>
            <div class="mb-2">
              <label class="form-label">Email</label>
              <input type="email" name="email" class="form-control" value="<?= e($employee['email'] ?? '') ?>">
            </div>
            <div class="mb-2">
              <label class="form-label">Teléfono</label>
              <input type="tel" name="telefono" class="form-control" value="<?= e($employee['telefono'] ?? '') ?>">
            </div>
            <div class="mb-2">
              <label class="form-label">DNI</label>
              <input type="text" name="dni" class="form-control" value="<?= e($employee['dni'] ?? '') ?>">
            </div>
            <div class="mb-2">
              <label class="form-label">Fecha de Nacimiento</label>
              <input type="date" name="fecha_nacimiento" class="form-control" value="<?= e($employee['fecha_nacimiento'] ?? '') ?>">
            </div>
            <div class="mb-2">
              <label class="form-label">Domicilio</label>
              <input type="text" name="domicilio" class="form-control" value="<?= e($employee['domicilio'] ?? '') ?>">
            </div>
            <div class="mb-2">
              <label class="form-label">Ciudad</label>
              <input type="text" name="ciudad" class="form-control" value="<?= e($employee['ciudad'] ?? '') ?>">
            </div>
            <div class="mb-2">
              <label class="form-label">Provincia</label>
              <input type="text" name="provincia" class="form-control" value="<?= e($employee['provincia'] ?? '') ?>">
            </div>
            <div class="mb-2">
              <label class="form-label">Código Postal</label>
              <input type="text" name="codigo_postal" class="form-control" value="<?= e($employee['codigo_postal'] ?? '') ?>">
            </div>
            <div class="mb-2">
              <label class="form-label">Fecha de Contratación *</label>
              <input type="date" name="fecha_contratacion" class="form-control" value="<?= e($employee['fecha_contratacion'] ?? '') ?>" required>
            </div>
            <div class="mb-2">
              <label class="form-label">Puesto</label>
              <input type="text" name="puesto" class="form-control" value="<?= e($employee['puesto'] ?? '') ?>">
            </div>
            <div class="mb-3">
              <label class="form-label">Sueldo Base Semanal *</label>
              <input type="number" step="0.01" min="0" name="sueldo_base_semanal" class="form-control" value="<?= e($employee['sueldo_base_semanal'] ?? '') ?>" required>
            </div>
            <div class="d-grid">
              <button type="submit" class="btn btn-primary">Guardar</button>
              <?php if ($employee): ?>
                <a href="<?= url('empleados.php?tab=empleados') ?>" class="btn btn-secondary mt-2">Cancelar</a>
              <?php endif; ?>
            </div>
          </form>
        </div>

        <div class="col-md-7">
          <h6>Empleados Activos</h6>
          <div class="table-responsive">
            <table class="table table-sm">
              <thead class="table-light">
                <tr>
                  <th>Nombre</th>
                  <th>Puesto</th>
                  <th>Contratación</th>
                  <th>Sueldo Semanal</th>
                  <th>Acción</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!$employees): ?>
                  <tr><td colspan="5" class="text-center text-muted py-3">Sin empleados.</td></tr>
                <?php else: foreach ($employees as $e): ?>
                  <tr>
                    <td><?= e($e['nombre'] . ' ' . $e['apellido']) ?></td>
                    <td><?= e($e['puesto'] ?? '—') ?></td>
                    <td><?= e($e['fecha_contratacion']) ?></td>
                    <td><?= money($e['sueldo_base_semanal']) ?></td>
                    <td>
                      <a href="<?= url('empleados.php?tab=empleados&id=' . (int)$e['id']) ?>" class="btn btn-sm btn-outline-primary">Editar</a>
                    </td>
                  </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- ASISTENCIA -->
    <div class="tab-pane fade <?= paneActive('asistencia',$tab) ?>" id="asistencia">
      <div class="row g-3">
        <div class="col-md-5">
          <h6>Registrar Asistencia</h6>
          <form method="post" class="border rounded p-3 bg-light">
            <input type="hidden" name="action" value="registrar_asistencia">
            <div class="mb-2">
              <label class="form-label">Empleado *</label>
              <select name="employee_id" class="form-select" required>
                <option value="">— Seleccionar —</option>
                <?php foreach ($employees_list as $e): ?>
                  <option value="<?= (int)$e['id'] ?>"><?= e($e['nombre_completo']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="mb-2">
              <label class="form-label">Fecha *</label>
              <input type="date" name="fecha" class="form-control" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="mb-2">
              <label class="form-label">Presente</label>
              <select name="presente" class="form-select">
                <option value="1">Sí</option>
                <option value="0">No</option>
              </select>
            </div>
            <div class="mb-2">
              <label class="form-label">Hora Entrada</label>
              <input type="time" name="hora_entrada" class="form-control">
            </div>
            <div class="mb-2">
              <label class="form-label">Hora Salida</label>
              <input type="time" name="hora_salida" class="form-control">
            </div>
            <div class="mb-2">
              <label class="form-label">
                <input type="checkbox" name="justificado" value="1"> Justificado
              </label>
            </div>
            <div class="mb-3">
              <label class="form-label">Notas</label>
              <textarea name="notas" class="form-control" rows="2"></textarea>
            </div>
            <div class="d-grid">
              <button type="submit" class="btn btn-primary">Registrar</button>
            </div>
          </form>
        </div>

        <div class="col-md-7">
          <h6>Últimas Asistencias</h6>
          <div class="table-responsive">
            <table class="table table-sm">
              <thead class="table-light">
                <tr>
                  <th>Empleado</th>
                  <th>Fecha</th>
                  <th>Entrada</th>
                  <th>Salida</th>
                  <th>Presente</th>
                  <th>Justificado</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!$attendance_list): ?>
                  <tr><td colspan="6" class="text-center text-muted py-3">Sin registros.</td></tr>
                <?php else: foreach ($attendance_list as $a): ?>
                  <tr>
                    <td><?= e($a['empleado']) ?></td>
                    <td><?= e($a['fecha']) ?></td>
                    <td><?= e($a['hora_entrada'] ?? '—') ?></td>
                    <td><?= e($a['hora_salida'] ?? '—') ?></td>
                    <td><?= $a['presente'] ? '✓' : '✗' ?></td>
                    <td><?= $a['justificado'] ? '✓' : '—' ?></td>
                  </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- DESCUENTOS -->
    <div class="tab-pane fade <?= paneActive('descuentos',$tab) ?>" id="descuentos">
      <div class="row g-3">
        <div class="col-md-5">
          <h6>Registrar Descuento</h6>
          <form method="post" class="border rounded p-3 bg-light">
            <input type="hidden" name="action" value="registrar_descuento">
            <div class="mb-2">
              <label class="form-label">Empleado *</label>
              <select name="employee_id" class="form-select" required>
                <option value="">— Seleccionar —</option>
                <?php foreach ($employees_list as $e): ?>
                  <option value="<?= (int)$e['id'] ?>"><?= e($e['nombre_completo']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="mb-2">
              <label class="form-label">Tipo *</label>
              <select name="tipo" class="form-select" required>
                <option value="FALTA">Falta</option>
                <option value="LLEGADA_TARDE">Llegada Tarde</option>
                <option value="OTRO">Otro</option>
              </select>
            </div>
            <div class="mb-2">
              <label class="form-label">Fecha *</label>
              <input type="date" name="fecha" class="form-control" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="mb-2">
              <label class="form-label">Minutos de Descuento</label>
              <input type="number" min="0" name="minutos_descuento" class="form-control" value="0">
            </div>
            <div class="mb-2">
              <label class="form-label">Monto de Descuento</label>
              <input type="number" step="0.01" min="0" name="monto_descuento" class="form-control" value="0">
            </div>
            <div class="mb-3">
              <label class="form-label">Razón</label>
              <textarea name="razon" class="form-control" rows="2"></textarea>
            </div>
            <div class="d-grid">
              <button type="submit" class="btn btn-primary">Registrar</button>
            </div>
          </form>
        </div>

        <div class="col-md-7">
          <h6>Descuentos Registrados</h6>
          <div class="table-responsive">
            <table class="table table-sm">
              <thead class="table-light">
                <tr>
                  <th>Empleado</th>
                  <th>Tipo</th>
                  <th>Fecha</th>
                  <th>Monto</th>
                  <th>Razón</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!$discounts_list): ?>
                  <tr><td colspan="5" class="text-center text-muted py-3">Sin registros.</td></tr>
                <?php else: foreach ($discounts_list as $d): ?>
                  <tr>
                    <td><?= e($d['empleado']) ?></td>
                    <td><?= e($d['tipo']) ?></td>
                    <td><?= e($d['fecha']) ?></td>
                    <td><?= money($d['monto_descuento']) ?></td>
                    <td><?= e($d['razon'] ?? '—') ?></td>
                  </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- PRÉSTAMOS -->
    <div class="tab-pane fade <?= paneActive('prestamos',$tab) ?>" id="prestamos">
      <div class="row g-3">
        <div class="col-md-5">
          <h6>Solicitar Préstamo</h6>
          <form method="post" class="border rounded p-3 bg-light">
            <input type="hidden" name="action" value="solicitar_prestamo">
            <div class="mb-2">
              <label class="form-label">Empleado *</label>
              <select name="employee_id" class="form-select" required>
                <option value="">— Seleccionar —</option>
                <?php foreach ($employees_list as $e): ?>
                  <option value="<?= (int)$e['id'] ?>"><?= e($e['nombre_completo']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="mb-2">
              <label class="form-label">Monto Solicitado *</label>
              <input type="number" step="0.01" min="0.01" name="monto_solicitado" class="form-control" required>
            </div>
            <div class="mb-2">
              <label class="form-label">Cantidad de Cuotas</label>
              <input type="number" min="1" name="cuotas_cantidad" class="form-control" value="1">
            </div>
            <div class="mb-3">
              <label class="form-label">Razón</label>
              <textarea name="razon" class="form-control" rows="2"></textarea>
            </div>
            <div class="d-grid">
              <button type="submit" class="btn btn-primary">Solicitar</button>
            </div>
          </form>
        </div>

        <div class="col-md-7">
          <h6>Préstamos</h6>
          <div class="table-responsive">
            <table class="table table-sm">
              <thead class="table-light">
                <tr>
                  <th>Empleado</th>
                  <th>Solicitado</th>
                  <th>Aprobado</th>
                  <th>Estado</th>
                  <th>Cuotas</th>
                  <th>Acción</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!$loans_list): ?>
                  <tr><td colspan="6" class="text-center text-muted py-3">Sin préstamos.</td></tr>
                <?php else: foreach ($loans_list as $l): ?>
                  <tr>
                    <td><?= e($l['empleado']) ?></td>
                    <td><?= money($l['monto_solicitado']) ?></td>
                    <td><?= $l['monto_aprobado'] ? money($l['monto_aprobado']) : '—' ?></td>
                    <td><span class="badge bg-<?= $l['estado']==='APROBADO'?'success':($l['estado']==='SOLICITADO'?'warning':'danger') ?>"><?= e($l['estado']) ?></span></td>
                    <td><?= (int)$l['cuotas_cantidad'] ?></td>
                    <td>
                      <?php if ($l['estado'] === 'SOLICITADO'): ?>
                        <form method="post" style="display:inline;">
                          <input type="hidden" name="action" value="aprobar_prestamo">
                          <input type="hidden" name="loan_id" value="<?= (int)$l['id'] ?>">
                          <input type="hidden" name="monto_aprobado" value="<?= $l['monto_solicitado'] ?>">
                          <button type="submit" class="btn btn-sm btn-success">Aprobar</button>
                        </form>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- ADELANTOS -->
    <div class="tab-pane fade <?= paneActive('adelantos',$tab) ?>" id="adelantos">
      <div class="row g-3">
        <div class="col-md-5">
          <h6>Solicitar Adelanto</h6>
          <form method="post" class="border rounded p-3 bg-light">
            <input type="hidden" name="action" value="solicitar_adelanto">
            <div class="mb-2">
              <label class="form-label">Empleado *</label>
              <select name="employee_id" class="form-select" required>
                <option value="">— Seleccionar —</option>
                <?php foreach ($employees_list as $e): ?>
                  <option value="<?= (int)$e['id'] ?>"><?= e($e['nombre_completo']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="mb-2">
              <label class="form-label">Monto *</label>
              <input type="number" step="0.01" min="0.01" name="monto" class="form-control" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Razón</label>
              <textarea name="razon" class="form-control" rows="2"></textarea>
            </div>
            <div class="d-grid">
              <button type="submit" class="btn btn-primary">Solicitar</button>
            </div>
          </form>
        </div>

        <div class="col-md-7">
          <h6>Adelantos</h6>
          <div class="table-responsive">
            <table class="table table-sm">
              <thead class="table-light">
                <tr>
                  <th>Empleado</th>
                  <th>Monto</th>
                  <th>Solicitud</th>
                  <th>Estado</th>
                  <th>Acción</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!$advances_list): ?>
                  <tr><td colspan="5" class="text-center text-muted py-3">Sin adelantos.</td></tr>
                <?php else: foreach ($advances_list as $a): ?>
                  <tr>
                    <td><?= e($a['empleado']) ?></td>
                    <td><?= money($a['monto']) ?></td>
                    <td><?= e($a['fecha_solicitud']) ?></td>
                    <td><span class="badge bg-<?= $a['estado']==='APROBADO'?'success':($a['estado']==='SOLICITADO'?'warning':'info') ?>"><?= e($a['estado']) ?></span></td>
                    <td>
                      <?php if ($a['estado'] === 'SOLICITADO'): ?>
                        <form method="post" style="display:inline;">
                          <input type="hidden" name="action" value="aprobar_adelanto">
                          <input type="hidden" name="advance_id" value="<?= (int)$a['id'] ?>">
                          <button type="submit" class="btn btn-sm btn-success">Aprobar</button>
                        </form>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- NÓMINA -->
    <div class="tab-pane fade <?= paneActive('nomina',$tab) ?>" id="nomina">
      <div class="row g-3">
        <div class="col-md-6">
          <h6>Registrar Pago de Nómina</h6>
          <form method="post" class="border rounded p-3 bg-light">
            <input type="hidden" name="action" value="registrar_nomina">
            <div class="mb-2">
              <label class="form-label">Empleado *</label>
              <select name="employee_id" class="form-select" required onchange="cargarDatosEmpleado(this.value)">
                <option value="">— Seleccionar —</option>
                <?php foreach ($employees_list as $e): ?>
                  <option value="<?= (int)$e['id'] ?>"><?= e($e['nombre_completo']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="mb-2">
              <label class="form-label">Fecha de Pago *</label>
              <input type="date" name="fecha_pago" class="form-control" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="mb-2">
              <label class="form-label">Semana Inicio</label>
              <input type="date" name="semana_inicio" class="form-control">
            </div>
            <div class="mb-2">
              <label class="form-label">Semana Fin</label>
              <input type="date" name="semana_fin" class="form-control">
            </div>
            <div class="mb-2">
              <label class="form-label">Sueldo Base *</label>
              <input type="number" step="0.01" min="0" name="sueldo_base" class="form-control" id="sueldoBase" required>
            </div>
            <div class="mb-2">
              <label class="form-label">Descuentos Total</label>
              <input type="number" step="0.01" min="0" name="descuentos_total" class="form-control" value="0" id="descuentos">
            </div>
            <div class="mb-2">
              <label class="form-label">Adelantos Total</label>
              <input type="number" step="0.01" min="0" name="adelantos_total" class="form-control" value="0" id="adelantos">
            </div>
            <div class="mb-2">
              <label class="form-label">Cuota Préstamo</label>
              <input type="number" step="0.01" min="0" name="prestamos_cuota" class="form-control" value="0" id="prestamos">
            </div>
            <div class="mb-2">
              <label class="form-label">Sueldo Neto *</label>
              <input type="number" step="0.01" min="0" name="sueldo_neto" class="form-control" id="sueldoNeto" required>
            </div>
            <div class="mb-2">
              <label class="form-label">Medio de Pago</label>
              <select name="medio_pago" class="form-select">
                <option value="EFECTIVO">Efectivo</option>
                <option value="TRANSFER">Transferencia</option>
                <option value="CHEQUE">Cheque</option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">Notas</label>
              <textarea name="notas" class="form-control" rows="2"></textarea>
            </div>
            <div class="d-grid">
              <button type="submit" class="btn btn-primary">Registrar Pago</button>
            </div>
          </form>
        </div>

        <div class="col-md-6">
          <h6>Nóminas Registradas</h6>
          <div class="table-responsive">
            <table class="table table-sm">
              <thead class="table-light">
                <tr>
                  <th>Empleado</th>
                  <th>Fecha Pago</th>
                  <th>Sueldo Neto</th>
                  <th>Medio</th>
                  <th>Estado</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!$payroll_list): ?>
                  <tr><td colspan="5" class="text-center text-muted py-3">Sin nóminas.</td></tr>
                <?php else: foreach ($payroll_list as $p): ?>
                  <tr>
                    <td><?= e($p['empleado']) ?></td>
                    <td><?= e($p['fecha_pago']) ?></td>
                    <td><?= money($p['sueldo_neto']) ?></td>
                    <td><?= e($p['medio_pago']) ?></td>
                    <td><span class="badge bg-success"><?= e($p['estado']) ?></span></td>
                  </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <script>
        function cargarDatosEmpleado(empId) {
          // Aquí puedes hacer AJAX para cargar el sueldo base automáticamente
          // Por ahora es un placeholder para mejoras futuras
          if (empId) {
            // fetch para obtener datos del empleado
          }
        }

        // Calcular sueldo neto automáticamente
        document.getElementById('sueldoBase')?.addEventListener('input', calcularNeto);
        document.getElementById('descuentos')?.addEventListener('input', calcularNeto);
        document.getElementById('adelantos')?.addEventListener('input', calcularNeto);
        document.getElementById('prestamos')?.addEventListener('input', calcularNeto);

        function calcularNeto() {
          const base = parseFloat(document.getElementById('sueldoBase')?.value || 0);
          const desc = parseFloat(document.getElementById('descuentos')?.value || 0);
          const adelantos = parseFloat(document.getElementById('adelantos')?.value || 0);
          const prestamos = parseFloat(document.getElementById('prestamos')?.value || 0);
          const neto = base - desc - adelantos - prestamos;
          document.getElementById('sueldoNeto').value = neto.toFixed(2);
        }
      </script>
    </div>

  </div>
</div>

<?php include __DIR__ . '/../views/partials/footer.php'; ?>
