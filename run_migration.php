<?php
require_once __DIR__ . '/app/db.php';

try {
    $sql = file_get_contents(__DIR__ . '/add_sqm_column.sql');
    db()->exec($sql);
    echo "Column added successfully.";
} catch (Throwable $e) {
    echo "Error: (" . $e->getCode() . ") " . $e->getMessage();
}
