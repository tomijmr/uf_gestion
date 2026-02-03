<?php
require_once __DIR__ . '/../app/auth.php';
require_login();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

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
             LEFT JOIN clientes c ON c.id=o.cliente_id
             $whereSql";
$st = db()->prepare($sqlCount);
$st->execute($params);
$total = (int)$st->fetchColumn();
$pages = max(1, (int)ceil($total / $limit));

$sql = "SELECT po.id, po.order_id, po.product_pt_id, po.cantidad, po.estado, po.fecha_ini, po.fecha_fin,
               p.codigo, p.nombre, c.nombre as cliente_nombre
        FROM production_orders po
        JOIN products p ON p.id=po.product_pt_id
        LEFT JOIN orders o ON o.id=po.order_id
        LEFT JOIN clientes c ON c.id=o.cliente_id
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
                  Ver BOM
                </button>

                <?php if ($r['estado']==='PENDIENTE'): ?>
                  <form method="post" class="d-inline" onsubmit="return confirm('¿Iniciar OP #<?= (int)$r['id'] ?>?');">
                    <input type="hidden" name="action" value="start">
                    <input type="hidden" name="op_id" value="<?= (int)$r['id'] ?>">
                    <button class="btn btn-sm btn-primary">Iniciar</button>
                  </form>

                <?php elseif ($r['estado']==='EN_CURSO'): ?>
                  <form method="post" class="d-inline" onsubmit="return confirm('¿Finalizar OP #<?= (int)$r['id'] ?>?');">
                    <input type="hidden" name="action" value="finish">
                    <input type="hidden" name="op_id" value="<?= (int)$r['id'] ?>">
                    <button class="btn btn-sm btn-success">Finalizar</button>
                  </form>

                <?php elseif ($r['estado']==='FINALIZADA' && $r['order_id']): ?>
                  <form method="post" class="d-inline" onsubmit="return confirm('¿Entregar OP #<?= (int)$r['id'] ?> al cliente?');">
                    <input type="hidden" name="action" value="deliver">
                    <input type="hidden" name="op_id" value="<?= (int)$r['id'] ?>">
                    <button class="btn btn-sm btn-outline-success">Entregar</button>
                  </form>

                <?php else: ?>
                  <span class="text-muted small">Sin acciones</span>
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
<?php include __DIR__ . '/../views/partials/footer.php'; ?>
