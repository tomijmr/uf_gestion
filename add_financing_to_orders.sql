-- Financiamiento para pedidos/presupuestos
-- Reglas: recargo unico 3% sobre saldo (total con IVA - seña), 2 a 12 cuotas.
-- Compatible con motores que no soportan ADD COLUMN IF NOT EXISTS.

SET @db_name = DATABASE();

SET @sql = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE orders ADD COLUMN financing_enabled TINYINT(1) NOT NULL DEFAULT 0',
    'SELECT 1'
  )
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'financing_enabled'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE orders ADD COLUMN financing_installments TINYINT UNSIGNED DEFAULT NULL',
    'SELECT 1'
  )
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'financing_installments'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
                                                            
SET @sql = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE orders ADD COLUMN financing_base_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00',
    'SELECT 1'
  )
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'financing_base_amount'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE orders ADD COLUMN financing_surcharge_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00',
    'SELECT 1'
  )
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'financing_surcharge_amount'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE orders ADD COLUMN financing_total DECIMAL(12,2) NOT NULL DEFAULT 0.00',
    'SELECT 1'
  )
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'financing_total'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE orders ADD COLUMN financing_installment_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00',
    'SELECT 1'
  )
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'financing_installment_amount'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS order_financing_installments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  installment_number TINYINT UNSIGNED NOT NULL,
  due_date DATE NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  status ENUM('PENDIENTE','PARCIAL','PAGADA') NOT NULL DEFAULT 'PENDIENTE',
  payment_id INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_order_financing_installments_order
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  INDEX idx_ofi_order (order_id),
  INDEX idx_ofi_order_installment (order_id, installment_number),
  INDEX idx_ofi_status_due (status, due_date)
);
