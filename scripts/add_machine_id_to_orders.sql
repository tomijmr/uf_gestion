-- Agrega la columna machine_id a la tabla orders si no existe
ALTER TABLE `orders` ADD COLUMN IF NOT EXISTS `machine_id` INT NULL DEFAULT NULL AFTER `customer_id`;
