<?php
require_once __DIR__ . '/../app/auth.php';
require_login();
header('Content-Type: text/plain; charset=utf-8');
print_r(user());