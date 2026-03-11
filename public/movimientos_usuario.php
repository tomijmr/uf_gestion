<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../app/auth.php';
require_login();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

// Obtener lista de usuarios
$usuarios = db()->query("SELECT id, nombre FROM users ORDER BY nombre")->fetchAll();

$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$desde = $_GET['desde'] ?? date('Y-m-01');
$hasta = $_GET['hasta'] ?? date('Y-m-d');

$movimientos = [];
if ($user_id > 0) {
    // Movimientos: compras, gastos, pagos, ventas
        $sql = "
       SELECT CAST('COMPRA' AS CHAR) AS tipo, p.id, p.fecha,
         CAST(p.proveedor AS CHAR) AS entidad,
         p.total AS importe,
         CAST(p.notas AS CHAR) AS detalle
       FROM purchases p WHERE p.created_by = ? AND DATE(p.fecha) BETWEEN ? AND ?
       UNION ALL
       SELECT CAST('GASTO' AS CHAR) AS tipo, e.id, e.fecha,
         CAST(e.categoria AS CHAR) AS entidad,
         e.importe,
         CAST(e.descripcion AS CHAR) AS detalle
       FROM cash_expenses e WHERE e.created_by = ? AND DATE(e.fecha) BETWEEN ? AND ?
       UNION ALL
       SELECT CAST('PAGO' AS CHAR) AS tipo, pay.id, pay.fecha,
         CAST(c.nombre AS CHAR) AS entidad,
         pay.importe,
         CAST(pay.referencia AS CHAR) AS detalle
       FROM payments pay JOIN customers c ON c.id = pay.customer_id WHERE pay.created_by = ? AND DATE(pay.fecha) BETWEEN ? AND ?
       UNION ALL
       SELECT CAST('VENTA' AS CHAR) AS tipo, o.id, o.fecha,
         CAST(c.nombre AS CHAR) AS entidad,
         o.total_neto AS importe,
         CAST(o.estado AS CHAR) AS detalle
       FROM orders o JOIN customers c ON c.id = o.customer_id WHERE o.created_by = ? AND DATE(o.fecha) BETWEEN ? AND ?
       ORDER BY fecha DESC, id DESC
       LIMIT 200
        ";
    $params = [
        $user_id, $desde, $hasta,
        $user_id, $desde, $hasta,
        $user_id, $desde, $hasta,
        $user_id, $desde, $hasta
    ];
    $st = db()->prepare($sql);
    $st->execute($params);
    $movimientos = $st->fetchAll();
}

include __DIR__ . '/../views/partials/header.php';
include __DIR__ . '/../views/partials/navbar.php';
?>
<div class="container py-4">
  <h4>Movimientos por Usuario</h4>
  <form class="row g-2 mb-3" method="get" action="">
    <div class="col-md-3">
      <label class="form-label">Usuario</label>
      <select name="user_id" class="form-select" required>
        <option value="">— Seleccionar —</option>
        <?php foreach ($usuarios as $u): ?>
          <option value="<?= (int)$u['id'] ?>" <?= $user_id===(int)$u['id']?'selected':'' ?>><?= e($u['nombre']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <label class="form-label">Desde</label>
      <input type="date" name="desde" class="form-control" value="<?= e($desde) ?>">
    </div>
    <div class="col-md-2">
      <label class="form-label">Hasta</label>
      <input type="date" name="hasta" class="form-control" value="<?= e($hasta) ?>">
    </div>
    <div class="col-md-2 d-grid">
      <label class="form-label">&nbsp;</label>
      <button class="btn btn-outline-primary">Filtrar</button>
    </div>
  </form>

  <div class="table-responsive">
    <table class="table table-sm align-middle">
      <thead class="table-light">
        <tr>
          <th>Tipo</th>
          <th>ID</th>
          <th>Fecha</th>
          <th>Entidad</th>
          <th class="text-end">Importe</th>
          <th>Detalle</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$movimientos): ?>
          <tr><td colspan="6" class="text-center text-muted py-4">No hay movimientos para el usuario y rango seleccionado.</td></tr>
        <?php else: foreach ($movimientos as $m): ?>
          <tr>
            <td><?= e($m['tipo']) ?></td>
            <td><?= (int)$m['id'] ?></td>
            <td><?= e($m['fecha']) ?></td>
            <td><?= e($m['entidad']) ?></td>
            <td class="text-end">$ <?= number_format((float)$m['importe'], 2, ',', '.') ?></td>
            <td><?= e($m['detalle']) ?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php include __DIR__ . '/../views/partials/footer.php'; ?>
