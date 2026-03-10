<?php
// app/proveedor.php
require_once __DIR__ . '/db.php';


function proveedor_all() {
    $sql = "SELECT * FROM proveedores ORDER BY nombre";
    return db()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}


function proveedor_get($id) {
    $stmt = db()->prepare("SELECT * FROM proveedores WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}


function proveedor_create($data) {
    $stmt = db()->prepare("INSERT INTO proveedores (nombre, contacto, telefono, email, direccion, cuit, condicion_iva, observaciones) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $data['nombre'],
        $data['contacto'],
        $data['telefono'],
        $data['email'],
        $data['direccion'],
        $data['cuit'],
        $data['condicion_iva'],
        $data['observaciones']
    ]);
    return db()->lastInsertId();
}


function proveedor_update($id, $data) {
    $stmt = db()->prepare("UPDATE proveedores SET nombre=?, contacto=?, telefono=?, email=?, direccion=?, cuit=?, condicion_iva=?, observaciones=? WHERE id=?");
    return $stmt->execute([
        $data['nombre'],
        $data['contacto'],
        $data['telefono'],
        $data['email'],
        $data['direccion'],
        $data['cuit'],
        $data['condicion_iva'],
        $data['observaciones'],
        $id
    ]);
}


function proveedor_delete($id) {
    $stmt = db()->prepare("DELETE FROM proveedores WHERE id=?");
    return $stmt->execute([$id]);
}
