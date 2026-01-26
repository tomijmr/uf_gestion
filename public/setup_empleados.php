<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';

$mensaje = '';
$error = '';

try {
  // Verificar conexión a BD
  $test = db()->query("SELECT 1")->fetch();
  if (!$test) throw new Exception("No hay conexión a la base de datos");

  // 1. Crear tabla employees
  db()->exec("CREATE TABLE IF NOT EXISTS employees (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(255) NOT NULL,
    apellido VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE,
    telefono VARCHAR(20),
    dni VARCHAR(20) UNIQUE,
    fecha_nacimiento DATE,
    domicilio VARCHAR(255),
    ciudad VARCHAR(100),
    provincia VARCHAR(100),
    codigo_postal VARCHAR(10),
    fecha_contratacion DATE NOT NULL,
    sueldo_base_semanal DECIMAL(12,2) NOT NULL,
    puesto VARCHAR(100),
    estado ENUM('ACTIVO','INACTIVO') DEFAULT 'ACTIVO',
    activo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
  $mensaje .= "✓ Tabla employees creada<br>";

  // 2. Crear tabla employee_attendance
  db()->exec("CREATE TABLE IF NOT EXISTS employee_attendance (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    fecha DATE NOT NULL,
    hora_entrada TIME,
    hora_salida TIME,
    presente TINYINT(1) DEFAULT 1,
    justificado TINYINT(1) DEFAULT 0,
    notas VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    UNIQUE KEY unique_attendance (employee_id, fecha)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
  $mensaje .= "✓ Tabla employee_attendance creada<br>";

  // 3. Crear tabla employee_discounts
  db()->exec("CREATE TABLE IF NOT EXISTS employee_discounts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    tipo ENUM('FALTA','LLEGADA_TARDE','OTRO') NOT NULL,
    fecha DATE NOT NULL,
    minutos_descuento INT DEFAULT 0,
    monto_descuento DECIMAL(12,2) DEFAULT 0,
    razon VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
  $mensaje .= "✓ Tabla employee_discounts creada<br>";

  // 4. Crear tabla employee_loans
  db()->exec("CREATE TABLE IF NOT EXISTS employee_loans (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    monto_solicitado DECIMAL(12,2) NOT NULL,
    monto_aprobado DECIMAL(12,2),
    fecha_solicitud DATE NOT NULL,
    fecha_aprobacion DATE,
    estado ENUM('SOLICITADO','APROBADO','RECHAZADO','PAGADO') DEFAULT 'SOLICITADO',
    razon VARCHAR(255),
    cuotas_cantidad INT DEFAULT 1,
    cuotas_pagadas INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
  $mensaje .= "✓ Tabla employee_loans creada<br>";

  // 5. Crear tabla employee_advances
  db()->exec("CREATE TABLE IF NOT EXISTS employee_advances (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    monto DECIMAL(12,2) NOT NULL,
    fecha_solicitud DATE NOT NULL,
    fecha_aprobacion DATE,
    estado ENUM('SOLICITADO','APROBADO','RECHAZADO','DESCONTADO') DEFAULT 'SOLICITADO',
    razon VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
  $mensaje .= "✓ Tabla employee_advances creada<br>";

  // 6. Crear tabla employee_payroll
  db()->exec("CREATE TABLE IF NOT EXISTS employee_payroll (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    fecha_pago DATE NOT NULL,
    semana_inicio DATE,
    semana_fin DATE,
    sueldo_base DECIMAL(12,2) NOT NULL,
    descuentos_total DECIMAL(12,2) DEFAULT 0,
    adelantos_total DECIMAL(12,2) DEFAULT 0,
    prestamos_cuota DECIMAL(12,2) DEFAULT 0,
    sueldo_neto DECIMAL(12,2) NOT NULL,
    medio_pago VARCHAR(50),
    estado ENUM('PENDIENTE','PAGADO','ANULADO') DEFAULT 'PENDIENTE',
    notas VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
  $mensaje .= "✓ Tabla employee_payroll creada<br>";

  $mensaje .= "<br><strong>¡Todas las tablas fueron creadas exitosamente!</strong>";

} catch (Throwable $e) {
  $error = 'Error: ' . $e->getMessage();
}

?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Setup Empleados</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container py-5">
  <h2>Setup de Módulo Empleados</h2>
  
  <?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  
  <?php if ($mensaje): ?>
    <div class="alert alert-success"><?= $mensaje ?></div>
  <?php endif; ?>

  <div class="mt-4">
    <a href="empleados.php" class="btn btn-primary">Ir a Empleados</a>
  </div>
</div>
</body>
</html>
