<?php
require_once __DIR__ . '/../app/auth.php';
require_login();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

$flash_ok = '';
$flash_err = '';

// Asegurar columna para saldo pendiente (si no existe)
try {
  db()->exec("ALTER TABLE employees ADD COLUMN IF NOT EXISTS saldo_pendiente DECIMAL(12,2) DEFAULT 0");
} catch (Throwable $e) {
  // No bloquear ejecución si ALTER no funciona en versiones antiguas
}

$validTabs = ['empleados','asistencia','movimientos','resumen','nomina'];
$tab = $_GET['tab'] ?? 'empleados';
if (!in_array($tab, $validTabs, true)) $tab = 'empleados';

$selected_emp_id = (int)($_GET['emp_id'] ?? 0);

// ========== POST: Acciones ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  // CREAR/EDITAR EMPLEADO
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
        $stmt = db()->prepare("UPDATE employees SET nombre=?, apellido=?, email=?, telefono=?, dni=?, fecha_nacimiento=?, domicilio=?, ciudad=?, provincia=?, codigo_postal=?, fecha_contratacion=?, sueldo_base_semanal=?, puesto=? WHERE id=?");
        $stmt->execute([$nombre, $apellido, $email ?: null, $telefono, $dni, $fecha_nacimiento ?: null, $domicilio, $ciudad, $provincia, $codigo_postal, $fecha_contratacion, $sueldo_base_semanal, $puesto, $emp_id]);
        $flash_ok = "Empleado actualizado correctamente.";
      } else {
        $stmt = db()->prepare("INSERT INTO employees (nombre, apellido, email, telefono, dni, fecha_nacimiento, domicilio, ciudad, provincia, codigo_postal, fecha_contratacion, sueldo_base_semanal, puesto) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$nombre, $apellido, $email ?: null, $telefono, $dni, $fecha_nacimiento ?: null, $domicilio, $ciudad, $provincia, $codigo_postal, $fecha_contratacion, $sueldo_base_semanal, $puesto]);
        $flash_ok = "Empleado creado correctamente.";
      }
      $tab = 'empleados';
    } catch (Throwable $e) {
      $flash_err = 'Error: ' . $e->getMessage();
    }
  }

  // REGISTRAR ASISTENCIA
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

      db()->prepare("INSERT INTO employee_attendance (employee_id, fecha, hora_entrada, hora_salida, presente, justificado, notas) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE hora_entrada=?, hora_salida=?, presente=?, justificado=?, notas=?")
        ->execute([$emp_id, $fecha, $hora_entrada, $hora_salida, $presente, $justificado, $notas, $hora_entrada, $hora_salida, $presente, $justificado, $notas]);
      $flash_ok = "Asistencia registrada.";
      $tab = 'asistencia';
    } catch (Throwable $e) {
      $flash_err = 'Error: ' . $e->getMessage();
    }
  }
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

      db()->prepare("INSERT INTO employee_discounts (employee_id, tipo, fecha, minutos_descuento, monto_descuento, razon) VALUES (?, ?, ?, ?, ?, ?)")
        ->execute([$emp_id, $tipo, $fecha, $minutos, $monto, $razon]);
      $flash_ok = "Descuento registrado.";
      $tab = 'movimientos';
      $selected_emp_id = $emp_id;
    } catch (Throwable $e) {
      $flash_err = 'Error: ' . $e->getMessage();
    }
  }

  // REGISTRAR PRÉSTAMO (directo, sin solicitud)
  if ($action === 'registrar_prestamo') {
    $emp_id = (int)($_POST['employee_id'] ?? 0);
    $monto = max(0, (float)($_POST['monto_solicitado'] ?? 0));
    $cuotas = max(1, (int)($_POST['cuotas_cantidad'] ?? 1));
    $razon = trim($_POST['razon'] ?? '');

    try {
      if ($emp_id <= 0) throw new Exception('Selecciona un empleado.');
      if ($monto <= 0) throw new Exception('Monto debe ser mayor a 0.');

      db()->prepare("INSERT INTO employee_loans (employee_id, monto_solicitado, monto_aprobado, fecha_solicitud, fecha_aprobacion, cuotas_cantidad, razon, estado) VALUES (?, ?, ?, NOW(), NOW(), ?, ?, 'APROBADO')")
        ->execute([$emp_id, $monto, $monto, $cuotas, $razon]);
      $flash_ok = "Préstamo registrado.";
      $tab = 'movimientos';
      $selected_emp_id = $emp_id;
    } catch (Throwable $e) {
      $flash_err = 'Error: ' . $e->getMessage();
    }
  }

  // REGISTRAR ADELANTO (directo, sin solicitud)
  if ($action === 'registrar_adelanto') {
    $emp_id = (int)($_POST['employee_id'] ?? 0);
    $monto = max(0, (float)($_POST['monto'] ?? 0));
    $razon = trim($_POST['razon'] ?? '');

    try {
      if ($emp_id <= 0) throw new Exception('Selecciona un empleado.');
      if ($monto <= 0) throw new Exception('Monto debe ser mayor a 0.');

      db()->prepare("INSERT INTO employee_advances (employee_id, monto, fecha_solicitud, fecha_aprobacion, razon, estado) VALUES (?, ?, NOW(), NOW(), ?, 'APROBADO')")
        ->execute([$emp_id, $monto, $razon]);
      
      // Registrar el adelanto como gasto en CAJA (cash_expenses)
      $stmt_emp = db()->prepare("SELECT nombre, apellido FROM employees WHERE id=?");
      $stmt_emp->execute([$emp_id]);
      $empleado_data = $stmt_emp->fetch();
      $empleado_nombre = ($empleado_data['nombre'] ?? '') . ' ' . ($empleado_data['apellido'] ?? '');
      
      $descripcion_gasto = "Adelanto de sueldo - {$empleado_nombre}";
      if ($razon) $descripcion_gasto .= " ({$razon})";
      
      $userId = (int)(user()['id'] ?? 0);
      
      db()->prepare("INSERT INTO cash_expenses (fecha, categoria, descripcion, medio, importe, created_by) VALUES (NOW(), 'SUELDOS', ?, 'EFECTIVO', ?, ?)")
        ->execute([$descripcion_gasto, $monto, $userId]);
      
      $flash_ok = "Adelanto registrado.";
      $tab = 'movimientos';
      $selected_emp_id = $emp_id;
    } catch (Throwable $e) {
      $flash_err = 'Error: ' . $e->getMessage();
    }
  }

  // REGISTRAR NÓMINA
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

      // Calcular lo que correspondería pagar esta semana (incluye saldo pendiente previo)
      $stmt = db()->prepare("SELECT COALESCE(saldo_pendiente,0) AS saldo_pendiente FROM employees WHERE id=?");
      $stmt->execute([$emp_id]);
      $saldo_prev = (float)($stmt->fetch()['saldo_pendiente'] ?? 0);

      $owed = $sueldo_base - $descuentos - $adelantos - $prestamos_cuota + $saldo_prev;
      // Si se paga menos, queda pendiente
      $remaining = round($owed - $sueldo_neto, 2);

      $estado = $remaining > 0 ? 'PENDIENTE' : 'PAGADO';

      $stmt = db()->prepare("INSERT INTO employee_payroll (employee_id, fecha_pago, semana_inicio, semana_fin, sueldo_base, descuentos_total, adelantos_total, prestamos_cuota, sueldo_neto, medio_pago, estado, notas) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
      $stmt->execute([$emp_id, $fecha_pago, $semana_inicio ?: null, $semana_fin ?: null, $sueldo_base, $descuentos, $adelantos, $prestamos_cuota, $sueldo_neto, $medio_pago, $estado, $notas]);

      // Actualizar saldo pendiente en empleados: si remaining > 0, quedó sin pagar; si remaining <= 0, se cubrió
      if ($remaining > 0) {
        // Queda monto sin pagar
        db()->prepare("UPDATE employees SET saldo_pendiente = ? WHERE id = ?")->execute([$remaining, $emp_id]);
      } else {
        // Se pagó todo o de más: saldo pendiente = 0
        db()->prepare("UPDATE employees SET saldo_pendiente = 0 WHERE id = ?")->execute([$emp_id]);
      }

      // Marcar adelantos como descontados sólo si el pago cubre los adelantos registrados
      if ($sueldo_neto >= $adelantos) {
        db()->prepare("UPDATE employee_advances SET estado='DESCONTADO' WHERE employee_id=? AND estado='APROBADO'")
          ->execute([$emp_id]);
      }

      // Registrar el pago como gasto en CAJA (cash_expenses)
      $stmt_emp = db()->prepare("SELECT nombre, apellido FROM employees WHERE id=?");
      $stmt_emp->execute([$emp_id]);
      $empleado_data = $stmt_emp->fetch();
      $empleado_nombre = ($empleado_data['nombre'] ?? '') . ' ' . ($empleado_data['apellido'] ?? '');
      
      $descripcion_gasto = "Pago nómina - {$empleado_nombre}";
      if ($notas) $descripcion_gasto .= " ({$notas})";
      
      $userId = (int)(user()['id'] ?? 0);
      
      // Convertir fecha a datetime si solo viene como fecha
      $fecha_gasto = $fecha_pago;
      if (strlen($fecha_gasto) === 10) {
        $fecha_gasto .= ' ' . date('H:i:s');
      }
      
      db()->prepare("INSERT INTO cash_expenses (fecha, categoria, descripcion, medio, importe, created_by) VALUES (?, 'SUELDOS', ?, ?, ?, ?)")
        ->execute([$fecha_gasto, $descripcion_gasto, $medio_pago, $sueldo_neto, $userId]);

      $flash_ok = "Nómina registrada.";
      $tab = 'nomina';
    } catch (Throwable $e) {
      $flash_err = 'Error: ' . $e->getMessage();
    }
  }
}

// ========== DATOS ==========

$employees = [];
$employees_list = [];
try {
  $employees = db()->query("SELECT * FROM employees WHERE activo=1 ORDER BY nombre, apellido LIMIT 500")->fetchAll();
  $employees_list = db()->query("SELECT id, CONCAT(nombre,' ',apellido) AS nombre_completo FROM employees WHERE activo=1 ORDER BY nombre")->fetchAll();
} catch (Throwable $e) {
  $flash_err = 'Error al cargar empleados: ' . $e->getMessage();
}

$selected_employee = null;
if ($selected_emp_id > 0) {
  $stmt = db()->prepare("SELECT * FROM employees WHERE id=?");
  $stmt->execute([$selected_emp_id]);
  $selected_employee = $stmt->fetch();
}

// Movimientos del empleado seleccionado
$movimientos = [];
$mov_error = null;
if ($selected_emp_id > 0) {
  try {
    $stmt = db()->prepare("SELECT 'DESCUENTO' AS tipo, id, fecha, monto_descuento AS monto, razon AS detalle FROM employee_discounts WHERE employee_id=? ORDER BY fecha DESC");
    $stmt->execute([$selected_emp_id]);
    $descuentos = $stmt->fetchAll() ?: [];
    
    $stmt = db()->prepare("SELECT 'ADELANTO' AS tipo, id, fecha_solicitud AS fecha, monto, razon AS detalle FROM employee_advances WHERE employee_id=? ORDER BY fecha_solicitud DESC");
    $stmt->execute([$selected_emp_id]);
    $adelantos = $stmt->fetchAll() ?: [];
    
    $stmt = db()->prepare("SELECT 'PRESTAMO' AS tipo, id, fecha_solicitud AS fecha, monto_solicitado AS monto, razon AS detalle FROM employee_loans WHERE employee_id=? ORDER BY fecha_solicitud DESC");
    $stmt->execute([$selected_emp_id]);
    $prestamos = $stmt->fetchAll() ?: [];
    
    $stmt = db()->prepare("SELECT 'PAGO' AS tipo, id, fecha_pago AS fecha, sueldo_neto AS monto, CONCAT('Pago nómina - ', estado) AS detalle FROM employee_payroll WHERE employee_id=? ORDER BY fecha_pago DESC");
    $stmt->execute([$selected_emp_id]);
    $pagos = $stmt->fetchAll() ?: [];
    
    $movimientos = array_merge($descuentos, $adelantos, $prestamos, $pagos);
    usort($movimientos, function($a, $b) { 
      $dateA = strtotime($a['fecha'] ?? '0000-00-00');
      $dateB = strtotime($b['fecha'] ?? '0000-00-00');
      return $dateB - $dateA;
    });
  } catch (Throwable $e) {
    $mov_error = $e->getMessage();
    error_log("Error cargando movimientos: " . $e->getMessage());
  }
}

// Calcular saldo semanal del empleado seleccionado
$saldo_semanal = 0;
if ($selected_employee) {
  try {
    $sueldo_base = (float)$selected_employee['sueldo_base_semanal'];
    
    $desc_stmt = db()->prepare("SELECT COALESCE(SUM(monto_descuento), 0) AS total FROM employee_discounts WHERE employee_id=? AND DATE(fecha) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
    $desc_stmt->execute([$selected_emp_id]);
    $descuentos_total = (float)($desc_stmt->fetch()['total'] ?? 0);
    
    $adelantos_stmt = db()->prepare("SELECT COALESCE(SUM(monto), 0) AS total FROM employee_advances WHERE employee_id=? AND estado='APROBADO'");
    $adelantos_stmt->execute([$selected_emp_id]);
    $adelantos_total = (float)($adelantos_stmt->fetch()['total'] ?? 0);
    
    $prestamos_stmt = db()->prepare("SELECT COALESCE(SUM(monto_aprobado / cuotas_cantidad), 0) AS total FROM employee_loans WHERE employee_id=? AND estado='APROBADO'");
    $prestamos_stmt->execute([$selected_emp_id]);
    $prestamos_total = (float)($prestamos_stmt->fetch()['total'] ?? 0);
    
    // Incluir saldo pendiente acumulado (carryover)
    $stmt = db()->prepare("SELECT COALESCE(saldo_pendiente,0) AS saldo_pendiente FROM employees WHERE id=?");
    $stmt->execute([$selected_emp_id]);
    $saldo_prev_emp = (float)($stmt->fetch()['saldo_pendiente'] ?? 0);

    $saldo_semanal = $sueldo_base - $descuentos_total - $adelantos_total - $prestamos_total + $saldo_prev_emp;
  } catch (Throwable $e) {}
}

// Resumen de todos los empleados - Versión simplificada sin subqueries
$resumen_empleados = [];
try {
  // Primero, obtener lista de empleados
  $empleados_base = db()->query("SELECT id, nombre, apellido, sueldo_base_semanal FROM employees WHERE activo=1 ORDER BY nombre, apellido")->fetchAll();
  
  // Para cada empleado, calcular sus valores
  foreach ($empleados_base as $emp) {
    $emp_id = (int)$emp['id'];
    
    // Descuentos
    $desc = db()->query("SELECT COALESCE(SUM(CASE WHEN tipo IN ('DESCUENTO','FALTA','LLEGADA_TARDE') THEN monto_descuento ELSE 0 END), 0) AS total FROM employee_discounts WHERE employee_id={$emp_id}")->fetch();
    $descuentos = (float)($desc['total'] ?? 0);
    
    // Adelantos
    $adel = db()->query("SELECT COALESCE(SUM(monto), 0) AS total FROM employee_advances WHERE employee_id={$emp_id} AND estado='APROBADO'")->fetch();
    $adelantos = (float)($adel['total'] ?? 0);
    
    // Préstamos
    $prest = db()->query("SELECT COALESCE(SUM(monto_aprobado / cuotas_cantidad), 0) AS total FROM employee_loans WHERE employee_id={$emp_id} AND estado='APROBADO'")->fetch();
    $cuota_prestamo = (float)($prest['total'] ?? 0);
    
    $resumen_empleados[] = [
      'id' => $emp_id,
      'nombre_completo' => $emp['nombre'] . ' ' . $emp['apellido'],
      'sueldo_base_semanal' => (float)$emp['sueldo_base_semanal'],
      'descuentos' => $descuentos,
      'adelantos' => $adelantos,
      'cuota_prestamo' => $cuota_prestamo
    ];
  }
} catch (Throwable $e) {
  error_log("Error cargando resumen: " . $e->getMessage());
}

// Calcular saldos
foreach ($resumen_empleados as &$emp) {
  // Incluir saldo pendiente por empleado
  try {
    $stmt = db()->prepare("SELECT COALESCE(saldo_pendiente,0) AS saldo_pendiente FROM employees WHERE id=?");
    $stmt->execute([(int)$emp['id']]);
    $pending = (float)($stmt->fetch()['saldo_pendiente'] ?? 0);
  } catch (Throwable $e) { $pending = 0; }
  $emp['saldo'] = $emp['sueldo_base_semanal'] - ($emp['descuentos'] + $emp['adelantos'] + $emp['cuota_prestamo']) + $pending;
}
unset($emp); // Romper referencia para evitar corrupción en loops siguientes

$attendance_list = [];
try {
  $attendance_list = db()->query("SELECT a.*, CONCAT(e.nombre,' ',e.apellido) AS empleado FROM employee_attendance a JOIN employees e ON e.id=a.employee_id ORDER BY a.fecha DESC, a.id DESC LIMIT 100")->fetchAll();
} catch (Throwable $e) {}

$payroll_list = [];
try {
  $payroll_list = db()->query("SELECT p.*, CONCAT(e.nombre,' ',e.apellido) AS empleado FROM employee_payroll p JOIN employees e ON e.id=p.employee_id ORDER BY p.fecha_pago DESC LIMIT 100")->fetchAll();
} catch (Throwable $e) {}

function tabActive($t, $tab) { return $t===$tab ? 'active' : ''; }
function paneActive($t, $tab) { return $t===$tab ? 'show active' : ''; }

include __DIR__ . '/../views/partials/header.php';
include __DIR__ . '/../views/partials/navbar.php';
?>

<div class="container-fluid py-4">
  <h5 class="mb-3">Gestión de Empleados</h5>

  <?php if ($flash_ok): ?><div class="alert alert-success"><?= e($flash_ok) ?></div><?php endif; ?>
  <?php if ($flash_err): ?><div class="alert alert-danger"><?= e($flash_err) ?></div><?php endif; ?>

  <!-- DEBUG: Info útil -->
  <?php if ($_GET['debug'] ?? false): ?>
  <div class="alert alert-secondary small" style="max-height: 300px; overflow-y: auto;">
    <strong>DEBUG INFO:</strong><br>
    Empleados totales: <?= count($employees_list) ?><br>
    Tab actual: <?= e($tab) ?><br>
    Empleado seleccionado ID: <?= $selected_emp_id ?><br>
    Empleado encontrado: <?= $selected_employee ? 'SÍ' : 'NO' ?><br>
    Movimientos cargados: <?= count($movimientos) ?><br>
    <?php if ($mov_error): ?>
      <strong style="color: red;">Error en movimientos:</strong> <?= e($mov_error) ?><br>
    <?php endif; ?>
    <?php if ($selected_emp_id > 0): ?>
      <strong>Movimientos por tipo (BD):</strong><br>
      <?php 
        try {
          $desc_count = db()->query("SELECT COUNT(*) as c FROM employee_discounts WHERE employee_id=?")->execute([$selected_emp_id])->fetch()['c'] ?? 0;
          $adel_count = db()->query("SELECT COUNT(*) as c FROM employee_advances WHERE employee_id=?")->execute([$selected_emp_id])->fetch()['c'] ?? 0;
          $prest_count = db()->query("SELECT COUNT(*) as c FROM employee_loans WHERE employee_id=?")->execute([$selected_emp_id])->fetch()['c'] ?? 0;
        } catch (Throwable $e) {
          echo "Error al contar movimientos: " . e($e->getMessage());
        }
      ?>
      Descuentos: <?= $desc_count ?? '0' ?><br>
      Adelantos: <?= $adel_count ?? '0' ?><br>
      Préstamos: <?= $prest_count ?? '0' ?>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <ul class="nav nav-tabs" role="tablist">
    <li class="nav-item"><button class="nav-link <?= tabActive('empleados',$tab) ?>" data-bs-toggle="tab" data-bs-target="#empleados" type="button">Empleados</button></li>
    <li class="nav-item"><button class="nav-link <?= tabActive('asistencia',$tab) ?>" data-bs-toggle="tab" data-bs-target="#asistencia" type="button">Asistencia</button></li>
    <li class="nav-item"><button class="nav-link <?= tabActive('movimientos',$tab) ?>" data-bs-toggle="tab" data-bs-target="#movimientos" type="button">Movimientos</button></li>
    <li class="nav-item"><button class="nav-link <?= tabActive('resumen',$tab) ?>" data-bs-toggle="tab" data-bs-target="#resumen" type="button">Resumen de Sueldos</button></li>
    <li class="nav-item"><button class="nav-link <?= tabActive('nomina',$tab) ?>" data-bs-toggle="tab" data-bs-target="#nomina" type="button">Nómina</button></li>
  </ul>

  <div class="tab-content border-bottom border-start border-end p-3 bg-white shadow-sm">

    <!-- EMPLEADOS -->
    <div class="tab-pane fade <?= paneActive('empleados',$tab) ?>" id="empleados">
      <div class="row g-3">
        <div class="col-md-5">
          <h6>Nuevo/Editar Empleado</h6>
          <form method="post" class="border rounded p-3 bg-light">
            <input type="hidden" name="action" value="guardar_empleado">
            <div class="mb-2"><label class="form-label">Nombre *</label><input type="text" name="nombre" class="form-control" required></div>
            <div class="mb-2"><label class="form-label">Apellido *</label><input type="text" name="apellido" class="form-control" required></div>
            <div class="mb-2"><label class="form-label">Email</label><input type="email" name="email" class="form-control"></div>
            <div class="mb-2"><label class="form-label">Teléfono</label><input type="tel" name="telefono" class="form-control"></div>
            <div class="mb-2"><label class="form-label">DNI</label><input type="text" name="dni" class="form-control"></div>
            <div class="mb-2"><label class="form-label">Domicilio</label><input type="text" name="domicilio" class="form-control"></div>
            <div class="mb-2"><label class="form-label">Ciudad</label><input type="text" name="ciudad" class="form-control"></div>
            <div class="mb-2"><label class="form-label">Puesto</label><input type="text" name="puesto" class="form-control"></div>
            <div class="mb-2"><label class="form-label">Fecha Contratación *</label><input type="date" name="fecha_contratacion" class="form-control" required></div>
            <div class="mb-3"><label class="form-label">Sueldo Base Semanal *</label><input type="number" step="0.01" min="0" name="sueldo_base_semanal" class="form-control" required></div>
            <div class="d-grid"><button type="submit" class="btn btn-primary">Guardar</button></div>
          </form>
        </div>

        <div class="col-md-7">
          <h6>Empleados Activos</h6>
          <div class="table-responsive">
            <table class="table table-sm">
              <thead class="table-light"><tr><th>Nombre</th><th>Puesto</th><th>Sueldo Semanal</th><th>Acción</th></tr></thead>
              <tbody>
                <?php if (!$employees): ?>
                  <tr><td colspan="4" class="text-center text-muted">Sin empleados</td></tr>
                <?php else: foreach ($employees as $e): ?>
                  <tr>
                    <td><?= e($e['nombre'] . ' ' . $e['apellido']) ?></td>
                    <td><?= e($e['puesto'] ?? '—') ?></td>
                    <td><?= money($e['sueldo_base_semanal']) ?></td>
                    <td><a href="?tab=movimientos&emp_id=<?= (int)$e['id'] ?>" class="btn btn-sm btn-outline-primary">Ver Legajo</a></td>
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
            <div class="mb-2"><label class="form-label">Empleado *</label><select name="employee_id" class="form-select" required><option value="">— Seleccionar —</option><?php foreach ($employees_list as $e): ?><option value="<?= (int)$e['id'] ?>"><?= e($e['nombre_completo']) ?></option><?php endforeach; ?></select></div>
            <div class="mb-2"><label class="form-label">Fecha *</label><input type="date" name="fecha" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
            <div class="mb-2"><label class="form-label">Hora Entrada</label><input type="time" name="hora_entrada" class="form-control"></div>
            <div class="mb-2"><label class="form-label">Hora Salida</label><input type="time" name="hora_salida" class="form-control"></div>
            <div class="mb-2"><label><input type="checkbox" name="presente" value="1" checked> Presente</label></div>
            <div class="mb-3"><label class="form-label">Notas</label><textarea name="notas" class="form-control" rows="2"></textarea></div>
            <div class="d-grid"><button type="submit" class="btn btn-primary">Registrar</button></div>
          </form>
        </div>

        <div class="col-md-7">
          <h6>Últimas Asistencias</h6>
          <div class="table-responsive">
            <table class="table table-sm">
              <thead class="table-light"><tr><th>Empleado</th><th>Fecha</th><th>Entrada</th><th>Salida</th><th>Presente</th></tr></thead>
              <tbody>
                <?php if (!$attendance_list): ?>
                  <tr><td colspan="5" class="text-center text-muted py-3">Sin registros</td></tr>
                <?php else: foreach ($attendance_list as $a): ?>
                  <tr><td><?= e($a['empleado']) ?></td><td><?= e($a['fecha']) ?></td><td><?= e($a['hora_entrada'] ?? '—') ?></td><td><?= e($a['hora_salida'] ?? '—') ?></td><td><?= $a['presente'] ? '✓' : '✗' ?></td></tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- MOVIMIENTOS -->
    <div class="tab-pane fade <?= paneActive('movimientos',$tab) ?>" id="movimientos">
      <div class="mb-3">
        <label class="form-label">Seleccionar Empleado</label>
        <select class="form-select" id="select-emp-movimientos">
          <option value="0">— Seleccionar —</option>
          <?php foreach ($employees_list as $e): ?>
            <option value="<?= (int)$e['id'] ?>" <?= $selected_emp_id === (int)$e['id'] ? 'selected' : '' ?>><?= e($e['nombre_completo']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <script>
        document.getElementById('select-emp-movimientos').addEventListener('change', function() {
          const emp_id = this.value;
          if (emp_id && emp_id !== '0') {
            window.location.href = '?tab=movimientos&emp_id=' + emp_id;
          }
        });
      </script>

      <?php if (!$employees_list): ?>
        <div class="alert alert-warning">No hay empleados creados. <a href="?tab=empleados">Crear empleado</a></div>
      <?php elseif (!$selected_emp_id): ?>
        <div class="alert alert-info">Selecciona un empleado para ver sus movimientos</div>
      <?php else: ?>
        <?php if ($selected_employee): ?>
          <div class="alert alert-info">
            <strong><?= e($selected_employee['nombre'] . ' ' . $selected_employee['apellido']) ?></strong> - 
            Sueldo Base Semanal: <strong><?= money($selected_employee['sueldo_base_semanal']) ?></strong> - 
            <strong style="color: <?= $saldo_semanal >= 0 ? 'green' : 'red' ?>">Saldo a Pagar: <?= money($saldo_semanal) ?></strong>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-4">
              <h6>Registrar Descuento</h6>
              <form method="post" class="border rounded p-3 bg-light">
                <input type="hidden" name="action" value="registrar_descuento">
                <input type="hidden" name="employee_id" value="<?= (int)$selected_emp_id ?>">
                <div class="mb-2"><label class="form-label">Tipo</label><select name="tipo" class="form-select"><option value="FALTA">Falta</option><option value="LLEGADA_TARDE">Llegada Tarde</option><option value="OTRO">Otro</option></select></div>
                <div class="mb-2"><label class="form-label">Fecha *</label><input type="date" name="fecha" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
                <div class="mb-2"><label class="form-label">Monto</label><input type="number" step="0.01" min="0" name="monto_descuento" class="form-control"></div>
                <div class="mb-3"><label class="form-label">Razón</label><textarea name="razon" class="form-control" rows="2"></textarea></div>
                <div class="d-grid"><button type="submit" class="btn btn-danger btn-sm">Registrar</button></div>
              </form>
            </div>

            <div class="col-md-4">
              <h6>Registrar Adelanto</h6>
              <form method="post" class="border rounded p-3 bg-light">
                <input type="hidden" name="action" value="registrar_adelanto">
                <input type="hidden" name="employee_id" value="<?= (int)$selected_emp_id ?>">
                <div class="mb-2"><label class="form-label">Monto *</label><input type="number" step="0.01" min="0.01" name="monto" class="form-control" required></div>
                <div class="mb-3"><label class="form-label">Razón</label><textarea name="razon" class="form-control" rows="3"></textarea></div>
                <div class="d-grid"><button type="submit" class="btn btn-warning btn-sm">Registrar</button></div>
              </form>
            </div>

            <div class="col-md-4">
              <h6>Registrar Préstamo</h6>
              <form method="post" class="border rounded p-3 bg-light">
                <input type="hidden" name="action" value="registrar_prestamo">
                <input type="hidden" name="employee_id" value="<?= (int)$selected_emp_id ?>">
                <div class="mb-2"><label class="form-label">Monto *</label><input type="number" step="0.01" min="0.01" name="monto_solicitado" class="form-control" required></div>
                <div class="mb-2"><label class="form-label">Cuotas</label><input type="number" min="1" name="cuotas_cantidad" class="form-control" value="1"></div>
                <div class="mb-3"><label class="form-label">Razón</label><textarea name="razon" class="form-control" rows="2"></textarea></div>
                <div class="d-grid"><button type="submit" class="btn btn-info btn-sm">Registrar</button></div>
              </form>
            </div>
          </div>

          <h6>Historial de Movimientos</h6>
          <div class="table-responsive">
            <table class="table table-sm">
              <thead class="table-light"><tr><th>Tipo</th><th>Fecha</th><th>Monto</th><th>Detalle</th></tr></thead>
              <tbody>
                <?php if (!$movimientos): ?>
                  <tr><td colspan="4" class="text-center text-muted py-3">Sin movimientos registrados</td></tr>
                <?php else: foreach ($movimientos as $m): ?>
                  <tr>
                    <td><span class="badge bg-<?= $m['tipo']==='DESCUENTO'?'danger':($m['tipo']==='ADELANTO'?'warning':($m['tipo']==='PAGO'?'success':'info')) ?>"><?= e($m['tipo']) ?></span></td>
                    <td><?= e($m['fecha']) ?></td>
                    <td><?= money($m['monto']) ?></td>
                    <td><?= e($m['detalle'] ?? '—') ?></td>
                  </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="alert alert-danger">Empleado no encontrado</div>
        <?php endif; ?>
      <?php endif; ?>
    </div>

    <!-- RESUMEN DE SUELDOS -->
    <div class="tab-pane fade <?= paneActive('resumen',$tab) ?>" id="resumen">
      <h6>Resumen de Sueldos Semanales</h6>
      <div class="table-responsive">
        <table class="table table-sm">
          <thead class="table-light">
            <tr>
              <th>Empleado</th>
              <th>Sueldo Base</th>
              <th>Descuentos</th>
              <th>Adelantos</th>
              <th>Cuota Préstamo</th>
              <th class="text-end">Saldo a Pagar</th>
              <th>Acción</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$resumen_empleados): ?>
              <tr><td colspan="7" class="text-center text-muted py-3">Sin empleados</td></tr>
            <?php else: foreach ($resumen_empleados as $emp): ?>
              <tr>
                <td><?= e($emp['nombre_completo']) ?></td>
                <td><?= money($emp['sueldo_base_semanal']) ?></td>
                <td><?= money($emp['descuentos']) ?></td>
                <td><?= money($emp['adelantos']) ?></td>
                <td><?= money($emp['cuota_prestamo']) ?></td>
                <td class="text-end fw-semibold" style="color: <?= $emp['saldo'] >= 0 ? 'green' : 'red' ?>"><?= money($emp['saldo']) ?></td>
                <td><a href="?tab=movimientos&emp_id=<?= (int)$emp['id'] ?>" class="btn btn-sm btn-outline-primary">Ver Legajo</a></td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- NÓMINA -->
    <div class="tab-pane fade <?= paneActive('nomina',$tab) ?>" id="nomina">
      <h6>Pago de Nóminas - Semana</h6>
      <div class="table-responsive">
        <table class="table table-sm">
          <thead class="table-light">
            <tr>
              <th>Empleado</th>
              <th>Sueldo Base</th>
              <th>Descuentos</th>
              <th>Adelantos</th>
              <th>Cuota Préstamo</th>
              <th class="text-end">Saldo a Pagar</th>
              <th>Acción</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$resumen_empleados): ?>
              <tr><td colspan="7" class="text-center text-muted py-3">Sin empleados</td></tr>
            <?php else: foreach ($resumen_empleados as $emp): ?>
              <tr>
                <td><?= e($emp['nombre_completo']) ?></td>
                <td><?= money($emp['sueldo_base_semanal']) ?></td>
                <td><?= money($emp['descuentos']) ?></td>
                <td><?= money($emp['adelantos']) ?></td>
                <td><?= money($emp['cuota_prestamo']) ?></td>
                <td class="text-end fw-semibold" style="color: <?= $emp['saldo'] >= 0 ? 'green' : 'red' ?>"><?= money($emp['saldo']) ?></td>
                <td>
                  <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#pagarModal" 
                    data-empid="<?= (int)$emp['id'] ?>" 
                    data-empname="<?= e($emp['nombre_completo']) ?>" 
                    data-sueldo="<?= (float)$emp['sueldo_base_semanal'] ?>" 
                    data-descuentos="<?= (float)$emp['descuentos'] ?>" 
                    data-adelantos="<?= (float)$emp['adelantos'] ?>" 
                    data-prestamos="<?= (float)$emp['cuota_prestamo'] ?>" 
                    data-saldo="<?= (float)$emp['saldo'] ?>">
                    PAGAR
                  </button>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>

      <hr>
      <h6>Nóminas Registradas</h6>
      <div class="table-responsive">
        <table class="table table-sm">
          <thead class="table-light"><tr><th>Empleado</th><th>Fecha</th><th>Sueldo Neto</th><th>Medio</th><th>Estado</th></tr></thead>
          <tbody>
            <?php if (!$payroll_list): ?>
              <tr><td colspan="5" class="text-center text-muted py-3">Sin nóminas</td></tr>
            <?php else: foreach ($payroll_list as $p): ?>
              <tr><td><?= e($p['empleado']) ?></td><td><?= e($p['fecha_pago']) ?></td><td><?= money($p['sueldo_neto']) ?></td><td><?= e($p['medio_pago']) ?></td><td><span class="badge bg-success"><?= e($p['estado']) ?></span></td></tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</div>

<!-- Modal: Pagar Nómina -->
<div class="modal fade" id="pagarModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <form method="post" id="pagar-form">
        <input type="hidden" name="action" value="registrar_nomina">
        <input type="hidden" name="employee_id" id="modal_employee_id" value="">
        <input type="hidden" name="fecha_pago" value="<?= date('Y-m-d') ?>">
        <input type="hidden" name="sueldo_base" id="modal_sueldo_base" value="0">
        <input type="hidden" name="descuentos_total" id="modal_descuentos" value="0">
        <input type="hidden" name="adelantos_total" id="modal_adelantos" value="0">
        <input type="hidden" name="prestamos_cuota" id="modal_prestamos" value="0">
        <div class="modal-header">
          <h5 class="modal-title">Pagar Nómina</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="mb-2"><strong id="modal_empname"></strong></div>
          <div class="mb-2">Sueldo Base: <span id="modal_sueldo_text"></span></div>
          <div class="mb-2">Descuentos: <span id="modal_desc_text"></span></div>
          <div class="mb-2">Adelantos: <span id="modal_adel_text"></span></div>
          <div class="mb-2">Cuota Préstamo: <span id="modal_prest_text"></span></div>
          <div class="mb-3">
            <label class="form-label">Monto a pagar</label>
            <input type="number" step="0.01" min="0" name="sueldo_neto" id="modal_monto_pagar" class="form-control">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-success">Confirmar pago</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  var pagarModal = document.getElementById('pagarModal');
  if (pagarModal) {
    pagarModal.addEventListener('show.bs.modal', function (event) {
      var button = event.relatedTarget;
      var empId = button.getAttribute('data-empid');
      var empName = button.getAttribute('data-empname');
      var sueldo = parseFloat(button.getAttribute('data-sueldo') || 0);
      var desc = parseFloat(button.getAttribute('data-descuentos') || 0);
      var adel = parseFloat(button.getAttribute('data-adelantos') || 0);
      var prest = parseFloat(button.getAttribute('data-prestamos') || 0);
      var saldo = parseFloat(button.getAttribute('data-saldo') || 0);

      document.getElementById('modal_employee_id').value = empId;
      document.getElementById('modal_empname').textContent = empName;
      document.getElementById('modal_sueldo_base').value = sueldo;
      document.getElementById('modal_descuentos').value = desc;
      document.getElementById('modal_adelantos').value = adel;
      document.getElementById('modal_prestamos').value = prest;

      document.getElementById('modal_sueldo_text').textContent = sueldo.toFixed(2);
      document.getElementById('modal_desc_text').textContent = desc.toFixed(2);
      document.getElementById('modal_adel_text').textContent = adel.toFixed(2);
      document.getElementById('modal_prest_text').textContent = prest.toFixed(2);
      document.getElementById('modal_monto_pagar').value = saldo.toFixed(2);
    });
  }

  // Opcional: validar antes de enviar
  document.getElementById('pagar-form')?.addEventListener('submit', function(e){
    var monto = parseFloat(document.getElementById('modal_monto_pagar').value || 0);
    if (isNaN(monto) || monto <= 0) {
      e.preventDefault();
      alert('Ingrese un monto válido para pagar');
    }
  });
</script>

<?php include __DIR__ . '/../views/partials/footer.php'; ?>
