<?php
require_once __DIR__ . '/app/db.php';

try {
    $sql = file_get_contents(__DIR__ . '/crear_tabla_comprobantes.sql');
    db()->exec($sql);
    echo "<h1>Tabla 'payment_receipts' creada correctamente.</h1>";
} catch (PDOException $e) {
    echo "<h1>Error al crear tabla: " . $e->getMessage() . "</h1>";
}
