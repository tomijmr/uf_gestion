-- Migración nómina/asistencias/legajo
-- Ejecutar en phpMyAdmin

-- Employees: columnas de pagos, estado y saldo
ALTER TABLE employees ADD COLUMN IF NOT EXISTS saldo_pendiente DECIMAL(12,2) DEFAULT 0;
ALTER TABLE employees ADD COLUMN IF NOT EXISTS pago_por_hora DECIMAL(10,2) DEFAULT 0;
ALTER TABLE employees ADD COLUMN IF NOT EXISTS pago_semanal DECIMAL(10,2) DEFAULT 0;
ALTER TABLE employees ADD COLUMN IF NOT EXISTS pago_mensual DECIMAL(10,2) DEFAULT 0;
ALTER TABLE employees ADD COLUMN IF NOT EXISTS suspendido TINYINT(1) DEFAULT 0;
ALTER TABLE employees ADD COLUMN IF NOT EXISTS fecha_inicio_suspension DATE NULL;
ALTER TABLE employees ADD COLUMN IF NOT EXISTS fecha_fin_suspension DATE NULL;
ALTER TABLE employees ADD COLUMN IF NOT EXISTS motivo_suspension TEXT NULL;
ALTER TABLE employees ADD COLUMN IF NOT EXISTS en_licencia_medica TINYINT(1) DEFAULT 0;
ALTER TABLE employees ADD COLUMN IF NOT EXISTS fecha_inicio_licencia DATE NULL;
ALTER TABLE employees ADD COLUMN IF NOT EXISTS fecha_fin_licencia DATE NULL;
ALTER TABLE employees ADD COLUMN IF NOT EXISTS motivo_licencia TEXT NULL;

-- Asistencia: columnas de turnos y horas
ALTER TABLE employee_attendance ADD COLUMN IF NOT EXISTS ingreso_manana TIME NULL;
ALTER TABLE employee_attendance ADD COLUMN IF NOT EXISTS ingreso_tarde TIME NULL;
ALTER TABLE employee_attendance ADD COLUMN IF NOT EXISTS horario_entrada VARCHAR(5) NULL;
ALTER TABLE employee_attendance ADD COLUMN IF NOT EXISTS turno VARCHAR(10) NULL;
ALTER TABLE employee_attendance ADD COLUMN IF NOT EXISTS horas_extras DECIMAL(5,2) DEFAULT 0;

-- Índice único por turno (opcional, si usas registros por turno)
-- Si ya existe, phpMyAdmin puede marcar error; podés comentarlo.
CREATE UNIQUE INDEX uq_employee_attendance_turno ON employee_attendance (employee_id, fecha, turno);

-- Incidencias
CREATE TABLE IF NOT EXISTS employee_incidents (
  id INT AUTO_INCREMENT PRIMARY KEY,
  employee_id INT NOT NULL,
  fecha DATE NOT NULL,
  tipo VARCHAR(100) DEFAULT 'OTRO',
  gravedad VARCHAR(20) DEFAULT 'LEVE',
  descripcion TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  created_by INT NULL,
  FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
);

-- Períodos de pago
CREATE TABLE IF NOT EXISTS payroll_periods (
  id INT AUTO_INCREMENT PRIMARY KEY,
  fecha_inicio DATE NOT NULL,
  fecha_fin DATE NOT NULL,
  estado VARCHAR(20) DEFAULT 'ACTIVO',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  closed_at TIMESTAMP NULL,
  closed_by INT NULL
);

-- Nómina: columnas de períodos y saldo anterior
ALTER TABLE employee_payroll ADD COLUMN IF NOT EXISTS period_id INT NULL;
ALTER TABLE employee_payroll ADD COLUMN IF NOT EXISTS saldo_periodo_anterior DECIMAL(12,2) DEFAULT 0;
