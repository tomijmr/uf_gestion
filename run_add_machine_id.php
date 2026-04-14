<?php
require_once __DIR__ . '/app/db.php';

try {
    echo "Iniciando migration para agregar columna machine_id a tabla orders...<br>";
    
    $sql = "ALTER TABLE `orders` ADD COLUMN IF NOT EXISTS `machine_id` INT NULL DEFAULT NULL AFTER `customer_id`";
    
    db()->exec($sql);
    
    echo "<strong style='color: green;'>✓ Migration ejecutada exitosamente!</strong><br>";
    echo "La columna machine_id ha sido agregada a la tabla orders.<br>";
    
    // Verificar que la columna existe
    $result = db()->query("SHOW COLUMNS FROM orders LIKE 'machine_id'")->fetch();
    if ($result) {
        echo "<br><strong>Verificación:</strong> Columna machine_id encontrada.<br>";
        echo "Tipo: " . $result['Type'] . "<br>";
        echo "Null: " . $result['Null'] . "<br>";
        echo "Default: " . ($result['Default'] ?? 'NULL') . "<br>";
    }
    
} catch (Exception $e) {
    echo "<strong style='color: red;'>✗ Error:</strong> " . $e->getMessage() . "<br>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
?>
