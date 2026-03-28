<?php
require_once __DIR__ . '/../app/auth.php';
require_login();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

$id = (int)($_GET['id'] ?? 0);

$stmt = db()->prepare("SELECT r.*, c.nombre, c.cuit_dni, c.telefono, c.direccion, p.medio, p.referencia AS ref_pago
                       FROM payment_receipts r
                       JOIN customers c ON c.id = r.customer_id
                       JOIN payments p ON p.id = r.payment_id
                       WHERE r.id = ?");
$stmt->execute([$id]);
$rec = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$rec) {
    die("Comprobante no encontrado.");
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
                    <?php if ($rec['order_id']): ?>
                        Pedido #<?= (int)$rec['order_id'] ?>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th>Medio de Pago</th>
                <td><?= e($rec['medio']) ?> (<?= e($rec['ref_pago']) ?>)</td>
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
            Monto Total: <?= money($rec['monto']) ?>
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
