<?php
// app/helpers.php
require_once __DIR__ . '/../scripts/recalcular_customer_ledger.php';

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function money($n): string { return '$ ' . number_format((float)$n, 2, ',', '.'); }
function today(): string { return (new DateTime('today'))->format('Y-m-d'); }

function get_order_delete_blockers(int $order_id): array {
    $checks = [
        'pagos asociados' => ["SELECT COUNT(*) FROM payments WHERE order_id=?", [$order_id]],
        'remitos generados' => ["SELECT COUNT(*) FROM remitos WHERE order_id=?", [$order_id]],
        'movimientos de stock' => ["SELECT COUNT(*) FROM stock_moves WHERE referencia_tipo='ORDER' AND referencia_id=?", [$order_id]],
        'consumos de produccion' => [
            "SELECT COUNT(*)
             FROM production_stock_movements psm
             JOIN production_orders po ON po.id = psm.production_order_id
             WHERE po.order_id=?",
            [$order_id]
        ],
    ];

    $blockers = [];
    foreach ($checks as $label => [$sql, $params]) {
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        if ((int)$stmt->fetchColumn() > 0) {
            $blockers[] = $label;
        }
    }

    return $blockers;
}

function delete_order_or_fail(int $order_id): array {
    db()->beginTransaction();

    try {
        $stmt = db()->prepare("SELECT id, estado, customer_id FROM orders WHERE id=? FOR UPDATE");
        $stmt->execute([$order_id]);
        $order = $stmt->fetch();
        if (!$order) {
            throw new Exception('Pedido no encontrado.');
        }

        $blockers = get_order_delete_blockers($order_id);
        if ($blockers) {
            throw new Exception('No se puede eliminar porque tiene ' . implode(', ', $blockers) . '.');
        }

        db()->prepare("DELETE FROM customer_ledger WHERE origen='VENTA' AND referencia_id=?")
            ->execute([$order_id]);
        db()->prepare("DELETE FROM production_orders WHERE order_id=?")
            ->execute([$order_id]);
        db()->prepare("DELETE FROM order_items WHERE order_id=?")->execute([$order_id]);
        db()->prepare("DELETE FROM orders WHERE id=?")->execute([$order_id]);

        if (!empty($order['customer_id'])) {
            recalcular_customer_ledger((int)$order['customer_id']);
        }

        if (isset($_SESSION['pedido_edit'][$order_id])) {
            unset($_SESSION['pedido_edit'][$order_id]);
        }
        if (isset($_SESSION['pedido']['order_id']) && (int)$_SESSION['pedido']['order_id'] === $order_id) {
            unset($_SESSION['pedido']);
        }

        db()->commit();
        return $order;
    } catch (Throwable $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        throw $e;
    }
}

// Detecta la base actual del proyecto (para subcarpetas como /dev/uf_gestion/public)
function base_path(): string {
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    return rtrim($scriptDir, '/');
}

function url(string $path = ''): string {
    $base = base_path();
    $path = '/' . ltrim($path, '/');
    return ($base === '' ? '' : $base) . $path;
}
