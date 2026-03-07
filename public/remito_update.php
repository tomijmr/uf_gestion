<?php
require_once __DIR__ . '/../app/db.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['numero'])) {
  $sql = "UPDATE remitos SET bultos=?, tipo_envio=?, nombre_sucursal=?, direccion_sucursal=?, transporte=? WHERE numero=?";
  $stmt = db()->prepare($sql);
  $stmt->execute([
    $_POST['bultos'] ?? null,
    $_POST['tipo_envio'] ?? null,
    $_POST['nombre_sucursal'] ?? null,
    $_POST['direccion_sucursal'] ?? null,
    $_POST['transporte'] ?? null,
    $_POST['numero']
  ]);
  echo 'OK';
  exit;
}
http_response_code(400);
echo 'ERROR';
exit;