<?php
require_once __DIR__ . '/../app/db.php';
try {
    $stmt = db()->query("DESCRIBE orders");
    $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $col) {
        echo $col['Field'] . " | " . $col['Type'] . " | " . $col['Null'] . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
