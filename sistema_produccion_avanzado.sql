-- ==================================================
-- SISTEMA DE PRODUCCIÓN AVANZADO
-- Flujo de estados con tracking de operarios y stock
-- ==================================================

-- 1. Tabla de Estados de Producción (tracking completo)
CREATE TABLE IF NOT EXISTS production_states (
    id INT AUTO_INCREMENT PRIMARY KEY,
    production_order_id INT NOT NULL,
    estado ENUM('SELECCION','CORTE','ARMADO','SOLDADURA','LIMPIEZA','PINTURA','ENSAMBLE','QC_FINAL','DESPACHO') NOT NULL,
    operario_id INT NULL,
    timestamp_inicio DATETIME NOT NULL,
    timestamp_fin DATETIME NULL,
    notas TEXT NULL,
    aprobado_qc TINYINT(1) DEFAULT 0 COMMENT 'QC aprobado antes de siguiente etapa',
    qc_aprobado_por INT NULL COMMENT 'User ID quien aprobó QC',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (production_order_id) REFERENCES production_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (operario_id) REFERENCES employees(id) ON DELETE SET NULL,
    FOREIGN KEY (qc_aprobado_por) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_po_estado (production_order_id, estado),
    INDEX idx_timestamp (timestamp_inicio, timestamp_fin)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Tabla de Movimientos de Stock por Etapa de Producción
CREATE TABLE IF NOT EXISTS production_stock_movements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    production_state_id INT NOT NULL,
    production_order_id INT NOT NULL,
    product_id INT NOT NULL COMMENT 'Componente MP consumido',
    cantidad DECIMAL(12,4) NOT NULL,
    etapa ENUM('CORTE','PINTURA','ENSAMBLE') NOT NULL,
    timestamp_descuento DATETIME NOT NULL,
    observaciones TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (production_state_id) REFERENCES production_states(id) ON DELETE CASCADE,
    FOREIGN KEY (production_order_id) REFERENCES production_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
    INDEX idx_po_etapa (production_order_id, etapa),
    INDEX idx_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Agregar columnas adicionales a production_orders
ALTER TABLE production_orders 
ADD COLUMN estado_actual ENUM('SELECCION','CORTE','ARMADO','SOLDADURA','LIMPIEZA','PINTURA','ENSAMBLE','QC_FINAL','DESPACHO','BLOQUEADA') NULL COMMENT 'Estado actual de la orden',
ADD COLUMN color_personalizado VARCHAR(100) NULL COMMENT 'Color seleccionado',
ADD COLUMN tapizado_personalizado VARCHAR(100) NULL COMMENT 'Tipo de tapizado',
ADD COLUMN bloqueada_razon TEXT NULL COMMENT 'Razón si está bloqueada por suministros',
ADD COLUMN ticket_impreso TINYINT(1) DEFAULT 0 COMMENT 'Si ya se imprimió el ticket',
ADD COLUMN qr_code VARCHAR(255) NULL COMMENT 'Token único para QR';

-- 4. Tabla de Configuración de Componentes por Etapa (mapeo BOM -> Etapa)
CREATE TABLE IF NOT EXISTS production_component_stages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    component_type VARCHAR(50) NOT NULL COMMENT 'Tipo de componente',
    etapa ENUM('CORTE','PINTURA','ENSAMBLE') NOT NULL,
    descripcion VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (component_type, etapa),
    INDEX idx_etapa (etapa)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Insertar configuración de etapas por defecto
INSERT INTO production_component_stages (component_type, etapa, descripcion) VALUES
('PERFIL', 'CORTE', 'Perfiles de metal para estructura'),
('CHAPA', 'CORTE', 'Chapas metálicas'),
('TUBO', 'CORTE', 'Tubos estructurales'),
('PINTURA', 'PINTURA', 'Pintura y acabados'),
('QUIMICO', 'PINTURA', 'Insumos químicos (limpieza, preparación)'),
('RODAMIENTO', 'ENSAMBLE', 'Rodamientos y bujes'),
('POLEA', 'ENSAMBLE', 'Poleas y sistemas de transmisión'),
('TORNILLERIA', 'ENSAMBLE', 'Tornillos, tuercas, arandelas'),
('TAPIZADO', 'ENSAMBLE', 'Tapizados y acolchados')
ON DUPLICATE KEY UPDATE descripcion=VALUES(descripcion);

-- 6. Tabla de Tickets de Producción (histórico de impresiones)
CREATE TABLE IF NOT EXISTS production_tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    production_order_id INT NOT NULL,
    qr_token VARCHAR(255) NOT NULL,
    url_qr TEXT NOT NULL COMMENT 'URL completa para el QR',
    impreso_por INT NULL,
    fecha_impresion DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (production_order_id) REFERENCES production_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (impreso_por) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY (qr_token),
    INDEX idx_po (production_order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Vista para estado actual de órdenes de producción
CREATE OR REPLACE VIEW v_production_orders_estado AS
SELECT 
    po.id,
    po.order_id,
    po.product_pt_id,
    po.cantidad,
    po.estado_actual,
    po.color_personalizado,
    po.tapizado_personalizado,
    po.bloqueada_razon,
    p.codigo,
    p.nombre AS producto_nombre,
    c.nombre AS cliente_nombre,
    ps.estado AS ultimo_estado_registrado,
    ps.timestamp_inicio AS ultimo_estado_inicio,
    ps.timestamp_fin AS ultimo_estado_fin,
    e.nombre AS operario_nombre,
    ps.aprobado_qc
FROM production_orders po
LEFT JOIN products p ON p.id = po.product_pt_id
LEFT JOIN orders o ON o.id = po.order_id
LEFT JOIN customers c ON c.id = o.customer_id
LEFT JOIN (
    SELECT ps1.*
    FROM production_states ps1
    WHERE ps1.timestamp_inicio = (
        SELECT MAX(ps2.timestamp_inicio)
        FROM production_states ps2
        WHERE ps2.production_order_id = ps1.production_order_id
    )
) ps ON ps.production_order_id = po.id
LEFT JOIN employees e ON e.id = ps.operario_id;

-- Índices adicionales para performance
CREATE INDEX idx_po_estado_actual ON production_orders(estado_actual);
CREATE INDEX idx_po_bloqueada ON production_orders(bloqueada_razon(100));
