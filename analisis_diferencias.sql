-- ==========================================
-- ANÁLISIS DETALLADO DE DIFERENCIAS
-- DEV vs PROD
-- ==========================================

-- CUSTOMERS: Clientes nuevos en DEV
-- DEV tiene 20 clientes, PROD solo 1
SELECT 'CUSTOMERS' AS tabla, 
       COUNT(*) AS registros_en_dev,
       (SELECT COUNT(*) FROM `a0011086_erp_mvp`.customers) AS registros_en_prod,
       COUNT(*) - (SELECT COUNT(*) FROM `a0011086_erp_mvp`.customers) AS diferencia,
       GROUP_CONCAT(CONCAT('ID:', id, ' - ', nombre) SEPARATOR ', ') AS detalles
FROM erp_mvp.customers;

-- ORDERS: Órdenes nuevas
SELECT 'ORDERS' AS tabla,
       COUNT(*) AS registros_en_dev,
       (SELECT COUNT(*) FROM `a0011086_erp_mvp`.orders) AS registros_en_prod,
       COUNT(*) - (SELECT COUNT(*) FROM `a0011086_erp_mvp`.orders) AS diferencia,
       SUM(total_neto) AS valor_total_dev
FROM erp_mvp.orders;

-- ORDER_ITEMS: Items de orden
SELECT 'ORDER_ITEMS' AS tabla,
       COUNT(*) AS registros_en_dev,
       (SELECT COUNT(*) FROM `a0011086_erp_mvp`.order_items) AS registros_en_prod,
       COUNT(*) - (SELECT COUNT(*) FROM `a0011086_erp_mvp`.order_items) AS diferencia,
       SUM(subtotal) AS valor_total_dev
FROM erp_mvp.order_items;

-- PAYMENTS: Pagos registrados
SELECT 'PAYMENTS' AS tabla,
       COUNT(*) AS registros_en_dev,
       (SELECT COUNT(*) FROM `a0011086_erp_mvp`.payments) AS registros_en_prod,
       COUNT(*) - (SELECT COUNT(*) FROM `a0011086_erp_mvp`.payments) AS diferencia,
       SUM(importe) AS monto_total_dev
FROM erp_mvp.payments;

-- PRODUCTION_ORDERS: Órdenes de producción
SELECT 'PRODUCTION_ORDERS' AS tabla,
       COUNT(*) AS registros_en_dev,
       (SELECT COUNT(*) FROM `a0011086_erp_mvp`.production_orders) AS registros_en_prod,
       COUNT(*) - (SELECT COUNT(*) FROM `a0011086_erp_mvp`.production_orders) AS diferencia,
       SUM(cantidad) AS cantidad_produccion_dev
FROM erp_mvp.production_orders;

-- STOCK_MOVES: Movimientos de stock
SELECT 'STOCK_MOVES' AS tabla,
       COUNT(*) AS registros_en_dev,
       (SELECT COUNT(*) FROM `a0011086_erp_mvp`.stock_moves) AS registros_en_prod,
       COUNT(*) - (SELECT COUNT(*) FROM `a0011086_erp_mvp`.stock_moves) AS diferencia,
       SUM(cantidad) AS cantidad_movida_dev
FROM erp_mvp.stock_moves;

-- PURCHASES: Compras registradas
SELECT 'PURCHASES' AS tabla,
       COUNT(*) AS registros_en_dev,
       (SELECT COUNT(*) FROM `a0011086_erp_mvp`.purchases) AS registros_en_prod,
       COUNT(*) - (SELECT COUNT(*) FROM `a0011086_erp_mvp`.purchases) AS diferencia,
       SUM(total) AS monto_total_dev
FROM erp_mvp.purchases;

-- PURCHASE_ITEMS: Items de compra
SELECT 'PURCHASE_ITEMS' AS tabla,
       COUNT(*) AS registros_en_dev,
       (SELECT COUNT(*) FROM `a0011086_erp_mvp`.purchase_items) AS registros_en_prod,
       COUNT(*) - (SELECT COUNT(*) FROM `a0011086_erp_mvp`.purchase_items) AS diferencia,
       SUM(subtotal) AS monto_total_dev
FROM erp_mvp.purchase_items;

-- CUSTOMER_LEDGER: Movimientos de cliente
SELECT 'CUSTOMER_LEDGER' AS tabla,
       COUNT(*) AS registros_en_dev,
       (SELECT COUNT(*) FROM `a0011086_erp_mvp`.customer_ledger) AS registros_en_prod,
       COUNT(*) - (SELECT COUNT(*) FROM `a0011086_erp_mvp`.customer_ledger) AS diferencia,
       SUM(monto) AS monto_total_dev
FROM erp_mvp.customer_ledger;

-- AUDIT_LOGS: Auditoría
SELECT 'AUDIT_LOGS' AS tabla,
       COUNT(*) AS registros_en_dev,
       (SELECT COUNT(*) FROM `a0011086_erp_mvp`.audit_logs) AS registros_en_prod,
       COUNT(*) - (SELECT COUNT(*) FROM `a0011086_erp_mvp`.audit_logs) AS diferencia
FROM erp_mvp.audit_logs;

-- CASH_EXPENSES: Gastos de caja
SELECT 'CASH_EXPENSES' AS tabla,
       COUNT(*) AS registros_en_dev,
       (SELECT COUNT(*) FROM `a0011086_erp_mvp`.cash_expenses) AS registros_en_prod,
       COUNT(*) - (SELECT COUNT(*) FROM `a0011086_erp_mvp`.cash_expenses) AS diferencia,
       SUM(importe) AS monto_total_dev
FROM erp_mvp.cash_expenses;

-- ==========================================
-- TABLA COMPARATIVA CONSOLIDADA
-- ==========================================
SELECT 
    'RESUMEN GLOBAL' AS seccion,
    (SELECT COUNT(*) FROM erp_mvp.customers) AS clientes_dev,
    (SELECT COUNT(*) FROM `a0011086_erp_mvp`.customers) AS clientes_prod,
    (SELECT COUNT(*) FROM erp_mvp.orders) AS ordenes_dev,
    (SELECT COUNT(*) FROM `a0011086_erp_mvp`.orders) AS ordenes_prod,
    (SELECT SUM(total_neto) FROM erp_mvp.orders) AS valor_ordenes_dev,
    (SELECT SUM(importe) FROM erp_mvp.payments) AS pagos_dev
UNION ALL
SELECT 
    'STOCK Y COMPRAS',
    (SELECT COUNT(*) FROM erp_mvp.stock_moves),
    (SELECT COUNT(*) FROM `a0011086_erp_mvp`.stock_moves),
    (SELECT COUNT(*) FROM erp_mvp.purchases),
    (SELECT COUNT(*) FROM `a0011086_erp_mvp`.purchases),
    (SELECT SUM(total) FROM erp_mvp.purchases),
    (SELECT SUM(importe) FROM erp_mvp.cash_expenses)
UNION ALL
SELECT 
    'PRODUCCIÓN',
    (SELECT COUNT(*) FROM erp_mvp.production_orders),
    (SELECT COUNT(*) FROM `a0011086_erp_mvp`.production_orders),
    (SELECT COUNT(*) FROM erp_mvp.customer_ledger),
    (SELECT COUNT(*) FROM `a0011086_erp_mvp`.customer_ledger),
    (SELECT SUM(monto) FROM erp_mvp.customer_ledger),
    (SELECT COUNT(*) FROM erp_mvp.audit_logs);
