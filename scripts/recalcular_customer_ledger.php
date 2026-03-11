<?php
// Script para recalcular los saldos de customer_ledger de un cliente
require_once __DIR__ . '/../app/db.php';

function recalcular_customer_ledger($customer_id) {
    // Obtener todos los movimientos ordenados por fecha e id
    $stmt = db()->prepare("SELECT id, tipo, monto FROM customer_ledger WHERE customer_id=? ORDER BY fecha ASC, id ASC");
    $stmt->execute([$customer_id]);
    $rows = $stmt->fetchAll();
    $saldo = 0;
    foreach ($rows as $row) {
        if ($row['tipo'] === 'CARGO') {
            $saldo += (float)$row['monto'];
        } else { // ABONO
            $saldo -= (float)$row['monto'];
        }
        db()->prepare("UPDATE customer_ledger SET saldo_resultante=? WHERE id=?")->execute([$saldo, $row['id']]);
    }
}

// Uso: php scripts/recalcular_customer_ledger.php <customer_id>
if (php_sapi_name() === 'cli' && isset($argv[1])) {
    $cid = (int)$argv[1];
    recalcular_customer_ledger($cid);
    echo "Saldos recalculados para cliente $cid\n";
}
