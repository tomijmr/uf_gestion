-- ==========================================
-- SCRIPT DE MIGRACIÓN: DEV → PROD
-- Sincroniza todos los datos nuevos de DEV a PROD
-- ==========================================
-- Base de Desarrollo: erp_mvp (MariaDB 10.4.28)
-- Base de Producción: a0011086_erp_mvp (MySQL 8.0.44)
-- Fecha: 26-01-2026

-- ADVERTENCIA: Hacer BACKUP de ambas bases antes de ejecutar

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- ==========================================
-- 1. INSERTAR CLIENTES NUEVOS
-- ==========================================
INSERT INTO `a0011086_erp_mvp`.customers 
  (id, nombre, gym, cuit_dni, telefono, email, direccion, condicion_iva, limite_credito, notas, activo, created_at)
SELECT id, nombre, gym, cuit_dni, telefono, email, direccion, condicion_iva, limite_credito, notas, activo, created_at
FROM erp_mvp.customers dev
WHERE NOT EXISTS (
  SELECT 1 FROM `a0011086_erp_mvp`.customers prod WHERE prod.id = dev.id
);

-- ==========================================
-- 2. INSERTAR NUEVOS PRODUCTOS (si hay cambios)
-- ==========================================
-- Verificar cambios en productos existentes
UPDATE `a0011086_erp_mvp`.products prod
SET 
  codigo = (SELECT codigo FROM erp_mvp.products WHERE id = prod.id),
  nombre = (SELECT nombre FROM erp_mvp.products WHERE id = prod.id),
  tipo = (SELECT tipo FROM erp_mvp.products WHERE id = prod.id),
  unidad = (SELECT unidad FROM erp_mvp.products WHERE id = prod.id),
  costo_std = (SELECT costo_std FROM erp_mvp.products WHERE id = prod.id),
  precio_std = (SELECT precio_std FROM erp_mvp.products WHERE id = prod.id),
  stock_actual = (SELECT stock_actual FROM erp_mvp.products WHERE id = prod.id),
  stock_reservado = (SELECT stock_reservado FROM erp_mvp.products WHERE id = prod.id),
  stock_minimo = (SELECT stock_minimo FROM erp_mvp.products WHERE id = prod.id),
  margen_pct = (SELECT margen_pct FROM erp_mvp.products WHERE id = prod.id)
WHERE EXISTS (
  SELECT 1 FROM erp_mvp.products WHERE id = prod.id
);

-- ==========================================
-- 3. INSERTAR ÓRDENES NUEVAS
-- ==========================================
INSERT INTO `a0011086_erp_mvp`.orders
  (id, customer_id, fecha, fecha_entrega, estado, total_bruto, descuento, total_neto, senia, saldo, observaciones)
SELECT id, customer_id, fecha, fecha_entrega, estado, total_bruto, descuento, total_neto, senia, saldo, observaciones
FROM erp_mvp.orders dev
WHERE NOT EXISTS (
  SELECT 1 FROM `a0011086_erp_mvp`.orders prod WHERE prod.id = dev.id
);

-- ==========================================
-- 4. INSERTAR ITEMS DE ÓRDENES NUEVOS
-- ==========================================
INSERT INTO `a0011086_erp_mvp`.order_items
  (id, order_id, product_id, cant, precio_unit, subtotal)
SELECT id, order_id, product_id, cant, precio_unit, subtotal
FROM erp_mvp.order_items dev
WHERE NOT EXISTS (
  SELECT 1 FROM `a0011086_erp_mvp`.order_items prod WHERE prod.id = dev.id
);

-- ==========================================
-- 5. INSERTAR PAGOS NUEVOS
-- ==========================================
INSERT INTO `a0011086_erp_mvp`.payments
  (id, customer_id, order_id, fecha, medio, importe, referencia)
SELECT id, customer_id, order_id, fecha, medio, importe, referencia
FROM erp_mvp.payments dev
WHERE NOT EXISTS (
  SELECT 1 FROM `a0011086_erp_mvp`.payments prod WHERE prod.id = dev.id
);

-- ==========================================
-- 6. INSERTAR ÓRDENES DE PRODUCCIÓN NUEVAS
-- ==========================================
INSERT INTO `a0011086_erp_mvp`.production_orders
  (id, order_id, product_pt_id, cantidad, estado, fecha_ini, fecha_fin, observaciones)
SELECT id, order_id, product_pt_id, cantidad, estado, fecha_ini, fecha_fin, observaciones
FROM erp_mvp.production_orders dev
WHERE NOT EXISTS (
  SELECT 1 FROM `a0011086_erp_mvp`.production_orders prod WHERE prod.id = dev.id
);

-- ==========================================
-- 7. INSERTAR MOVIMIENTOS DE STOCK NUEVOS
-- ==========================================
INSERT INTO `a0011086_erp_mvp`.stock_moves
  (id, fecha, tipo, motivo, product_id, cantidad, referencia_tipo, referencia_id, observaciones)
SELECT id, fecha, tipo, motivo, product_id, cantidad, referencia_tipo, referencia_id, observaciones
FROM erp_mvp.stock_moves dev
WHERE NOT EXISTS (
  SELECT 1 FROM `a0011086_erp_mvp`.stock_moves prod WHERE prod.id = dev.id
);

-- ==========================================
-- 8. INSERTAR COMPRAS NUEVAS
-- ==========================================
INSERT INTO `a0011086_erp_mvp`.purchases
  (id, fecha, proveedor, comp_tipo, comp_serie, comp_numero, total, moneda, archivo_path, notas, created_by, created_at)
SELECT id, fecha, proveedor, comp_tipo, comp_serie, comp_numero, total, moneda, archivo_path, notas, created_by, created_at
FROM erp_mvp.purchases dev
WHERE NOT EXISTS (
  SELECT 1 FROM `a0011086_erp_mvp`.purchases prod WHERE prod.id = dev.id
);

-- ==========================================
-- 9. INSERTAR ITEMS DE COMPRAS NUEVOS
-- ==========================================
INSERT INTO `a0011086_erp_mvp`.purchase_items
  (id, purchase_id, product_id, codigo, nombre, unidad, cantidad, costo_unit, subtotal, notas)
SELECT id, purchase_id, product_id, codigo, nombre, unidad, cantidad, costo_unit, subtotal, notas
FROM erp_mvp.purchase_items dev
WHERE NOT EXISTS (
  SELECT 1 FROM `a0011086_erp_mvp`.purchase_items prod WHERE prod.id = dev.id
);

-- ==========================================
-- 10. INSERTAR REGISTROS DE CLIENTE NUEVOS
-- ==========================================
INSERT INTO `a0011086_erp_mvp`.customer_ledger
  (id, customer_id, fecha, tipo, origen, referencia_id, detalle, monto, saldo_resultante)
SELECT id, customer_id, fecha, tipo, origen, referencia_id, detalle, monto, saldo_resultante
FROM erp_mvp.customer_ledger dev
WHERE NOT EXISTS (
  SELECT 1 FROM `a0011086_erp_mvp`.customer_ledger prod WHERE prod.id = dev.id
);

-- ==========================================
-- 11. INSERTAR AUDITORÍA NUEVA
-- ==========================================
INSERT INTO `a0011086_erp_mvp`.audit_logs
  (id, user_id, fecha, accion, entidad, entidad_id, detalle)
SELECT id, user_id, fecha, accion, entidad, entidad_id, detalle
FROM erp_mvp.audit_logs dev
WHERE NOT EXISTS (
  SELECT 1 FROM `a0011086_erp_mvp`.audit_logs prod WHERE prod.id = dev.id
);

-- ==========================================
-- 12. INSERTAR GASTOS DE CAJA NUEVOS (si existen)
-- ==========================================
INSERT INTO `a0011086_erp_mvp`.cash_expenses
  (id, fecha, categoria, descripcion, medio, importe, created_by, created_at)
SELECT id, fecha, categoria, descripcion, medio, importe, created_by, created_at
FROM erp_mvp.cash_expenses dev
WHERE NOT EXISTS (
  SELECT 1 FROM `a0011086_erp_mvp`.cash_expenses prod WHERE prod.id = dev.id
);

-- ==========================================
-- 13. REPORTE DE RESUMEN ANTES DE COMMIT
-- ==========================================
SELECT '=== RESUMEN DE MIGRACION ===' as info;
SELECT CONCAT('Clientes nuevos migrados: ', COUNT(*)) FROM erp_mvp.customers WHERE NOT EXISTS (SELECT 1 FROM `a0011086_erp_mvp`.customers p WHERE p.id = erp_mvp.customers.id);
SELECT CONCAT('Órdenes nuevas migradas: ', COUNT(*)) FROM erp_mvp.orders WHERE NOT EXISTS (SELECT 1 FROM `a0011086_erp_mvp`.orders p WHERE p.id = erp_mvp.orders.id);
SELECT CONCAT('Items de orden nuevos: ', COUNT(*)) FROM erp_mvp.order_items WHERE NOT EXISTS (SELECT 1 FROM `a0011086_erp_mvp`.order_items p WHERE p.id = erp_mvp.order_items.id);
SELECT CONCAT('Pagos nuevos: ', COUNT(*)) FROM erp_mvp.payments WHERE NOT EXISTS (SELECT 1 FROM `a0011086_erp_mvp`.payments p WHERE p.id = erp_mvp.payments.id);
SELECT CONCAT('OP nuevas: ', COUNT(*)) FROM erp_mvp.production_orders WHERE NOT EXISTS (SELECT 1 FROM `a0011086_erp_mvp`.production_orders p WHERE p.id = erp_mvp.production_orders.id);
SELECT CONCAT('Stock moves nuevos: ', COUNT(*)) FROM erp_mvp.stock_moves WHERE NOT EXISTS (SELECT 1 FROM `a0011086_erp_mvp`.stock_moves p WHERE p.id = erp_mvp.stock_moves.id);

-- COMMIT O ROLLBACK SEGÚN NECESIDAD
-- COMMIT;
-- ROLLBACK;
