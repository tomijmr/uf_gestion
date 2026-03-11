-- Agrega el campo created_by a la tabla payments
ALTER TABLE payments ADD COLUMN created_by INT NOT NULL DEFAULT 1 AFTER voucher_path;
-- Opcional: actualiza los pagos existentes para asignar el usuario 1 (admin)
UPDATE payments SET created_by = 1 WHERE created_by IS NULL;
