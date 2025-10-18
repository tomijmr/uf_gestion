<?php
// app/helpers.php
function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
function money($n): string { return '$ ' . number_format((float)$n, 2, ',', '.'); }
function today(): string { return (new DateTime('today'))->format('Y-m-d'); }

// Detecta la base actual del proyecto (para subcarpetas como /dev/uf_gestion/public)
function base_path(): string {
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    return rtrim($scriptDir, '/');
}

function url(string $path = ''): string {
    $base = base_path();
    $path = '/' . ltrim($path, '/');
    return ($base === '' ? '' : $base) . $path;
}
