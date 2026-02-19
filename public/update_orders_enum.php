<?php
require_once __DIR__ . '/../app/db.php';

try {
    $pdo = db();
    $sql = "ALTER TABLE orders MODIFY COLUMN estado ENUM('PRESUPUESTO','BORRADOR','CONFIRMADO','EN_PRODUCCION','LISTO_ENTREGA','ENTREGADO','CERRADO') NOT NULL DEFAULT 'BORRADOR'";
    $pdo->exec($sql);
    echo "<h1>Tabla 'orders' actualizada correctamente. columna 'estado' modificada.</h1>";
} catch (PDOException $e) {
    echo "<h1>Error al modificar tabla: " . $e->getMessage() . "</h1>";
}
