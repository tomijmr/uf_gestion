<?php
require_once __DIR__ . '/../app/auth.php';
if (user()) { header('Location: ' . url('dashboard.php')); exit; }
header('Location: ' . url('login.php'));
