<?php
// app/config.php (opcional)
define('APP_BASE', '/dev/uf_gestion/public');
function url(string $path=''): string { return rtrim(APP_BASE,'/').'/'.ltrim($path,'/'); }