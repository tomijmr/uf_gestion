<?php
// Helper para obtener ingresos reales de pedidos en un rango de fechas
function get_real_income_by_orders($start, $end) {
    $db = db();
    // Obtener todos los pedidos en el rango
    $sql = "SELECT id, total_neto FROM orders WHERE fecha BETWEEN ? AND ? AND estado NOT IN ('BORRADOR','PRESUPUESTO','CANCELADO')";
    $stmt = $db->prepare($sql);
    $stmt->execute([$start . ' 00:00:00', $end . ' 23:59:59']);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$orders) return [0, 0, []];
    $order_ids = array_column($orders, 'id');
    if (empty($order_ids)) return [0, 0, []];
    // Obtener pagos asociados a esos pedidos
    $in_query = implode(',', array_fill(0, count($order_ids), '?'));
    $sql2 = "SELECT order_id, SUM(importe) as pagado FROM payments WHERE order_id IN ($in_query) GROUP BY order_id";
    $stmt2 = $db->prepare($sql2);
    $stmt2->execute($order_ids);
    $pagos = $stmt2->fetchAll(PDO::FETCH_KEY_PAIR); // [order_id => pagado]
    $total_pedidos = 0;
    $total_pagado = 0;
    $detalle = [];
    foreach ($orders as $o) {
        $total_pedidos += (float)$o['total_neto'];
        $pagado = isset($pagos[$o['id']]) ? (float)$pagos[$o['id']] : 0;
        $total_pagado += $pagado;
        $detalle[] = [
            'id' => $o['id'],
            'total' => (float)$o['total_neto'],
            'pagado' => $pagado
        ];
    }
    return [$total_pedidos, $total_pagado, $detalle];
}
