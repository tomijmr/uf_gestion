<?php
// app/pago_proveedor.php
require_once __DIR__ . '/db.php';

function pago_proveedor_create($data) {
    $stmt = db()->prepare("INSERT INTO pagos_proveedores (proveedor_id, fecha, monto, comprobante, observaciones) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([
        $data['proveedor_id'],
        $data['fecha'],
        $data['monto'],
        $data['comprobante'],
        $data['observaciones']
    ]);
    return db()->lastInsertId();
}

function pagos_by_proveedor($proveedor_id) {
    $stmt = db()->prepare("SELECT * FROM pagos_proveedores WHERE proveedor_id = ? ORDER BY fecha DESC");
    $stmt->execute([$proveedor_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
