<?php
// app/compra.php
require_once __DIR__ . '/db.php';


function compra_all_by_proveedor($proveedor_id) {
    $stmt = db()->prepare("SELECT * FROM purchases WHERE proveedor = (SELECT nombre COLLATE utf8mb4_unicode_ci FROM proveedores WHERE id = ?) ORDER BY fecha DESC");
    $stmt->execute([$proveedor_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


function compra_create($data) {
    $stmt = db()->prepare("INSERT INTO purchases (proveedor, fecha, comp_numero, total, notas, estado, pago_id) VALUES (?, ?, ?, ?, ?, 'PENDIENTE', NULL)");
    $stmt->execute([
        $data['proveedor'],
        $data['fecha'],
        $data['comp_numero'],
        $data['total'],
        $data['notas']
    ]);
    return db()->lastInsertId();
}

function compra_consolidar($compra_id, $pago_id) {
    $stmt = db()->prepare("UPDATE purchases SET estado='CONSOLIDADA', pago_id=? WHERE id=?");
    return $stmt->execute([$pago_id, $compra_id]);
}
