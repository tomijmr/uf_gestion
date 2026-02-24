<?php
// Mock environment
$_GET['export_presupuesto'] = '1';
$_GET['order_id'] = '55';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['SCRIPT_NAME'] = '/dev/uf_gestion/public/pedido_nuevo.php';

// Mock session
session_start();
$_SESSION['user'] = ['id' => 1, 'nombre' => 'Admin', 'rol_id' => 1]; 

// Include the file
require 'public/pedido_nuevo.php';
