-- Agrega los campos estado y pago_id a la tabla purchases
ALTER TABLE purchases 
  ADD COLUMN estado ENUM('PENDIENTE','CONSOLIDADA') NOT NULL DEFAULT 'PENDIENTE' AFTER notas,
  ADD COLUMN pago_id INT DEFAULT NULL AFTER estado;
-- Opcional: actualiza las compras existentes a estado 'PENDIENTE'
UPDATE purchases SET estado = 'PENDIENTE' WHERE estado IS NULL;
