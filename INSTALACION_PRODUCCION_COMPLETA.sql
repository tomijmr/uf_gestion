-- ============================================
-- SISTEMA DE PRODUCCIÓN AVANZADO - SQL COMPLETO
-- Universal Fitness SA
-- Fecha: 4 de febrero de 2026
-- ============================================

-- Este archivo contiene TODAS las consultas SQL necesarias
-- para que el sistema de producción avanzado funcione correctamente.

-- ============================================
-- 1. TABLAS PRINCIPALES DEL SISTEMA DE PRODUCCIÓN
-- ============================================

-- Tabla: production_states (Historial de estados de cada OP)
CREATE TABLE IF NOT EXISTS production_states (
  id INT AUTO_INCREMENT PRIMARY KEY,
  production_order_id INT NOT NULL,
  estado VARCHAR(30) NOT NULL COMMENT 'SELECCION, CORTE, ARMADO, SOLDADURA, LIMPIEZA, PINTURA, ENSAMBLE, QC_FINAL, DESPACHO',
  operario_id INT NULL COMMENT 'ID del empleado que realiza el cambio',
  timestamp_inicio DATETIME NOT NULL,
  timestamp_fin DATETIME NULL,
  notas TEXT NULL,
  aprobado_qc TINYINT(1) DEFAULT 0 COMMENT '1 si fue aprobado por QC',
  qc_aprobado_por INT NULL COMMENT 'User ID que aprobó el QC',
  INDEX idx_po_id (production_order_id),
  INDEX idx_estado (estado),
  INDEX idx_timestamps (timestamp_inicio, timestamp_fin),
  FOREIGN KEY (production_order_id) REFERENCES production_orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Historial de estados de producción';

-- Tabla: production_stock_movements (Movimientos de stock por producción)
CREATE TABLE IF NOT EXISTS production_stock_movements (
  id INT AUTO_INCREMENT PRIMARY KEY,
  production_state_id INT NOT NULL,
  production_order_id INT NOT NULL,
  product_id INT NOT NULL COMMENT 'Componente descontado',
  cantidad DECIMAL(10,2) NOT NULL,
  etapa VARCHAR(30) NOT NULL COMMENT 'CORTE, PINTURA, ENSAMBLE',
  timestamp_descuento DATETIME NOT NULL,
  observaciones TEXT NULL,
  INDEX idx_state (production_state_id),
  INDEX idx_po (production_order_id),
  INDEX idx_product (product_id),
  INDEX idx_etapa (etapa),
  FOREIGN KEY (production_state_id) REFERENCES production_states(id) ON DELETE CASCADE,
  FOREIGN KEY (production_order_id) REFERENCES production_orders(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Movimientos de stock por etapa de producción';

-- Tabla: production_component_stages (Configuración de componentes por etapa)
CREATE TABLE IF NOT EXISTS production_component_stages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  component_type VARCHAR(50) NOT NULL COMMENT 'PERFIL, CHAPA, TUBO, PINTURA, QUIMICO, RODAMIENTO, etc',
  etapa_stock VARCHAR(30) NOT NULL COMMENT 'CORTE, PINTURA, ENSAMBLE',
  nombre_display VARCHAR(100) NOT NULL,
  orden INT DEFAULT 0,
  activo TINYINT(1) DEFAULT 1,
  UNIQUE KEY unique_type_etapa (component_type, etapa_stock)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Configuración de qué componentes se descargan en cada etapa';

-- Datos iniciales para production_component_stages
INSERT INTO production_component_stages (component_type, etapa_stock, nombre_display, orden) VALUES
('PERFIL', 'CORTE', 'Perfiles metálicos', 1),
('CHAPA', 'CORTE', 'Chapas y láminas', 2),
('TUBO', 'CORTE', 'Tubos y caños', 3),
('PINTURA', 'PINTURA', 'Pinturas y esmaltes', 1),
('QUIMICO', 'PINTURA', 'Químicos y diluyentes', 2),
('RODAMIENTO', 'ENSAMBLE', 'Rodamientos', 1),
('POLEA', 'ENSAMBLE', 'Poleas', 2),
('TORNILLERIA', 'ENSAMBLE', 'Tornillería y fijaciones', 3),
('TAPIZADO', 'ENSAMBLE', 'Tapizados y textiles', 4)
ON DUPLICATE KEY UPDATE nombre_display=VALUES(nombre_display);

-- Tabla: production_tickets (Registro de tickets impresos)
CREATE TABLE IF NOT EXISTS production_tickets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  production_order_id INT NOT NULL,
  qr_token VARCHAR(100) NULL COMMENT 'Token único del QR',
  url_qr TEXT NULL COMMENT 'URL completa del QR',
  estado_ticket VARCHAR(30) NULL COMMENT 'Estado para el que se imprimió el ticket',
  impreso_por INT NULL COMMENT 'User ID',
  fecha_impresion DATETIME NOT NULL,
  INDEX idx_po (production_order_id),
  INDEX idx_estado (estado_ticket),
  FOREIGN KEY (production_order_id) REFERENCES production_orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Historial de tickets impresos';

-- ============================================
-- 2. MODIFICACIONES A TABLA production_orders
-- ============================================

-- Verificar y agregar columnas necesarias a production_orders
-- (Usar ALTER TABLE ... ADD COLUMN IF NOT EXISTS no funciona en todas las versiones de MySQL)

-- Columna: estado_actual
SET @exist := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
               WHERE TABLE_SCHEMA = DATABASE() 
               AND TABLE_NAME = 'production_orders' 
               AND COLUMN_NAME = 'estado_actual');
SET @sqlstmt := IF(@exist = 0, 
    'ALTER TABLE production_orders ADD COLUMN estado_actual VARCHAR(30) NULL COMMENT "Estado actual del sistema avanzado"',
    'SELECT "La columna estado_actual ya existe" AS mensaje');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Columna: color_personalizado
SET @exist := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
               WHERE TABLE_SCHEMA = DATABASE() 
               AND TABLE_NAME = 'production_orders' 
               AND COLUMN_NAME = 'color_personalizado');
SET @sqlstmt := IF(@exist = 0, 
    'ALTER TABLE production_orders ADD COLUMN color_personalizado VARCHAR(100) NULL COMMENT "Color especificado por el cliente"',
    'SELECT "La columna color_personalizado ya existe" AS mensaje');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Columna: tapizado_personalizado
SET @exist := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
               WHERE TABLE_SCHEMA = DATABASE() 
               AND TABLE_NAME = 'production_orders' 
               AND COLUMN_NAME = 'tapizado_personalizado');
SET @sqlstmt := IF(@exist = 0, 
    'ALTER TABLE production_orders ADD COLUMN tapizado_personalizado VARCHAR(100) NULL COMMENT "Tapizado especificado por el cliente"',
    'SELECT "La columna tapizado_personalizado ya existe" AS mensaje');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Columna: bloqueada_razon
SET @exist := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
               WHERE TABLE_SCHEMA = DATABASE() 
               AND TABLE_NAME = 'production_orders' 
               AND COLUMN_NAME = 'bloqueada_razon');
SET @sqlstmt := IF(@exist = 0, 
    'ALTER TABLE production_orders ADD COLUMN bloqueada_razon TEXT NULL COMMENT "Razón por la que está bloqueada (ej: falta stock)"',
    'SELECT "La columna bloqueada_razon ya existe" AS mensaje');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Columna: ticket_impreso
SET @exist := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
               WHERE TABLE_SCHEMA = DATABASE() 
               AND TABLE_NAME = 'production_orders' 
               AND COLUMN_NAME = 'ticket_impreso');
SET @sqlstmt := IF(@exist = 0, 
    'ALTER TABLE production_orders ADD COLUMN ticket_impreso TINYINT(1) DEFAULT 0 COMMENT "1 si ya se imprimió algún ticket"',
    'SELECT "La columna ticket_impreso ya existe" AS mensaje');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Columna: qr_code
SET @exist := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
               WHERE TABLE_SCHEMA = DATABASE() 
               AND TABLE_NAME = 'production_orders' 
               AND COLUMN_NAME = 'qr_code');
SET @sqlstmt := IF(@exist = 0, 
    'ALTER TABLE production_orders ADD COLUMN qr_code VARCHAR(100) NULL COMMENT "Token único para QR de escaneo rápido"',
    'SELECT "La columna qr_code ya existe" AS mensaje');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================
-- 3. VISTA OPTIMIZADA
-- ============================================

-- Vista: v_production_orders_estado (Para consultas rápidas)
CREATE OR REPLACE VIEW v_production_orders_estado AS
SELECT 
    po.*,
    p.codigo AS producto_codigo,
    p.nombre AS producto_nombre,
    o.customer_id,
    c.nombre AS cliente_nombre,
    ps.estado AS ultimo_estado,
    ps.timestamp_inicio AS ultimo_cambio,
    e.nombre AS ultimo_operario
FROM production_orders po
JOIN products p ON p.id = po.product_pt_id
LEFT JOIN orders o ON o.id = po.order_id
LEFT JOIN customers c ON c.id = o.customer_id
LEFT JOIN production_states ps ON ps.production_order_id = po.id 
    AND ps.id = (SELECT MAX(id) FROM production_states WHERE production_order_id = po.id)
LEFT JOIN employees e ON e.id = ps.operario_id;

-- ============================================
-- 4. COLUMNAS ADICIONALES (IVA, ETC)
-- ============================================

-- Columna: incluye_iva en tabla purchases
SET @exist := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
               WHERE TABLE_SCHEMA = DATABASE() 
               AND TABLE_NAME = 'purchases' 
               AND COLUMN_NAME = 'incluye_iva');
SET @sqlstmt := IF(@exist = 0, 
    'ALTER TABLE purchases ADD COLUMN incluye_iva TINYINT(1) DEFAULT 1 COMMENT "1=Con IVA, 0=Sin IVA"',
    'SELECT "La columna incluye_iva en purchases ya existe" AS mensaje');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Columna: incluye_iva en tabla orders
SET @exist := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
               WHERE TABLE_SCHEMA = DATABASE() 
               AND TABLE_NAME = 'orders' 
               AND COLUMN_NAME = 'incluye_iva');
SET @sqlstmt := IF(@exist = 0, 
    'ALTER TABLE orders ADD COLUMN incluye_iva TINYINT(1) DEFAULT 1 COMMENT "1=Con IVA, 0=Sin IVA"',
    'SELECT "La columna incluye_iva en orders ya existe" AS mensaje');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================
-- 5. ÍNDICES ADICIONALES PARA PERFORMANCE
-- ============================================

-- Índice para búsquedas por QR
SET @exist := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
               WHERE TABLE_SCHEMA = DATABASE() 
               AND TABLE_NAME = 'production_orders' 
               AND INDEX_NAME = 'idx_qr_code');
SET @sqlstmt := IF(@exist = 0, 
    'CREATE INDEX idx_qr_code ON production_orders(qr_code)',
    'SELECT "El índice idx_qr_code ya existe" AS mensaje');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Índice para búsquedas por estado_actual
SET @exist := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
               WHERE TABLE_SCHEMA = DATABASE() 
               AND TABLE_NAME = 'production_orders' 
               AND INDEX_NAME = 'idx_estado_actual');
SET @sqlstmt := IF(@exist = 0, 
    'CREATE INDEX idx_estado_actual ON production_orders(estado_actual)',
    'SELECT "El índice idx_estado_actual ya existe" AS mensaje');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================
-- 6. TABLA EMPLOYEES (SI NO EXISTE)
-- ============================================

-- Crear tabla employees si no existe
CREATE TABLE IF NOT EXISTS employees (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  apellido VARCHAR(100) NULL,
  documento VARCHAR(20) NULL,
  email VARCHAR(100) NULL,
  telefono VARCHAR(20) NULL,
  puesto VARCHAR(50) NULL COMMENT 'Operario, Supervisor, etc',
  fecha_ingreso DATE NULL,
  activo TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_activo (activo),
  INDEX idx_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Empleados y operarios';

-- Insertar algunos empleados de ejemplo si la tabla está vacía
INSERT INTO employees (nombre, puesto, activo)
SELECT 'Operario 1', 'Operario de Corte', 1
WHERE NOT EXISTS (SELECT 1 FROM employees LIMIT 1);

INSERT INTO employees (nombre, puesto, activo)
SELECT 'Operario 2', 'Operario de Soldadura', 1
WHERE NOT EXISTS (SELECT 1 FROM employees WHERE nombre = 'Operario 2');

INSERT INTO employees (nombre, puesto, activo)
SELECT 'Operario 3', 'Operario de Pintura', 1
WHERE NOT EXISTS (SELECT 1 FROM employees WHERE nombre = 'Operario 3');

INSERT INTO employees (nombre, puesto, activo)
SELECT 'Supervisor QC', 'Control de Calidad', 1
WHERE NOT EXISTS (SELECT 1 FROM employees WHERE nombre = 'Supervisor QC');

-- ============================================
-- FIN DEL SCRIPT
-- ============================================

-- Verificación final: mostrar todas las tablas creadas
SELECT 'TABLAS CREADAS/VERIFICADAS:' AS resultado;
SHOW TABLES LIKE 'production_%';

SELECT 'COLUMNAS AGREGADAS A production_orders:' AS resultado;
SELECT COLUMN_NAME, COLUMN_TYPE, COLUMN_COMMENT 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'production_orders'
  AND COLUMN_NAME IN ('estado_actual', 'color_personalizado', 'tapizado_personalizado', 
                      'bloqueada_razon', 'ticket_impreso', 'qr_code');

SELECT 'EMPLEADOS DISPONIBLES:' AS resultado;
SELECT id, nombre, puesto FROM employees WHERE activo = 1;

SELECT '✅ INSTALACIÓN COMPLETA - Sistema de Producción Avanzado listo para usar' AS resultado;
