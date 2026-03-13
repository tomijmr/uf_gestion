<?php
// app/compra.php
require_once __DIR__ . '/db.php';


function compra_all_by_proveedor($proveedor_id) {
    $stmt = db()->prepare("SELECT * FROM purchases WHERE proveedor = (SELECT nombre COLLATE utf8mb4_unicode_ci FROM proveedores WHERE id = ?) ORDER BY fecha DESC");
    $stmt->execute([$proveedor_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


function compra_create($data, $forzarConsolidada = false) {
    $estado = $forzarConsolidada ? 'CONSOLIDADA' : 'PENDIENTE';
    $pago_id = $forzarConsolidada && isset($data['pago_id']) ? $data['pago_id'] : null;
    $comp_tipo = $data['comp_tipo'] ?? 'OTRO';
    $comp_serie = $data['comp_serie'] ?? '';
    $moneda = $data['moneda'] ?? 'ARS';
    $archivo_path = $data['archivo_path'] ?? null;
    $created_by = $data['created_by'] ?? (function_exists('user') ? (int)user()['id'] : 1);
    $incluye_iva = $data['incluye_iva'] ?? 1;
    $stmt = db()->prepare("INSERT INTO purchases (proveedor, fecha, comp_tipo, comp_serie, comp_numero, total, moneda, archivo_path, notas, created_by, incluye_iva, estado, pago_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $data['proveedor'],
        $data['fecha'],
        $comp_tipo,
        $comp_serie,
        $data['comp_numero'],
        $data['total'],
        $moneda,
        $archivo_path,
        $data['notas'],
        $created_by,
        $incluye_iva,
        $estado,
        $pago_id
    ]);
    return db()->lastInsertId();
}

function compra_consolidar($compra_id, $pago_id) {
    $stmt = db()->prepare("UPDATE purchases SET estado='CONSOLIDADA', pago_id=? WHERE id=?");
    return $stmt->execute([$pago_id, $compra_id]);
}
