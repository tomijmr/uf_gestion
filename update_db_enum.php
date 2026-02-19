<?php
require_once __DIR__ . '/app/db.php';
try {
    $sql = "ALTER TABLE orders MODIFY COLUMN estado ENUM('PRESUPUESTO','BORRADOR','CONFIRMADO','EN_PRODUCCION','LISTO_ENTREGA','ENTREGADO','CERRADO') NOT NULL DEFAULT 'BORRADOR'";
    db()->exec($sql);
    echo "Tabla orders modificada correctamente.";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
