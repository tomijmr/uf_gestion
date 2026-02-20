<?php
require_once __DIR__ . '/../app/db.php';
$stmt = db()->query("DESCRIBE orders");
$cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $c) {
    echo $c['Field'] . " " . $c['Type'] . "\n";
}
