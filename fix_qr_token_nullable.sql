-- Hacer que la columna qr_token sea nullable
ALTER TABLE production_tickets 
MODIFY COLUMN qr_token VARCHAR(100) NULL;
