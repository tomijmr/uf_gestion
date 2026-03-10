<?php
// app/compra.php
require_once __DIR__ . '/db.php';


function compra_all_by_proveedor($proveedor_id) {
    $stmt = db()->prepare("SELECT * FROM compras WHERE proveedor_id = ? ORDER BY fecha DESC");
    $stmt->execute([$proveedor_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


function compra_create($data) {
    $stmt = db()->prepare("INSERT INTO compras (proveedor_id, fecha, numero_factura, monto, detalle) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([
        $data['proveedor_id'],
        $data['fecha'],
        $data['numero_factura'],
        $data['monto'],
        $data['detalle']
    ]);
    return db()->lastInsertId();
}
