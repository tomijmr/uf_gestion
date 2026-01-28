<?php
require_once __DIR__ . '/../app/auth.php';
require_login();

// Obtener el nombre del archivo del parámetro
$file = $_GET['file'] ?? '';

if (empty($file)) {
    http_response_code(404);
    die('Archivo no especificado.');
}

// Sanitizar el nombre del archivo para evitar ataques de path traversal
$file = basename($file);

// Construir la ruta completa
$filepath = __DIR__ . '/../storage/vouchers/' . $file;

// Verificar que el archivo existe
if (!file_exists($filepath) || !is_file($filepath)) {
    http_response_code(404);
    die('Archivo no encontrado.');
}

// Determinar el tipo MIME
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $filepath);
finfo_close($finfo);

// Enviar headers apropiados
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($filepath));
header('Content-Disposition: inline; filename="' . $file . '"');
header('Cache-Control: private, max-age=3600');

// Enviar el archivo
readfile($filepath);
exit;
