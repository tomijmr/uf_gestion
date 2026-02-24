<?php
require_once __DIR__ . '/app/db.php';

try {
    $stmt = db()->query("DESCRIBE products");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        echo $col['Field'] . " - " . $col['Type'] . "\n";
    }
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage();
}
