-- ============================================
-- Script de Prueba - Sistema de Producción Avanzado
-- ============================================
-- Este script crea datos de prueba para validar el nuevo sistema

-- 1. Crear tabla employees si no existe
CREATE TABLE IF NOT EXISTS employees (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  activo TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Insertar empleados de prueba
INSERT INTO employees (nombre, activo) VALUES 
('Juan Pérez - Operario Corte', 1),
('María González - Operaria Armado', 1),
('Carlos Rodríguez - Operario Soldadura', 1),
('Ana Martínez - Operaria Pintura', 1),
('Luis Fernández - Operario Ensamble', 1),
('Patricia Silva - Control de Calidad', 1)
ON DUPLICATE KEY UPDATE nombre = nombre;

-- 3. Verificar que existen productos PT para crear OPs de prueba
-- Mostrar productos tipo PT disponibles
SELECT 'Productos PT Disponibles:' as '';
SELECT id, codigo, nombre, stock_actual 
FROM products 
WHERE tipo = 'PT' 
LIMIT 5;

-- 4. Verificar que existen componentes para BOM
SELECT 'Componentes disponibles para BOM:' as '';
SELECT id, codigo, nombre, tipo, stock_actual 
FROM products 
WHERE tipo = 'MP' 
ORDER BY codigo 
LIMIT 10;

-- 5. Crear componentes de prueba si no existen
INSERT INTO products (codigo, nombre, tipo, unidad, precio_compra, precio_venta, stock_actual, stock_minimo) VALUES
('PERF-001', 'Perfil Cuadrado 40x40', 'MP', 'mts', 150.00, 200.00, 100, 10),
('CH-001', 'Chapa Galvanizada 1mm', 'MP', 'unid', 500.00, 650.00, 50, 5),
('TUBO-001', 'Tubo Estructural 50mm', 'MP', 'mts', 180.00, 230.00, 80, 8),
('PINT-001', 'Pintura Epoxi Rojo', 'MP', 'litros', 350.00, 450.00, 30, 5),
('PINT-002', 'Pintura Epoxi Negro', 'MP', 'litros', 350.00, 450.00, 30, 5),
('ROD-001', 'Rodamiento 6204', 'MP', 'unid', 120.00, 160.00, 40, 10),
('TORN-001', 'Tornillo M8x20', 'MP', 'unid', 5.00, 8.00, 500, 100),
('TAPIZ-001', 'Tapizado Negro Premium', 'MP', 'mts', 250.00, 350.00, 20, 5)
ON DUPLICATE KEY UPDATE nombre = nombre;

-- 6. Mostrar resumen de empleados
SELECT 'Empleados Registrados:' as '';
SELECT id, nombre, activo FROM employees;

-- 7. Instrucciones para crear OP de prueba
SELECT '
============================================
INSTRUCCIONES PARA PRUEBA MANUAL
============================================

1. Acceder a public/op.php
2. Crear una nueva OP con una máquina (PT) que tenga BOM
3. Asegurarse de que el BOM incluya componentes con códigos como:
   - PERF-* (para probar descuento en CORTE)
   - PINT-* (para probar descuento en PINTURA)
   - ROD-* o TAPIZ-* (para probar descuento en ENSAMBLE)

4. Probar el flujo:
   a) Click en "Cambiar Estado" en la OP
   b) Seleccionar estado SELECCION, elegir operario
   c) Repetir para CORTE (se descuenta stock de perfiles/chapas)
   d) Continuar hasta ARMADO
   e) Click en "Ver Timeline", aprobar QC del estado ARMADO
   f) Avanzar a PINTURA (se descuenta stock de pinturas)
   g) Avanzar a ENSAMBLE (se descuenta stock de rodamientos/tapizados)
   h) Continuar hasta DESPACHO
   i) Click en "Imprimir Ticket" para ver el QR

5. Probar bloqueos:
   a) Crear OP sin stock suficiente → intentar CORTE (debe fallar)
   b) Intentar saltar de SELECCION a PINTURA (debe fallar)
   c) Intentar PINTURA sin QC en ARMADO (debe fallar)

============================================
' as INSTRUCCIONES;

-- 8. Query para verificar el estado del sistema después de pruebas
SELECT '
-- Para ver historial de estados de una OP:
SELECT ps.*, e.nombre as operario
FROM production_states ps
LEFT JOIN employees e ON e.id = ps.operario_id
WHERE ps.production_order_id = ?
ORDER BY ps.timestamp_inicio;

-- Para ver movimientos de stock:
SELECT psm.*, po.id as op_id, p.nombre as componente
FROM production_stock_movements psm
JOIN production_orders po ON po.id = psm.production_order_id
JOIN products p ON p.id = psm.component_id
ORDER BY psm.timestamp DESC;

-- Para ver OPs bloqueadas:
SELECT po.id, po.estado_actual, po.bloqueada_razon, p.nombre
FROM production_orders po
JOIN products p ON p.id = po.product_pt_id
WHERE po.bloqueada_razon IS NOT NULL;
' as QUERIES_UTILES;
