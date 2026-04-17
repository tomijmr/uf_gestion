<?php
require_once __DIR__ . '/../app/auth.php';
require_login();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

db()->exec("CREATE TABLE IF NOT EXISTS payment_receipt_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    receipt_id INT NOT NULL,
    payment_id INT NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_receipt_payment (receipt_id, payment_id),
    KEY idx_payment_id (payment_id),
    CONSTRAINT fk_pri_receipt FOREIGN KEY (receipt_id) REFERENCES payment_receipts(id) ON DELETE CASCADE,
    CONSTRAINT fk_pri_payment FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$id = (int)($_GET['id'] ?? 0);

$stmt = db()->prepare("SELECT r.*, c.nombre, c.cuit_dni, c.telefono, c.direccion
                       FROM payment_receipts r
                       JOIN customers c ON c.id = r.customer_id
                       WHERE r.id = ?");
$stmt->execute([$id]);
$rec = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$rec) {
    die("Comprobante no encontrado.");
}

$maquinas_pedido = [];
if (!empty($rec['order_id'])) {
    $stmtMaquinas = db()->prepare("SELECT p.codigo, p.nombre, oi.cant
                                   FROM order_items oi
                                   JOIN products p ON p.id = oi.product_id
                                   WHERE oi.order_id = ? AND p.tipo = 'PT'
                                   ORDER BY p.nombre");
    $stmtMaquinas->execute([(int)$rec['order_id']]);
    $maquinas_pedido = $stmtMaquinas->fetchAll(PDO::FETCH_ASSOC);
}

$stmtItems = db()->prepare("SELECT p.id, p.order_id, p.fecha, p.medio, p.importe, p.referencia, p.third_party_name, ba.nombre AS bank_name
                           FROM payment_receipt_items pri
                           JOIN payments p ON p.id = pri.payment_id
                           LEFT JOIN bank_accounts ba ON ba.id = p.bank_account_id
                           WHERE pri.receipt_id = ?
                           ORDER BY p.fecha ASC, p.id ASC");
$stmtItems->execute([$id]);
$payment_items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

if (!$payment_items && !empty($rec['payment_id'])) {
    $stmtFallback = db()->prepare("SELECT p.id, p.order_id, p.fecha, p.medio, p.importe, p.referencia, p.third_party_name, ba.nombre AS bank_name
                                  FROM payments p
                                  LEFT JOIN bank_accounts ba ON ba.id = p.bank_account_id
                                  WHERE p.id = ?");
    $stmtFallback->execute([(int)$rec['payment_id']]);
    $single = $stmtFallback->fetch(PDO::FETCH_ASSOC);
    if ($single) {
        $payment_items = [$single];
    }
}

$pedidos_ref = [];
$total_detalle = 0.0;
foreach ($payment_items as $it) {
    $total_detalle += (float)($it['importe'] ?? 0);
    if (!empty($it['order_id'])) {
        $pedidos_ref[(int)$it['order_id']] = true;
    }
}

// Calcular Saldo Actual del Cliente
$stmtSaldo = db()->prepare("SELECT COALESCE(SUM(CASE WHEN tipo='CARGO' THEN monto ELSE -monto END),0) AS saldo
                            FROM customer_ledger WHERE customer_id=?");
$stmtSaldo->execute([$rec['customer_id']]);
$saldo_actual = (float)$stmtSaldo->fetchColumn();

$logo = url('favicon-96x96.png');
$fecha = date('d/m/Y H:i', strtotime($rec['fecha']));
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Comprobante #<?= str_pad($rec['id'], 6, '0', STR_PAD_LEFT) ?></title>
    <style>
        body { font-family: Arial, sans-serif; color: #222; margin: 0; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; border: 1px solid #ccc; padding: 40px; }
        .header { display: flex; justify-content: space-between; border-bottom: 2px solid #0d6efd; padding-bottom: 20px; margin-bottom: 30px; }
        .logo-box img { height: 60px; }
        .company-info { font-size: 14px; color: #555; margin-top: 5px; }
        .receipt-title { font-size: 24px; font-weight: bold; text-transform: uppercase; color: #0d6efd; text-align: right; }
        .receipt-meta { text-align: right; margin-top: 10px; font-size: 14px; }
        .client-box { background: #f9f9f9; padding: 15px; border-radius: 4px; margin-bottom: 30px; }
        .details-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        .details-table th, .details-table td { padding: 12px; border-bottom: 1px solid #eee; text-align: left; }
        .details-table th { background: #f4f4f4; width: 40%; }
        .total-box { text-align: right; font-size: 20px; font-weight: bold; padding: 15px; background: #eef; border-radius: 4px; }
        .footer { margin-top: 50px; text-align: center; font-size: 12px; color: #777; border-top: 1px solid #eee; padding-top: 20px; }
        .btn-print { display: block; margin: 20px auto; padding: 10px 20px; background: #0d6efd; color: #fff; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; width: fit-content;}
        @media print {
            .btn-print { display: none; }
            .container { border: none; padding: 0; }
        }
    </style>
</head>
<body>
    <a href="javascript:window.print()" class="btn-print">Imprimir Comprobante</a>

    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="logo-box">
                <img src="<?= e($logo) ?>" alt="Logo">
                <div class="company-info">
                    <strong>Universal Fitness SA</strong><br>
                    Gestión y Producción
                </div>
            </div>
            <div>
                <div class="receipt-title">Recibo de Cobro</div>
                <div class="receipt-meta">
                    Nº: <strong><?= str_pad($rec['id'], 6, '0', STR_PAD_LEFT) ?></strong><br>
                    Fecha: <?= e($fecha) ?>
                </div>
            </div>
        </div>

        <!-- Cliente -->
        <div class="client-box">
            <strong>Recibimos de:</strong> <?= e($rec['nombre']) ?><br>
            <?php if(!empty($rec['cuit_dni'])): ?>
                <strong>CUIT/DNI:</strong> <?= e($rec['cuit_dni']) ?><br>
            <?php endif; ?>
            <?php if(!empty($rec['direccion'])): ?>
                <strong>Dirección:</strong> <?= e($rec['direccion']) ?>
            <?php endif; ?>
        </div>

        <!-- Detalles -->
        <table class="details-table">
            <tr>
                <th>Concepto</th>
                <td><?= e($rec['concepto']) ?></td>
            </tr>
            <tr>
                <th>Referencia / Pedido</th>
                <td>
                    <?php if (!empty($rec['order_id'])): ?>
                        Pedido #<?= (int)$rec['order_id'] ?>
                    <?php elseif (count($pedidos_ref) === 1): ?>
                        Pedido #<?= (int)array_key_first($pedidos_ref) ?>
                    <?php elseif (count($pedidos_ref) > 1): ?>
                        Varios pedidos: <?= e(implode(', ', array_map(static fn($n) => '#' . (int)$n, array_keys($pedidos_ref)))) ?>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th>Maquinas del Pedido</th>
                <td>
                    <?php if ($rec['order_id'] && $maquinas_pedido): ?>
                        <ul style="margin: 0; padding-left: 18px;">
                            <?php foreach ($maquinas_pedido as $m): ?>
                                <?php $cantTxt = rtrim(rtrim(number_format((float)$m['cant'], 2, ',', ''), '0'), ','); ?>
                                <li><?= e($m['nombre']) ?> (x<?= e($cantTxt) ?>)</li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th>Detalle de pagos</th>
                <td>
                    <?php if (!$payment_items): ?>
                        -
                    <?php else: ?>
                        <table style="width:100%; border-collapse:collapse;">
                            <thead>
                                <tr>
                                    <th style="border-bottom:1px solid #ddd; text-align:left; padding:4px;">Pago</th>
                                    <th style="border-bottom:1px solid #ddd; text-align:left; padding:4px;">Fecha</th>
                                    <th style="border-bottom:1px solid #ddd; text-align:left; padding:4px;">Medio</th>
                                    <th style="border-bottom:1px solid #ddd; text-align:left; padding:4px;">Referencia</th>
                                    <th style="border-bottom:1px solid #ddd; text-align:right; padding:4px;">Importe</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payment_items as $it): ?>
                                    <tr>
                                        <td style="padding:4px;">#<?= (int)$it['id'] ?></td>
                                        <td style="padding:4px;"><?= e(date('d/m/Y H:i', strtotime((string)$it['fecha']))) ?></td>
                                        <td style="padding:4px;"><?= e($it['medio']) ?><?= !empty($it['bank_name']) ? ' (' . e($it['bank_name']) . ')' : '' ?></td>
                                        <td style="padding:4px;"><?= e($it['referencia'] ?: '-') ?><?= !empty($it['third_party_name']) ? ' | Tercero: ' . e($it['third_party_name']) : '' ?></td>
                                        <td style="padding:4px; text-align:right;"><?= money((float)$it['importe']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </td>
            </tr>
            <?php if(!empty($rec['notes'])): ?>
            <tr>
                <th>Notas / Observaciones</th>
                <td><?= nl2br(e($rec['notes'])) ?></td>
            </tr>
            <?php endif; ?>
        </table>

        <!-- Total -->
        <div class="total-box">
            Monto Total: <?= money($total_detalle > 0 ? $total_detalle : (float)$rec['monto']) ?>
        </div>

        <?php if ($saldo_actual > 0): ?>
            <div style="text-align: right; margin-top: 10px; font-size: 14px; font-weight: bold; color: #d32f2f;">
                Saldo Restante: <?= money($saldo_actual) ?>
            </div>
        <?php elseif ($saldo_actual < 0): ?>
            <div style="text-align: right; margin-top: 10px; font-size: 14px; font-weight: bold; color: #198754;">
                Saldo a Favor: <?= money(abs($saldo_actual)) ?>
            </div>
        <?php else: ?>
            <div style="text-align: right; margin-top: 10px; font-size: 14px; font-weight: bold; color: #198754;">
                Sin deuda pendiente.
            </div>
        <?php endif; ?>

        <!-- Footer -->
        <div class="footer">
            <p>Tiempo de entrega de 60 a 90 dias a convenir, saldo restante sujeto a actualizacion de precios si los hubiera.</p>
            Este documento servirá como comprobante válido de pago una vez registrado en nuestro sistema.<br>
            Gracias por su confianza.
        </div>
    </div>
</body>
</html>
