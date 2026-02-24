<?php
require_once __DIR__ . '/../app/auth.php';
require_login();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

// Exportar presupuesto en HTML (para imprimir a PDF)
if (isset($_GET['export_presupuesto'])) {
  if (!isset($_SESSION['pedido'])) {
    $_SESSION['pedido'] = [
      'customer_id' => null,
      'items' => [],
      'senia' => 0.0,
      'medio' => 'EFECTIVO',
      'observaciones' => '',
      'transporte_bonificado' => 0,
      'empresa_transporte' => '',
      'voucher_path' => null,
      'bank_account_id' => null,
      'third_party_name' => '',
      'fecha_entrega' => '',
      'dias_entrega' => '',
      'incluye_iva' => 1,
    ];
  }
  $P =& $_SESSION['pedido'];

  $order_id = (int)($_GET['order_id'] ?? 0);
  $logo = url('favicon-96x96.png');
  $fecha = date('d/m/Y H:i');

  if ($order_id) {
    $stmt = db()->prepare("SELECT o.*, c.nombre AS cliente_nombre, c.cuit_dni, c.telefono
                           FROM orders o
                           LEFT JOIN customers c ON c.id = o.customer_id
                           WHERE o.id = ?");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
      http_response_code(404);
      echo "<h1>No encontrado</h1><p>El presupuesto #{$order_id} no existe.</p>";
      exit;
    }
    
    // Si es manual, pisar cliente_nombre
    if (!$order['customer_id'] && !empty($order['cliente_manual'])) {
        $order['cliente_nombre'] = $order['cliente_manual'];
        $order['telefono'] = $order['cliente_manual_contacto'] ?: '-';
        $order['cuit_dni'] = '-';
    }

    $itemsStmt = db()->prepare("SELECT oi.cant, oi.precio_unit, oi.subtotal, p.codigo, p.nombre, p.metros_cuadrados, p.video_url
                                FROM order_items oi
                                JOIN products p ON p.id = oi.product_id
                                WHERE oi.order_id = ?");
    $itemsStmt->execute([$order_id]);
    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

    $incluye_iva = (int)($order['incluye_iva'] ?? 1);
    $iva_pct = 0.21;
    $iva_monto = $incluye_iva ? ((float)$order['total_neto'] * $iva_pct) : 0;
    $total_con_iva = (float)$order['total_neto'] + $iva_monto;
  } else {

    $order = [
      'cliente_nombre' => null,
      'cuit_dni' => null,
      'telefono' => null,
      'total_bruto' => pedido_total_bruto($P['items'] ?? []),
      'descuento' => 0,
      'total_neto' => pedido_total_bruto($P['items'] ?? []),
      'senia' => (float)($P['senia'] ?? 0),
      'saldo' => max(0, pedido_total_bruto($P['items'] ?? []) - (float)($P['senia'] ?? 0)),
      'observaciones' => trim($P['observaciones'] ?? ''),
      'incluye_iva' => (int)($P['incluye_iva'] ?? 1),
    ];
    $items = array_map(function ($it) {
      return [
        'codigo' => $it['codigo'],
        'nombre' => $it['nombre'],
        'cant' => $it['cant'],
        'precio_unit' => $it['precio'],
        'subtotal' => $it['subtotal'],
        'metros_cuadrados' => (float)($it['metros_cuadrados'] ?? 0),
        'video_url' => $it['video_url'] ?? '',
      ];
    }, $P['items'] ?? []);

    $cli = getCliente($P['customer_id'] ?? null);
    if ($cli) {
      $order['cliente_nombre'] = $cli['nombre'];
      $order['cuit_dni'] = $cli['cuit_dni'];
      $order['telefono'] = $cli['telefono'];
    } elseif (!empty($P['cliente_manual'])) {
      $order['cliente_nombre'] = $P['cliente_manual'];
      $order['cuit_dni'] = '-';
      $order['telefono'] = $P['cliente_manual_contacto'] ?: '-';
    }

    $incluye_iva = (int)$order['incluye_iva'];
    $iva_pct = 0.21;
    $iva_monto = $incluye_iva ? ((float)$order['total_neto'] * $iva_pct) : 0;
    $total_con_iva = (float)$order['total_neto'] + $iva_monto;
  }
  ?>
  <!doctype html>
  <html lang="es">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Presupuesto #<?= (int)$order_id ?></title>
    <style>
      body { font-family: Arial, sans-serif; color: #222; margin: 24px; }
      .header { display: flex; align-items: center; gap: 16px; border-bottom: 2px solid #0d6efd; padding-bottom: 12px; }
      .header img { width: 48px; height: 48px; }
      .title { font-size: 20px; font-weight: bold; }
      .sub { color: #555; }
      .section { margin-top: 16px; }
      table { width: 100%; border-collapse: collapse; margin-top: 8px; }
      th, td { border-bottom: 1px solid #ddd; padding: 8px; text-align: left; }
      th { background: #f6f6f6; }
      .text-end { text-align: right; }
      .totals { margin-top: 12px; float: right; min-width: 260px; }
      .totals div { display: flex; justify-content: space-between; padding: 4px 0; }
      .badge { display: inline-block; padding: 4px 8px; background: #0d6efd; color: #fff; border-radius: 4px; font-size: 12px; }
      .print { margin-top: 16px; }
      @media print { .print { display: none; } }
    </style>
  </head>
  <body>
    <div class="header">
      <img src="<?= e($logo) ?>" alt="Logo">
      <div>
        <div class="title">Presupuesto</div>
        <div class="sub">Universal Fitness SA</div>
      </div>
      <div style="margin-left:auto; text-align:right;">
        <div class="badge">#<?= (int)$order_id ?></div>
        <div class="sub"><?= e($fecha) ?></div>
      </div>
    </div>

    <div class="section">
      <strong>Cliente:</strong> <?= e($order['cliente_nombre']) ?><br>
      <strong>CUIT/DNI:</strong> <?= e($order['cuit_dni']) ?><br>
      <strong>Teléfono:</strong> <?= e($order['telefono']) ?>
    </div>

    <div class="section">
      <table>
        <thead>
          <tr>
            <th>Código</th>
            <th>Producto</th>
            <th class="text-end">m² Total</th>
            <th class="text-end">Cantidad</th>
            <th class="text-end">Precio</th>
            <th class="text-end">Subtotal</th>
          </tr>
        </thead>
        <tbody>
          <?php $total_m2 = 0; ?>
          <?php foreach ($items as $it): 
            $m2 = (float)($it['metros_cuadrados'] ?? 0);
            $cant = (float)$it['cant'];
            $row_m2 = $m2 * $cant;
            $total_m2 += $row_m2;
          ?>
            <tr>
              <td><?= e($it['codigo']) ?></td>
              <td>
                <?= e($it['nombre']) ?>
                <?php if ($m2 > 0): ?><div style="font-size:11px; color:#666;">(<?= $m2 ?> m²/u)</div><?php endif; ?>
                <?php if (!empty($it['video_url'])): ?>
                   <div style="margin-top:2px;">
                     <a href="<?= e($it['video_url']) ?>" target="_blank" style="font-size:12px; color:#d32f2f; text-decoration:none;">
                       ▶ Ver Video
                     </a>
                   </div>
                <?php endif; ?>
              </td>
              <td class="text-end"><?= $row_m2 > 0 ? number_format($row_m2, 4) : '-' ?></td>
              <td class="text-end"><?= $cant ?></td>
              <td class="text-end"><?= money($it['precio_unit']) ?></td>
              <td class="text-end"><?= money($it['subtotal']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <div class="totals">
        <div style="border-bottom: 1px solid #eee; margin-bottom: 5px;"><span>m² Total</span><strong><?= number_format($total_m2, 0) ?></strong></div>
        <div><span>Subtotal</span><strong><?= money($order['total_bruto']) ?></strong></div>
        <div><span>Descuento</span><strong><?= money($order['descuento']) ?></strong></div>
        <div><span>Total Neto</span><strong><?= money($order['total_neto']) ?></strong></div>
        <div><span>IVA (21%)</span><strong><?= money($iva_monto) ?></strong></div>
        <div><span>Total con IVA</span><strong><?= money($total_con_iva) ?></strong></div>
        <div><span>Seña</span><strong><?= money($order['senia']) ?></strong></div>
        <div><span>Saldo</span><strong><?= money($incluye_iva ? ($total_con_iva - (float)$order['senia']) : $order['saldo']) ?></strong></div>
      </div>
      <div style="clear: both;"></div>
    </div>

    <?php if (!empty($order['observaciones'])): ?>
      <div class="section">
        <strong>Observaciones:</strong> <?= e($order['observaciones']) ?>
      </div>
    <?php endif; ?>

    <div class="print">
      <button onclick="window.print()">Imprimir / Guardar PDF</button>
    </div>
  </body>
  </html>
  <?php
  exit;
}

// Asegurar columnas de transporte en orders
try {
  db()->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS transporte_bonificado TINYINT(1) DEFAULT 0");
  db()->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS empresa_transporte VARCHAR(100) NULL");
} catch (Throwable $e) {
  // Silenciar errores de compatibilidad
}

// Borrador de pedido en sesión
if (!isset($_SESSION['pedido'])) {
  $_SESSION['pedido'] = [
    'customer_id' => null,
    'items' => [], // ['product_id','codigo','nombre','precio','cant','subtotal']
    'senia' => 0.0,
    'medio' => 'EFECTIVO',
    'observaciones' => '',
    'transporte_bonificado' => 0,
    'empresa_transporte' => '',
    'voucher_path' => null,
    'bank_account_id' => null,
    'third_party_name' => '',
    'fecha_entrega' => '',
    'dias_entrega' => '',
    'incluye_iva' => 1,
    'type' => 'PEDIDO', // NEW: PEDIDO or PRESUPUESTO
    'cliente_manual' => '', // NEW: Para presupuestos manuales
    'cliente_manual_contacto' => '',
  ];
}
// Detectar si estamos creando un presupuesto o pedido normal
if (isset($_GET['type'])) {
    if ($_GET['type'] === 'presupuesto') {
        $_SESSION['pedido']['type'] = 'PRESUPUESTO';
    } else {
        $_SESSION['pedido']['type'] = 'PEDIDO';
    }
}
// Si no viene $_GET['type'] pero estamos empezando (items vacios, etc), asumimos Pedido normal?
// No, mejor dejar persistencia en sesion para los pasos intermedios que no envían ?type=...
// Pero si vengo "fresco" desde el menú, debería resetearse. 
// Normalmente el link del menú debería tener un parametro de reset.
// Por ahora, asumimos que los links de entrada tienen ?type=... o ?reset=1 (no implementado).
// Los links de Navbar apuntan a pedido_nuevo.php sin parms para "Pedidos".

$P =& $_SESSION['pedido'];

function pedido_total_bruto(array $items): float {
  $t = 0.0; foreach ($items as $it) $t += (float)$it['subtotal']; return $t;
}

$step = max(1, min(3, (int)($_GET['step'] ?? 1)));

// ---------- Acciones POST ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  // --- NUEVA ACCIÓN: SELECCIONAR CLIENTE MANUAL ---
  if (($_POST['action'] ?? '') === 'select_manual_customer') {
    $P['customer_id'] = null; // Clear ID so we use manual info
    $P['cliente_manual'] = trim($_POST['cliente_manual'] ?? '');
    $P['cliente_manual_contacto'] = trim($_POST['cliente_manual_contacto'] ?? '');
    if ($P['cliente_manual'] !== '') {
        $step = 2;
    } else {
        $error = "Debe ingresar al menos un nombre para el cliente manual.";
    }
  }

  // --- NUEVA ACCIÓN: GUARDAR PRESUPUESTO ---
  if (($_POST['action'] ?? '') === 'save_budget') {
    $error = '';
    // Si no hay customer_id, debe haber cliente manual
    if (!$P['customer_id'] && empty($P['cliente_manual'])) {
        $error = 'Debes seleccionar un cliente o ingresar uno manual.';
    }
    if (empty($P['items'])) $error = 'El presupuesto no tiene ítems.';

    if (empty($error)) {
      try {
        db()->beginTransaction();

        $total_bruto = pedido_total_bruto($P['items']);
        $descuento   = 0.0;
        $total_neto  = $total_bruto - $descuento;
        $senia       = 0; // Presupuestos no suelen tener seña, o sí? Asumamos 0 por ahora o la que pongan. 
        $saldo       = max(0, $total_neto - $senia);
        $incluye_iva = $P['incluye_iva'] ?? 1;

        // Crear pedido con estado PRESUPUESTO
        $sqlOrder = "INSERT INTO orders (customer_id, cliente_manual, cliente_manual_contacto, fecha, fecha_entrega, estado, total_bruto, descuento, total_neto, senia, saldo, observaciones, transporte_bonificado, empresa_transporte, incluye_iva)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
        db()->prepare($sqlOrder)->execute([
          $P['customer_id'], 
          $P['cliente_manual'] ?: null, 
          $P['cliente_manual_contacto'] ?: null, 
          date('Y-m-d H:i:s'), $P['fecha_entrega'] ?: null, 'PRESUPUESTO',
          $total_bruto, $descuento, $total_neto, $senia, $saldo, $P['observaciones'],
          $P['transporte_bonificado'], $P['empresa_transporte'] ?: null, $incluye_iva
        ]);
        $order_id = (int)db()->lastInsertId();

        // Ítems (sin reservar stock)
        $sqlItem = "INSERT INTO order_items (order_id, product_id, cant, precio_unit, subtotal) VALUES (?,?,?,?,?)";
        $stmtItem = db()->prepare($sqlItem);
        foreach ($P['items'] as $it) {
          $stmtItem->execute([$order_id, $it['product_id'], $it['cant'], $it['precio'], $it['subtotal']]);
        }

        db()->commit();
        unset($_SESSION['pedido']);
        header('Location: ' . url('presupuestos.php?ok=presupuesto_creado&id=' . $order_id));
        exit;

      } catch (Throwable $e) {
        db()->rollBack();
        $error = 'Error al guardar presupuesto: ' . $e->getMessage();
      }
    }
  }

  // --- ACCIÓN ORIGINAL: SELECCIONAR CLIENTE ---
  if (($_POST['action'] ?? '') === 'select_customer') {
    $P['customer_id'] = (int)($_POST['customer_id'] ?? 0);
    $step = 2;
  }

  // Agregar item por ID
  if (($_POST['action'] ?? '') === 'add_item') {
    $pid = (int)($_POST['product_id'] ?? 0);
    $cant = max(1, (float)($_POST['cant'] ?? 1));
    $stmt = db()->prepare("SELECT id, codigo, nombre, tipo, precio_std, metros_cuadrados, video_url FROM products WHERE id=? AND activo=1");
    $stmt->execute([$pid]);
    if ($prod = $stmt->fetch()) {
      if ($prod['tipo'] !== 'PT') {
        $error = "Solo se pueden vender productos terminados (PT).";
      } else {
        $precio = (float)$prod['precio_std'];
        $subtotal = $precio * $cant;
        $metros_cuadrados = (float)($prod['metros_cuadrados'] ?? 0);
        $video_url = $prod['video_url'] ?? '';

        $found = false;
        foreach ($P['items'] as &$it) {
          if ((int)$it['product_id'] === (int)$pid) {
            $it['cant'] += $cant;
            $it['subtotal'] = $it['cant'] * $it['precio'];
            $it['metros_cuadrados'] = $metros_cuadrados;
            $it['video_url'] = $video_url;
            $found = true; break;
          }
        } unset($it);

        if (!$found) {
          $P['items'][] = [
            'product_id' => (int)$prod['id'],
            'codigo'     => $prod['codigo'],
            'nombre'     => $prod['nombre'],
            'precio'     => $precio,
            'cant'       => $cant,
            'subtotal'   => $subtotal,
            'metros_cuadrados' => $metros_cuadrados,
            'video_url'  => $video_url,
          ];
        }
      }
    }
    $step = 2;
  }

  // Agregar item por CÓDIGO
  if (($_POST['action'] ?? '') === 'add_item_by_code') {
    $codigo = trim($_POST['codigo'] ?? '');
    $cant = max(1, (float)($_POST['cant'] ?? 1));
    if ($codigo !== '') {
      $stmt = db()->prepare("SELECT id, codigo, nombre, tipo, precio_std, metros_cuadrados, video_url FROM products WHERE codigo=? AND activo=1");
      $stmt->execute([$codigo]);
      if ($prod = $stmt->fetch()) {
        if ($prod['tipo'] !== 'PT') {
          $error = "El código ingresado no corresponde a un producto terminado (PT).";
        } else {
          $precio = (float)$prod['precio_std'];
          $subtotal = $precio * $cant;
          $metros_cuadrados = (float)($prod['metros_cuadrados'] ?? 0);
          $video_url = $prod['video_url'] ?? '';

          $found = false;
          foreach ($P['items'] as &$it) {
            if ((int)$it['product_id'] === (int)$prod['id']) {
              $it['cant'] += $cant;
              $it['subtotal'] = $it['cant'] * $it['precio'];
              $it['metros_cuadrados'] = $metros_cuadrados;
              $it['video_url'] = $video_url;
              $found = true; break;
            }
          } unset($it);

          if (!$found) {
            $P['items'][] = [
              'product_id' => (int)$prod['id'],
              'codigo'     => $prod['codigo'],
              'nombre'     => $prod['nombre'],
              'precio'     => $precio,
              'cant'       => $cant,
              'subtotal'   => $subtotal,
              'metros_cuadrados' => $metros_cuadrados,
              'video_url'  => $video_url,
            ];
          }
        }
      } else {
        $error = "No se encontró un producto activo con el código ingresado.";
      }
    }
    $step = 2;
  }

  // Quitar item
  if (($_POST['action'] ?? '') === 'remove_item') {
    $pid = (int)($_POST['product_id'] ?? 0);
    $P['items'] = array_values(array_filter($P['items'], fn($it) => (int)$it['product_id'] !== $pid));
    $step = 2;
  }

  // Actualizar items
  if (($_POST['action'] ?? '') === 'update_items') {
    $cants = $_POST['cant'] ?? [];
    $precios = $_POST['precio'] ?? [];
    foreach ($P['items'] as &$it) {
      $id = (string)$it['product_id'];
      if (isset($cants[$id], $precios[$id])) {
        $it['cant'] = max(1, (float)$cants[$id]);
        $it['precio'] = max(0, (float)$precios[$id]);
        $it['subtotal'] = $it['cant'] * $it['precio'];
      }
    } unset($it);
    $step = 2;
  }

  // Guardar pago/observaciones
  if (($_POST['action'] ?? '') === 'set_payment') {
    $P['senia'] = max(0, (float)($_POST['senia'] ?? 0));
    $P['medio'] = $_POST['medio'] ?? 'EFECTIVO';
    $P['observaciones'] = trim($_POST['observaciones'] ?? '');
    $P['transporte_bonificado'] = (int)($_POST['transporte_bonificado'] ?? 0);
    $P['empresa_transporte'] = trim($_POST['empresa_transporte'] ?? '');
    $P['bank_account_id'] = ($P['medio'] === 'TRANSFER') ? ((int)($_POST['bank_account_id'] ?? 0)) : null;
    $P['third_party_name'] = trim($_POST['third_party_name'] ?? '');
    $P['dias_entrega'] = trim($_POST['dias_entrega'] ?? '');
    $P['fecha_entrega'] = trim($_POST['fecha_entrega'] ?? '');
    $P['incluye_iva'] = isset($_POST['incluye_iva']) ? 1 : 0;
    
    // Calcular fecha de entrega si se eligió días
    if ($P['dias_entrega'] !== '' && $P['dias_entrega'] !== 'manual') {
      $dias = (int)$P['dias_entrega'];
      $P['fecha_entrega'] = date('Y-m-d', strtotime("+$dias days"));
    }
    
    // Procesar comprobante si se cargó
    $P['voucher_path'] = null;
    if (isset($_FILES['voucher']) && $_FILES['voucher']['error'] !== UPLOAD_ERR_NO_FILE && $_FILES['voucher']['name'] !== '') {
      try {
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
          mkdir(dirname($filepath), 0777, true);
        }
        
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
          throw new Exception('No se pudo guardar el comprobante.');
        }
        
        // Guardar solo el nombre del archivo
        $P['voucher_path'] = $filename;
      } catch (Exception $e) {
        $error = $e->getMessage();
      }
    }
    
    $step = 3;
  }

  // Confirmar pedido
  if (($_POST['action'] ?? '') === 'confirm_order') {
    $error = '';
    if (!$P['customer_id']) $error = 'Debes seleccionar un cliente.';
    if (empty($P['items'])) $error = 'El pedido no tiene ítems.';

    if (empty($error)) {
      try {
        db()->beginTransaction();

        $total_bruto = pedido_total_bruto($P['items']);
        $descuento   = 0.0;
        $total_neto  = $total_bruto - $descuento;
        $senia       = $P['senia'];
        $saldo       = max(0, $total_neto - $senia);
        $incluye_iva = $P['incluye_iva'] ?? 1;

        // Crear pedido
        $sqlOrder = "INSERT INTO orders (customer_id, fecha, fecha_entrega, estado, total_bruto, descuento, total_neto, senia, saldo, observaciones, transporte_bonificado, empresa_transporte, incluye_iva)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)";
        db()->prepare($sqlOrder)->execute([
          $P['customer_id'], date('Y-m-d H:i:s'), $P['fecha_entrega'] ?: null, 'CONFIRMADO',
          $total_bruto, $descuento, $total_neto, $senia, $saldo, $P['observaciones'],
          $P['transporte_bonificado'], $P['empresa_transporte'] ?: null, $incluye_iva
        ]);
        $order_id = (int)db()->lastInsertId();

        // Ítems
        $sqlItem = "INSERT INTO order_items (order_id, product_id, cant, precio_unit, subtotal) VALUES (?,?,?,?,?)";
        $stmtItem = db()->prepare($sqlItem);
        foreach ($P['items'] as $it) {
          $stmtItem->execute([$order_id, $it['product_id'], $it['cant'], $it['precio'], $it['subtotal']]);
        }

        // Reservas y OP
        $estadoFinal = 'LISTO_ENTREGA';
        $sqlGetProd = db()->prepare("SELECT id, tipo, stock_actual, stock_reservado FROM products WHERE id=? FOR UPDATE");
        $sqlUpdReserva = db()->prepare("UPDATE products SET stock_reservado = stock_reservado + ? WHERE id=?");
        $sqlOP = db()->prepare("INSERT INTO production_orders (order_id, product_pt_id, cantidad, estado) VALUES (?,?,?,'PENDIENTE')");

        foreach ($P['items'] as $it) {
          $pid = (int)$it['product_id'];
          $cant = (float)$it['cant'];
          $sqlGetProd->execute([$pid]);
          $prod = $sqlGetProd->fetch();
          if (!$prod) continue;

          if ($prod['tipo'] === 'PT') {
            $disponible = (float)$prod['stock_actual'] - (float)$prod['stock_reservado'];
            $aReservar = min($disponible, $cant);
            if ($aReservar > 0) {
              $sqlUpdReserva->execute([$aReservar, $pid]);
            }
            $faltante = $cant - $aReservar;
            if ($faltante > 0) {
              $sqlOP->execute([$order_id, $pid, $faltante]);
              $estadoFinal = 'EN_PRODUCCION';
            }
          }
        }

        db()->prepare("UPDATE orders SET estado=? WHERE id=?")->execute([$estadoFinal, $order_id]);

        // Ledger: CARGO total_neto
        $cid = (int)$P['customer_id'];
        $stmtSaldo = db()->prepare("SELECT COALESCE(SUM(CASE WHEN tipo='CARGO' THEN monto ELSE -monto END),0) AS saldo
                                    FROM customer_ledger WHERE customer_id=?");
        $stmtSaldo->execute([$cid]);
        $saldoAnterior = (float)($stmtSaldo->fetch()['saldo'] ?? 0);

        $saldoResult = $saldoAnterior + $total_neto;
        db()->prepare("INSERT INTO customer_ledger (customer_id, fecha, tipo, origen, referencia_id, detalle, monto, saldo_resultante)
                       VALUES (?,?,?,?,?,?,?,?)")
          ->execute([$cid, date('Y-m-d H:i:s'), 'CARGO', 'VENTA', $order_id, 'Venta pedido #'.$order_id, $total_neto, $saldoResult]);

        // Seña opcional
        if ($senia > 0) {
          db()->prepare("INSERT INTO payments (customer_id, order_id, fecha, medio, importe, referencia, voucher_path, bank_account_id, third_party_name)
                         VALUES (?,?,?,?,?,?,?,?,?)")
            ->execute([$cid, $order_id, date('Y-m-d H:i:s'), $P['medio'], $senia, 'Seña', $P['voucher_path'], $P['bank_account_id'], $P['third_party_name'] ?: null]);

          $saldoResult = $saldoResult - $senia;
          db()->prepare("INSERT INTO customer_ledger (customer_id, fecha, tipo, origen, referencia_id, detalle, monto, saldo_resultante)
                         VALUES (?,?,?,?,?,?,?,?)")
            ->execute([$cid, date('Y-m-d H:i:s'), 'ABONO', 'PAGO', $order_id, 'Seña pedido #'.$order_id, $senia, $saldoResult]);
        }

        db()->commit();
        unset($_SESSION['pedido']);
        header('Location: ' . url('pedido_nuevo.php') . '?step=3&ok=1&order_id=' . $order_id);
        exit;

      } catch (Throwable $e) {
        db()->rollBack();
        $error = 'Error al confirmar: ' . $e->getMessage();
      }
    }
    $step = 3;
  }
}

// Utilidad
function getCliente(?int $id) {
  if (!$id) return null;
  $s = db()->prepare("SELECT id, nombre, cuit_dni, telefono FROM customers WHERE id=?");
  $s->execute([$id]);
  return $s->fetch();
}

// ------------------- UI -------------------
include __DIR__ . '/../views/partials/header.php';
include __DIR__ . '/../views/partials/navbar.php';

// Paso 1: Cliente
if ($step === 1):
  $q = trim($_GET['q'] ?? '');
  $rows = [];
  if ($q !== '') {
    $s = db()->prepare("SELECT id, nombre, cuit_dni, telefono FROM customers
                        WHERE nombre LIKE ? OR cuit_dni LIKE ? OR telefono LIKE ?
                        ORDER BY nombre LIMIT 30");
    $like = "%$q%"; $s->execute([$like,$like,$like]);
    $rows = $s->fetchAll();
  }
  ?>
  <div class="container py-4">
    <h5 class="mb-3">Nuevo Pedido — Paso 1: Seleccionar cliente</h5>

    <form class="row g-2 mb-3" method="get" action="<?= url('pedido_nuevo.php') ?>">
      <input type="hidden" name="step" value="1">
      <div class="col-md-10"><input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Buscar cliente por nombre / CUIT-DNI / Teléfono"></div>
      <div class="col-md-2 d-grid"><button class="btn btn-primary">Buscar</button></div>
    </form>

    <div class="card shadow-sm">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead class="table-light"><tr><th>ID</th><th>Nombre</th><th>CUIT/DNI</th><th>Teléfono</th><th class="text-end">Acción</th></tr></thead>
            <tbody>
            <?php if (!$rows): ?>
              <tr><td colspan="5" class="text-center text-muted py-4">Buscá un cliente para continuar.</td></tr>
            <?php else: foreach ($rows as $r): ?>
              <tr>
                <td><?= (int)$r['id'] ?></td>
                <td><?= e($r['nombre']) ?></td>
                <td><?= e($r['cuit_dni']) ?></td>
                <td><?= e($r['telefono']) ?></td>
                <td class="text-end">
                  <form method="post" class="d-inline">
                    <input type="hidden" name="action" value="select_customer">
                    <input type="hidden" name="customer_id" value="<?= (int)$r['id'] ?>">
                    <button class="btn btn-sm btn-outline-primary">Seleccionar</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="mt-3">
      <a class="btn btn-outline-secondary" href="<?= url('clientes.php') ?>" target="_blank">+ Crear cliente nuevo</a>
    </div>

    <!-- Opción de cliente manual para presupuestos -->
    <?php if (isset($P['type']) && $P['type'] === 'PRESUPUESTO'): ?>
      <hr class="my-4">
      <div class="card shadow-sm border-warning">
        <div class="card-header bg-warning text-dark">
          <h6 class="mb-0"><i class="bi bi-person-exclamation"></i> Cliente Manual (Sin registrar)</h6>
        </div>
        <div class="card-body">
            <p class="small text-muted mb-2">Si el cliente no está registrado y solo desea hacer un presupuesto rápido, ingrese sus datos aquí.</p>
            <form method="post" class="row g-2 align-items-end">
                <input type="hidden" name="action" value="select_manual_customer">
                <div class="col-md-5">
                    <label class="form-label">Nombre / Razón Social *</label>
                    <input type="text" name="cliente_manual" class="form-control" required placeholder="Ej: Juan Pérez">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Contacto (Tel/Email)</label>
                    <input type="text" name="cliente_manual_contacto" class="form-control" placeholder="Opcional">
                </div>
                <div class="col-md-3">
                    <button class="btn btn-warning w-100">Usar manual</button>
                </div>
            </form>
        </div>
      </div>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php
// Paso 2: Ítems
if ($step === 2):
  // Check customer validation (manual or registered)
  if (!$P['customer_id'] && empty($P['cliente_manual'])) { header('Location: ' . url('pedido_nuevo.php') . '?step=1'); exit; }
  
  $cli = null; 
  if ($P['customer_id']) {
      $cli = getCliente($P['customer_id']);
      if (!$cli) {
          unset($_SESSION['pedido']['customer_id']);
          header('Location: ' . url('pedido_nuevo.php') . '?step=1'); 
          exit; 
      }
      $clientName = $cli['nombre'];
      $clientId = $cli['id'];
  } else {
      $clientName = ($P['cliente_manual'] ?? '') . ' (Manual)';
      $clientId = null;
  }
  
  $q = trim($_GET['q'] ?? '');

  // Ahora SIEMPRE mostramos productos: si no hay búsqueda, listamos los primeros 30 PT
  if ($q !== '') {
    $stmtProd = db()->prepare("SELECT id, codigo, nombre, precio_std, (stock_actual - stock_reservado) AS disponible
                               FROM products
                               WHERE activo=1 AND tipo='PT' AND (codigo LIKE ? OR nombre LIKE ?)
                               ORDER BY nombre LIMIT 30");
    $like = "%$q%"; $stmtProd->execute([$like,$like]);
  } else {
    $stmtProd = db()->query("SELECT id, codigo, nombre, precio_std, (stock_actual - stock_reservado) AS disponible
                             FROM products
                             WHERE activo=1 AND tipo='PT'
                             ORDER BY nombre LIMIT 30");
  }
  $prods = $stmtProd->fetchAll();
  $total = pedido_total_bruto($P['items']);
  ?>
  <div class="container py-4">
    <h5 class="mb-1">Nuevo Pedido — Paso 2: Ítems</h5>
    <div class="text-muted mb-3">Cliente: <strong><?= e($clientName) ?></strong> <?= $clientId ? '(ID ' . (int)$clientId . ')' : '' ?></div>

    <!-- Buscar productos -->
    <form class="row g-2 mb-3" method="get" action="<?= url('pedido_nuevo.php') ?>">
      <input type="hidden" name="step" value="2">
      <div class="col-md-8"><input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Buscar producto (código o nombre)"></div>
      <div class="col-md-2 d-grid"><button class="btn btn-outline-secondary">Buscar</button></div>
      <div class="col-md-2 d-grid">
        <a class="btn btn-outline-secondary" href="<?= url('pedido_nuevo.php') ?>?step=2">Limpiar</a>
      </div>
    </form>

    <!-- Agregar por CÓDIGO -->
    <form class="row g-2 mb-3" method="post">
      <input type="hidden" name="action" value="add_item_by_code">
      <div class="col-md-4">
        <input class="form-control" name="codigo" placeholder="Código exacto (ej: PT-MAQ1)">
      </div>
      <div class="col-md-2">
        <input class="form-control" type="number" name="cant" step="1" min="1" value="1" placeholder="Cant.">
      </div>
      <div class="col-md-2 d-grid">
        <button class="btn btn-primary">Agregar por código</button>
      </div>
      <div class="col-md-4 text-muted d-flex align-items-center">
        <small>Tip: si no buscás nada, se muestran los primeros 30 productos terminados.</small>
      </div>
      <small>AVISO: Todos los precios son SIN IVA. En caso de necesitarlo al cliente sumarle +21%</small>
    </form>

    <?php if (!empty($error)): ?>
      <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <!-- Listado de productos -->
    <div class="card shadow-sm mb-4">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead class="table-light"><tr><th>Código</th><th>Nombre</th><th class="text-end">Precio</th><th class="text-center">Disp.</th><th class="text-end">Agregar</th></tr></thead>
            <tbody>
            <?php if (!$prods): ?>
              <tr><td colspan="5" class="text-center text-muted py-3">No hay productos para mostrar.</td></tr>
            <?php else: foreach ($prods as $p): ?>
              <tr>
                <td><?= e($p['codigo']) ?></td>
                <td><?= e($p['nombre']) ?></td>
                <td class="text-end"><?= money($p['precio_std']) ?></td>
                <td class="text-center"><?= (float)$p['disponible'] ?></td>
                <td class="text-end">
                  <form method="post" class="d-inline">
                    <input type="hidden" name="action" value="add_item">
                    <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                    <div class="input-group input-group-sm" style="max-width:180px; margin-left:auto;">
                      <input type="number" name="cant" step="1" min="1" value="1" class="form-control">
                      <button class="btn btn-primary">Agregar</button>
                    </div>
                  </form>
                </td>
              </tr>
            <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Carrito -->
    <div class="card shadow-sm">
      <div class="card-body p-0">
        <form method="post">
          <input type="hidden" name="action" value="update_items">
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead class="table-light"><tr>
                <th>Código</th><th>Nombre</th>
                <th class="text-end" style="width:150px;">Precio</th>
                <th class="text-end" style="width:120px;">Cant.</th>
                <th class="text-end" style="width:150px;">Subtotal</th>
                <th class="text-end" style="width:90px;">Quitar</th>
              </tr></thead>
              <tbody>
              <?php if (!$P['items']): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">Aún no agregaste productos.</td></tr>
              <?php else: foreach ($P['items'] as $it): $id=(int)$it['product_id']; ?>
                <tr>
                  <td><?= e($it['codigo']) ?></td>
                  <td><?= e($it['nombre']) ?></td>
                  <td class="text-end">
                    <input class="form-control form-control-sm text-end" type="number" step="0.01" name="precio[<?= $id ?>]" value="<?= (float)$it['precio'] ?>">
                  </td>
                  <td class="text-end">
                    <input class="form-control form-control-sm text-end" type="number" step="1" min="1" name="cant[<?= $id ?>]" value="<?= (float)$it['cant'] ?>">
                  </td>
                  <td class="text-end"><?= money($it['subtotal']) ?></td>
                  <td class="text-end">
                    <form method="post" class="d-inline">
                      <input type="hidden" name="action" value="remove_item">
                      <input type="hidden" name="product_id" value="<?= $id ?>">
                      <button class="btn btn-sm btn-outline-danger">x</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
          <div class="p-3 d-flex justify-content-between align-items-center">
            <div>
              <a class="btn btn-outline-secondary" href="<?= url('pedido_nuevo.php') ?>?step=1">« Volver a Cliente</a>
            </div>
            <div class="d-flex align-items-center gap-3">
              <div class="fw-semibold">Total: <?= money($total) ?></div>
              <button class="btn btn-outline-primary">Actualizar</button>
              <a class="btn btn-primary" href="<?= url('pedido_nuevo.php') ?>?step=3">Siguiente »</a>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
<?php endif; ?>

<?php
// Paso 3: Pago + Confirmación
if ($step === 3):
    $order_id_export = (int)($_GET['order_id'] ?? 0);
    // DEBUG SESSION
    // echo "P ID: " . ($P['customer_id'] ?? 'NULL') . "\n";
    // echo "P MANUAL: " . ($P['cliente_manual'] ?? 'NULL') . "\n";
    // exit;
    
    // Validation: Must have customer (ID or manual) OR be an export view
  if (!$P['customer_id'] && empty($P['cliente_manual']) && !$order_id_export) { 
      header('Location: ' . url('pedido_nuevo.php') . '?step=1'); 
      exit; 
  }
  
  if (empty($P['items']) && !$order_id_export) { 
      header('Location: ' . url('pedido_nuevo.php') . '?step=2'); 
      exit; 
  }

  $cli = null;
  if ($P['customer_id']) {
      $cli = getCliente($P['customer_id']);
  } elseif (!empty($P['cliente_manual'])) {
      // Create fake CLI array for display
       $cli = [
        'nombre' => $P['cliente_manual'] . ' (Manual)',
        'cuit_dni' => '-',
        'telefono' => $P['cliente_manual_contacto'] ?: '-',
        'id' => null,
      ];
  }

  $total = !empty($P['items']) ? pedido_total_bruto($P['items']) : 0;
  $descuento = 0; 
  $neto = $total - $descuento;
  $senia = (float)($P['senia'] ?? 0); 
  $saldo = max(0, $neto - $senia);
  $items_view = $P['items'];

  if ($order_id_export) {
    // UPDATED QUERY FOR MANUAL CLIENTS
    $stmtOrder = db()->prepare("SELECT o.*, COALESCE(c.nombre, o.cliente_manual) AS cliente_nombre, c.cuit_dni, c.telefono, o.cliente_manual_contacto
                                FROM orders o
                                LEFT JOIN customers c ON c.id = o.customer_id
                                WHERE o.id = ?");
    $stmtOrder->execute([$order_id_export]);
    if ($order_export = $stmtOrder->fetch(PDO::FETCH_ASSOC)) {
      if (empty($order_export['cuit_dni'])) {
           // Si es manual
           $order_export['telefono'] = $order_export['cliente_manual_contacto'];
           $order_export['cuit_dni'] = '-';
      }
      $cli = [
        'nombre' => $order_export['cliente_nombre'],
        'cuit_dni' => $order_export['cuit_dni'],
        'telefono' => $order_export['telefono'],
        'id' => $order_export['customer_id'] ?? null,
      ];
      $total = (float)$order_export['total_bruto'];
      $descuento = (float)$order_export['descuento'];
      $neto = (float)$order_export['total_neto'];
      $senia = (float)$order_export['senia'];
      $saldo = (float)$order_export['saldo'];

      $stmtItems = db()->prepare("SELECT oi.cant, oi.precio_unit, oi.subtotal, p.codigo, p.nombre
                                  FROM order_items oi
                                  JOIN products p ON p.id = oi.product_id
                                  WHERE oi.order_id = ?");
      $stmtItems->execute([$order_id_export]);
      $items_view = $stmtItems->fetchAll(PDO::FETCH_ASSOC);
    }
  } else {
    // Si NO es export, definir $cli correctamente para display
    if ($P['customer_id']) {
        $cli = getCliente($P['customer_id']);
    } elseif (!empty($P['cliente_manual'])) {
        $cli = [
            'nombre' => $P['cliente_manual'] . ' (Manual)',
            'cuit_dni' => '-',
            'telefono' => $P['cliente_manual_contacto'] ?: '-',
            'id' => null,
        ];
    }
  }
  
  // Cargar cuentas bancarias
  $bank_accounts = db()->query("SELECT id, nombre FROM bank_accounts WHERE activo=1 ORDER BY nombre")->fetchAll();

  // Prepare display variables
  $clientName = $cli['nombre'] ?? 'Desconocido';
  $clientId   = $cli['id'] ?? null;

  ?>
  <div class="container py-4">
    <h5 class="mb-1">Nuevo Pedido — Paso 3: Pago y Confirmación</h5>
    <div class="text-muted mb-3">Cliente: <strong><?= e($clientName) ?></strong> <?= $clientId ? '(ID ' . (int)$clientId . ')' : '' ?></div>

    <?php if (!empty($error)): ?>
      <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="row g-3">
      <div class="col-lg-7">
        <div class="card shadow-sm">
          <div class="card-body">
            <h6 class="mb-3">Resumen de Ítems</h6>
            <div class="table-responsive">
              <table class="table table-sm align-middle">
                <thead class="table-light"><tr><th>Código</th><th>Nombre</th><th class="text-end">Precio</th><th class="text-end">Cant</th><th class="text-end">Subtotal</th></tr></thead>
                <tbody>
                <?php foreach ($items_view as $it): ?>
                  <tr>
                    <td><?= e($it['codigo']) ?></td>
                    <td><?= e($it['nombre']) ?></td>
                    <td class="text-end"><?= money($it['precio'] ?? $it['precio_unit']) ?></td>
                    <td class="text-end"><?= (float)$it['cant'] ?></td>
                    <td class="text-end"><?= money($it['subtotal']) ?></td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>

            <div class="d-flex justify-content-end">
              <div class="text-end">
                <div>Subtotal: <strong><?= money($total) ?></strong></div>
                <div>Descuento: <strong><?= money($descuento) ?></strong></div>
                <div>Total Neto: <strong><?= money($neto) ?></strong></div>
                <div>Seña: <strong><?= money($senia) ?></strong></div>
                <div>Saldo: <strong><?= money($saldo) ?></strong></div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-lg-5">
        <div class="card shadow-sm mb-3">
          <div class="card-body">
            <h6 class="mb-3">Pago (opcional)</h6>
            <form method="post" class="row g-2" enctype="multipart/form-data">
              <input type="hidden" name="action" value="set_payment">
              <div class="col-6">
                <label class="form-label">Seña</label>
                <input class="form-control" type="number" step="0.01" name="senia" value="<?= (float)$P['senia'] ?>">
              </div>
              <div class="col-6">
                <label class="form-label">Medio</label>
                <select name="medio" class="form-select" id="medioSelect" onchange="toggleTransferFields()">
                  <?php foreach (['EFECTIVO','DEBITO','TRANSFER','CREDITO','NC'] as $m): ?>
                    <option value="<?= $m ?>" <?= $P['medio']===$m?'selected':'' ?>><?= $m ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              
              <!-- Cuenta bancaria para transferencias -->
              <div class="col-12" id="bankAccountDiv" style="display: <?= $P['medio'] === 'TRANSFER' ? 'block' : 'none' ?>;">
                <label class="form-label">Cuenta Bancaria *</label>
                <select name="bank_account_id" class="form-select" id="bankAccountSelect" onchange="toggleThirdPartyField()">
                  <option value="">— Seleccionar —</option>
                  <?php foreach ($bank_accounts as $ba): ?>
                    <option value="<?= (int)$ba['id'] ?>" <?= $P['bank_account_id'] === (int)$ba['id'] ? 'selected' : '' ?>><?= e($ba['nombre']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              
              <!-- Tercero si es CUENTA TERCERO -->
              <div class="col-12" id="thirdPartyDiv" style="display: none;">
                <label class="form-label">Tercero (nombre de quien recibe) *</label>
                <input type="text" name="third_party_name" class="form-control" value="<?= e($P['third_party_name']) ?>" placeholder="Ej: Juan Pérez, Acerlot, etc.">
              </div>
              
              <!-- Comprobante para transferencias -->
              <div class="col-12" id="voucherDiv" style="display: <?= $P['medio'] === 'TRANSFER' ? 'block' : 'none' ?>;">
                <label class="form-label">Comprobante (PDF o Foto)</label>
                <input type="file" name="voucher" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                <small class="text-muted">PDF, JPG o PNG. Máximo 5MB</small>
                <?php if (!empty($P['voucher_path'])): ?>
                  <div class="mt-1"><small class="text-success">✓ Comprobante cargado</small></div>
                <?php endif; ?>
              </div>
              
              <div class="col-12">
                <label class="form-label">Observaciones</label>
                <textarea name="observaciones" class="form-control" rows="2"><?= e($P['observaciones']) ?></textarea>
              </div>
              
              <div class="col-12">
                <label class="form-label">Fecha de Entrega</label>
                <div class="row g-2">
                  <div class="col-6">
                    <select name="dias_entrega" class="form-select" id="diasEntrega" onchange="toggleFechaManual()">
                      <option value="">— Seleccionar —</option>
                      <option value="30" <?= $P['dias_entrega'] === '30' ? 'selected' : '' ?>>30 días</option>
                      <option value="45" <?= $P['dias_entrega'] === '45' ? 'selected' : '' ?>>45 días</option>
                      <option value="60" <?= $P['dias_entrega'] === '60' ? 'selected' : '' ?>>60 días</option>
                      <option value="manual" <?= $P['dias_entrega'] === 'manual' ? 'selected' : '' ?>>Fecha manual</option>
                    </select>
                  </div>
                  <div class="col-6" id="fechaManualDiv" style="display: <?= $P['dias_entrega'] === 'manual' ? 'block' : 'none' ?>;">
                    <input type="date" name="fecha_entrega" class="form-control" value="<?= e($P['fecha_entrega']) ?>">
                  </div>
                </div>
                <?php if ($P['fecha_entrega'] && $P['dias_entrega'] !== 'manual'): ?>
                  <small class="text-muted">Calculado: <?= e($P['fecha_entrega']) ?></small>
                <?php endif; ?>
              </div>
              
              <div class="col-12">
                <label class="form-label">Empresa de Transporte</label>
                <input type="text" name="empresa_transporte" class="form-control" value="<?= e($P['empresa_transporte']) ?>" placeholder="Ej: Andreani, OCA, etc.">
              </div>
              <div class="col-12">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="transporte_bonificado" value="1" id="transp_bonif" <?= $P['transporte_bonificado'] ? 'checked' : '' ?>>
                  <label class="form-check-label" for="transp_bonif">
                    Transporte bonificado
                  </label>
                </div>
              </div>
              <div class="col-12">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="incluye_iva" value="1" id="incluye_iva" <?= ($P['incluye_iva'] ?? 1) ? 'checked' : '' ?>>
                  <label class="form-check-label" for="incluye_iva">
                    Incluir IVA (21%)
                  </label>
                </div>
              </div>
              <div class="col-12 d-flex justify-content-between">
                <a class="btn btn-outline-secondary" href="<?= url('pedido_nuevo.php') ?>?step=2">« Volver a Ítems</a>
                <button class="btn btn-outline-primary">Guardar cambios</button>
              </div>
            </form>
            
            <script>
              function toggleFechaManual() {
                const select = document.getElementById('diasEntrega');
                const div = document.getElementById('fechaManualDiv');
                div.style.display = select.value === 'manual' ? 'block' : 'none';
              }
              
              function toggleTransferFields() {
                const medio = document.getElementById('medioSelect').value;
                const bankDiv = document.getElementById('bankAccountDiv');
                const voucherDiv = document.getElementById('voucherDiv');
                const thirdPartyDiv = document.getElementById('thirdPartyDiv');
                
                if (medio === 'TRANSFER') {
                  bankDiv.style.display = 'block';
                  voucherDiv.style.display = 'block';
                  toggleThirdPartyField();
                } else {
                  bankDiv.style.display = 'none';
                  voucherDiv.style.display = 'none';
                  thirdPartyDiv.style.display = 'none';
                }
              }
              
              function toggleThirdPartyField() {
                const bankAccountSelect = document.getElementById('bankAccountSelect');
                const thirdPartyDiv = document.getElementById('thirdPartyDiv');
                
                if (!bankAccountSelect) return;
                
                const selectedOption = bankAccountSelect.options[bankAccountSelect.selectedIndex];
                const accountName = selectedOption ? selectedOption.text : '';
                
                if (accountName === 'CUENTA TERCERO') {
                  thirdPartyDiv.style.display = 'block';
                } else {
                  thirdPartyDiv.style.display = 'none';
                }
              }
              
              // Inicializar al cargar la página
              document.addEventListener('DOMContentLoaded', function() {
                toggleTransferFields();
                toggleFechaManual();
              });
            </script>
          </div>
        </div>

        <div class="card shadow-sm">
          <div class="card-body">
            <h6 class="mb-3">Confirmación</h6>
            <?php 
            // Si es tipo presupuesto O tiene cliente manual, forzamos modo presupuesto
            $isBudget = (isset($P['type']) && $P['type'] === 'PRESUPUESTO') || !empty($P['cliente_manual']);
            ?>
            
            <?php if ($isBudget): ?>
              <a class="btn btn-outline-success w-100 mb-2" target="_blank"
                 href="<?= url('pedido_nuevo.php?export_presupuesto=1') ?>">
                Previsualizar PDF
              </a>
              <!-- Botones Presupuesto -->
              <form method="post" class="mb-2">
                <input type="hidden" name="action" value="save_budget">
                <button class="btn btn-primary w-100">
                  <i class="bi bi-save"></i> Guardar Presupuesto
                </button>
              </form>
            <?php else: ?>
              <!-- Botones Pedido Normal -->
              <?php if ($order_id_export): ?>
                <a class="btn btn-outline-success w-100 mb-2" target="_blank"
                   href="<?= url('pedido_nuevo.php?export_presupuesto=1&order_id=' . $order_id_export) ?>">
                  Exportar presupuesto
                </a>
              <?php else: ?>
                <a class="btn btn-outline-success w-100 mb-2" target="_blank"
                   href="<?= url('pedido_nuevo.php?export_presupuesto=1') ?>">
                  Exportar presupuesto
                </a>
              <?php endif; ?>
              
              <form method="post">
                <input type="hidden" name="action" value="confirm_order">
                <button class="btn btn-primary w-100">Confirmar pedido</button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
<?php endif; ?>

<?php include __DIR__ . '/../views/partials/footer.php'; ?>
