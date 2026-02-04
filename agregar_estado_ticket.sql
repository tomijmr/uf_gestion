-- Agregar columna para identificar el estado del ticket
ALTER TABLE production_tickets 
ADD COLUMN estado_ticket VARCHAR(30) NULL COMMENT 'Estado para el que se imprimió el ticket';
