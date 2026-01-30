<?php
require_once __DIR__ . '/../app/auth.php';
require_login();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

// Determinar si es rol RRHH (solo ve asistencias e incidencias)
$is_rrhh_only = check_role('RRHH') && !check_role('ADMIN', 'CAJA');

$flash_ok = '';
$flash_err = '';

// Asegurar columna para saldo pendiente (si no existe)
try {
  db()->exec("ALTER TABLE employees ADD COLUMN IF NOT EXISTS saldo_pendiente DECIMAL(12,2) DEFAULT 0");
  db()->exec("ALTER TABLE employees ADD COLUMN IF NOT EXISTS pago_por_hora DECIMAL(10,2) DEFAULT 0");
  db()->exec("ALTER TABLE employees ADD COLUMN IF NOT EXISTS pago_semanal DECIMAL(10,2) DEFAULT 0");
  db()->exec("ALTER TABLE employees ADD COLUMN IF NOT EXISTS pago_mensual DECIMAL(10,2) DEFAULT 0");
  db()->exec("ALTER TABLE employees ADD COLUMN IF NOT EXISTS suspendido TINYINT(1) DEFAULT 0");
  db()->exec("ALTER TABLE employees ADD COLUMN IF NOT EXISTS fecha_inicio_suspension DATE NULL");
  db()->exec("ALTER TABLE employees ADD COLUMN IF NOT EXISTS fecha_fin_suspension DATE NULL");
  db()->exec("ALTER TABLE employees ADD COLUMN IF NOT EXISTS motivo_suspension TEXT NULL");
  db()->exec("ALTER TABLE employees ADD COLUMN IF NOT EXISTS en_licencia_medica TINYINT(1) DEFAULT 0");
  db()->exec("ALTER TABLE employees ADD COLUMN IF NOT EXISTS fecha_inicio_licencia DATE NULL");
  db()->exec("ALTER TABLE employees ADD COLUMN IF NOT EXISTS fecha_fin_licencia DATE NULL");
  db()->exec("ALTER TABLE employees ADD COLUMN IF NOT EXISTS motivo_licencia TEXT NULL");
} catch (Throwable $e) {
  // No bloquear ejecución si ALTER no funciona en versiones antiguas
}
// Asegurar columnas nuevas para asistencia (ingreso mañana/tarde y horas extras)
try {
  db()->exec("ALTER TABLE employee_attendance ADD COLUMN IF NOT EXISTS ingreso_manana TIME NULL");
  db()->exec("ALTER TABLE employee_attendance ADD COLUMN IF NOT EXISTS ingreso_tarde TIME NULL");
  db()->exec("ALTER TABLE employee_attendance ADD COLUMN IF NOT EXISTS horas_extras DECIMAL(5,2) DEFAULT 0");
} catch (Throwable $e) {
  // Silenciar errores de compatibilidad
}

// Crear tabla para incidencias de empleados
try {
  db()->exec("CREATE TABLE IF NOT EXISTS employee_incidents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    fecha DATE NOT NULL,
    tipo VARCHAR(100) DEFAULT 'OTRO',
    gravedad VARCHAR(20) DEFAULT 'LEVE',
    descripcion TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INT NULL,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
  )");
} catch (Throwable $e) {
  // Silenciar errores si la tabla ya existe
}

// Crear tabla para períodos de pago
try {
  db()->exec("CREATE TABLE IF NOT EXISTS payroll_periods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fecha_inicio DATE NOT NULL,
    fecha_fin DATE NOT NULL,
    estado VARCHAR(20) DEFAULT 'ACTIVO',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    closed_at TIMESTAMP NULL,
    closed_by INT NULL
  )");
  
  // Agregar columna period_id a employee_payroll si no existe
  db()->exec("ALTER TABLE employee_payroll ADD COLUMN IF NOT EXISTS period_id INT NULL");
  
  // Agregar columna para saldo del período
  db()->exec("ALTER TABLE employee_payroll ADD COLUMN IF NOT EXISTS saldo_periodo_anterior DECIMAL(12,2) DEFAULT 0");
  
} catch (Throwable $e) {
  // Silenciar errores
}

// ========== FUNCIONES DE PERÍODOS ==========
function get_active_period() {
  $stmt = db()->prepare("SELECT * FROM payroll_periods WHERE estado='ACTIVO' ORDER BY fecha_inicio DESC LIMIT 1");
  $stmt->execute();
  return $stmt->fetch();
}

function create_period_if_needed() {
  $active = get_active_period();
  if (!$active) {
    // Crear período para la semana actual
    $inicio = date('Y-m-d', strtotime('monday this week'));
    $fin = date('Y-m-d', strtotime('sunday this week'));
    db()->prepare("INSERT INTO payroll_periods (fecha_inicio, fecha_fin, estado) VALUES (?, ?, 'ACTIVO')")
      ->execute([$inicio, $fin]);
    return get_active_period();
  }
  return $active;
}

function get_employee_period_balance($employee_id, $period_id) {
  // Obtener todos los pagos del período actual
  $stmt = db()->prepare("SELECT COALESCE(SUM(sueldo_neto), 0) as pagado FROM employee_payroll WHERE employee_id=? AND period_id=?");
  $stmt->execute([$employee_id, $period_id]);
  $pagado = (float)($stmt->fetch()['pagado'] ?? 0);
  
  // Obtener el saldo del período anterior (solo del primer registro del período)
  $stmt = db()->prepare("SELECT saldo_periodo_anterior FROM employee_payroll WHERE employee_id=? AND period_id=? ORDER BY id ASC LIMIT 1");
  $stmt->execute([$employee_id, $period_id]);
  $saldo_anterior = (float)($stmt->fetch()['saldo_periodo_anterior'] ?? 0);
  
  return ['pagado' => $pagado, 'saldo_anterior' => $saldo_anterior];
}

$validTabs = ['empleados','asistencia','movimientos','resumen','nomina','periodos'];
$tab = $_GET['tab'] ?? 'empleados';

// Si es RRHH, solo permitir asistencia e incidencias (movimientos)
if ($is_rrhh_only) {
  $validTabs = ['asistencia', 'movimientos'];
  $tab = in_array($tab, $validTabs, true) ? $tab : 'asistencia';
}

if (!in_array($tab, $validTabs, true)) $tab = 'empleados';

$selected_emp_id = (int)($_GET['emp_id'] ?? 0);

// ========== POST: Acciones ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  // CERRAR PERÍODO Y CREAR NUEVO
  if ($action === 'cerrar_periodo') {
    try {
      $period = get_active_period();
      if (!$period) throw new Exception('No hay un período activo.');
      
      $userId = (int)(user()['id'] ?? 0);
      
      db()->beginTransaction();
      
      // Cerrar el período actual
      db()->prepare("UPDATE payroll_periods SET estado='CERRADO', closed_at=NOW(), closed_by=? WHERE id=?")
        ->execute([$userId, $period['id']]);
      
      // Calcular y transferir saldos pendientes de cada empleado al nuevo período
      $employees = db()->query("SELECT id, pago_semanal, pago_por_hora FROM employees WHERE activo=1")->fetchAll();
      
      foreach ($employees as $emp) {
        $emp_id = (int)$emp['id'];
        
        // Calcular lo que debía cobrar en el período
        $sueldo_base = (float)($emp['pago_semanal'] ?? 0);
        
        // Horas extras del período
        $he_stmt = db()->prepare("SELECT COALESCE(SUM(horas_extras), 0) as total FROM employee_attendance WHERE employee_id=? AND fecha BETWEEN ? AND ?");
        $he_stmt->execute([$emp_id, $period['fecha_inicio'], $period['fecha_fin']]);
        $horas_extras = (float)($he_stmt->fetch()['total'] ?? 0);
        $pago_horas_extras = $horas_extras * (float)($emp['pago_por_hora'] ?? 0);
        
        // Descuentos del período
        $desc_stmt = db()->prepare("SELECT COALESCE(SUM(monto_descuento), 0) as total FROM employee_discounts WHERE employee_id=? AND fecha BETWEEN ? AND ?");
        // Descuentos del período
        $desc_stmt = db()->prepare("SELECT COALESCE(SUM(monto_descuento), 0) as total FROM employee_discounts WHERE employee_id=? AND fecha BETWEEN ? AND ?");
        $desc_stmt->execute([$emp_id, $period['fecha_inicio'], $period['fecha_fin']]);
        $descuentos = (float)($desc_stmt->fetch()['total'] ?? 0);
        
        // Total a pagar en el período (base + extras - descuentos)
        $total_periodo = $sueldo_base + $pago_horas_extras - $descuentos;
        
        // Total pagado en el período
        $pagado_stmt = db()->prepare("SELECT COALESCE(SUM(sueldo_neto), 0) as total FROM employee_payroll WHERE employee_id=? AND period_id=?");
        $pagado_stmt->execute([$emp_id, $period['id']]);
        $pagado = (float)($pagado_stmt->fetch()['total'] ?? 0);
        
        // Obtener el saldo del período anterior (que estaba guardado antes de iniciar este período)
        $saldo_anterior_stmt = db()->prepare("SELECT COALESCE(saldo_pendiente, 0) as saldo FROM employees WHERE id=?");
        $saldo_anterior_stmt->execute([$emp_id]);
        $saldo_anterior = (float)($saldo_anterior_stmt->fetch()['saldo'] ?? 0);
        
        // Calcular el nuevo saldo: lo que debía (total_periodo) + saldo anterior - lo pagado
        $nuevo_saldo = round($total_periodo + $saldo_anterior - $pagado, 2);
        
        // Actualizar el saldo_pendiente del empleado para el nuevo período
        db()->prepare("UPDATE employees SET saldo_pendiente=? WHERE id=?")
          ->execute([$nuevo_saldo, $emp_id]);
      }
      
      // Crear nuevo período (próxima semana)
      $nuevo_inicio = date('Y-m-d', strtotime($period['fecha_fin'] . ' +1 day'));
      $nuevo_fin = date('Y-m-d', strtotime($nuevo_inicio . ' +6 days'));
      db()->prepare("INSERT INTO payroll_periods (fecha_inicio, fecha_fin, estado) VALUES (?, ?, 'ACTIVO')")
        ->execute([$nuevo_inicio, $nuevo_fin]);
      
      db()->commit();
      $flash_ok = "Período cerrado correctamente. Nuevo período creado del $nuevo_inicio al $nuevo_fin.";
      $tab = 'periodos';
    } catch (Throwable $e) {
      db()->rollBack();
      $flash_err = 'Error: ' . $e->getMessage();
    }
  }

  // CREAR/EDITAR EMPLEADO
  if ($action === 'guardar_empleado') {
    $emp_id = (int)($_POST['employee_id'] ?? 0);
    $nombre = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $dni = trim($_POST['dni'] ?? '');
    $domicilio = trim($_POST['domicilio'] ?? '');
    $fecha_contratacion = trim($_POST['fecha_contratacion'] ?? '');
    $pago_por_hora = max(0, (float)($_POST['pago_por_hora'] ?? 0));
    $pago_semanal = max(0, (float)($_POST['pago_semanal'] ?? 0));
    $pago_mensual = max(0, (float)($_POST['pago_mensual'] ?? 0));
    $suspendido = (int)($_POST['suspendido'] ?? 0);
    $fecha_inicio_suspension = trim($_POST['fecha_inicio_suspension'] ?? '') ?: null;
    $fecha_fin_suspension = trim($_POST['fecha_fin_suspension'] ?? '') ?: null;
    $motivo_suspension = trim($_POST['motivo_suspension'] ?? '');
    $en_licencia_medica = (int)($_POST['en_licencia_medica'] ?? 0);
    $fecha_inicio_licencia = trim($_POST['fecha_inicio_licencia'] ?? '') ?: null;
    $fecha_fin_licencia = trim($_POST['fecha_fin_licencia'] ?? '') ?: null;
    $motivo_licencia = trim($_POST['motivo_licencia'] ?? '');

    try {
      if ($nombre === '') throw new Exception('El nombre es obligatorio.');
      if ($apellido === '') throw new Exception('El apellido es obligatorio.');
      if ($fecha_contratacion === '') throw new Exception('La fecha de contratación es obligatoria.');
      if ($pago_por_hora <= 0 && $pago_semanal <= 0 && $pago_mensual <= 0) {
        throw new Exception('Debe especificar al menos un tipo de pago (por hora, semanal o mensual).');
      }

      if ($emp_id > 0) {
        $stmt = db()->prepare("UPDATE employees SET nombre=?, apellido=?, telefono=?, dni=?, domicilio=?, fecha_contratacion=?, pago_por_hora=?, pago_semanal=?, pago_mensual=?, sueldo_base_semanal=?, suspendido=?, fecha_inicio_suspension=?, fecha_fin_suspension=?, motivo_suspension=?, en_licencia_medica=?, fecha_inicio_licencia=?, fecha_fin_licencia=?, motivo_licencia=? WHERE id=?");
        $stmt->execute([$nombre, $apellido, $telefono, $dni, $domicilio, $fecha_contratacion, $pago_por_hora, $pago_semanal, $pago_mensual, $pago_semanal, $suspendido, $fecha_inicio_suspension, $fecha_fin_suspension, $motivo_suspension, $en_licencia_medica, $fecha_inicio_licencia, $fecha_fin_licencia, $motivo_licencia, $emp_id]);
        $flash_ok = "Empleado actualizado correctamente.";
      } else {
        $stmt = db()->prepare("INSERT INTO employees (nombre, apellido, telefono, dni, domicilio, fecha_contratacion, pago_por_hora, pago_semanal, pago_mensual, sueldo_base_semanal, suspendido, fecha_inicio_suspension, fecha_fin_suspension, motivo_suspension, en_licencia_medica, fecha_inicio_licencia, fecha_fin_licencia, motivo_licencia) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$nombre, $apellido, $telefono, $dni, $domicilio, $fecha_contratacion, $pago_por_hora, $pago_semanal, $pago_mensual, $pago_semanal, $suspendido, $fecha_inicio_suspension, $fecha_fin_suspension, $motivo_suspension, $en_licencia_medica, $fecha_inicio_licencia, $fecha_fin_licencia, $motivo_licencia]);
        $flash_ok = "Empleado creado correctamente.";
      }
      $tab = 'empleados';
    } catch (Throwable $e) {
      $flash_err = 'Error: ' . $e->getMessage();
    }
  }

  // REGISTRAR INCIDENCIA
  if ($action === 'registrar_incidencia') {
    $emp_id = (int)($_POST['employee_id'] ?? 0);
    $fecha = trim($_POST['fecha'] ?? '');
    $tipo = trim($_POST['tipo'] ?? 'OTRO');
    $gravedad = trim($_POST['gravedad'] ?? 'LEVE');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $userId = (int)(user()['id'] ?? 0);

    try {
      if ($emp_id <= 0) throw new Exception('Selecciona un empleado.');
      if ($fecha === '') throw new Exception('La fecha es obligatoria.');
      if ($descripcion === '') throw new Exception('La descripción es obligatoria.');

      $stmt = db()->prepare("INSERT INTO employee_incidents (employee_id, fecha, tipo, gravedad, descripcion, created_by) VALUES (?, ?, ?, ?, ?, ?)");
      $stmt->execute([$emp_id, $fecha, $tipo, $gravedad, $descripcion, $userId]);
      $flash_ok = "Incidencia registrada correctamente.";
      $tab = 'movimientos';
    } catch (Throwable $e) {
      $flash_err = 'Error: ' . $e->getMessage();
    }
  }

  // REGISTRAR ASISTENCIA
  if ($action === 'registrar_asistencia') {
    $emp_id = (int)($_POST['employee_id'] ?? 0);
    $fecha = trim($_POST['fecha'] ?? '');
    $presente = (int)($_POST['presente'] ?? 1);
    $ingreso_manana = trim($_POST['ingreso_manana'] ?? '') ?: null;
    $ingreso_tarde = trim($_POST['ingreso_tarde'] ?? '') ?: null;
    $horas_extras = max(0, (float)($_POST['horas_extras'] ?? 0));
    $justificado = (int)($_POST['justificado'] ?? 0);
    $notas = trim($_POST['notas'] ?? '');

    try {
      if ($emp_id <= 0) {
        // Debug: mostrar qué viene en POST
        error_log("DEBUG empleados.php - POST data: " . print_r($_POST, true));
        throw new Exception('Selecciona un empleado.');
      }
      if ($fecha === '') throw new Exception('Selecciona una fecha.');

      db()->prepare("INSERT INTO employee_attendance (employee_id, fecha, ingreso_manana, ingreso_tarde, horas_extras, presente, justificado, notas) VALUES (?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE ingreso_manana=?, ingreso_tarde=?, horas_extras=?, presente=?, justificado=?, notas=?")
        ->execute([$emp_id, $fecha, $ingreso_manana, $ingreso_tarde, $horas_extras, $presente, $justificado, $notas, $ingreso_manana, $ingreso_tarde, $horas_extras, $presente, $justificado, $notas]);
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

      // Obtener o crear el período activo
      $period = create_period_if_needed();
      
      // Calcular cuánto se debe EN ESTE PERÍODO (sin incluir saldo anterior)
      $owed_this_period = $sueldo_base - $descuentos - $adelantos - $prestamos_cuota;
      
      // Calcular cuánto se ha pagado en total en este período
      $stmt = db()->prepare("SELECT COALESCE(SUM(sueldo_neto), 0) as total FROM employee_payroll WHERE employee_id=? AND period_id=?");
      $stmt->execute([$emp_id, $period['id']]);
      $total_pagado_periodo = (float)($stmt->fetch()['total'] ?? 0);
      
      // Saldo del período actual = lo que debe este período - lo pagado hasta ahora - el pago actual
      $saldo_periodo_actual = round($owed_this_period - $total_pagado_periodo - $sueldo_neto, 2);

      $estado = $saldo_periodo_actual > 0 ? 'PENDIENTE' : 'PAGADO';

      $stmt = db()->prepare("INSERT INTO employee_payroll (employee_id, period_id, fecha_pago, semana_inicio, semana_fin, sueldo_base, descuentos_total, adelantos_total, prestamos_cuota, sueldo_neto, medio_pago, estado, notas) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
      $stmt->execute([$emp_id, $period['id'], $fecha_pago, $semana_inicio ?: null, $semana_fin ?: null, $sueldo_base, $descuentos, $adelantos, $prestamos_cuota, $sueldo_neto, $medio_pago, $estado, $notas]);

      // NO actualizar saldo_pendiente aquí - se calcula dinámicamente en la vista
      // El saldo_pendiente se actualiza SOLO al cerrar el período

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
    
    $stmt = db()->prepare("SELECT 'INCIDENCIA' AS tipo, id, fecha, 0 AS monto, CONCAT(tipo, ' - ', gravedad, ': ', descripcion) AS detalle, gravedad FROM employee_incidents WHERE employee_id=? ORDER BY fecha DESC");
    $stmt->execute([$selected_emp_id]);
    $incidencias = $stmt->fetchAll() ?: [];
    
    $movimientos = array_merge($descuentos, $adelantos, $prestamos, $pagos, $incidencias);
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
    // Usar pago semanal como base (si no tiene, usar 0)
    $sueldo_base = (float)($selected_employee['pago_semanal'] ?? 0);
    
    // Si el empleado está suspendido o en licencia, el sueldo base es 0
    if ($selected_employee['suspendido'] || $selected_employee['en_licencia_medica']) {
      $sueldo_base = 0;
    }
    
    // Calcular horas extras de la última semana
    $horas_extras_stmt = db()->prepare("SELECT COALESCE(SUM(horas_extras), 0) AS total FROM employee_attendance WHERE employee_id=? AND DATE(fecha) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
    $horas_extras_stmt->execute([$selected_emp_id]);
    $horas_extras_total = (float)($horas_extras_stmt->fetch()['total'] ?? 0);
    $pago_horas_extras = $horas_extras_total * (float)($selected_employee['pago_por_hora'] ?? 0);
    
    $desc_stmt = db()->prepare("SELECT COALESCE(SUM(monto_descuento), 0) AS total FROM employee_discounts WHERE employee_id=? AND DATE(fecha) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
    $desc_stmt->execute([$selected_emp_id]);
    $descuentos_total = (float)($desc_stmt->fetch()['total'] ?? 0);
    
    $adelantos_stmt = db()->prepare("SELECT COALESCE(SUM(monto), 0) AS total FROM employee_advances WHERE employee_id=? AND estado='APROBADO'");
    $adelantos_stmt->execute([$selected_emp_id]);
    $adelantos_total = (float)($adelantos_stmt->fetch()['total'] ?? 0);
    
    $prestamos_stmt = db()->prepare("SELECT COALESCE(SUM(monto_aprobado / cuotas_cantidad), 0) AS total FROM employee_loans WHERE employee_id=? AND estado='APROBADO'");
    $prestamos_stmt->execute([$selected_emp_id]);
    $prestamos_total = (float)($prestamos_stmt->fetch()['total'] ?? 0);
    
    // Obtener saldo del período anterior (transferido)
    $stmt_saldo = db()->prepare("SELECT COALESCE(saldo_pendiente,0) AS saldo_pendiente FROM employees WHERE id=?");
    $stmt_saldo->execute([$selected_emp_id]);
    $saldo_periodo_anterior = (float)($stmt_saldo->fetch()['saldo_pendiente'] ?? 0);
    
    // El saldo semanal = base + extras + saldo anterior - descuentos - adelantos - préstamos - pagos
    $debe_este_periodo = $sueldo_base + $pago_horas_extras - $descuentos_total - $adelantos_total - $prestamos_total;
    
    // Obtener pagos del período actual
    $period = get_active_period();
    $pagos_periodo_actual = 0;
    if ($period) {
      $stmt_pagos = db()->prepare("SELECT COALESCE(SUM(sueldo_neto), 0) as total FROM employee_payroll WHERE employee_id=? AND period_id=?");
      $stmt_pagos->execute([$selected_emp_id, $period['id']]);
      $pagos_periodo_actual = (float)($stmt_pagos->fetch()['total'] ?? 0);
    }
    
    $saldo_semanal = round($debe_este_periodo + $saldo_periodo_anterior - $pagos_periodo_actual, 2);
  } catch (Throwable $e) {}
}

// Resumen de todos los empleados - Versión simplificada sin subqueries
$resumen_empleados = [];
try {
  // Primero, obtener lista de empleados
  $empleados_base = db()->query("SELECT id, nombre, apellido, pago_semanal, pago_por_hora, suspendido, en_licencia_medica FROM employees WHERE activo=1 ORDER BY nombre, apellido")->fetchAll();
  
  // Para cada empleado, calcular sus valores
  foreach ($empleados_base as $emp) {
    $emp_id = (int)$emp['id'];
    
    // Usar pago semanal como base
    $sueldo_base = (float)($emp['pago_semanal'] ?? 0);
    
    // Si está suspendido o en licencia, no tiene sueldo base
    if ($emp['suspendido'] || $emp['en_licencia_medica']) {
      $sueldo_base = 0;
    }
    
    // Horas extras de la última semana
    $he = db()->query("SELECT COALESCE(SUM(horas_extras), 0) AS total FROM employee_attendance WHERE employee_id={$emp_id} AND DATE(fecha) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)")->fetch();
    $horas_extras = (float)($he['total'] ?? 0);
    $pago_horas_extras = $horas_extras * (float)($emp['pago_por_hora'] ?? 0);
    
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
      'sueldo_base_semanal' => $sueldo_base,
      'pago_horas_extras' => $pago_horas_extras,
      'horas_extras' => $horas_extras,
      'descuentos' => $descuentos,
      'adelantos' => $adelantos,
      'cuota_prestamo' => $cuota_prestamo,
      'suspendido' => $emp['suspendido'],
      'en_licencia' => $emp['en_licencia_medica']
    ];
  }
} catch (Throwable $e) {
  error_log("Error cargando resumen: " . $e->getMessage());
}

// Calcular saldos del período actual (incluyendo saldo anterior si fue transferido)
foreach ($resumen_empleados as &$emp) {
  try {
    $period = get_active_period();
    
    // Obtener saldo del período anterior (transferido al crear el período)
    $stmt_saldo = db()->prepare("SELECT COALESCE(saldo_pendiente,0) AS saldo_pendiente FROM employees WHERE id=?");
    $stmt_saldo->execute([(int)$emp['id']]);
    $saldo_periodo_anterior = (float)($stmt_saldo->fetch()['saldo_pendiente'] ?? 0);
    
    // Lo que debe este período (base + extras - descuentos - adelantos - préstamos)
    $debe_periodo = $emp['sueldo_base_semanal'] + $emp['pago_horas_extras'] - ($emp['descuentos'] + $emp['adelantos'] + $emp['cuota_prestamo']);
    
    // Lo pagado en este período
    $pagos_periodo = 0;
    if ($period) {
      $stmt_pagos = db()->prepare("SELECT COALESCE(SUM(sueldo_neto), 0) as total FROM employee_payroll WHERE employee_id=? AND period_id=?");
      $stmt_pagos->execute([(int)$emp['id'], $period['id']]);
      $pagos_periodo = (float)($stmt_pagos->fetch()['total'] ?? 0);
    }
    
    // Saldo total = lo que debe este período + saldo anterior - lo pagado
    // El saldo anterior YA está incluido en saldo_pendiente cuando se transfiere el período
    // Por lo tanto: saldo final = sueldo_base + horas_extras - descuentos - adelantos - prestamos + saldo_anterior - pagado
    $emp['saldo'] = round($debe_periodo + $saldo_periodo_anterior - $pagos_periodo, 2);
  } catch (Throwable $e) { 
    error_log("Error calculando saldo: " . $e->getMessage());
    $emp['saldo'] = 0; 
  }
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
    <?php if (!$is_rrhh_only): ?>
    <li class="nav-item"><button class="nav-link <?= tabActive('empleados',$tab) ?>" data-bs-toggle="tab" data-bs-target="#empleados" type="button">Empleados</button></li>
    <?php endif; ?>
    <li class="nav-item"><button class="nav-link <?= tabActive('asistencia',$tab) ?>" data-bs-toggle="tab" data-bs-target="#asistencia" type="button">Asistencia</button></li>
    <li class="nav-item"><button class="nav-link <?= tabActive('movimientos',$tab) ?>" data-bs-toggle="tab" data-bs-target="#movimientos" type="button">Legajo</button></li>
    <?php if (!$is_rrhh_only): ?>
    <li class="nav-item"><button class="nav-link <?= tabActive('resumen',$tab) ?>" data-bs-toggle="tab" data-bs-target="#resumen" type="button">Resumen de Sueldos</button></li>
    <li class="nav-item"><button class="nav-link <?= tabActive('nomina',$tab) ?>" data-bs-toggle="tab" data-bs-target="#nomina" type="button">Nómina</button></li>
    <li class="nav-item"><button class="nav-link <?= tabActive('periodos',$tab) ?>" data-bs-toggle="tab" data-bs-target="#periodos" type="button">Períodos</button></li>
    <?php endif; ?>
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
            <div class="mb-2"><label class="form-label">DNI</label><input type="text" name="dni" class="form-control"></div>
            <div class="mb-2"><label class="form-label">Teléfono</label><input type="tel" name="telefono" class="form-control"></div>
            <div class="mb-2"><label class="form-label">Domicilio</label><input type="text" name="domicilio" class="form-control"></div>
            <div class="mb-2"><label class="form-label">Fecha Contratación *</label><input type="date" name="fecha_contratacion" class="form-control" required></div>
            
            <hr class="my-3">
            <div class="alert alert-info small mb-3">Especifica al menos un tipo de pago (por hora, semanal o mensual)</div>
            
            <div class="mb-2"><label class="form-label">Pago por Hora</label><input type="number" step="0.01" min="0" name="pago_por_hora" class="form-control" placeholder="0.00"></div>
            <div class="mb-2"><label class="form-label">Pago Semanal</label><input type="number" step="0.01" min="0" name="pago_semanal" class="form-control" placeholder="0.00"></div>
            <div class="mb-2"><label class="form-label">Pago Mensual</label><input type="number" step="0.01" min="0" name="pago_mensual" class="form-control" placeholder="0.00"></div>
            
            <hr class="my-3">
            <div class="mb-2"><label><input type="checkbox" name="suspendido" value="1"> Suspendido</label></div>
            <div class="mb-2"><label class="form-label">Inicio Suspensión</label><input type="date" name="fecha_inicio_suspension" class="form-control"></div>
            <div class="mb-2"><label class="form-label">Fin Suspensión</label><input type="date" name="fecha_fin_suspension" class="form-control"></div>
            <div class="mb-3"><label class="form-label">Motivo Suspensión</label><textarea name="motivo_suspension" class="form-control" rows="2"></textarea></div>
            
            <div class="mb-2"><label><input type="checkbox" name="en_licencia_medica" value="1"> Licencia Médica</label></div>
            <div class="mb-2"><label class="form-label">Inicio Licencia</label><input type="date" name="fecha_inicio_licencia" class="form-control"></div>
            <div class="mb-2"><label class="form-label">Fin Licencia</label><input type="date" name="fecha_fin_licencia" class="form-control"></div>
            <div class="mb-3"><label class="form-label">Motivo Licencia</label><textarea name="motivo_licencia" class="form-control" rows="2"></textarea></div>
            
            <div class="d-grid"><button type="submit" class="btn btn-primary">Guardar</button></div>
          </form>
        </div>

        <div class="col-md-7">
          <h6>Empleados Activos</h6>
          <div class="table-responsive">
            <table class="table table-sm">
              <thead class="table-light">
                <tr>
                  <th>Nombre</th>
                  <th>Pago/Hr</th>
                  <th>Pago Sem.</th>
                  <th>Pago Mens.</th>
                  <th>Estado</th>
                  <th>Acción</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!$employees): ?>
                  <tr><td colspan="6" class="text-center text-muted">Sin empleados</td></tr>
                <?php else: foreach ($employees as $e): 
                  $estado = '';
                  if ($e['suspendido']) $estado = '<span class="badge bg-danger">Suspendido</span>';
                  elseif ($e['en_licencia_medica']) $estado = '<span class="badge bg-warning">Licencia</span>';
                  else $estado = '<span class="badge bg-success">Activo</span>';
                ?>
                  <tr>
                    <td><?= e($e['nombre'] . ' ' . $e['apellido']) ?></td>
                    <td><?= $e['pago_por_hora'] > 0 ? money($e['pago_por_hora']) : '—' ?></td>
                    <td><?= $e['pago_semanal'] > 0 ? money($e['pago_semanal']) : '—' ?></td>
                    <td><?= $e['pago_mensual'] > 0 ? money($e['pago_mensual']) : '—' ?></td>
                    <td><?= $estado ?></td>
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
            <div class="mb-2"><label class="form-label">Ingreso Mañana</label><input type="time" name="ingreso_manana" class="form-control"></div>
            <div class="mb-2"><label class="form-label">Ingreso Tarde</label><input type="time" name="ingreso_tarde" class="form-control"></div>
            <div class="mb-2"><label class="form-label">Horas Extra</label><input type="number" step="0.25" min="0" name="horas_extras" class="form-control" placeholder="0"></div>
            <div class="mb-2"><label><input type="hidden" name="presente" value="0"><input type="checkbox" name="presente" value="1" checked> Presente</label></div>
            <div class="mb-3"><label class="form-label">Notas</label><textarea name="notas" class="form-control" rows="2"></textarea></div>
            <div class="d-grid"><button type="submit" class="btn btn-primary">Registrar</button></div>
          </form>
        </div>

        <div class="col-md-7">
          <h6>Últimas Asistencias</h6>
          <div class="table-responsive">
            <table class="table table-sm align-middle">
              <thead class="table-light"><tr><th>Empleado</th><th>Fecha</th><th>Ingreso Mañana</th><th>Ingreso Tarde</th><th>Horas Extra</th><th>Presente</th><th>Notas</th><th></th></tr></thead>
              <tbody>
                <?php if (!$attendance_list): ?>
                  <tr><td colspan="8" class="text-center text-muted py-3">Sin registros</td></tr>
                <?php else: foreach ($attendance_list as $a): $formId = 'att-' . (int)$a['id']; ?>
                  <tr>
                    <td><?= e($a['empleado']) ?></td>
                    <td><?= e($a['fecha']) ?></td>
                    <td><input type="time" name="ingreso_manana" form="<?= $formId ?>" class="form-control form-control-sm" value="<?= $a['ingreso_manana'] ? e(substr($a['ingreso_manana'], 0, 5)) : '' ?>"></td>
                    <td><input type="time" name="ingreso_tarde" form="<?= $formId ?>" class="form-control form-control-sm" value="<?= $a['ingreso_tarde'] ? e(substr($a['ingreso_tarde'], 0, 5)) : '' ?>"></td>
                    <td><input type="number" step="0.25" min="0" name="horas_extras" form="<?= $formId ?>" class="form-control form-control-sm" value="<?= e((string)($a['horas_extras'] ?? '0')) ?>"></td>
                    <td class="text-center">
                      <input type="hidden" name="presente" value="0" form="<?= $formId ?>">
                      <input type="checkbox" name="presente" value="1" form="<?= $formId ?>" <?= $a['presente'] ? 'checked' : '' ?>>
                    </td>
                    <td><input type="text" name="notas" form="<?= $formId ?>" class="form-control form-control-sm" value="<?= e($a['notas'] ?? '') ?>" placeholder="Notas"></td>
                    <td class="text-nowrap">
                      <form id="<?= $formId ?>" method="post">
                        <input type="hidden" name="action" value="registrar_asistencia">
                        <input type="hidden" name="employee_id" value="<?= (int)$a['employee_id'] ?>">
                        <input type="hidden" name="fecha" value="<?= e($a['fecha']) ?>">
                        <button type="submit" class="btn btn-sm btn-outline-primary">Guardar</button>
                      </form>
                    </td>
                  </tr>
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
        <div class="alert alert-info">Selecciona un empleado para ver su legajo</div>
      <?php else: ?>
        <?php if ($selected_employee): ?>
          <div class="card mb-3">
            <div class="card-body small">
              <h6 class="mb-2">Datos del empleado</h6>
              <div class="row g-2">
                <div class="col-md-6">
                  <div><strong>Nombre:</strong> <?= e($selected_employee['nombre'] . ' ' . $selected_employee['apellido']) ?></div>
                  <div><strong>DNI:</strong> <?= e($selected_employee['dni'] ?? '—') ?></div>
                  <div><strong>Teléfono:</strong> <?= e($selected_employee['telefono'] ?? '—') ?></div>
                  <div><strong>Domicilio:</strong> <?= e($selected_employee['domicilio'] ?? '—') ?></div>
                  <div><strong>Fecha Contratación:</strong> <?= e($selected_employee['fecha_contratacion'] ?? '—') ?></div>
                </div>
                <div class="col-md-6">
                  <div><strong>Pago por Hora:</strong> <?= $selected_employee['pago_por_hora'] > 0 ? money($selected_employee['pago_por_hora']) : '—' ?></div>
                  <div><strong>Pago Semanal:</strong> <?= $selected_employee['pago_semanal'] > 0 ? money($selected_employee['pago_semanal']) : '—' ?></div>
                  <div><strong>Pago Mensual:</strong> <?= $selected_employee['pago_mensual'] > 0 ? money($selected_employee['pago_mensual']) : '—' ?></div>
                  <?php if ($selected_employee['suspendido']): ?>
                    <div class="mt-2"><span class="badge bg-danger">SUSPENDIDO</span></div>
                    <div><strong>Desde:</strong> <?= e($selected_employee['fecha_inicio_suspension'] ?? '—') ?></div>
                    <div><strong>Hasta:</strong> <?= e($selected_employee['fecha_fin_suspension'] ?? '—') ?></div>
                    <div><strong>Motivo:</strong> <?= e($selected_employee['motivo_suspension'] ?? '—') ?></div>
                  <?php endif; ?>
                  <?php if ($selected_employee['en_licencia_medica']): ?>
                    <div class="mt-2"><span class="badge bg-warning">LICENCIA MÉDICA</span></div>
                    <div><strong>Desde:</strong> <?= e($selected_employee['fecha_inicio_licencia'] ?? '—') ?></div>
                    <div><strong>Hasta:</strong> <?= e($selected_employee['fecha_fin_licencia'] ?? '—') ?></div>
                    <div><strong>Motivo:</strong> <?= e($selected_employee['motivo_licencia'] ?? '—') ?></div>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-3">
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

            <div class="col-md-3">
              <h6>Registrar Adelanto</h6>
              <form method="post" class="border rounded p-3 bg-light">
                <input type="hidden" name="action" value="registrar_adelanto">
                <input type="hidden" name="employee_id" value="<?= (int)$selected_emp_id ?>">
                <div class="mb-2"><label class="form-label">Monto *</label><input type="number" step="0.01" min="0.01" name="monto" class="form-control" required></div>
                <div class="mb-3"><label class="form-label">Razón</label><textarea name="razon" class="form-control" rows="3"></textarea></div>
                <div class="d-grid"><button type="submit" class="btn btn-warning btn-sm">Registrar</button></div>
              </form>
            </div>

            <div class="col-md-3">
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

            <div class="col-md-3">
              <h6>Registrar Incidencia</h6>
              <form method="post" class="border rounded p-3 bg-light">
                <input type="hidden" name="action" value="registrar_incidencia">
                <input type="hidden" name="employee_id" value="<?= (int)$selected_emp_id ?>">
                <div class="mb-2"><label class="form-label">Tipo</label>
                  <select name="tipo" class="form-select">
                    <option value="FALTA_INJUSTIFICADA">Falta Injustificada</option>
                    <option value="COMPORTAMIENTO">Comportamiento</option>
                    <option value="INCUMPLIMIENTO">Incumplimiento</option>
                    <option value="RETRASO">Retraso</option>
                    <option value="OTRO">Otro</option>
                  </select>
                </div>
                <div class="mb-2"><label class="form-label">Gravedad</label>
                  <select name="gravedad" class="form-select">
                    <option value="LEVE">Leve</option>
                    <option value="MODERADA">Moderada</option>
                    <option value="GRAVE">Grave</option>
                  </select>
                </div>
                <div class="mb-2"><label class="form-label">Fecha *</label><input type="date" name="fecha" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
                <div class="mb-3"><label class="form-label">Descripción *</label><textarea name="descripcion" class="form-control" rows="2" required></textarea></div>
                <div class="d-grid"><button type="submit" class="btn btn-secondary btn-sm">Registrar</button></div>
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
                <?php else: foreach ($movimientos as $m): 
                  $badge_color = 'secondary';
                  if ($m['tipo'] === 'DESCUENTO') $badge_color = 'danger';
                  elseif ($m['tipo'] === 'ADELANTO') $badge_color = 'warning';
                  elseif ($m['tipo'] === 'PAGO') $badge_color = 'success';
                  elseif ($m['tipo'] === 'PRESTAMO') $badge_color = 'info';
                  elseif ($m['tipo'] === 'INCIDENCIA') {
                    $gravedad = $m['gravedad'] ?? 'LEVE';
                    if ($gravedad === 'GRAVE') $badge_color = 'dark';
                    elseif ($gravedad === 'MODERADA') $badge_color = 'warning text-dark';
                    else $badge_color = 'secondary';
                  }
                ?>
                  <tr>
                    <td><span class="badge bg-<?= $badge_color ?>"><?= e($m['tipo']) ?></span></td>
                    <td><?= e($m['fecha']) ?></td>
                    <td><?= $m['tipo'] === 'INCIDENCIA' ? '—' : money($m['monto']) ?></td>
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
              <th>Horas Extras</th>
              <th>Pago H.E.</th>
              <th>Descuentos</th>
              <th>Adelantos</th>
              <th>Cuota Préstamo</th>
              <th>Estado</th>
              <th class="text-end">Saldo a Pagar</th>
              <th>Acción</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$resumen_empleados): ?>
              <tr><td colspan="10" class="text-center text-muted py-3">Sin empleados</td></tr>
            <?php else: foreach ($resumen_empleados as $emp): 
              $estado_badge = '';
              if ($emp['suspendido']) $estado_badge = '<span class="badge bg-danger">Suspendido</span>';
              elseif ($emp['en_licencia']) $estado_badge = '<span class="badge bg-warning">Licencia</span>';
              else $estado_badge = '<span class="badge bg-success">Activo</span>';
            ?>
              <tr>
                <td><?= e($emp['nombre_completo']) ?></td>
                <td><?= money($emp['sueldo_base_semanal']) ?></td>
                <td><?= number_format($emp['horas_extras'], 2) ?> hs</td>
                <td><?= money($emp['pago_horas_extras']) ?></td>
                <td><?= money($emp['descuentos']) ?></td>
                <td><?= money($emp['adelantos']) ?></td>
                <td><?= money($emp['cuota_prestamo']) ?></td>
                <td><?= $estado_badge ?></td>
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
              <th>Horas Extras</th>
              <th>Pago H.E.</th>
              <th>Descuentos</th>
              <th>Adelantos</th>
              <th>Cuota Préstamo</th>
              <th>Estado</th>
              <th class="text-end">Saldo a Pagar</th>
              <th>Acción</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$resumen_empleados): ?>
              <tr><td colspan="10" class="text-center text-muted py-3">Sin empleados</td></tr>
            <?php else: foreach ($resumen_empleados as $emp): 
              $estado_badge = '';
              if ($emp['suspendido']) $estado_badge = '<span class="badge bg-danger">Suspendido</span>';
              elseif ($emp['en_licencia']) $estado_badge = '<span class="badge bg-warning">Licencia</span>';
              else $estado_badge = '<span class="badge bg-success">Activo</span>';
            ?>
              <tr>
                <td><?= e($emp['nombre_completo']) ?></td>
                <td><?= money($emp['sueldo_base_semanal']) ?></td>
                <td><?= number_format($emp['horas_extras'], 2) ?> hs</td>
                <td><?= money($emp['pago_horas_extras']) ?></td>
                <td><?= money($emp['descuentos']) ?></td>
                <td><?= money($emp['adelantos']) ?></td>
                <td><?= money($emp['cuota_prestamo']) ?></td>
                <td><?= $estado_badge ?></td>
                <td class="text-end fw-semibold" style="color: <?= $emp['saldo'] >= 0 ? 'green' : 'red' ?>"><?= money($emp['saldo']) ?></td>
                <td>
                  <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#pagarModal" 
                    data-empid="<?= (int)$emp['id'] ?>" 
                    data-empname="<?= e($emp['nombre_completo']) ?>" 
                    data-sueldo="<?= (float)$emp['sueldo_base_semanal'] ?>" 
                    data-horasextras="<?= (float)$emp['pago_horas_extras'] ?>"
                    data-descuentos="<?= (float)$emp['descuentos'] ?>" 
                    data-adelantos="<?= (float)$emp['adelantos'] ?>" 
                    data-prestamos="<?= (float)$emp['cuota_prestamo'] ?>" 
                    data-saldo="<?= (float)$emp['saldo'] ?>"
                    <?= ($emp['suspendido'] || $emp['en_licencia']) ? 'disabled title="Empleado no disponible para pago"' : '' ?>>
                    PAGAR
                  </button>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
          <?php if ($resumen_empleados): 
            $total_saldo = array_sum(array_column($resumen_empleados, 'saldo'));
          ?>
          <tfoot class="table-light fw-bold">
            <tr>
              <td colspan="8">TOTAL A PAGAR</td>
              <td class="text-end" style="color: <?= $total_saldo >= 0 ? 'green' : 'red' ?>"><?= money($total_saldo) ?></td>
              <td></td>
            </tr>
          </tfoot>
          <?php endif; ?>
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
          <div class="mb-2">Horas Extras: <span id="modal_he_text"></span></div>
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
      var horasExtras = parseFloat(button.getAttribute('data-horasextras') || 0);
      var desc = parseFloat(button.getAttribute('data-descuentos') || 0);
      var adel = parseFloat(button.getAttribute('data-adelantos') || 0);
      var prest = parseFloat(button.getAttribute('data-prestamos') || 0);
      var saldo = parseFloat(button.getAttribute('data-saldo') || 0);

      document.getElementById('modal_employee_id').value = empId;
      document.getElementById('modal_empname').textContent = empName;
      document.getElementById('modal_sueldo_base').value = sueldo + horasExtras; // Include HE in base
      document.getElementById('modal_descuentos').value = desc;
      document.getElementById('modal_adelantos').value = adel;
      document.getElementById('modal_prestamos').value = prest;

      document.getElementById('modal_sueldo_text').textContent = sueldo.toFixed(2);
      document.getElementById('modal_he_text').textContent = horasExtras.toFixed(2);
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

    <!-- PERÍODOS -->
    <div class="tab-pane fade <?= paneActive('periodos',$tab) ?>" id="periodos">
      <h6>Gestión de Períodos de Pago</h6>
      
      <?php 
      $current_period = get_active_period();
      $all_periods = db()->query("SELECT * FROM payroll_periods ORDER BY fecha_inicio DESC LIMIT 20")->fetchAll();
      ?>
      
      <?php if ($current_period): ?>
        <div class="alert alert-info">
          <strong>Período Actual (ACTIVO):</strong> 
          <?= e($current_period['fecha_inicio']) ?> al <?= e($current_period['fecha_fin']) ?>
          <form method="post" class="d-inline float-end" onsubmit="return confirm('¿Está seguro de cerrar el período actual? Esto calculará los saldos pendientes y creará un nuevo período.');">
            <input type="hidden" name="action" value="cerrar_periodo">
            <button type="submit" class="btn btn-sm btn-danger">Cerrar Período y Crear Nuevo</button>
          </form>
        </div>
      <?php else: ?>
        <div class="alert alert-warning">
          No hay un período activo. Se creará automáticamente al registrar un pago.
        </div>
      <?php endif; ?>

      <div class="card mt-3">
        <div class="card-body p-0">
          <table class="table table-sm mb-0">
            <thead class="table-light">
              <tr>
                <th>ID</th>
                <th>Fecha Inicio</th>
                <th>Fecha Fin</th>
                <th>Estado</th>
                <th>Fecha Cierre</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$all_periods): ?>
                <tr><td colspan="5" class="text-center text-muted py-3">No hay períodos registrados</td></tr>
              <?php else: foreach ($all_periods as $p): ?>
                <tr>
                  <td><?= (int)$p['id'] ?></td>
                  <td><?= e($p['fecha_inicio']) ?></td>
                  <td><?= e($p['fecha_fin']) ?></td>
                  <td><span class="badge bg-<?= $p['estado'] === 'ACTIVO' ? 'success' : 'secondary' ?>"><?= e($p['estado']) ?></span></td>
                  <td><?= $p['closed_at'] ? e($p['closed_at']) : '—' ?></td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="alert alert-secondary mt-3 small">
        <strong>Cómo funcionan los períodos:</strong>
        <ul class="mb-0">
          <li>Cada semana es un período independiente de pago</li>
          <li>Los pagos dentro del mismo período no duplican saldos</li>
          <li>Al cerrar un período, los saldos pendientes se transfieren al siguiente</li>
          <li>Un período debe cerrarse antes de que los saldos pasen a la próxima semana</li>
        </ul>
      </div>
    </div>

  </div>
</div>

<?php include __DIR__ . '/../views/partials/footer.php'; ?>
