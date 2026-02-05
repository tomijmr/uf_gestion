<?php
require_once __DIR__ . '/../app/auth.php';
require_login();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/produccion.php';
require_once __DIR__ . '/../app/ticket_produccion.php';

// Source - https://stackoverflow.com/a/21429652
// Posted by Fancy John, modified by community. See post 'Timeline' for change history
// Retrieved 2026-02-04, License - CC BY-SA 4.0

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);


// QR: cambio de estado desde escaneo
if (isset($_GET['qr'])) {
  $qr = trim((string)$_GET['qr']);
  $stmt = db()->prepare("SELECT id, estado_actual FROM production_orders WHERE qr_code = ? LIMIT 1");
  $stmt->execute([$qr]);
  $po = $stmt->fetch(PDO::FETCH_ASSOC);

  $error = null;
  $mensaje = null;
  $next_estado = null;
  $estado_actual = null;

  if (!$po) {
    $error = 'QR inválido o no asociado a una OP.';
  } else {
    $po_id = (int)$po['id'];
    $estado_actual = obtener_estado_actual($po_id);
    if (!$estado_actual) {
      $next_estado = 'SELECCION';
    } else {
      $idx = array_search($estado_actual['estado'], ESTADOS_PRODUCCION, true);
      if ($idx !== false && isset(ESTADOS_PRODUCCION[$idx + 1])) {
        $next_estado = ESTADOS_PRODUCCION[$idx + 1];
      }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'qr_avanzar') {
      $operario_id = (int)($_POST['operario_id'] ?? 0);
      $notas = trim((string)($_POST['notas'] ?? ''));

      if (!$next_estado) {
        $error = 'La OP ya está en el último estado.';
      } elseif (!$operario_id) {
        $error = 'Debe seleccionar un operario.';
      } else {
        // Finalizar estado actual si está en curso
        if ($estado_actual && !$estado_actual['timestamp_fin']) {
          db()->prepare("UPDATE production_states SET timestamp_fin = NOW() WHERE id = ?")
            ->execute([$estado_actual['id']]);
        }

        $res = cambiar_estado_produccion($po_id, $next_estado, $operario_id, $notas);
        if ($res['ok'] ?? false) {
          $mensaje = $res['message'] ?? 'Estado actualizado.';
          $estado_actual = obtener_estado_actual($po_id);
          $idx = array_search($estado_actual['estado'], ESTADOS_PRODUCCION, true);
          $next_estado = ($idx !== false && isset(ESTADOS_PRODUCCION[$idx + 1])) ? ESTADOS_PRODUCCION[$idx + 1] : null;
        } else {
          $error = $res['error'] ?? 'No se pudo cambiar el estado.';
        }
      }
    }
  }

  $empleados = db()->query("SELECT id, nombre FROM employees WHERE activo=1 ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

  include __DIR__ . '/../views/partials/header.php';
  include __DIR__ . '/../views/partials/navbar.php';
  ?>
  <div class="container py-4">
    <div class="row justify-content-center">
      <div class="col-md-8 col-lg-6">
        <div class="card shadow-sm">
          <div class="card-header">
            <h5 class="mb-0">QR Producción</h5>
          </div>
          <div class="card-body">
            <?php if ($error): ?>
              <div class="alert alert-danger"><?= e($error) ?></div>
            <?php endif; ?>
            <?php if ($mensaje): ?>
              <div class="alert alert-success"><?= e($mensaje) ?></div>
            <?php endif; ?>

            <?php if ($po && !$error): ?>
              <p class="mb-2"><strong>OP #<?= (int)$po['id'] ?></strong></p>
              <p class="text-muted mb-3">Estado actual: <strong><?= e($estado_actual['estado'] ?? 'SIN INICIAR') ?></strong></p>

              <?php if ($next_estado): ?>
                <div class="alert alert-info">Siguiente estado: <strong><?= e($next_estado) ?></strong></div>
                <form method="POST">
                  <input type="hidden" name="action" value="qr_avanzar">
                  <div class="mb-3">
                    <label class="form-label">Operario *</label>
                    <select class="form-select" name="operario_id" required>
                      <option value="">-- Seleccionar --</option>
                      <?php foreach ($empleados as $emp): ?>
                        <option value="<?= (int)$emp['id'] ?>"><?= e($emp['nombre']) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="mb-3">
                    <label class="form-label">Notas</label>
                    <textarea class="form-control" name="notas" rows="3"></textarea>
                  </div>
                  <button type="submit" class="btn btn-primary w-100">Finalizar y avanzar</button>
                </form>
              <?php else: ?>
                <div class="alert alert-success mb-0">La OP ya está en DESPACHO.</div>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
  <?php
  include __DIR__ . '/../views/partials/footer.php';
  exit;
}

// AJAX: Imprimir ticket de producción
if (isset($_GET['print_ticket'])) {
  $po_id = (int)$_GET['po_id'];
  echo imprimir_ticket_produccion($po_id, 'html');
  exit;
}

// AJAX: Imprimir ticket por estado
if (isset($_GET['print_ticket_estado'])) {
  $po_id = (int)($_GET['po_id'] ?? 0);
  $estado = trim($_GET['estado'] ?? '');
  if ($po_id && $estado) {
    echo generar_ticket_por_estado($po_id, $estado);
  } else {
    echo '<div style="padding:20px;">Error: Faltan parámetros</div>';
  }
  exit;
}

// AJAX: Obtener empleados activos
if (isset($_GET['_ajax_empleados'])) {
  header('Content-Type: application/json');
  $empleados = db()->query("SELECT id, nombre FROM employees WHERE activo=1 ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
  echo json_encode($empleados);
  exit;
}

// AJAX: Obtener timeline de estados de producción
if (isset($_GET['_ajax_timeline'])) {
  header('Content-Type: application/json');
  $po_id = (int)($_GET['po_id'] ?? 0);
  
  if (!$po_id) {
    echo json_encode(['error' => 'ID de OP inválido']);
    exit;
  }
  
  try {
    $stmt = db()->prepare("
      SELECT ps.*, e.nombre as operario_nombre
      FROM production_states ps
      LEFT JOIN employees e ON e.id = ps.operario_id
      WHERE ps.production_order_id = ?
      ORDER BY ps.timestamp_inicio ASC
    ");
    $stmt->execute([$po_id]);
    $estados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['estados' => $estados]);
  } catch (Exception $e) {
    echo json_encode(['error' => 'Error al obtener historial: ' . $e->getMessage()]);
  }
  exit;
}

error_reporting(E_ALL);
ini_set('display_errors', 1);


// ---------- Utilidades ----------
function fetchBom(int $pt_id): array {
  $s = db()->prepare("SELECT b.id AS bom_id, b.component_id, p.codigo, p.nombre, p.unidad, b.cant_por_unidad,
                             p.stock_actual, p.stock_reservado
                      FROM product_bom b
                      JOIN products p ON p.id=b.component_id
                      WHERE b.product_pt_id=?");
  $s->execute([$pt_id]);
  return $s->fetchAll(PDO::FETCH_ASSOC);
}

function fetchOrderMachines(int $order_id): array {
  $s = db()->prepare("SELECT oi.id, oi.product_id, oi.cant, p.codigo, p.nombre, p.tipo, p.stock_actual
                      FROM order_items oi
                      JOIN products p ON p.id=oi.product_id
                      WHERE oi.order_id=? AND p.tipo='PT'
                      ORDER BY p.nombre");
  $s->execute([$order_id]);
  return $s->fetchAll(PDO::FETCH_ASSOC);
}

function reserveForOrdersIfPossible(int $pt_id): void {
  // Heurística MVP: reservar para órdenes EN_PRODUCCION que requieran este PT
  $sOrders = db()->prepare("
    SELECT DISTINCT o.id
    FROM orders o
    JOIN order_items oi ON oi.order_id=o.id
    WHERE o.estado='EN_PRODUCCION' AND oi.product_id=? 
    ORDER BY o.fecha ASC, o.id ASC
  ");
  $sOrders->execute([$pt_id]);
  $orderRows = $sOrders->fetchAll(PDO::FETCH_ASSOC);
  if (!$orderRows) return;
  $orderIds = array_map(fn($r) => (int)$r['id'], $orderRows);

  // Lock del producto PT
  $sProd = db()->prepare("SELECT stock_actual, stock_reservado FROM products WHERE id=? FOR UPDATE");
  $sProd->execute([$pt_id]);
  $prod = $sProd->fetch(PDO::FETCH_ASSOC);
  if (!$prod) return;
  $disponible = (float)$prod['stock_actual'] - (float)$prod['stock_reservado'];
  if ($disponible <= 0) return;

  $updReserva = db()->prepare("UPDATE products SET stock_reservado = stock_reservado + ? WHERE id=?");
  $getItemsForOrder = db()->prepare("
      SELECT p.id as product_id, p.tipo, oi.cant,
             (p.stock_actual - p.stock_reservado) AS disponible_global
      FROM order_items oi
      JOIN products p ON p.id=oi.product_id
      WHERE oi.order_id=? AND p.tipo='PT'
  ");

  foreach ($orderIds as $oid) {
    if ($disponible <= 0) break;
    $getItemsForOrder->execute([$oid]);
    $its = $getItemsForOrder->fetchAll(PDO::FETCH_ASSOC);
    if (!$its) continue;

    $faltantesPorProd = [];
    foreach ($its as $it) {
      $need = (float)$it['cant'];
      $disp = max(0, (float)$it['disponible_global']);
      $falt = max(0, $need - $disp);
      if ($falt > 0) $faltantesPorProd[(int)$it['product_id']] = $falt;
    }

    if (!$faltantesPorProd) {
      // Ya puede cubrirse todo -> marcar listo entrega
      db()->prepare("UPDATE orders SET estado='LISTO_ENTREGA' WHERE id=?")->execute([$oid]);
      continue;
    }

    if (isset($faltantesPorProd[$pt_id]) && $disponible > 0) {
      $aRes = min($disponible, $faltantesPorProd[$pt_id]);
      if ($aRes > 0) {
        $updReserva->execute([$aRes, $pt_id]);
        $disponible -= $aRes;
        // Re-evaluación mínima: marcar LISTO_ENTREGA si ahora puede cubrirse (heurística MVP)
        $getItemsForOrder->execute([$oid]);
        $its2 = $getItemsForOrder->fetchAll(PDO::FETCH_ASSOC);
        $aunFalta = false;
        foreach ($its2 as $it2) {
          $need2 = (float)$it2['cant'];
          $disp2 = max(0, (float)$it2['disponible_global']);
          if ($disp2 < $need2) { $aunFalta = true; break; }
        }
        if (!$aunFalta) {
          db()->prepare("UPDATE orders SET estado='LISTO_ENTREGA' WHERE id=?")->execute([$oid]);
        }
      }
    }
  }
}

function column_exists(string $table, string $column): bool {
  $stmt = db()->prepare(
    "SELECT 1
     FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = ?
       AND COLUMN_NAME = ?
     LIMIT 1"
  );
  $stmt->execute([$table, $column]);
  return (bool)$stmt->fetchColumn();
}

// ---------- Acciones ----------
$flash_ok = '';
$flash_err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  $op_id  = (int)($_POST['op_id'] ?? 0);
  $userId = $_SESSION['user']['id'] ?? null;
  $userName = $_SESSION['user']['nombre'] ?? ($_SESSION['user']['email'] ?? 'Desconocido');

  // ---------- INICIAR ----------
  if ($action === 'start') {
    try {
      db()->beginTransaction();

      $s = db()->prepare("SELECT po.id, po.product_pt_id, po.cantidad, po.estado, po.order_id, p.nombre AS pt_nombre
                          FROM production_orders po
                          JOIN products p ON p.id=po.product_pt_id
                          WHERE po.id=? FOR UPDATE");
      $s->execute([$op_id]);
      $op = $s->fetch(PDO::FETCH_ASSOC);
      if (!$op) throw new Exception("OP no encontrada");
      if ($op['estado'] !== 'PENDIENTE') throw new Exception("La OP no está en estado PENDIENTE");

      $bom = fetchBom((int)$op['product_pt_id']);
      if (!$bom) throw new Exception("El PT no tiene BOM definido");
      $updMP  = db()->prepare("UPDATE products SET stock_actual = stock_actual - ? WHERE id=?");
      $insMov = db()->prepare("INSERT INTO stock_moves (fecha, tipo, motivo, product_id, cantidad, referencia_tipo, referencia_id, observaciones)
                               VALUES (NOW(), 'SALIDA', 'PROD_CONSUMO', ?, ?, 'OP', ?, 'Consumo OP')");

      // Chequear disponibilidad
      foreach ($bom as $row) {
        $need = (float)$row['cant_por_unidad'] * (float)$op['cantidad'];
        if ($need <= 0) continue;
        if ((float)$row['stock_actual'] < $need) {
          throw new Exception("Stock insuficiente de {$row['codigo']} ({$row['nombre']}). Necesario: $need, Actual: {$row['stock_actual']}");
        }
      }

      // Consumir MP
      foreach ($bom as $row) {
        $need = (float)$row['cant_por_unidad'] * (float)$op['cantidad'];
        if ($need <= 0) continue;
        $updMP->execute([$need, (int)$row['component_id']]);
        $insMov->execute([(int)$row['component_id'], $need, $op_id]);
      }

      db()->prepare("UPDATE production_orders SET estado='EN_CURSO', fecha_ini=NOW() WHERE id=?")->execute([$op_id]);

      db()->commit();
      $flash_ok = "OP #{$op_id} iniciada. Se consumió BOM.";
    } catch (Throwable $e) {
      db()->rollBack();
      $flash_err = "No se pudo iniciar la OP: " . $e->getMessage();
    }
  }

  // ---------- FINALIZAR ----------
  if ($action === 'finish') {
    try {
      db()->beginTransaction();

      $s = db()->prepare("SELECT po.id, po.product_pt_id, po.cantidad, po.estado, po.order_id, p.nombre AS pt_nombre
                          FROM production_orders po
                          JOIN products p ON p.id=po.product_pt_id
                          WHERE po.id=? FOR UPDATE");
      $s->execute([$op_id]);
      $op = $s->fetch(PDO::FETCH_ASSOC);
      if (!$op) throw new Exception("OP no encontrada");
      if ($op['estado'] !== 'EN_CURSO') throw new Exception("La OP debe estar EN_CURSO para finalizar");

      $updPT  = db()->prepare("UPDATE products SET stock_actual = stock_actual + ? WHERE id=?");
      $insMov = db()->prepare("INSERT INTO stock_moves (fecha, tipo, motivo, product_id, cantidad, referencia_tipo, referencia_id, observaciones)
                               VALUES (NOW(), 'ENTRADA', 'PROD_ALTA', ?, ?, 'OP', ?, 'Alta PT de OP')");
      $updPT->execute([(float)$op['cantidad'], (int)$op['product_pt_id']]);
      $insMov->execute([(int)$op['product_pt_id'], (float)$op['cantidad'], $op_id]);

      db()->prepare("UPDATE production_orders SET estado='FINALIZADA', fecha_fin=NOW() WHERE id=?")->execute([$op_id]);

      // Intentar auto-reservar para pedidos EN_PRODUCCION que esperen este PT
      reserveForOrdersIfPossible((int)$op['product_pt_id']);

      db()->commit();
      $flash_ok = "OP #{$op_id} finalizada. PT ingresado a stock y reservas actualizadas.";
    } catch (Throwable $e) {
      db()->rollBack();
      $flash_err = "No se pudo finalizar la OP: " . $e->getMessage();
    }
  }

  // ---------- ENTREGAR (al cliente) ----------
  if ($action === 'deliver') {
    try {
      db()->beginTransaction();

      // Lock OP row
      $s = db()->prepare("SELECT po.id, po.order_id, po.product_pt_id, po.cantidad, po.estado, p.nombre AS pt_nombre
                          FROM production_orders po
                          JOIN products p ON p.id=po.product_pt_id
                          WHERE po.id=? FOR UPDATE");
      $s->execute([$op_id]);
      $op = $s->fetch(PDO::FETCH_ASSOC);
      if (!$op) throw new Exception("OP no encontrada");
      if ($op['estado'] !== 'FINALIZADA') throw new Exception("La OP debe estar FINALIZADA para entregar");
      if (empty($op['order_id'])) throw new Exception("No hay pedido asociado a la OP");

      $pt_id = (int)$op['product_pt_id'];
      $qty   = (float)$op['cantidad'];
      if ($qty <= 0) throw new Exception("Cantidad inválida");

      // Verificar stock suficiente del PT
      $sProd = db()->prepare("SELECT stock_actual FROM products WHERE id=? FOR UPDATE");
      $sProd->execute([$pt_id]);
      $prod = $sProd->fetch(PDO::FETCH_ASSOC);
      if (!$prod) throw new Exception("Producto PT no encontrado");
      if ((float)$prod['stock_actual'] < $qty) {
        throw new Exception("Stock insuficiente del PT para entrega. Necesario: $qty, Actual: {$prod['stock_actual']}");
      }

      // Descontar stock del PT
      $updPT  = db()->prepare("UPDATE products SET stock_actual = stock_actual - ? WHERE id=?");
      $updPT->execute([$qty, $pt_id]);

      // Insertar movimiento de stock (ENTREGA_CLIENTE)
      $insMov = db()->prepare("INSERT INTO stock_moves (fecha, tipo, motivo, product_id, cantidad, referencia_tipo, referencia_id, observaciones)
                               VALUES (NOW(), 'SALIDA', 'ENTREGA_CLIENTE', ?, ?, 'ORDER', ?, ?)");
      $obs = "Entrega a cliente desde OP #{$op_id}. Usuario: {$userName} (id: {$userId})";
      $insMov->execute([$pt_id, $qty, (int)$op['order_id'], $obs]);

      // Marcar pedido como ENTREGADO
      db()->prepare("UPDATE orders SET estado='ENTREGADO' WHERE id=?")->execute([(int)$op['order_id']]);

      // Registrar en audit_logs quién hizo la entrega
      $insLog = db()->prepare("INSERT INTO audit_logs (user_id, accion, entidad, entidad_id, detalle) VALUES (?, ?, ?, ?, ?)");
      $accion = "ENTREGA_OP";
      $entidad = "production_orders";
      $detalle = json_encode([
        'op_id' => (int)$op_id,
        'order_id' => (int)$op['order_id'],
        'product_pt_id' => $pt_id,
        'cantidad' => $qty,
        'usuario_id' => $userId,
        'usuario_nombre' => $userName,
        'obs' => $obs
      ]);
      $insLog->execute([$userId, $accion, $entidad, (int)$op_id, $detalle]);

      // Mantenemos production_orders en FINALIZADA (no cambiamos a un estado no definido en el enum)
      // Si querés marcar que se entregó, podés agregar una columna `fecha_entrega` o `entregado_por` en production_orders.

      db()->commit();
      $flash_ok = "OP #{$op_id} entregada correctamente. Se descontó stock del PT y se registró la entrega (usuario: {$userName}).";
    } catch (Throwable $e) {
      db()->rollBack();
      $flash_err = "No se pudo entregar la OP: " . $e->getMessage();
    }
  }

  // ---------- SISTEMA DE PRODUCCIÓN AVANZADO ----------
  
  // Cambiar estado de producción
  if ($action === 'cambiar_estado') {
    try {
      $po_id = (int)($_POST['po_id'] ?? 0);
      $nuevo_estado = trim($_POST['nuevo_estado'] ?? '');
      $operario_id = !empty($_POST['operario_id']) ? (int)$_POST['operario_id'] : null;
      $notas = trim($_POST['notas'] ?? '');
      $redirect_url = trim($_POST['redirect'] ?? '');
      
      $resultado = cambiar_estado_produccion($po_id, $nuevo_estado, $operario_id, $notas);
      
      if ($resultado['ok']) {
        $flash_ok = $resultado['message'];
        
        // Abrir ticket automáticamente en nueva ventana
        $estado_cambio = $resultado['nuevo_estado'] ?? $nuevo_estado;
        $_SESSION['abrir_ticket'] = [
          'po_id' => $po_id,
          'estado' => $estado_cambio
        ];
        
        if ($redirect_url) {
          header("Location: {$redirect_url}");
          exit;
        }
      } else {
        $flash_err = $resultado['error'];
      }
    } catch (Throwable $e) {
      $flash_err = "Error al cambiar estado: " . $e->getMessage();
    }
  }
  
  // Aprobar QC de un estado
  if ($action === 'aprobar_qc') {
    try {
      $state_id = (int)($_POST['state_id'] ?? 0);
      $user_id = $_SESSION['user']['id'] ?? null;
      $redirect_url = trim($_POST['redirect'] ?? '');
      
      if (!$user_id) throw new Exception("Usuario no identificado");
      
      aprobar_qc_estado($state_id, $user_id);
      $flash_ok = "QC aprobado correctamente";
      
      if ($redirect_url) {
        header("Location: {$redirect_url}");
        exit;
      }
    } catch (Throwable $e) {
      $flash_err = "Error al aprobar QC: " . $e->getMessage();
    }
  }
  
  // Finalizar estado actual
  if ($action === 'finalizar_estado') {
    try {
      $po_id = (int)($_POST['po_id'] ?? 0);
      $estado_actual = obtener_estado_actual($po_id);
      
      if (!$estado_actual) {
        throw new Exception("No hay estado actual para finalizar");
      }
      
      if ($estado_actual['timestamp_fin']) {
        throw new Exception("El estado actual ya está finalizado");
      }
      
      db()->prepare("UPDATE production_states SET timestamp_fin = NOW() WHERE id = ?")
        ->execute([$estado_actual['id']]);
      
      $flash_ok = "Estado {$estado_actual['estado']} finalizado correctamente";
    } catch (Throwable $e) {
      $flash_err = "Error al finalizar estado: " . $e->getMessage();
    }
  }
}

// ---------- Filtros / listado ----------
$estado = trim($_GET['estado'] ?? '');
$q      = trim($_GET['q'] ?? '');

$validEstados = ['PENDIENTE','EN_CURSO','FINALIZADA','OBSERVADA'];
$where = [];
$params = [];

if ($estado !== '' && in_array($estado, $validEstados, true)) {
  $where[] = "po.estado = ?";
  $params[] = $estado;
}
if ($q !== '') {
  $where[] = "(p.codigo LIKE ? OR p.nombre LIKE ? OR po.id = ?)";
  $params[] = "%$q%";
  $params[] = "%$q%";
  $params[] = ctype_digit($q) ? (int)$q : 0;
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$page  = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$off   = ($page - 1) * $limit;

$sqlCount = "SELECT COUNT(*) total
             FROM production_orders po
             JOIN products p ON p.id=po.product_pt_id
             LEFT JOIN orders o ON o.id=po.order_id
             LEFT JOIN customers c ON c.id=o.customer_id
             $whereSql";
$st = db()->prepare($sqlCount);
$st->execute($params);
$total = (int)$st->fetchColumn();
$pages = max(1, (int)ceil($total / $limit));

$estadoActualSelect = column_exists('production_orders', 'estado_actual')
  ? "po.estado_actual,"
  : "NULL as estado_actual,";

$sql = "SELECT po.id, po.order_id, po.product_pt_id, po.cantidad, po.estado, po.fecha_ini, po.fecha_fin,
               {$estadoActualSelect} p.codigo, p.nombre, c.nombre as cliente_nombre
        FROM production_orders po
        JOIN products p ON p.id=po.product_pt_id
        LEFT JOIN orders o ON o.id=po.order_id
        LEFT JOIN customers c ON c.id=o.customer_id
        $whereSql
        ORDER BY FIELD(po.estado, 'PENDIENTE','EN_CURSO','FINALIZADA','OBSERVADA'), po.id DESC
        LIMIT $limit OFFSET $off";
$st = db()->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

function page_url(int $p): string {
  $qs = $_GET; $qs['page'] = $p;
  return url('op.php') . '?' . http_build_query($qs);
}

function badge_op(string $s): string {
  $map = [
    'PENDIENTE'  => 'secondary',
    'EN_CURSO'   => 'warning',
    'FINALIZADA' => 'success',
    'OBSERVADA'  => 'danger',
  ];
  $cls = $map[$s] ?? 'secondary';
  return '<span class="badge bg-'.$cls.'">'.e($s).'</span>';
}

// ---------- UI ----------
include __DIR__ . '/../views/partials/header.php';
include __DIR__ . '/../views/partials/navbar.php';
?>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">Órdenes de Producción</h5>
    <a class="btn btn-outline-secondary" href="<?= url('pedidos.php') ?>">Ir a Pedidos</a>
  </div>

  <?php if ($flash_ok): ?><div class="alert alert-success"><?= e($flash_ok) ?></div><?php endif; ?>
  <?php if ($flash_err): ?><div class="alert alert-danger"><?= e($flash_err) ?></div><?php endif; ?>

  <form class="row g-2 mb-3" method="get" action="<?= url('op.php') ?>">
    <div class="col-md-4">
      <input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Buscar por código/nombre PT o #OP">
    </div>
    <div class="col-md-3">
      <select name="estado" class="form-select">
        <option value="">Todos los estados</option>
        <?php foreach ($validEstados as $e): ?>
          <option value="<?= $e ?>" <?= $estado===$e?'selected':'' ?>><?= $e ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2 d-grid">
      <button class="btn btn-outline-secondary">Filtrar</button>
    </div>
  </form>

  <div class="card shadow-sm">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th style="width:90px;">#OP</th>
              <th>Máquina</th>
              <th class="text-end">Cant.</th>
              <th class="text-center">Estado</th>
              <th>Cliente / Pedido</th>
              <th style="width:330px;" class="text-end">Acciones</th>
            </tr>
          </thead>
          <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="6" class="text-center text-muted py-4">No hay OPs.</td></tr>
          <?php else: foreach ($rows as $r): ?>
            <tr>
              <td>#<?= (int)$r['id'] ?></td>
              <td><?= e($r['codigo']) ?> — <?= e($r['nombre']) ?></td>
              <td class="text-end"><?= (float)$r['cantidad'] ?></td>
              <td class="text-center"><?= badge_op($r['estado']) ?></td>
              <td><?= $r['cliente_nombre'] ? e($r['cliente_nombre']) : '-' ?> <br><small class="text-muted"><?= $r['order_id'] ? '#'.(int)$r['order_id'] : '-' ?></small></td>
              <td class="text-end">
                <button class="btn btn-sm btn-outline-secondary" type="button"
                        data-bs-toggle="collapse" data-bs-target="#bom<?= (int)$r['id'] ?>">
                  Ver Detalle
                </button>
                
                <!-- Botones Sistema Avanzado -->
                <button class="btn btn-sm btn-outline-info" type="button"
                        onclick="abrirModalEstado(<?= (int)$r['id'] ?>, '<?= (int)$r['id'] ?>', '<?= e($r['estado_actual'] ?? '') ?>')">
                  Cambiar Estado
                </button>
                
                <button class="btn btn-sm btn-outline-secondary" type="button"
                        onclick="verTimeline(<?= (int)$r['id'] ?>, '<?= (int)$r['id'] ?>')">
                  Ver Timeline
                </button>
                
                <?php if ($r['estado_actual']): ?>
                <a class="btn btn-sm btn-outline-success" 
                   href="<?= url('op.php?print_ticket_estado=1&po_id=' . (int)$r['id'] . '&estado=' . urlencode($r['estado_actual'])) ?>" 
                   target="_blank"
                   title="Ticket del estado actual: <?= e($r['estado_actual']) ?>">
                  📋 Ticket Estado
                </a>
                <?php endif; ?>
                
                <a class="btn btn-sm btn-outline-primary" href="<?= url('op.php?print_ticket=1&po_id=' . (int)$r['id']) ?>" target="_blank">
                  Imprimir Ticket
                </a>

                <?php if ($r['estado']==='PENDIENTE'): ?>
                  <form method="post" class="d-inline" onsubmit="return confirm('¿Iniciar OP #<?= (int)$r['id'] ?>?');">
                    <input type="hidden" name="action" value="start">
                    <input type="hidden" name="op_id" value="<?= (int)$r['id'] ?>">
                    <button class="btn btn-sm btn-primary">Iniciar (Antiguo)</button>
                  </form>

                <?php elseif ($r['estado']==='EN_CURSO'): ?>
                  <form method="post" class="d-inline" onsubmit="return confirm('¿Finalizar OP #<?= (int)$r['id'] ?>?');">
                    <input type="hidden" name="action" value="finish">
                    <input type="hidden" name="op_id" value="<?= (int)$r['id'] ?>">
                    <button class="btn btn-sm btn-success">Finalizar (Antiguo)</button>
                  </form>

                <?php elseif ($r['estado']==='FINALIZADA' && $r['order_id']): ?>
                  <form method="post" class="d-inline" onsubmit="return confirm('¿Entregar OP #<?= (int)$r['id'] ?> al cliente?');">
                    <input type="hidden" name="action" value="deliver">
                    <input type="hidden" name="op_id" value="<?= (int)$r['id'] ?>">
                    <button class="btn btn-sm btn-outline-success">Entregar</button>
                  </form>

                <?php endif; ?>
              </td>
            </tr>

            <tr class="collapse" id="bom<?= (int)$r['id'] ?>">
              <td colspan="6" class="bg-light">
                <div class="p-3">
                  <div class="row">
                    <!-- Máquinas del Pedido -->
                    <div class="col-md-6">
                      <div class="fw-semibold mb-2">Máquinas en el Pedido</div>
                      <?php $machines = $r['order_id'] ? fetchOrderMachines((int)$r['order_id']) : []; ?>
                      <?php if ($machines): ?>
                        <div class="table-responsive">
                          <table class="table table-sm mb-0">
                            <thead><tr><th>Código</th><th>Máquina</th><th class="text-end">Cant.</th><th class="text-end">Stock</th></tr></thead>
                            <tbody>
                              <?php foreach ($machines as $m): ?>
                                <tr>
                                  <td><?= e($m['codigo']) ?></td>
                                  <td><?= e($m['nombre']) ?></td>
                                  <td class="text-end"><?= (float)$m['cant'] ?></td>
                                  <td class="text-end"><span class="badge bg-<?= (float)$m['stock_actual'] >= (float)$m['cant'] ? 'success' : 'warning' ?>"><?= (float)$m['stock_actual'] ?></span></td>
                                </tr>
                              <?php endforeach; ?>
                            </tbody>
                          </table>
                        </div>
                      <?php else: ?>
                        <div class="text-muted small">Sin máquinas en este pedido.</div>
                      <?php endif; ?>
                    </div>

                    <!-- Componentes (BOM) de esta máquina -->
                    <div class="col-md-6">
                      <div class="fw-semibold mb-2">Componentes de esta máquina (x <?= (float)$r['cantidad'] ?>)</div>
                      <?php $bom = fetchBom((int)$r['product_pt_id']); ?>
                      <?php if ($bom): ?>
                        <div class="table-responsive">
                          <table class="table table-sm mb-0">
                            <thead><tr><th>Código</th><th>Componente</th><th class="text-end">Nec.</th><th class="text-end">Stock</th></tr></thead>
                            <tbody>
                              <?php foreach ($bom as $b): 
                                $need = (float)$b['cant_por_unidad'] * (float)$r['cantidad']; ?>
                                <tr>
                                  <td><?= e($b['codigo']) ?></td>
                                  <td><?= e($b['nombre']) ?></td>
                                  <td class="text-end"><?= $need ?></td>
                                  <td class="text-end"><span class="badge bg-<?= (float)$b['stock_actual'] >= $need ? 'success' : 'danger' ?>"><?= (float)$b['stock_actual'] ?></span></td>
                                </tr>
                              <?php endforeach; ?>
                            </tbody>
                          </table>
                        </div>
                      <?php else: ?>
                        <div class="text-muted small">Sin componentes en la BOM.</div>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              </td>
            </tr>

          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Paginación -->
  <div class="d-flex justify-content-between align-items-center mt-3">
    <div class="small text-muted">
      Mostrando <?= $rows ? ($off + 1) : 0 ?>–<?= $off + count($rows) ?> de <?= $total ?>
    </div>
    <nav>
      <ul class="pagination pagination-sm mb-0">
        <li class="page-item <?= $page<=1 ? 'disabled' : '' ?>">
          <a class="page-link" href="<?= $page>1 ? page_url($page-1) : '#' ?>">&laquo; Anterior</a>
        </li>
        <li class="page-item disabled"><span class="page-link">Página <?= $page ?> / <?= $pages ?></span></li>
        <li class="page-item <?= $page>=$pages ? 'disabled' : '' ?>">
          <a class="page-link" href="<?= $page<$pages ? page_url($page+1) : '#' ?>">Siguiente &raquo;</a>
        </li>
      </ul>
    </nav>
  </div>
</div>

<!-- Modal Cambiar Estado de Producción -->
<div class="modal fade" id="modalCambiarEstado" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Cambiar Estado de Producción</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="cambiar_estado">
        <input type="hidden" name="po_id" id="modal_po_id">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">OP #<span id="modal_po_number"></span></label>
          </div>
          <div class="mb-3">
            <label class="form-label">Estado Actual</label>
            <input type="text" class="form-control" id="modal_estado_actual" readonly>
          </div>
          <div class="mb-3">
            <label class="form-label">Nuevo Estado *</label>
            <select class="form-select" name="nuevo_estado" id="modal_nuevo_estado" required>
              <option value="">-- Seleccionar --</option>
              <option value="SELECCION">1. SELECCIÓN</option>
              <option value="CORTE">2. CORTE (descuenta perfiles/chapas)</option>
              <option value="ARMADO">3. ARMADO</option>
              <option value="SOLDADURA">4. SOLDADURA</option>
              <option value="LIMPIEZA">5. LIMPIEZA</option>
              <option value="PINTURA">6. PINTURA (descuenta pinturas) - Requiere QC en ARMADO</option>
              <option value="ENSAMBLE">7. ENSAMBLE (descuenta rodamientos/poleas/tapizados)</option>
              <option value="QC_FINAL">8. QC FINAL</option>
              <option value="DESPACHO">9. DESPACHO</option>
            </select>
            <div class="form-text">Los estados deben seguir el orden secuencial</div>
          </div>
          <div class="mb-3">
            <label class="form-label">Operario *</label>
            <select class="form-select" name="operario_id" id="modal_operario_id" required>
              <option value="">-- Cargando operarios... --</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Notas</label>
            <textarea class="form-control" name="notas" rows="3" placeholder="Observaciones opcionales..."></textarea>
          </div>
          <div id="modal_advertencias" class="alert alert-warning d-none"></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Cambiar Estado</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Ver Timeline de Estados -->
<div class="modal fade" id="modalTimeline" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Historial de Producción - OP #<span id="timeline_po_number"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="timeline_content">
          <div class="text-center py-5">
            <div class="spinner-border text-primary" role="status">
              <span class="visually-hidden">Cargando...</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
// Abrir ticket automáticamente si hay uno pendiente
<?php if (isset($_SESSION['abrir_ticket'])): ?>
window.addEventListener('DOMContentLoaded', function() {
  const ticketData = <?= json_encode($_SESSION['abrir_ticket']) ?>;
  const url = `<?= url('op.php') ?>?print_ticket_estado=1&po_id=${ticketData.po_id}&estado=${encodeURIComponent(ticketData.estado)}`;
  window.open(url, '_blank', 'width=800,height=600');
  <?php unset($_SESSION['abrir_ticket']); ?>
});
<?php endif; ?>

// Cargar operarios al abrir modal
function cargarOperarios() {
  fetch('?_ajax_empleados')
    .then(r => r.json())
    .then(data => {
      const sel = document.getElementById('modal_operario_id');
      sel.innerHTML = '<option value="">-- Seleccionar operario --</option>';
      data.forEach(emp => {
        sel.innerHTML += `<option value="${emp.id}">${emp.nombre}</option>`;
      });
    })
    .catch(() => {
      document.getElementById('modal_operario_id').innerHTML = '<option value="">Error al cargar</option>';
    });
}

// Abrir modal cambiar estado
function abrirModalEstado(poId, poNumber, estadoActual) {
  document.getElementById('modal_po_id').value = poId;
  document.getElementById('modal_po_number').textContent = poNumber;
  document.getElementById('modal_estado_actual').value = estadoActual || 'Sin iniciar';
  document.getElementById('modal_nuevo_estado').value = '';
  document.getElementById('modal_advertencias').classList.add('d-none');
  
  cargarOperarios();
  
  const modal = new bootstrap.Modal(document.getElementById('modalCambiarEstado'));
  modal.show();
}

// Validar cambio de estado
document.getElementById('modal_nuevo_estado')?.addEventListener('change', function() {
  const estadoActual = document.getElementById('modal_estado_actual').value;
  const nuevoEstado = this.value;
  const advertencias = document.getElementById('modal_advertencias');
  
  let msgs = [];
  
  if (nuevoEstado === 'CORTE') {
    msgs.push('⚠️ Al avanzar a CORTE se descontará automáticamente el stock de perfiles, chapas y tubos del BOM.');
  }
  if (nuevoEstado === 'PINTURA') {
    msgs.push('⚠️ Requiere que el estado ARMADO esté aprobado por QC.');
    msgs.push('⚠️ Al avanzar a PINTURA se descontará automáticamente el stock de pinturas y químicos.');
  }
  if (nuevoEstado === 'ENSAMBLE') {
    msgs.push('⚠️ Al avanzar a ENSAMBLE se descontará automáticamente el stock de rodamientos, poleas, tornillería y tapizados.');
  }
  
  if (msgs.length > 0) {
    advertencias.innerHTML = msgs.join('<br>');
    advertencias.classList.remove('d-none');
  } else {
    advertencias.classList.add('d-none');
  }
});

// Ver timeline de producción
function verTimeline(poId, poNumber) {
  document.getElementById('timeline_po_number').textContent = poNumber;
  const content = document.getElementById('timeline_content');
  content.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary"></div></div>';
  
  const modal = new bootstrap.Modal(document.getElementById('modalTimeline'));
  modal.show();
  
  fetch(`?_ajax_timeline&po_id=${poId}`)
    .then(r => r.json())
    .then(data => {
      if (data.error) {
        content.innerHTML = `<div class="alert alert-danger">${data.error}</div>`;
        return;
      }
      
      let html = '<div class="timeline">';
      
      if (!data.estados || data.estados.length === 0) {
        html += '<p class="text-muted">No hay historial de estados aún.</p>';
      } else {
        data.estados.forEach((estado, idx) => {
          const isCurrent = !estado.timestamp_fin;
          const isApproved = estado.qc_aprobado == 1;
          
          html += `
            <div class="timeline-item ${isCurrent ? 'active' : ''}">
              <div class="timeline-marker ${isCurrent ? 'bg-primary' : 'bg-success'}">
                ${idx + 1}
              </div>
              <div class="timeline-content">
                <div class="d-flex justify-content-between align-items-start">
                  <div>
                    <h6 class="mb-1">${estado.estado}</h6>
                    <small class="text-muted">
                      Operario: ${estado.operario_nombre || 'N/A'}<br>
                      Inicio: ${estado.timestamp_inicio}<br>
                      ${estado.timestamp_fin ? 'Fin: ' + estado.timestamp_fin : '<span class="badge bg-primary">EN CURSO</span>'}
                    </small>
                  </div>
                  <div class="text-end">
                    ${isApproved ? '<span class="badge bg-success">✓ QC Aprobado</span>' : ''}
                    ${isCurrent && !isApproved && (estado.estado === 'ARMADO' || estado.estado === 'QC_FINAL') ? 
                      `<form method="POST" class="d-inline" onsubmit="return confirm('¿Aprobar QC para este estado?')">
                        <input type="hidden" name="action" value="aprobar_qc">
                        <input type="hidden" name="state_id" value="${estado.id}">
                        <button type="submit" class="btn btn-sm btn-success">Aprobar QC</button>
                      </form>` : ''}
                  </div>
                </div>
                ${estado.notas ? `<div class="mt-2"><small><strong>Notas:</strong> ${estado.notas}</small></div>` : ''}
              </div>
            </div>
          `;
        });
      }
      
      html += '</div>';
      
      // CSS para timeline
      html += `
        <style>
          .timeline { position: relative; padding: 20px 0; }
          .timeline-item { position: relative; padding-left: 50px; padding-bottom: 30px; }
          .timeline-item:before { content: ''; position: absolute; left: 19px; top: 40px; bottom: -10px; width: 2px; background: #dee2e6; }
          .timeline-item:last-child:before { display: none; }
          .timeline-marker { position: absolute; left: 0; top: 0; width: 40px; height: 40px; border-radius: 50%; color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; }
          .timeline-content { background: #f8f9fa; padding: 15px; border-radius: 8px; }
          .timeline-item.active .timeline-content { background: #e7f3ff; border: 2px solid #0d6efd; }
        </style>
      `;
      
      content.innerHTML = html;
    })
    .catch(err => {
      content.innerHTML = '<div class="alert alert-danger">Error al cargar el timeline</div>';
    });
}
</script>

<?php include __DIR__ . '/../views/partials/footer.php'; ?>
