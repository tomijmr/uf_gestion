<?php
require_once __DIR__ . '/../app/auth.php';
require_login();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';


error_reporting(E_ALL);
ini_set('display_errors', 1);

// ------------------------------------
// MANEJO DE IMPRESIÓN Y EXPORTACIÓN (reporte limpio)
// ------------------------------------
$is_print  = isset($_GET['print']) && $_GET['print'] === '1';
$is_export = isset($_GET['export']) && $_GET['export'] === 'csv';

// --- Imprimir Cuenta Corriente ---
if (($is_print || $is_export) && ($_GET['tab'] ?? '') === 'cc') {
  $cc_customer = (int)($_GET['cc_customer'] ?? 0);
  if ($cc_customer > 0) {
    // Info Cliente
    $stC = db()->prepare("SELECT nombre FROM customers WHERE id=?");
    $stC->execute([$cc_customer]);
    $cli = $stC->fetch();
    $nombreCliente = $cli['nombre'] ?? 'Cliente Desconocido';

    // Saldo
    $sSaldo = db()->prepare("SELECT COALESCE(SUM(CASE WHEN tipo='CARGO' THEN monto ELSE -monto END),0) AS saldo
                           FROM customer_ledger WHERE customer_id=?");
    $sSaldo->execute([$cc_customer]);
    $cc_saldo = (float)$sSaldo->fetch()['saldo'];

    // Movimientos (mism query que vista, pero quizás sin limit o limit más grande)
    $sqlCC = "SELECT cl.id, cl.fecha, cl.tipo, cl.origen, cl.referencia_id, cl.detalle, cl.monto, cl.saldo_resultante,
                     p.medio, p.bank_account_id, p.third_party_name, p.referencia, ba.nombre AS bank_name
              FROM customer_ledger cl
              LEFT JOIN payments p ON p.id = cl.referencia_id AND cl.origen = 'PAGO'
              LEFT JOIN bank_accounts ba ON ba.id = p.bank_account_id
              WHERE cl.customer_id=?
              ORDER BY cl.fecha DESC, cl.id DESC";
    $stCc = db()->prepare($sqlCC);
    $stCc->execute([$cc_customer]);
    $cc_rows = $stCc->fetchAll();

    $logo = 'https://ui-avatars.com/api/?name=UF&background=0D8ABC&color=fff&size=128'; 
    $fecha = date('d/m/Y H:i');

    if ($is_print) {
      ?>
      <!doctype html>
      <html lang="es">
      <head>
        <meta charset="utf-8">
        <title>Cuenta Corriente - <?= e($nombreCliente) ?></title>
        <style>
          @page { size: A4; margin: 15mm; }
          body { font-family: Arial, sans-serif; color: #222; margin: 20px; }
          .header { display: flex; justify-content: space-between; border-bottom: 2px solid #0d6efd; padding-bottom: 10px; margin-bottom: 20px; }
          .title { font-size: 20px; font-weight: bold; }
          .sub { font-size: 12px; color: #555; }
          .saldo-box { text-align: right; margin-bottom: 15px; font-size: 14px; font-weight: bold; background: #f8f9fa; padding: 10px; border-radius: 4px; }
          table { width: 100%; border-collapse: collapse; font-size: 11px; }
          th, td { border-bottom: 1px solid #ddd; padding: 6px; text-align: left; }
          th { background: #f0f0f0; }
          .text-end { text-align: right; }
          .badge { padding: 2px 4px; border-radius: 3px; font-size: 9px; border: 1px solid #ccc; }
        </style>
      </head>
      <body>
        <div class="header">
          <div>
            <div class="title">Cuenta Corriente</div>
            <div class="sub">Cliente: <strong><?= e($nombreCliente) ?></strong></div>
            <div class="sub">Universal Fitness SA</div>
          </div>
          <div style="text-align:right">
            <div class="sub"><?= e($fecha) ?></div>
          </div>
        </div>

        <div class="saldo-box">
          Saldo Actual: $ <?= number_format($cc_saldo, 2, ',', '.') ?>
        </div>

        <table>
          <thead>
            <tr>
              <th>Fecha</th>
              <th>Tipo</th>
              <th>Origen</th>
              <th>Detalle/Ref</th>
              <th class="text-end">Monto</th>
              <th class="text-end">Saldo</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($cc_rows as $row): ?>
              <tr>
                <td><?= date('d/m/Y', strtotime($row['fecha'])) ?></td>
                <td><?= e($row['tipo']) ?></td>
                <td><?= e($row['origen']) ?></td>
                <td>
                  <?= e($row['detalle']) ?>
                  <?php if($row['referencia']) echo " (Ref: {$row['referencia']})"; ?>
                </td>
                <td class="text-end">$ <?= number_format((float)$row['monto'], 2, ',', '.') ?></td>
                <td class="text-end">$ <?= number_format((float)$row['saldo_resultante'], 2, ',', '.') ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </body>
      </html>
      <?php
      exit;
    }
  }
}

if (($is_print || $is_export) && ($_GET['tab'] ?? '') === 'reportes') {
  $rep_desde = $_GET['rep_desde'] ?? date('Y-m-01');
  $rep_hasta = $_GET['rep_hasta'] ?? date('Y-m-d');
  $rep_tipo  = $_GET['rep_tipo'] ?? 'AMBOS';

  $rep_ingresos = [];
  $rep_gastos = [];
  $rep_total_ingresos = 0;
  $rep_total_gastos = 0;

  // INGRESOS
  if ($rep_tipo === 'AMBOS' || $rep_tipo === 'INGRESOS') {
    $sqlRI = "SELECT p.id, p.fecha, p.medio, p.importe, p.referencia, c.nombre AS cliente
              FROM payments p
              JOIN customers c ON c.id=p.customer_id
              WHERE DATE(p.fecha) BETWEEN ? AND ?
              ORDER BY p.fecha DESC";
    $stmtRI = db()->prepare($sqlRI);
    $stmtRI->execute([$rep_desde, $rep_hasta]);
    $rep_ingresos = $stmtRI->fetchAll();
    foreach ($rep_ingresos as $r) $rep_total_ingresos += (float)$r['importe'];
  }

  // GASTOS (Gastos Caja + Compras)
  if ($rep_tipo === 'AMBOS' || $rep_tipo === 'GASTOS') {
    // Usamos CAST para evitar problemas de colación en el UNION
    $sqlRG = "SELECT e.fecha, 
                     CAST(e.categoria AS CHAR) as categoria, 
                     CAST(e.descripcion AS CHAR) as descripcion, 
                     CAST(e.medio AS CHAR) as medio, 
                     e.importe, 
                     CAST(u.nombre AS CHAR) AS usuario 
              FROM cash_expenses e
              LEFT JOIN users u ON u.id = e.created_by
              WHERE DATE(e.fecha) BETWEEN ? AND ?

              UNION ALL

              SELECT p.id, p.fecha, 'COMPRA' as categoria,
                CONCAT('Prov: ', p.proveedor, ' - ', p.comp_tipo, ' ', p.comp_numero, IF(p.notas IS NOT NULL AND p.notas != '', CONCAT(' (', p.notas, ')'), '')) as descripcion,
                'COMPRA' as medio,
                p.total as importe,
                u.nombre AS usuario
              FROM purchases p
              LEFT JOIN users u ON u.id = p.created_by
              WHERE DATE(p.fecha) BETWEEN ? AND ?
                AND p.estado = 'CONSOLIDADA'

              ORDER BY fecha $g_orden_fecha, id $g_orden_fecha
              LIMIT 200";
    $paramsG2 = array_merge($paramsG, [$g_desde, $g_hasta]);
    $stG = db()->prepare($sqlG);
    $stG->execute($paramsG2);
    $gastos = $stG->fetchAll();
    foreach ($rep_gastos as $r) $rep_total_gastos += (float)$r['importe'];
  }

  // ---------------------------------
  // MODO CSV
  // ---------------------------------
  if ($is_export) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=reporte_caja_' . $rep_tipo . '_' . date('Y-m-d') . '.csv');
    
    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF"); // BOM para Excel

    // Bloque INGRESOS
    if ($rep_tipo === 'AMBOS' || $rep_tipo === 'INGRESOS') {
      fputcsv($out, ['--- REPORTE DE INGRESOS ---'], ",", "\"", "\\");
      fputcsv($out, ['Desde:', $rep_desde, 'Hasta:', $rep_hasta], ",", "\"", "\\");
      fputcsv($out, [], ",", "\"", "\\"); // Espacio
      fputcsv($out, ['ID', 'FECHA', 'CLIENTE', 'MEDIO', 'REFERENCIA', 'IMPORTE'], ",", "\"", "\\");
      
      foreach ($rep_ingresos as $r) {
        fputcsv($out, [
          $r['id'],
          $r['fecha'],
          $r['cliente'],
          $r['medio'],
          $r['referencia'],
          number_format((float)$r['importe'], 2, '.', '')
        ], ",", "\"", "\\");
      }
      fputcsv($out, ['TOTAL INGRESOS', '', '', '', '', number_format($rep_total_ingresos, 2, '.', '')], ",", "\"", "\\");
      fputcsv($out, [], ",", "\"", "\\"); // Separador
    }

    // Bloque GASTOS
    if ($rep_tipo === 'AMBOS' || $rep_tipo === 'GASTOS') {
      fputcsv($out, ['--- REPORTE DE EGRESOS ---'], ",", "\"", "\\");
      fputcsv($out, ['Desde:', $rep_desde, 'Hasta:', $rep_hasta], ",", "\"", "\\");
      fputcsv($out, [], ",", "\"", "\\"); // Espacio
      fputcsv($out, ['FECHA', 'CATEGORIA', 'DESCRIPCION', 'MEDIO', 'USUARIO', 'IMPORTE'], ",", "\"", "\\");
      
      foreach ($rep_gastos as $r) {
        fputcsv($out, [
          $r['fecha'],
          $r['categoria'],
          $r['descripcion'],
          $r['medio'],
          $r['usuario'],
          number_format((float)$r['importe'], 2, '.', '')
        ], ",", "\"", "\\");
      }
      fputcsv($out, ['TOTAL EGRESOS', '', '', '', '', number_format($rep_total_gastos, 2, '.', '')], ",", "\"", "\\");
      fputcsv($out, [], ",", "\"", "\\"); // Separador
    }
    
    // Balance Final si es AMBOS
    if ($rep_tipo === 'AMBOS') {
      $balance = $rep_total_ingresos - $rep_total_gastos;
      fputcsv($out, ['BALANCE TOTAL (INGRE - EGRE)', number_format($balance, 2, '.', '')], ",", "\"", "\\");
    }

    fclose($out);
    exit;
  }

  // ---------------------------------
  // MODO PRINT (HTML)
  // ---------------------------------
  $tituloReporte = "Reporte de Caja";
  if ($rep_tipo === 'INGRESOS') $tituloReporte = "Reporte de Ingresos";
  if ($rep_tipo === 'GASTOS') $tituloReporte = "Reporte de Egresos";

  $logo = 'https://ui-avatars.com/api/?name=UF&background=0D8ABC&color=fff&size=128'; // Fallback o url real
  // Intentar usar favicon local si existe como en stock_reportes
  // $logo = url('favicon-96x96.png');
  // Se asume que helper url() funciona.

  ?>
  <!doctype html>
  <html lang="es">
  <head>
    <meta charset="utf-8">
    <title><?= e($tituloReporte) ?></title>
    <style>
      @page { size: A4; margin: 15mm; }
      body { font-family: Arial, sans-serif; color: #222; margin: 20px; }
      .header { display: flex; align-items: center; justify-content: space-between; border-bottom: 2px solid #0d6efd; padding-bottom: 10px; margin-bottom: 20px; }
      .header-left { display: flex; align-items: center; gap: 15px; }
      .header-logo { width: 50px; height: 50px; object-fit: contain; }
      .title { font-size: 24px; font-weight: bold; color: #333; }
      .subtitle { font-size: 14px; color: #555; margin-top: 4px; }
      .meta { text-align: right; font-size: 12px; color: #666; }
      
      .section-title { 
        background-color: #f8f9fa; 
        padding: 8px 12px; 
        font-weight: bold; 
        border-left: 4px solid #0d6efd; 
        margin-top: 20px; 
        margin-bottom: 10px;
        display: flex;
        justify-content: space-between;
      }
      
      table { width: 100%; border-collapse: collapse; font-size: 12px; margin-bottom: 20px; }
      th, td { border-bottom: 1px solid #ddd; padding: 8px; text-align: left; vertical-align: top; }
      th { background-color: #f1f1f1; font-weight: bold; }
      .text-end { text-align: right; }
      .fw-bold { font-weight: bold; }
      .text-success { color: #198754; }
      .text-danger { color: #dc3545; }
      
      .total-row td { border-top: 2px solid #333; font-weight: bold; background-color: #fdfdfd; }
      
      .summary-box { 
        border: 1px solid #ccc; 
        padding: 15px; 
        width: 300px; 
        margin-left: auto; 
        background: #f9f9f9;
        margin-top: 30px;
      }
      .summary-row { display: flex; justify-content: space-between; margin-bottom: 5px; }
      .summary-total { border-top: 1px solid #999; padding-top: 5px; margin-top: 5px; font-weight: bold; font-size: 14px; }

      @media print {
        body { margin: 0; }
        .no-print { display: none; }
      }
    </style>
  </head>
  <body onload="window.print()">
    <div class="header">
      <div class="header-left">
        <!-- Puedes ajustar la ruta del logo -->
        <div style="font-size:32px; font-weight:bold; color:#0d6efd;">UF</div>
        <div>
          <div class="title"><?= e($tituloReporte) ?></div>
          <div class="subtitle">Desde <?= date('d/m/Y', strtotime($rep_desde)) ?> Hasta <?= date('d/m/Y', strtotime($rep_hasta)) ?></div>
        </div>
      </div>
      <div class="meta">
        Generado el: <?= date('d/m/Y H:i') ?><br>
        Usuario: <?= e(user()['nombre']) ?>
      </div>
    </div>

    <!-- SECCIÓN INGRESOS -->
    <?php if ($rep_tipo === 'AMBOS' || $rep_tipo === 'INGRESOS'): ?>
      <div class="section-title">
        <span>INGRESOS</span>
        <span>Total: $ <?= number_format($rep_total_ingresos, 2, ',', '.') ?></span>
      </div>
      <?php if (empty($rep_ingresos)): ?>
        <p style="text-align:center; color:#777; font-style:italic;">No hay ingresos registrados en este período.</p>
      <?php else: ?>
        <table>
          <thead>
            <tr>
              <th style="width: 100px;">Fecha</th>
              <th>Cliente</th>
              <th>Medio</th>
              <th>Referencia</th>
              <th class="text-end" style="width: 120px;">Importe</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rep_ingresos as $ri): ?>
              <tr>
                <td><?= date('d/m/Y', strtotime($ri['fecha'])) ?></td>
                <td><?= e($ri['cliente']) ?></td>
                <td><?= e($ri['medio']) ?></td>
                <td><?= e($ri['referencia']) ?></td>
                <td class="text-end">$ <?= number_format((float)$ri['importe'], 2, ',', '.') ?></td>
              </tr>
            <?php endforeach; ?>
            <tr class="total-row">
              <td colspan="4" class="text-end">TOTAL INGRESOS</td>
              <td class="text-end text-success">$ <?= number_format($rep_total_ingresos, 2, ',', '.') ?></td>
            </tr>
          </tbody>
        </table>
      <?php endif; ?>
    <?php endif; ?>

    <!-- SECCIÓN GASTOS -->
    <?php if ($rep_tipo === 'AMBOS' || $rep_tipo === 'GASTOS'): ?>
      <div class="section-title" style="border-left-color: #dc3545;">
        <span>EGRESOS (GASTOS)</span>
        <span>Total: $ <?= number_format($rep_total_gastos, 2, ',', '.') ?></span>
      </div>
      <?php if (empty($rep_gastos)): ?>
        <p style="text-align:center; color:#777; font-style:italic;">No hay gastos registrados en este período.</p>
      <?php else: ?>
        <table>
          <thead>
            <tr>
              <th style="width: 100px;">Fecha</th>
              <th>Categoría</th>
              <th>Descripción</th>
              <th>Usuario</th>
              <th class="text-end" style="width: 120px;">Importe</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rep_gastos as $rg): ?>
              <tr>
                <td><?= date('d/m/Y', strtotime($rg['fecha'])) ?></td>
                <td><?= e($rg['categoria']) ?></td>
                <td><?= e($rg['descripcion']) ?></td>
                <td><?= e($rg['usuario'] ?? '-') ?></td>
                <td class="text-end">$ <?= number_format((float)$rg['importe'], 2, ',', '.') ?></td>
              </tr>
            <?php endforeach; ?>
            <tr class="total-row">
              <td colspan="4" class="text-end">TOTAL GASTOS</td>
              <td class="text-end text-danger">$ <?= number_format($rep_total_gastos, 2, ',', '.') ?></td>
            </tr>
          </tbody>
        </table>
      <?php endif; ?>
    <?php endif; ?>

    <!-- RESUMEN FINAL -->
    <?php if ($rep_tipo === 'AMBOS'): ?>
      <div class="summary-box">
        <div class="summary-row">
          <span>Total Ingresos:</span>
          <span class="text-success">$ <?= number_format($rep_total_ingresos, 2, ',', '.') ?></span>
        </div>
        <div class="summary-row">
          <span>Total Gastos:</span>
          <span class="text-danger">- $ <?= number_format($rep_total_gastos, 2, ',', '.') ?></span>
        </div>
        <div class="summary-row summary-total">
          <span>Balance Neto:</span>
          <span>$ <?= number_format($rep_total_ingresos - $rep_total_gastos, 2, ',', '.') ?></span>
        </div>
      </div>
    <?php endif; ?>

  </body>
  </html>
  <?php
  exit; // Detener ejecución para no cargar el resto de la página normal
}


$flash_ok = '';
$flash_err = '';

$MEDIOS = ['EFECTIVO','DEBITO','TRANSFER','CREDITO','NC'];
$GASTO_CATEGORIAS = ['SERVICIOS','SUELDOS','ALQUILER','IMPUESTOS','INSUMOS','OTROS'];

// --------------------
// Tab activo por GET
// --------------------
$validTabs = ['cobrar','recientes','cc','resumen','gastos','reportes'];
$tab = $_GET['tab'] ?? 'cobrar';
if (!in_array($tab, $validTabs, true)) $tab = 'cobrar';

// -------------------------------
// POST: Registrar pago (cobranza)
// -------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  // --- COBRO ---
  if ($action === 'registrar_pago') {
    $customer_id = (int)($_POST['customer_id'] ?? 0);
    $order_id    = (int)($_POST['order_id'] ?? 0);
    $medio       = $_POST['medio'] ?? 'EFECTIVO';
    $importe     = max(0, (float)($_POST['importe'] ?? 0));
    $referencia  = trim($_POST['referencia'] ?? '');
    $bank_account_id = ($medio === 'TRANSFER') ? ((int)($_POST['bank_account_id'] ?? 0)) : null;
    $third_party_name = trim($_POST['third_party_name'] ?? '');
    $voucher_path = null;

    try {
      if ($customer_id <= 0) throw new Exception('Debe seleccionar un cliente.');
      if (!in_array($medio, $MEDIOS, true)) throw new Exception('Medio de pago inválido.');
      if ($importe <= 0) throw new Exception('Importe inválido.');
      
      // Validaciones para transferencias
      if ($medio === 'TRANSFER') {
        if (!$bank_account_id) throw new Exception('Debe seleccionar una cuenta bancaria para transferencias.');
        
        $stmt_ba = db()->prepare("SELECT nombre FROM bank_accounts WHERE id=?");
        $stmt_ba->execute([$bank_account_id]);
        $ba = $stmt_ba->fetch();
        if (!$ba) throw new Exception('Cuenta bancaria no encontrada.');
        
        if ($ba['nombre'] === 'CUENTA TERCERO' && empty($third_party_name)) {
          throw new Exception('Debe especificar el tercero para transferencias a CUENTA TERCERO.');
        }
      }

      db()->beginTransaction();

      if ($order_id > 0) {
        $so = db()->prepare("SELECT id, estado, saldo FROM orders WHERE id=? AND customer_id=? FOR UPDATE");
        $so->execute([$order_id, $customer_id]);
        $o = $so->fetch();
        if (!$o) throw new Exception('El pedido no existe o no pertenece al cliente.');
      }

      // Procesar archivo comprobante si se cargó
      if (isset($_FILES['voucher']) && $_FILES['voucher']['error'] !== UPLOAD_ERR_NO_FILE && $_FILES['voucher']['name'] !== '') {
        $file = $_FILES['voucher'];
        $allowed_types = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'];
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
          throw new Exception('Error al subir el archivo: ' . $file['error']);
        }
        
        if (!in_array($file['type'], $allowed_types)) {
          throw new Exception('Solo se permiten archivos PDF, JPG o PNG.');
        }
        
        if ($file['size'] > 5242880) { // 5MB
          throw new Exception('El archivo no debe superar 5MB.');
        }
        
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $ext = strtolower($ext);
        $filename = 'voucher_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $filepath = __DIR__ . '/../storage/vouchers/' . $filename;
        
        if (!is_dir(dirname($filepath))) {
          throw new Exception('La carpeta de almacenamiento no existe.');
        }
        
        if (!is_writable(dirname($filepath))) {
          throw new Exception('No hay permisos de escritura en la carpeta de almacenamiento.');
        }
        
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
          throw new Exception('No se pudo guardar el comprobante. Verifica permisos de la carpeta.');
        }
        
        // Guardar solo el nombre del archivo
        $voucher_path = $filename;
      }

      $sp = db()->prepare("INSERT INTO payments (customer_id, order_id, fecha, medio, importe, referencia, bank_account_id, third_party_name, voucher_path)
                           VALUES (?, ?, NOW(), ?, ?, ?, ?, ?, ?)");
      $sp->execute([$customer_id, $order_id ?: null, $medio, $importe, $referencia, $bank_account_id, $third_party_name ?: null, $voucher_path]);
      
      // Obtener el ID del pago recién insertado
      $payment_id = db()->lastInsertId();

      // --- Generar Comprobante ---
      db()->prepare("INSERT INTO payment_receipts (customer_id, order_id, payment_id, fecha, monto, concepto, notes, created_at)
                     VALUES (?, ?, ?, NOW(), ?, ?, ?, NOW())")
        ->execute([$customer_id, $order_id ?: null, $payment_id, $importe, 'Pago en Caja', 'Referencia: '.$referencia]);

      // Ledger ABONO
      $ss = db()->prepare("SELECT COALESCE(SUM(CASE WHEN tipo='CARGO' THEN monto ELSE -monto END),0) AS saldo
                           FROM customer_ledger WHERE customer_id=?");
      $ss->execute([$customer_id]);
      $saldoAnterior = (float)($ss->fetch()['saldo'] ?? 0);

      $saldoResultante = $saldoAnterior - $importe;
      $sl = db()->prepare("INSERT INTO customer_ledger (customer_id, fecha, tipo, origen, referencia_id, detalle, monto, saldo_resultante)
                           VALUES (?, NOW(), 'ABONO', 'PAGO', ?, ?, ?, ?)");
      $sl->execute([$customer_id, $payment_id, 'Pago registrado en caja', $importe, $saldoResultante]);

      if ($order_id > 0) {
        $newSaldo = max(0, (float)$o['saldo'] - $importe);
        db()->prepare("UPDATE orders SET saldo=? WHERE id=?")->execute([$newSaldo, $order_id]);
        if ($newSaldo <= 0.00001 && $o['estado'] === 'ENTREGADO') {
          db()->prepare("UPDATE orders SET estado='CERRADO' WHERE id=?")->execute([$order_id]);
        }
      }

      db()->commit();
      $flash_ok = "Pago registrado correctamente.";
      $tab = 'cobrar';
    } catch (Throwable $e) {
      db()->rollBack();
      $flash_err = 'No se pudo registrar el pago: ' . $e->getMessage();
      $tab = 'cobrar';
    }
  }

  // --- GASTO ---
  if ($action === 'registrar_gasto') {
    $fechaG      = trim($_POST['fecha'] ?? '');
    $categoria   = trim($_POST['categoria'] ?? '');
    $medioG      = $_POST['medio'] ?? 'EFECTIVO';
    $importeG    = max(0, (float)($_POST['importe'] ?? 0));
    $descripcion = trim($_POST['descripcion'] ?? '');

    try {
      if ($fechaG === '') {
        $fechaG = date('Y-m-d H:i:s');
      } else {
        // Viene como datetime-local: 2025-11-30T14:30
        $fechaG = str_replace('T', ' ', $fechaG);
      }

      if ($categoria === '') {
        throw new Exception('Debe seleccionar una categoría de gasto.');
      }
      if ($importeG <= 0) {
        throw new Exception('Importe de gasto inválido.');
      }

      // Permitimos los mismos medios + eventualmente OTRO
      if (!in_array($medioG, array_merge($MEDIOS, ['OTRO']), true)) {
        throw new Exception('Medio de pago de gasto inválido.');
      }

      $userId = (int)user()['id'];

      $sg = db()->prepare("INSERT INTO cash_expenses (fecha, categoria, descripcion, medio, importe, created_by)
                           VALUES (?, ?, ?, ?, ?, ?)");
      $sg->execute([$fechaG, $categoria, $descripcion, $medioG, $importeG, $userId]);

      $flash_ok = "Gasto registrado correctamente.";
      $tab = 'gastos';
    } catch (Throwable $e) {
      $flash_err = 'No se pudo registrar el gasto: ' . $e->getMessage();
      $tab = 'gastos';
    }
  }
}

// ------------------------------------
// Datos para selects (clientes/pedidos/cuentas bancarias)
// ------------------------------------
$clientes = db()->query("SELECT id, nombre FROM customers WHERE activo=1 ORDER BY nombre LIMIT 500")->fetchAll();
$bank_accounts = db()->query("SELECT id, nombre FROM bank_accounts WHERE activo=1 ORDER BY nombre")->fetchAll();

$pref_customer_id = (int)($_GET['customer_id'] ?? 0);
$pedidos_cliente = [];
if ($pref_customer_id > 0) {
  $spc = db()->prepare("SELECT id, estado, saldo, total_neto FROM orders WHERE customer_id=? AND saldo>0 ORDER BY id DESC LIMIT 200");
  $spc->execute([$pref_customer_id]);
  $pedidos_cliente = $spc->fetchAll();
}

// --------------------
// Filtros P. Recientes
// --------------------
$desde = $_GET['desde'] ?? date('Y-m-01'); // Default to start of month for recent
$hasta = $_GET['hasta'] ?? date('Y-m-d');
$f_customer = (int)($_GET['f_customer'] ?? 0);
$orden_fecha = $_GET['orden_fecha'] ?? 'DESC'; 

$wherePay = [];
$paramsPay = [];
$wherePay[] = "DATE(p.fecha) BETWEEN ? AND ?";
$paramsPay[] = $desde; 
$paramsPay[] = $hasta;
if ($f_customer > 0) {
  $wherePay[] = "p.customer_id = ?";
  $paramsPay[] = $f_customer;
}
$wherePaySql = $wherePay ? ('WHERE ' . implode(' AND ', $wherePay)) : '';

$sqlPays = "SELECT p.id, p.fecha, p.medio, p.importe, p.referencia, p.bank_account_id, 
                   p.third_party_name, p.voucher_path, c.nombre AS cliente, p.order_id, 
                   ba.nombre AS bank_name, pr.id AS receipt_id
            FROM payments p
            JOIN customers c ON c.id=p.customer_id
            LEFT JOIN bank_accounts ba ON ba.id=p.bank_account_id
            LEFT JOIN payment_receipts pr ON pr.payment_id = p.id
            $wherePaySql
            ORDER BY p.fecha $orden_fecha, p.id $orden_fecha
            LIMIT 200";
$stPays = db()->prepare($sqlPays);
$stPays->execute($paramsPay);
$pays = $stPays->fetchAll();

// --------------------
// Filtro de GASTOS
// --------------------
$g_desde    = $_GET['g_desde'] ?? date('Y-m-d');
$g_hasta    = $_GET['g_hasta'] ?? date('Y-m-d');
$g_categoria = $_GET['g_categoria'] ?? '';
$g_orden_fecha = $_GET['g_orden_fecha'] ?? 'DESC';

$whereG = [];
$paramsG = [];
$whereG[] = "DATE(e.fecha) BETWEEN ? AND ?";
$paramsG[] = $g_desde;
$paramsG[] = $g_hasta;

if ($g_categoria !== '') {
  $whereG[] = "e.categoria = ?";
  $paramsG[] = $g_categoria;
}

$whereGSql = 'WHERE ' . implode(' AND ', $whereG);

// Validar orden
$g_orden_fecha = in_array($g_orden_fecha, ['ASC', 'DESC']) ? $g_orden_fecha : 'DESC';


// UNION cash_expenses + compras consolidadas
$sqlG = "SELECT e.id, e.fecha,
                CAST(e.categoria AS CHAR) as categoria,
                CAST(e.descripcion AS CHAR) as descripcion,
                CAST(e.medio AS CHAR) as medio,
                e.importe,
                CAST(u.nombre AS CHAR) AS usuario
           FROM cash_expenses e
           LEFT JOIN users u ON u.id = e.created_by
           $whereGSql

           UNION ALL

           SELECT p.id, p.fecha,
                'COMPRA' as categoria,
                CAST(CONCAT('Prov: ', p.proveedor, ' - ', p.comp_tipo, ' ', p.comp_numero, IF(p.notas IS NOT NULL AND p.notas != '', CONCAT(' (', p.notas, ')'), '')) AS CHAR) as descripcion,
                'COMPRA' as medio,
                p.total as importe,
                CAST(u.nombre AS CHAR) AS usuario
           FROM purchases p
           LEFT JOIN users u ON u.id = p.created_by
           WHERE DATE(p.fecha) BETWEEN ? AND ?
             AND p.estado = 'CONSOLIDADA'

           ORDER BY fecha $g_orden_fecha, id $g_orden_fecha
           LIMIT 200";
$paramsG2 = array_merge($paramsG, [$g_desde, $g_hasta]);
$stG = db()->prepare($sqlG);
$stG->execute($paramsG2);
$gastos = $stG->fetchAll();

// ---------------------
// Cuenta Corriente (CC)
// ---------------------
$cc_customer = (int)($_GET['cc_customer'] ?? 0);
$cc_rows = [];
$cc_saldo = null;
if ($cc_customer > 0) {
  $stCc = db()->prepare("SELECT cl.id, cl.fecha, cl.tipo, cl.origen, cl.referencia_id, cl.detalle, cl.monto, cl.saldo_resultante,
                                p.medio, p.bank_account_id, p.third_party_name, p.voucher_path, p.referencia, ba.nombre AS bank_name
                         FROM customer_ledger cl
                         LEFT JOIN payments p ON p.id = cl.referencia_id AND cl.origen = 'PAGO'
                         LEFT JOIN bank_accounts ba ON ba.id = p.bank_account_id
                         WHERE cl.customer_id=?
                         ORDER BY cl.fecha DESC, cl.id DESC
                         LIMIT 300");
  $stCc->execute([$cc_customer]);
  $cc_rows = $stCc->fetchAll();

  $sSaldo = db()->prepare("SELECT COALESCE(SUM(CASE WHEN tipo='CARGO' THEN monto ELSE -monto END),0) AS saldo
                           FROM customer_ledger WHERE customer_id=?");
  $sSaldo->execute([$cc_customer]);
  $cc_saldo = (float)$sSaldo->fetch()['saldo'];
}

// --------------
// Resumen diario
// --------------
$hoy = date('Y-m-d');

// Cobros por medio
$stRes = db()->prepare("SELECT medio, SUM(importe) AS total 
                        FROM payments 
                        WHERE DATE(fecha)=? 
                        GROUP BY medio 
                        ORDER BY medio");
$stRes->execute([$hoy]);
$resumenHoy = $stRes->fetchAll();

// Transferencias a cuentas propias (CUENTA GONZA, CUENTA PAO)
$stResTransfPropias = db()->prepare("SELECT SUM(p.importe) AS total
                                      FROM payments p
                                      JOIN bank_accounts ba ON ba.id = p.bank_account_id
                                      WHERE DATE(p.fecha)=? AND p.medio='TRANSFER' AND ba.nombre IN ('CUENTA GONZA', 'CUENTA PAO')");
$stResTransfPropias->execute([$hoy]);
$transferencias_propias = (float)($stResTransfPropias->fetch()['total'] ?? 0);

// Transferencias a cuentas de terceros
$stResTransfTerceros = db()->prepare("SELECT SUM(p.importe) AS total
                                      FROM payments p
                                      JOIN bank_accounts ba ON ba.id = p.bank_account_id
                                      WHERE DATE(p.fecha)=? AND p.medio='TRANSFER' AND ba.nombre = 'CUENTA TERCERO'");
$stResTransfTerceros->execute([$hoy]);
$transferencias_terceros = (float)($stResTransfTerceros->fetch()['total'] ?? 0);

// Gastos por categoría
$stResG = db()->prepare("SELECT categoria, SUM(importe) AS total
                         FROM cash_expenses
                         WHERE DATE(fecha)=?
                         GROUP BY categoria
                         ORDER BY categoria");
$stResG->execute([$hoy]);
$resumenGastosHoy = $stResG->fetchAll();

// --------------------
// Reportes (tab=reportes)
// --------------------
$rep_desde = $_GET['rep_desde'] ?? date('Y-m-01');
$rep_hasta = $_GET['rep_hasta'] ?? date('Y-m-d');
$rep_tipo  = $_GET['rep_tipo'] ?? 'AMBOS'; // AMBOS, INGRESOS, GASTOS

$rep_ingresos = [];
$rep_gastos = [];
$rep_total_ingresos = 0;
$rep_total_gastos = 0;

if ($tab === 'reportes') {
  // INGRESOS
  if ($rep_tipo === 'AMBOS' || $rep_tipo === 'INGRESOS') {
    $sqlRI = "SELECT p.id, p.fecha, p.medio, p.importe, p.referencia, c.nombre AS cliente, p.voucher_path, pr.id AS receipt_id
              FROM payments p
              JOIN customers c ON c.id=p.customer_id
              LEFT JOIN payment_receipts pr ON pr.payment_id = p.id
              WHERE DATE(p.fecha) BETWEEN ? AND ?
              ORDER BY p.fecha DESC";
    $stmtRI = db()->prepare($sqlRI);
    $stmtRI->execute([$rep_desde, $rep_hasta]);
    $rep_ingresos = $stmtRI->fetchAll();

    foreach ($rep_ingresos as $r) {
      $rep_total_ingresos += (float)$r['importe'];
    }
  }

    // GASTOS (Gastos Caja + Compras)
    if ($rep_tipo === 'AMBOS' || $rep_tipo === 'GASTOS') {
      // Usamos CAST para evitar problemas de colación en el UNION
      $sqlRG = "SELECT e.id, e.fecha, 
                     CAST(e.categoria AS CHAR CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci) as categoria, 
                     CAST(e.descripcion AS CHAR CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci) as descripcion, 
                     CAST(e.medio AS CHAR CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci) as medio, 
                     e.importe, 
                     CAST(u.nombre AS CHAR CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci) AS usuario 
              FROM cash_expenses e
              LEFT JOIN users u ON u.id = e.created_by
              WHERE DATE(e.fecha) BETWEEN ? AND ?

              UNION ALL

              SELECT p.id, p.fecha,
                CAST('COMPRA' AS CHAR CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci) as categoria,
                CAST(CONCAT('Prov: ', p.proveedor, ' - ', p.comp_tipo, ' ', p.comp_numero, IF(p.notas IS NOT NULL AND p.notas != '', CONCAT(' (', p.notas, ')'), '')) AS CHAR CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci) as descripcion,
                CAST('COMPRA' AS CHAR CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci) as medio,
                p.total as importe,
                CAST(u.nombre AS CHAR CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci) AS usuario
              FROM purchases p
              LEFT JOIN users u ON u.id = p.created_by
              WHERE DATE(p.fecha) BETWEEN ? AND ?
                AND p.estado = 'CONSOLIDADA'

              ORDER BY fecha $g_orden_fecha, id $g_orden_fecha
              LIMIT 200";
      $paramsRep = [$rep_desde, $rep_hasta, $rep_desde, $rep_hasta];
      $stG = db()->prepare($sqlRG);
      $stG->execute($paramsRep);
      $rep_gastos = $stG->fetchAll();
      foreach ($rep_gastos as $r) $rep_total_gastos += (float)$r['importe'];
    }
}

function money0($n) { return '$ ' . number_format((float)$n, 0, ',', '.'); }

include __DIR__ . '/../views/partials/header.php';
include __DIR__ . '/../views/partials/navbar.php';

// helpers para clases de pestañas
function tabActive($t, $tab) { return $t===$tab ? 'active' : ''; }
function paneActive($t, $tab) { return $t===$tab ? 'show active' : ''; }
?>
<style>
@media print {
  @page { size: A4; margin: 1cm; }
  body { background-color: white !important; font-family: sans-serif; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  
  /* Ocultar elementos de navegación e interfaz */
  header, nav, .navbar, .nav-tabs, .btn, form, .alert-info, .card-header .btn, footer, .d-print-none {
    display: none !important;
  }
  
  /* Ajustes generales de contenedor */
  .container { max-width: 100% !important; width: 100% !important; padding: 0 !important; margin: 0 !important; }
  .tab-content { border: none !important; box-shadow: none !important; padding: 0 !important; margin: 0 !important; }
  .tab-pane { display: block !important; opacity: 1 !important; visibility: visible !important; }
  
  /* Mostrar solo el reporte activo */
  #reportes { display: block !important; }
  /* Ocultar otros tabs si por alguna razón se imprimen */
  #cobrar, #recientes, #gastos, #cc, #resumen { display: none !important; }

  /* Layout de columnas - FLEXIBILIDAD Y CENTRADO */
  .row.print-row {
    display: flex !important;
    flex-wrap: wrap !important; /* Permitir wrap si es necesario, aunque intentaremos que no */
    width: 100% !important;
    margin: 0 !important;
    justify-content: center !important; /* Centrar horizontalmente */
  }

  /* Caso: Dos columnas (Ingresos + Gastos) */
  .col-print-6 {
    flex: 0 0 48% !important;
    max-width: 48% !important;
    margin: 0 1% !important; /* Pequeño margen entre columnas */
  }

  /* Caso: Una sola columna (Solo Ingresos o Solo Gastos) */
  .col-print-12 {
    flex: 0 0 100% !important;
    max-width: 100% !important;
    margin: 0 !important;
  }

  /* Tablas y Cards */
  .card { 
    border: 1px solid #ddd !important; 
    break-inside: avoid; /* Evitar que una tarjeta se rompa entre páginas si es posible */
    margin-bottom: 20px !important;
    box-shadow: none !important;
  }
  .card-header { 
    border-bottom: 2px solid #000 !important; 
    background-color: transparent !important; /* Ahorrar tinta, usar borde/texto color */
    color: #000 !important; /* Texto negro para contraste */
  }
  
  /* Colores para distinguir si se desea (aunque en blaco y negro el borde manda) */
  .border-success { border-color: #000 !important; } /* Borde negro simple */
  .border-danger { border-color: #000 !important; }
  
  /* Texto en cabecera de card: forzar color o dejar negro */
  .card-header.bg-success { border-bottom: 2px solid green !important; }
  .card-header.bg-danger { border-bottom: 2px solid red !important; }
  
  /* Altura automática y sin scroll */
  .table-responsive { 
    max-height: none !important; 
    overflow: visible !important; 
    display: block !important;
  }
  
  /* Títulos de impresión */
  .print-header { 
    display: block !important; 
    margin-bottom: 20px; 
    text-align: center; 
    border-bottom: 2px solid #333; 
    padding-bottom: 15px; 
    width: 100%;
  }
  
  /* Ajustes de fuente para que entre más info */
  body, table { font-size: 10pt !important; }
  h2 { font-size: 18pt !important; margin: 0; }
  p { margin: 5px 0 0 0; font-size: 11pt; }
  
  /* Evitar saltos de página dentro de filas de tabla */
  tr { break-inside: avoid; page-break-inside: avoid; }
}
.print-header { display: none; }
</style>

<div class="container py-4">
  <h5 class="mb-3">Caja</h5>

  <?php if ($flash_ok): ?><div class="alert alert-success"><?= e($flash_ok) ?></div><?php endif; ?>
  <?php if ($flash_err): ?><div class="alert alert-danger"><?= e($flash_err) ?></div><?php endif; ?>

  <ul class="nav nav-tabs" id="tabs" role="tablist">
    <li class="nav-item" role="presentation">
      <button class="nav-link <?= tabActive('cobrar',$tab) ?>" id="cobrar-tab" data-bs-toggle="tab" data-bs-target="#cobrar" type="button" role="tab">Cobrar</button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link <?= tabActive('recientes',$tab) ?>" id="recientes-tab" data-bs-toggle="tab" data-bs-target="#recientes" type="button" role="tab">Pagos recientes</button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link <?= tabActive('gastos',$tab) ?>" id="gastos-tab" data-bs-toggle="tab" data-bs-target="#gastos" type="button" role="tab">Gastos</button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link <?= tabActive('reportes',$tab) ?>" id="reportes-tab" data-bs-toggle="tab" data-bs-target="#reportes" type="button" role="tab">Reportes</button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link <?= tabActive('cc',$tab) ?>" id="cc-tab" data-bs-toggle="tab" data-bs-target="#cc" type="button" role="tab">Cuenta Corriente</button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link <?= tabActive('resumen',$tab) ?>" id="resumen-tab" data-bs-toggle="tab" data-bs-target="#resumen" type="button" role="tab">Resumen diario</button>
    </li>
  </ul>

  <div class="tab-content border-bottom border-start border-end p-3 bg-white shadow-sm">

    <!-- COBRAR -->
    <div class="tab-pane fade <?= paneActive('cobrar',$tab) ?>" id="cobrar" role="tabpanel" aria-labelledby="cobrar-tab">
      <form class="row g-3" method="post" action="<?= url('caja.php') ?>?tab=cobrar" enctype="multipart/form-data" id="formCobro">
        <input type="hidden" name="action" value="registrar_pago">

        <div class="col-md-6">
          <label class="form-label">Cliente</label>
          <select name="customer_id" class="form-select" required onchange="location.href='<?= url('caja.php') ?>?tab=cobrar&customer_id='+this.value">
            <option value="">— Seleccionar —</option>
            <?php foreach ($clientes as $c): ?>
              <option value="<?= (int)$c['id'] ?>" <?= $pref_customer_id===(int)$c['id']?'selected':'' ?>>
                <?= e($c['nombre']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <div class="form-text">Opcional: precargá pedidos al elegir cliente.</div>
        </div>

        <div class="col-md-6">
          <label class="form-label">Pedido (opcional)</label>
          <select name="order_id" class="form-select" <?= $pref_customer_id>0?'':'disabled' ?>>
            <option value="">— Sin pedido —</option>
            <?php foreach ($pedidos_cliente as $p): ?>
              <option value="<?= (int)$p['id'] ?>">#<?= (int)$p['id'] ?> — <?= e($p['estado']) ?> — Saldo <?= money($p['saldo']) ?></option>
            <?php endforeach; ?>
          </select>
          <div class="form-text">Si no seleccionás, el pago impacta solo en la cuenta corriente.</div>
        </div>

        <div class="col-md-3">
          <label class="form-label">Medio</label>
          <select name="medio" class="form-select" id="medioSelect" onchange="toggleTransferFields()">
            <?php foreach ($MEDIOS as $m): ?>
              <option value="<?= $m ?>"><?= $m ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-3">
          <label class="form-label">Importe</label>
          <input type="number" step="0.01" min="0.01" name="importe" class="form-control" required>
        </div>

        <div class="col-md-6">
          <label class="form-label">Referencia / Observación</label>
          <input name="referencia" class="form-control" placeholder="Comprobante, banco, últimos 4, etc.">
        </div>

        <!-- Campos para transferencias -->
        <div class="col-md-6" id="bankAccountDiv" style="display: none;">
          <label class="form-label">Cuenta Bancaria *</label>
          <select name="bank_account_id" class="form-select" id="bankAccountSelect" onchange="toggleThirdPartyField()">
            <option value="">— Seleccionar —</option>
            <?php foreach ($bank_accounts as $ba): ?>
              <option value="<?= (int)$ba['id'] ?>"><?= e($ba['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-6" id="thirdPartyDiv" style="display: none;">
          <label class="form-label">Tercero (nombre de quien recibe) *</label>
          <input type="text" name="third_party_name" class="form-control" placeholder="Ej: Juan Pérez, Acerlot, etc.">
        </div>

        <!-- Carga de comprobante -->
        <div class="col-md-6" id="voucherDiv" style="display: none;">
          <label class="form-label">Comprobante (PDF o Foto)</label>
          <input type="file" name="voucher" class="form-control" accept=".pdf,.jpg,.jpeg,.png" id="voucherInput">
          <small class="text-muted">PDF, JPG o PNG. Máximo 5MB</small>
        </div>

        <div class="col-12 d-grid">
          <button class="btn btn-primary" type="submit">Registrar pago</button>
        </div>
      </form>

      <script>
        function toggleTransferFields() {
          const medio = document.getElementById('medioSelect').value;
          const bankDiv = document.getElementById('bankAccountDiv');
          const thirdPartyDiv = document.getElementById('thirdPartyDiv');
          const voucherDiv = document.getElementById('voucherDiv');
          
          if (medio === 'TRANSFER') {
            bankDiv.style.display = 'block';
            voucherDiv.style.display = 'block';
            toggleThirdPartyField();
          } else {
            bankDiv.style.display = 'none';
            thirdPartyDiv.style.display = 'none';
            voucherDiv.style.display = 'none';
            document.getElementById('bankAccountSelect').value = '';
            document.getElementById('voucherInput').value = '';
          }
        }

        function toggleThirdPartyField() {
          const bankAccount = document.getElementById('bankAccountSelect').value;
          const thirdPartyDiv = document.getElementById('thirdPartyDiv');
          const thirdPartyInput = document.querySelector('input[name="third_party_name"]');
          
          // Obtener el texto de la opción seleccionada
          const select = document.getElementById('bankAccountSelect');
          const selectedText = select.options[select.selectedIndex]?.text || '';
          
          if (selectedText.includes('CUENTA TERCERO')) {
            thirdPartyDiv.style.display = 'block';
            thirdPartyInput.required = true;
          } else {
            thirdPartyDiv.style.display = 'none';
            thirdPartyInput.required = false;
            thirdPartyInput.value = '';
          }
        }
      </script>
    </div>

    <!-- PAGOS RECIENTES -->
    <div class="tab-pane fade <?= paneActive('recientes',$tab) ?>" id="recientes" role="tabpanel" aria-labelledby="recientes-tab">
      <form class="row g-2 mb-3" method="get" action="<?= url('caja.php') ?>">
        <input type="hidden" name="tab" value="recientes">
        <div class="col-md-2">
          <label class="form-label">Desde</label>
          <input type="date" name="desde" class="form-control" value="<?= e($desde) ?>">
        </div>
        <div class="col-md-2">
          <label class="form-label">Hasta</label>
          <input type="date" name="hasta" class="form-control" value="<?= e($hasta) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Cliente</label>
          <select name="f_customer" class="form-select">
            <option value="0">Todos</option>
            <?php foreach ($clientes as $c): ?>
              <option value="<?= (int)$c['id'] ?>" <?= $f_customer===(int)$c['id']?'selected':'' ?>>
                <?= e($c['nombre']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Ordenar por fecha</label>
          <select name="orden_fecha" class="form-select">
            <option value="DESC" <?= $orden_fecha==='DESC'?'selected':'' ?>>Más recientes primero</option>
            <option value="ASC" <?= $orden_fecha==='ASC'?'selected':'' ?>>Más antiguos primero</option>
          </select>
        </div>
        <div class="col-md-2 d-grid">
          <label class="form-label">&nbsp;</label>
          <button class="btn btn-outline-secondary">Filtrar</button>
        </div>
      </form>

      <div class="table-responsive">
        <table class="table table-sm align-middle">
          <thead class="table-light">
          <tr>
            <th style="width:70px;">#</th>
            <th>Fecha</th>
            <th>Cliente</th>
            <th>Medio</th>
            <th class="text-end">Importe</th>
            <th>Pedido</th>
            <th>Cuenta Bancaria</th>
            <th>Tercero</th>
            <th>Comprobante</th>
            <th>Referencia</th>
            <th>Recibo</th>
          </tr>
          </thead>
          <tbody>
          <?php if (!$pays): ?>
            <tr><td colspan="10" class="text-center text-muted py-4">No hay pagos en el rango seleccionado.</td></tr>
          <?php else: foreach ($pays as $p): ?>
            <tr>
              <td><?= (int)$p['id'] ?></td>
              <td><?= e($p['fecha']) ?></td>
              <td><?= e($p['cliente']) ?></td>
              <td><?= e($p['medio']) ?></td>
              <td class="text-end"><?= money($p['importe']) ?></td>
              <td><?= $p['order_id'] ? '#'.(int)$p['order_id'] : '-' ?></td>
              <td><?= $p['bank_name'] ? e($p['bank_name']) : '-' ?></td>
              <td><?= $p['third_party_name'] ? e($p['third_party_name']) : '-' ?></td>
              <td>
                <?php if ($p['voucher_path']): ?>
                  <a href="<?= url('voucher.php?file=' . urlencode($p['voucher_path'])) ?>" target="_blank" class="btn btn-sm btn-outline-secondary">Ver</a>
                <?php endif; ?>
              </td>
              <td><?= e($p['referencia']) ?></td>
              <td>
                <?php if ($p['receipt_id']): ?>
                  <a href="<?= url('comprobante_pago.php?id=' . $p['receipt_id']) ?>" target="_blank" class="btn btn-sm btn-outline-primary" title="Imprimir Recibo">🖨️</a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- GASTOS -->
    <div class="tab-pane fade <?= paneActive('gastos',$tab) ?>" id="gastos" role="tabpanel" aria-labelledby="gastos-tab">
      <div class="row g-3 mb-4">
        <div class="col-md-5">
          <h6>Registrar gasto</h6>
          <form method="post" action="<?= url('caja.php') ?>?tab=gastos" class="border rounded p-3 bg-light">
            <input type="hidden" name="action" value="registrar_gasto">
            <div class="mb-2">
              <label class="form-label">Fecha</label>
              <input type="datetime-local" name="fecha" class="form-control" value="<?= date('Y-m-d\TH:i') ?>">
            </div>
            <div class="mb-2">
              <label class="form-label">Categoría</label>
              <select name="categoria" class="form-select" required>
                <option value="">— Seleccionar —</option>
                <?php foreach ($GASTO_CATEGORIAS as $cat): ?>
                  <option value="<?= e($cat) ?>"><?= e($cat) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="mb-2">
              <label class="form-label">Medio</label>
              <select name="medio" class="form-select">
                <?php foreach (array_merge($MEDIOS,['OTRO']) as $m): ?>
                  <option value="<?= $m ?>"><?= $m ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="mb-2">
              <label class="form-label">Importe</label>
              <input type="number" step="0.01" min="0.01" name="importe" class="form-control" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Descripción</label>
              <input name="descripcion" class="form-control" placeholder="Ej: Luz, agua, sueldo Juan, etc.">
            </div>
            <div class="d-grid">
              <button class="btn btn-danger">Registrar gasto</button>
            </div>
          </form>
        </div>

        <div class="col-md-7">
          <h6>Gastos registrados</h6>
          <form class="row g-2 mb-2" method="get" action="<?= url('caja.php') ?>">
            <input type="hidden" name="tab" value="gastos">
            <div class="col-md-3">
              <label class="form-label">Desde</label>
              <input type="date" name="g_desde" class="form-control" value="<?= e($g_desde) ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label">Hasta</label>
              <input type="date" name="g_hasta" class="form-control" value="<?= e($g_hasta) ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label">Categoría</label>
              <select name="g_categoria" class="form-select">
                <option value="">Todas</option>
                <?php foreach ($GASTO_CATEGORIAS as $cat): ?>
                  <option value="<?= e($cat) ?>" <?= $g_categoria===$cat?'selected':'' ?>><?= e($cat) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">Ordenar</label>
              <select name="g_orden_fecha" class="form-select">
                <option value="DESC" <?= $g_orden_fecha==='DESC'?'selected':'' ?>>Más recientes</option>
                <option value="ASC" <?= $g_orden_fecha==='ASC'?'selected':'' ?>>Más antiguos</option>
              </select>
            </div>
            <div class="col-md-12 d-grid">
              <button class="btn btn-outline-secondary">Filtrar</button>
            </div>
          </form>

          <div class="table-responsive">
            <table class="table table-sm align-middle">
              <thead class="table-light">
              <tr>
                <th style="width:70px;">#</th>
                <th>Fecha</th>
                <th>Categoría</th>
                <th>Medio</th>
                <th class="text-end">Importe</th>
                <th>Descripción</th>
                <th>Usuario</th>
              </tr>
              </thead>
              <tbody>
              <?php if (!$gastos): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">No hay gastos en el rango seleccionado.</td></tr>
              <?php else: foreach ($gastos as $g): ?>
                <tr>
                  <td><?= (int)$g['id'] ?></td>
                  <td><?= e($g['fecha']) ?></td>
                  <td><?= e($g['categoria']) ?></td>
                  <td><?= e($g['medio']) ?></td>
                  <td class="text-end"><?= money($g['importe']) ?></td>
                  <td><?= e($g['descripcion']) ?></td>
                  <td><?= e($g['usuario'] ?? '—') ?></td>
                </tr>
              <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- REPORTES -->
    <div class="tab-pane fade <?= paneActive('reportes',$tab) ?>" id="reportes" role="tabpanel" aria-labelledby="reportes-tab">
      <div class="print-header">
        <?php 
          $tituloReporte = "Reporte de Caja";
          if ($rep_tipo === 'INGRESOS') $tituloReporte = "Reporte de Ingresos";
          if ($rep_tipo === 'GASTOS') $tituloReporte = "Reporte de Egresos";
        ?>
        <h2><?= $tituloReporte ?></h2>
        <p><strong>Desde:</strong> <?= date('d/m/Y', strtotime($rep_desde)) ?> &nbsp;&nbsp;&nbsp; <strong>Hasta:</strong> <?= date('d/m/Y', strtotime($rep_hasta)) ?></p>
      </div>

      <form class="row g-2 mb-4 align-items-end d-print-none" method="get" action="<?= url('caja.php') ?>">
        <input type="hidden" name="tab" value="reportes">
        <div class="col-auto">
          <label class="form-label fw-bold">Desde</label>
          <input type="date" name="rep_desde" class="form-control" value="<?= e($rep_desde) ?>">
        </div>
        <div class="col-auto">
          <label class="form-label fw-bold">Hasta</label>
          <input type="date" name="rep_hasta" class="form-control" value="<?= e($rep_hasta) ?>">
        </div>
        <div class="col-auto">
          <label class="form-label fw-bold">Tipo</label>
          <select name="rep_tipo" class="form-select">
            <option value="AMBOS" <?= $rep_tipo==='AMBOS'?'selected':'' ?>>Ambos (Resumen)</option>
            <option value="INGRESOS" <?= $rep_tipo==='INGRESOS'?'selected':'' ?>>Solo Ingresos</option>
            <option value="GASTOS" <?= $rep_tipo==='GASTOS'?'selected':'' ?>>Solo Egresos</option>
          </select>
        </div>
        <div class="col-auto">
          <button class="btn btn-primary">Generar</button>
        </div>
        <div class="col-auto ms-auto d-flex gap-2">
          <button type="button" class="btn btn-info" onclick="reporteGastosCategoria()">
            <i class="bi bi-bar-chart-fill"></i> Gastos por Categoría
          </button>
          <button type="button" class="btn btn-success" onclick="exportarCSV()">
            <i class="bi bi-file-earmark-spreadsheet"></i> Exportar CSV
          </button>
          <button type="button" class="btn btn-secondary" onclick="imprimirReporte()">
            <i class="bi bi-printer"></i> Imprimir
          </button>
        </div>
      </form>

      <script>
        function reporteGastosCategoria() {
          const d = document.querySelector('input[name=rep_desde]').value;
          const h = document.querySelector('input[name=rep_hasta]').value;
          
          // Mostrar menú de opciones
          const choice = confirm('¿Qué deseas hacer?\n\nOK = Imprimir\nCancelar = Descargar CSV');
          
          if (choice) {
            // Imprimir en A4
            window.open(`export_gastos_categoria.php?start_date=${d}&end_date=${h}&print=1`, '_blank');
          } else {
            // Descargar como CSV
            window.open(`export_gastos_categoria.php?start_date=${d}&end_date=${h}&export=csv`, '_blank');
          }
        }

        function exportarCSV() {
          const d = document.querySelector('input[name=rep_desde]').value;
          const h = document.querySelector('input[name=rep_hasta]').value;
          const t = document.querySelector('select[name=rep_tipo]').value;
          window.open(`caja.php?tab=reportes&export=csv&rep_desde=${d}&rep_hasta=${h}&rep_tipo=${t}`, '_blank');
        }

        function imprimirReporte() {
          const d = document.querySelector('input[name=rep_desde]').value;
          const h = document.querySelector('input[name=rep_hasta]').value;
          const t = document.querySelector('select[name=rep_tipo]').value;
          const url = '<?= url("caja.php") ?>?tab=reportes&print=1&rep_desde='+d+'&rep_hasta='+h+'&rep_tipo='+t;
          window.open(url, '_blank');
        }
      </script>

      <?php if ($tab === 'reportes'): ?>
        
        <div class="row g-4 print-row">
          <?php 
            // Lógica de grilla: si es ambos, col-lg-6. Si es uno solo, col-lg-12
            $colClass = ($rep_tipo === 'AMBOS') ? 'col-lg-6 col-print-6' : 'col-lg-12 col-print-12';
          ?>

          <!-- INGRESOS -->
          <?php if ($rep_tipo === 'AMBOS' || $rep_tipo === 'INGRESOS'): ?>
          <div class="<?= $colClass ?>">
            <div class="card h-100 border-success">
              <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                <span class="fw-bold">INGRESOS</span>
                <span class="badge bg-white text-success fs-6"><?= money($rep_total_ingresos) ?></span>
              </div>
              <div class="card-body p-0">
                <div class="table-responsive" style="max-height: 600px;">
                  <table class="table table-sm table-hover mb-0">
                    <thead class="table-light sticky-top">
                      <tr>
                        <th>Fecha</th>
                        <th>Cliente / Detalle</th>
                        <th class="text-end">Importe</th>
                      </tr>
                    </thead>
                    <tbody>
                    <?php if (!$rep_ingresos): ?>
                      <tr><td colspan="3" class="text-center text-muted py-3">No hay ingresos en este período.</td></tr>
                    <?php else: foreach ($rep_ingresos as $ri): ?>
                      <tr>
                        <td style="white-space:nowrap;"><?= date('d/m/Y', strtotime($ri['fecha'])) ?></td>
                        <td>
                          <div class="fw-semibold"><?= e($ri['cliente']) ?></div>
                          <small class="text-muted">
                            <?= e($ri['medio']) ?> <?= $ri['referencia'] ? ' - '.e($ri['referencia']) : '' ?>
                            <?php if (!empty($ri['receipt_id'])): ?>
                                <a href="comprobante_pago.php?id=<?= $ri['receipt_id'] ?>" target="_blank" class="text-decoration-none ms-2" title="Imprimir Comprobante">📄 Recibo</a>
                            <?php endif; ?>
                          </small>
                        </td>
                        <td class="text-end text-success fw-semibold"><?= money($ri['importe']) ?></td>
                      </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
          <?php endif; ?>

          <!-- GASTOS -->
          <?php if ($rep_tipo === 'AMBOS' || $rep_tipo === 'GASTOS'): ?>
          <div class="<?= $colClass ?>">
            <div class="card h-100 border-danger">
              <div class="card-header bg-danger text-white d-flex justify-content-between align-items-center">
                <span class="fw-bold">GASTOS</span>
                <span class="badge bg-white text-danger fs-6"><?= money($rep_total_gastos) ?></span>
              </div>
              <div class="card-body p-0">
                <div class="table-responsive" style="max-height: 600px;">
                  <table class="table table-sm table-hover mb-0">
                    <thead class="table-light sticky-top">
                      <tr>
                        <th>Fecha</th>
                        <th>Categoría / Descripción</th>
                        <th class="text-end">Importe</th>
                      </tr>
                    </thead>
                    <tbody>
                    <?php if (!$rep_gastos): ?>
                      <tr><td colspan="3" class="text-center text-muted py-3">No hay gastos en este período.</td></tr>
                    <?php else: foreach ($rep_gastos as $rg): ?>
                      <tr>
                        <td style="white-space:nowrap;"><?= date('d/m/Y', strtotime($rg['fecha'])) ?></td>
                        <td>
                          <div class="badge bg-secondary mb-1"><?= e($rg['categoria']) ?></div>
                          <div class="small"><?= e($rg['descripcion']) ?></div>
                          <small class="text-muted fst-italic">Por: <?= e($rg['usuario'] ?? 'Anónimo') ?></small>
                        </td>
                        <td class="text-end text-danger fw-semibold"><?= money($rg['importe']) ?></td>
                      </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
          <?php endif; ?>
        </div>


        <div class="alert alert-secondary mt-3 text-center">
            <?php $rep_neto = $rep_total_ingresos - $rep_total_gastos; ?>
            <span class="fw-bold fs-5">Balance del período: 
              <span class="<?= $rep_neto >= 0 ? 'text-success' : 'text-danger' ?>"><?= money($rep_neto) ?></span>
            </span>
        </div>

      <?php else: ?>
        <div class="alert alert-info">Seleccioná un rango de fechas y hacé clic en "Generar reporte".</div>
      <?php endif; ?>
    </div>

    <!-- CUENTA CORRIENTE -->
    <div class="tab-pane fade <?= paneActive('cc',$tab) ?>" id="cc" role="tabpanel" aria-labelledby="cc-tab">
      <form class="row g-2 mb-3" method="get" action="<?= url('caja.php') ?>">
        <input type="hidden" name="tab" value="cc">
        <div class="col-md-8">
          <label class="form-label">Cliente</label>
          <select name="cc_customer" class="form-select" onchange="this.form.submit()">
            <option value="0">— Seleccionar —</option>
            <?php foreach ($clientes as $c): ?>
              <option value="<?= (int)$c['id'] ?>" <?= $cc_customer===(int)$c['id']?'selected':'' ?>><?= e($c['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2 d-grid">
          <label class="form-label">&nbsp;</label>
          <button class="btn btn-outline-secondary">Ver movimientos</button>
        </div>
        <div class="col-md-2 d-grid">
          <label class="form-label">&nbsp;</label>
          <button type="submit" name="print" value="1" formtarget="_blank" class="btn btn-outline-primary">
            <i class="bi bi-printer"></i> Imprimir
          </button>
        </div>
      </form>

      <?php if ($cc_customer > 0): ?>
        <div class="d-flex justify-content-between align-items-center mb-2">
          <div class="fw-semibold">Saldo actual:</div>
          <div class="fs-5"><?= money($cc_saldo) ?></div>
        </div>

        <div class="table-responsive">
          <table class="table table-sm align-middle">
            <thead class="table-light">
            <tr>
              <th style="width:70px;">#</th>
              <th>Fecha</th>
              <th>Tipo</th>
              <th>Origen</th>
              <th class="text-end">Monto</th>
              <th class="text-end">Saldo</th>
              <th>Medio</th>
              <th>Cuenta</th>
              <th>Tercero</th>
              <th>Comprobante</th>
              <th>Referencia</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$cc_rows): ?>
              <tr><td colspan="11" class="text-center text-muted py-4">No hay movimientos.</td></tr>
            <?php else: foreach ($cc_rows as $m): ?>
              <tr>
                <td><?= (int)$m['id'] ?></td>
                <td><?= e($m['fecha']) ?></td>
                <td><?= e($m['tipo']) ?></td>
                <td><?= e($m['origen']) ?></td>
                <td class="text-end"><?= money($m['monto']) ?></td>
                <td class="text-end"><?= money($m['saldo_resultante']) ?></td>
                <td><?= $m['medio'] ? e($m['medio']) : '—' ?></td>
                <td><?= $m['bank_name'] ? e($m['bank_name']) : '—' ?></td>
                <td><?= $m['third_party_name'] ? e($m['third_party_name']) : '—' ?></td>
                <td>
                  <?php if ($m['voucher_path']): ?>
                    <a href="<?= url('voucher.php?file=' . urlencode($m['voucher_path'])) ?>" target="_blank" class="btn btn-sm btn-outline-primary">Ver</a>
                  <?php else: ?>
                    —
                  <?php endif; ?>
                </td>
                <td><?= e($m['referencia'] ?? $m['detalle']) ?></td>
              </tr>
            <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <div class="alert alert-info">Elegí un cliente para ver su cuenta corriente.</div>
      <?php endif; ?>
    </div>

    <!-- RESUMEN DIARIO -->
    <div class="tab-pane fade <?= paneActive('resumen',$tab) ?>" id="resumen" role="tabpanel" aria-labelledby="resumen-tab">
      <div class="mb-3">
        <div class="fw-semibold">Hoy (<?= e($hoy) ?>)</div>
      </div>

      <div class="row g-3">
        <div class="col-md-6">
          <h6>Cobros por medio</h6>
          <div class="table-responsive">
            <table class="table table-sm align-middle">
              <thead class="table-light"><tr><th>Medio</th><th class="text-end">Total</th></tr></thead>
              <tbody>
              <?php
                $totCobros = 0;
                if (!$resumenHoy): ?>
                  <tr><td colspan="2" class="text-center text-muted py-3">Sin cobros hoy.</td></tr>
                <?php else:
                  foreach ($resumenHoy as $r):
                    $totCobros += (float)$r['total']; ?>
                    <tr>
                      <td><?= e($r['medio']) ?></td>
                      <td class="text-end"><?= money($r['total']) ?></td>
                    </tr>
                  <?php endforeach; ?>
                  <tr class="table-light">
                    <td class="fw-semibold">TOTAL COBROS</td>
                    <td class="text-end fw-semibold"><?= money($totCobros) ?></td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <div class="col-md-6">
          <h6>Gastos por categoría</h6>
          <div class="table-responsive">
            <table class="table table-sm align-middle">
              <thead class="table-light"><tr><th>Categoría</th><th class="text-end">Total</th></tr></thead>
              <tbody>
              <?php
                $totGastos = 0;
                if (!$resumenGastosHoy): ?>
                  <tr><td colspan="2" class="text-center text-muted py-3">Sin gastos hoy.</td></tr>
                <?php else:
                  foreach ($resumenGastosHoy as $g):
                    $totGastos += (float)$g['total']; ?>
                    <tr>
                      <td><?= e($g['categoria']) ?></td>
                      <td class="text-end text-danger">-<?= money($g['total']) ?></td>
                    </tr>
                  <?php endforeach; ?>
                  <tr class="table-light">
                    <td class="fw-semibold">TOTAL GASTOS</td>
                    <td class="text-end fw-semibold text-danger">-<?= money($totGastos) ?></td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Resumen de transferencias bancarias -->
      <div class="row g-3 mt-2">
        <div class="col-md-6">
          <h6>Transferencias a cuentas propias</h6>
          <div class="table-responsive">
            <table class="table table-sm align-middle">
              <thead class="table-light"><tr><th>Tipo</th><th class="text-end">Total</th></tr></thead>
              <tbody>
                <tr>
                  <td>CUENTA GONZA + CUENTA PAO</td>
                  <td class="text-end"><?= money($transferencias_propias) ?></td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="col-md-6">
          <h6>Transferencias a cuentas de terceros</h6>
          <div class="table-responsive">
            <table class="table table-sm align-middle">
              <thead class="table-light"><tr><th>Tipo</th><th class="text-end">Total</th></tr></thead>
              <tbody>
                <tr>
                  <td>CUENTA TERCERO</td>
                  <td class="text-end"><?= money($transferencias_terceros) ?></td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <?php
        $neto = $totCobros - $totGastos;
      ?>
      <div class="mt-3">
        <div class="alert <?= $neto >= 0 ? 'alert-success' : 'alert-danger' ?> d-flex justify-content-between align-items-center">
          <span class="fw-semibold">Total neto del día (Cobros - Gastos):</span>
          <span class="fs-5"><?= money($neto) ?></span>
        </div>
      </div>

      <p class="small text-muted">Tip: Podés usar “Pagos recientes” y “Gastos” con rango de fechas para cortes no diarios.</p>
    </div>

  </div> <!-- tab-content -->
</div>
<?php include __DIR__ . '/../views/partials/footer.php'; ?>
