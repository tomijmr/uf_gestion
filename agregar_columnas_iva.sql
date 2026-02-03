-- Agregar columnas para controlar si se aplica IVA o no

-- Tabla purchases: agregar columna incluye_iva
ALTER TABLE purchases 
ADD COLUMN incluye_iva TINYINT(1) DEFAULT 1 COMMENT '1=Con IVA, 0=Sin IVA';

-- Tabla orders: agregar columna incluye_iva
ALTER TABLE orders 
ADD COLUMN incluye_iva TINYINT(1) DEFAULT 1 COMMENT '1=Con IVA, 0=Sin IVA';
