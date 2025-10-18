<?php
require_once __DIR__ . '/../app/auth.php';
require_login();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

include __DIR__ . '/../views/partials/header.php';
include __DIR__ . '/../views/partials/navbar.php';

include __DIR__ . '/../public/clientes_include.php';

// Parámetros
$q     = trim($_GET['q'] ?? '');
$page  = max(1, (int)($_GET['page'] ?? 1));
$limit = 50;
$off   = ($page - 1) * $limit;

// WHERE y params
$where  = '';
$params = [];
if ($q !== '') {
  $where = " WHERE c.nombre LIKE ? OR c.cuit_dni LIKE ? OR c.telefono LIKE ? ";
  $like  = "%$q%";
  $params = [$like, $like, $like];
}

// Total para paginación
$sqlCount = "SELECT COUNT(*) AS total
             FROM customers c" . $where;
$stmt = db()->prepare($sqlCount);
$stmt->execute($params);
$total = (int)$stmt->fetch()['total'];
$pages = max(1, (int)ceil($total / $limit));

// Datos
$sql = "SELECT c.id, c.nombre, c.cuit_dni, c.telefono, COALESCE(v.saldo,0) AS saldo
        FROM customers c
        LEFT JOIN v_cc_cliente v ON v.customer_id = c.id
        $where
        ORDER BY c.nombre
        LIMIT $limit OFFSET $off";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Helper para links de paginación
function page_url(int $p, string $q): string {
  $qs = http_build_query(['q' => $q, 'page' => $p]);
  return url('clientes.php') . '?' . $qs;
}


?>

