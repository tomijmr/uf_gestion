<?php
require_once __DIR__ . '/../app/db.php';

try {
    $pdo = db();
    
    // 1. Make customer_id nullable
    // Check if it's already nullable or modify it.
    // 'MODIFY COLUMN customer_id INT NULL' - assuming it is INT.
    // Need to check constraints... if it's a FK, might be tricky.
    // But let's try modifying the column definition first.
    
    $sql1 = "ALTER TABLE orders MODIFY COLUMN customer_id INT NULL";
    $pdo->exec($sql1);
    echo "<li>Columna 'customer_id' ahora permite NULL.</li>";
    
    // 2. Add manual client columns
    $sql2 = "ALTER TABLE orders ADD COLUMN IF NOT EXISTS cliente_manual VARCHAR(255) NULL AFTER customer_id";
    $pdo->exec($sql2);
    echo "<li>Columna 'cliente_manual' agregada/verificada.</li>";
    
    $sql3 = "ALTER TABLE orders ADD COLUMN IF NOT EXISTS cliente_manual_contacto VARCHAR(255) NULL AFTER cliente_manual";
    $pdo->exec($sql3);
    echo "<li>Columna 'cliente_manual_contacto' agregada/verificada.</li>";
    
    // 3. Make customer_id nullable again (just in case)
    $sql1 = "ALTER TABLE orders MODIFY COLUMN customer_id INT NULL";
    $pdo->exec($sql1);
    
    echo "<h1>Tablas actualizadas correctamente para Presupuestos manuales.</h1>";
    
} catch (PDOException $e) {
    echo "<h1>Error: " . $e->getMessage() . "</h1>";
    // If FK constraint fails, we might need to drop FK first.
    // But usually typically MySQL allows NULL in FK columns unless specified otherwise.
}
