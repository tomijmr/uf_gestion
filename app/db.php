<?php
// app/db.php
function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;

    $host = getenv('DB_HOST') ?: 'localhost';
    $db   = getenv('DB_NAME') ?: 'a0011086_erp_mvp';
    $user = getenv('DB_USER') ?: 'a0011086';
    $pass = getenv('DB_PASS') ?: 'PObitovi56';
    $dsn  = "mysql:host={$host};dbname={$db};charset=utf8mb4";

    // $host = getenv('DB_HOST') ?: 'localhost';
    // $db   = getenv('DB_NAME') ?: 'erp_mvp';
    // $user = getenv('DB_USER') ?: 'root';
    // $pass = getenv('DB_PASS') ?: '';
    // $dsn  = "mysql:host={$host};dbname={$db};charset=utf8mb4";

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    try {
        $pdo = new PDO($dsn, $user, $pass, $options);
        $tz = getenv('APP_TIMEZONE') ?: 'America/Argentina/Buenos_Aires';
        $pdo->query("SET time_zone = '" . date('P') . "'");
        return $pdo;
    } catch (Throwable $e) {
        http_response_code(500);
        die('DB error: ' . $e->getMessage());
    }
}
