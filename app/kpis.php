<?php
// app/kpis.php
require_once __DIR__.'/db.php';


function kpi_pedidos_hoy(): int {
$sql = "SELECT COUNT(*) c FROM orders WHERE DATE(fecha)=CURDATE()";
return (int)db()->query($sql)->fetch()['c'];
}


function kpi_en_produccion(): int {
$sql = "SELECT COUNT(*) c FROM production_orders WHERE estado IN ('PENDIENTE','EN_CURSO')";
return (int)db()->query($sql)->fetch()['c'];
}


function kpi_cobranzas_pend(): float {
$sql = "SELECT COALESCE(SUM(CASE WHEN l.tipo='CARGO' THEN l.monto ELSE -l.monto END),0) saldo
FROM customer_ledger l"; // saldo global
return (float)db()->query($sql)->fetch()['saldo'];
}


function kpi_faltantes(): int {
$sql = "SELECT COUNT(*) c FROM products WHERE (stock_actual - stock_reservado) < 0";
return (int)db()->query($sql)->fetch()['c'];
}