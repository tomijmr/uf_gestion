<?php
require_once __DIR__ . '/public/index.php'; // Cargar entorno (auth, db, helpers) - Ajustar si es necesario
// O mejor, cargar solo lo necesario:

require_once __DIR__ . '/app/db.php';

try {
    $sql = file_get_contents(__DIR__ . '/add_video_column.sql');
    db()->exec($sql);
    echo "Column 'video_url' added successfully.\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
