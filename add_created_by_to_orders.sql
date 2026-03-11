-- Agrega el campo created_by a la tabla orders
ALTER TABLE orders ADD COLUMN created_by INT NOT NULL DEFAULT 1 AFTER empresa_transporte;
-- Opcional: actualiza los pedidos existentes para asignar el usuario 1 (admin)
UPDATE orders SET created_by = 1 WHERE created_by IS NULL;
