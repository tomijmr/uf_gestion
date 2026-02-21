<?php
require_once __DIR__ . '/../app/auth.php';
require_login();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

include __DIR__ . '/../views/partials/header.php';
include __DIR__ . '/../views/partials/navbar.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$guardado = false;
$error = '';

// --- Guardar (alta o edición) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'nombre' => trim($_POST['nombre'] ?? ''),
        'gym' => trim($_POST['gym'] ?? ''),
        'cuit_dni' => trim($_POST['cuit_dni'] ?? ''),
        'telefono' => trim($_POST['telefono'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'direccion' => trim($_POST['direccion'] ?? ''),
        'condicion_iva' => trim($_POST['condicion_iva'] ?? ''),
        'limite_credito' => (float)($_POST['limite_credito'] ?? 0),
        'saldo_deuda' => (float)($_POST['saldo_deuda'] ?? 0),
        'deuda_info' => trim($_POST['deuda_info'] ?? ''),
        'notas' => trim($_POST['notas'] ?? ''),
    ];

    try {
        if ($id > 0) {
            // actualizar
            $sql = "UPDATE customers 
                    SET nombre=?, gym=?, cuit_dni=?, telefono=?, email=?, direccion=?, 
                        condicion_iva=?, limite_credito=?, saldo_deuda=?, deuda_info=?, notas=? 
                    WHERE id=?";
            db()->prepare($sql)->execute([
                $data['nombre'], $data['gym'], $data['cuit_dni'], $data['telefono'], $data['email'],
                $data['direccion'], $data['condicion_iva'], $data['limite_credito'], 
                $data['saldo_deuda'], $data['deuda_info'], $data['notas'], $id
            ]);
        } else {
            // insertar
            $sql = "INSERT INTO customers 
                    (nombre, gym, cuit_dni, telefono, email, direccion, condicion_iva, limite_credito, saldo_deuda, deuda_info, notas) 
                    VALUES (?,?,?,?,?,?,?,?,?,?,?)";
            db()->prepare($sql)->execute([
                $data['nombre'], $data['gym'], $data['cuit_dni'], $data['telefono'], $data['email'],
                $data['direccion'], $data['condicion_iva'], $data['limite_credito'],
                $data['saldo_deuda'], $data['deuda_info'], $data['notas']
            ]);
            $id = (int)db()->lastInsertId();
        }
        $guardado = true;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

// --- Obtener datos ---
$row = [
    'nombre' => '',
    'gym' => '',
    'cuit_dni' => '',
    'telefono' => '',
    'email' => '',
    'direccion' => '',
    'condicion_iva' => 'Consumidor Final',
    'limite_credito' => 0,
    'saldo_deuda' => 0,
    'deuda_info' => '',
    'notas' => ''
];

if ($id > 0) {
    $stmt = db()->prepare("SELECT * FROM customers WHERE id=?");
    $stmt->execute([$id]);
    $row = $stmt->fetch() ?: $row;
}
?>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><?= $id ? 'Editar Cliente' : 'Nuevo Cliente' ?></h5>
    <a class="btn btn-outline-secondary" href="<?= url('clientes.php') ?>">Volver</a>
  </div>

  <?php if ($guardado): ?>
    <div class="alert alert-success">Cliente guardado correctamente ✅</div>
  <?php endif; ?>

  <?php if ($error): ?>
    <div class="alert alert-danger">Error: <?= e($error) ?></div>
  <?php endif; ?>

  <form method="post" class="row g-3">
    <div class="col-md-6">
      <label class="form-label">Nombre / Razón Social *</label>
      <input required name="nombre" class="form-control" value="<?= e($row['nombre']) ?>">
    </div>

    <div class="col-md-6">
      <label class="form-label">Gimnasio</label>
      <input name="gym" class="form-control" placeholder="Nombre del gimnasio" value="<?= e($row['gym']) ?>">
    </div>

    <div class="col-md-3">
      <label class="form-label">CUIT / DNI</label>
      <input name="cuit_dni" class="form-control" value="<?= e($row['cuit_dni']) ?>">
    </div>

    <div class="col-md-3">
      <label class="form-label">Teléfono</label>
      <input name="telefono" class="form-control" value="<?= e($row['telefono']) ?>">
    </div>

    <div class="col-md-4">
      <label class="form-label">Email</label>
      <input type="email" name="email" class="form-control" value="<?= e($row['email']) ?>">
    </div>

    <div class="col-md-8">
      <label class="form-label">Dirección</label>
      <input name="direccion" class="form-control" value="<?= e($row['direccion']) ?>">
    </div>

    <div class="col-md-4">
      <label class="form-label">Condición IVA</label>
      <input name="condicion_iva" class="form-control" value="<?= e($row['condicion_iva']) ?>">
    </div>

    <div class="col-md-4">
      <label class="form-label">Límite de crédito</label>
      <input name="limite_credito" type="number" step="0.01" class="form-control" value="<?= e($row['limite_credito']) ?>">
    </div>

    <div class="col-12">
        <div class="card bg-light border-warning mt-2">
            <div class="card-header bg-warning text-dark fw-bold">
                <i class="bi bi-calendar-event"></i> Plan de Pagos / Deuda Anterior
            </div>
            <div class="card-body row g-3">
                <div class="col-md-6">
                    <label class="form-label">Saldo Deuda Inicial ($)</label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input name="saldo_deuda" type="number" step="0.01" class="form-control" value="<?= e($row['saldo_deuda']) ?>">
                    </div>
                    <small class="text-muted">Monto total de la deuda PREVIA al sistema.</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Detalle del Plan / Cuotas</label>
                    <input name="deuda_info" class="form-control" placeholder="Ej: 12 cuotas de $50,000" value="<?= e($row['deuda_info']) ?>">
                    <small class="text-muted">Descripción para seguimiento (opcional).</small>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12">
      <label class="form-label">Notas</label>
      <textarea name="notas" class="form-control" rows="2"><?= e($row['notas']) ?></textarea>
    </div>

    <div class="col-12">
      <button class="btn btn-primary">Guardar Cambios</button>
    </div>
  </form>

<?php if($id > 0): ?>
    <hr class="my-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="mb-0"><i class="bi bi-wallet2"></i> Estado de Cuenta</h3>
        <!-- Boton para registrar pago directo -->
        <a href="caja.php?customer_id=<?= $id ?>" class="btn btn-success"><i class="bi bi-cash-coin"></i> Registrar Nuevo Pago</a>
    </div>

    <?php
    // Calculate totals
    $total_deuda_inicial = (float)($row['saldo_deuda'] ?? 0);

    // Obtener saldo del sistema desde el ledger (más preciso que recalcular)
    $stmt_l = db()->prepare("SELECT saldo_resultante FROM customer_ledger 
                             WHERE customer_id=? 
                             ORDER BY fecha DESC, id DESC 
                             LIMIT 1");
    $stmt_l->execute([$id]);
    $saldo_sistema = (float)($stmt_l->fetchColumn() ?: 0);

    // Saldo Final Real
    $saldo_actual = $total_deuda_inicial + $saldo_sistema;

    // Obtener últimos movimientos del LEDGER para consistencia
    $stmt_led = db()->prepare("SELECT fecha, tipo, origen, detalle, monto, saldo_resultante 
                               FROM customer_ledger 
                               WHERE customer_id=? 
                               ORDER BY fecha DESC, id DESC 
                               LIMIT 20");
    $stmt_led->execute([$id]);
    $movimientos = $stmt_led->fetchAll(PDO::FETCH_ASSOC);
    ?>

    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="card bg-light border-0 h-100">
                <div class="card-body text-center">
                    <small class="text-muted text-uppercase fw-bold">Deuda Inicial Importada</small>
                    <h3 class="fw-bold mb-0 text-secondary">$<?= number_format($total_deuda_inicial, 2) ?></h3>
                    <small class="text-muted"><?= e($row['deuda_info']) ?></small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-light border-0 h-100">
                <div class="card-body text-center">
                    <small class="text-muted text-uppercase fw-bold">Movimientos del Sistema</small>
                    <h3 class="fw-bold mb-0 text-dark">$<?= number_format($saldo_sistema, 2) ?></h3>
                    <small class="text-muted">Pedidos y Pagos registrados aquí</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card <?= $saldo_actual > 0 ? 'bg-danger text-white' : 'bg-success text-white' ?> border-0 h-100">
                <div class="card-body text-center">
                    <small class="text-uppercase fw-bold text-white-50">Saldo Total Pendiente</small>
                    <h2 class="fw-bold mb-0">$<?= number_format($saldo_actual, 2) ?></h2>
                    <small class="text-white-50"><?= $saldo_actual > 0 ? 'Debe' : 'A Favor' ?></small>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabla Movimientos Recientes -->
    <div class="card shadow-sm">
        <div class="card-header bg-white">
            <h6 class="mb-0 fw-bold">Últimos Movimientos (Ledger)</h6>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Fecha</th>
                        <th>Tipo</th>
                        <th>Detalle</th>
                        <th class="text-end">Monto</th>
                        <th class="text-end">Saldo Parcial</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($movimientos)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-3">No hay movimientos recientes.</td></tr>
                    <?php else: ?>
                        <?php foreach($movimientos as $m): 
                            $es_abono = ($m['tipo'] === 'ABONO'); // Pago
                            $clase_monto = $es_abono ? 'text-success' : 'text-danger';
                            // Saldo parcial del ledger NO incluye deuda inicial, ojo visual
                        ?>
                        <tr>
                            <td><?= date('d/m/Y H:i', strtotime($m['fecha'])) ?></td>
                            <td><span class="badge bg-light text-dark border"><?= $m['origen'] ?></span></td>
                            <td><?= e($m['detalle']) ?></td>
                            <td class="text-end <?= $clase_monto ?> fw-bold">
                                <?= $es_abono ? '-' : '' ?>$<?= number_format($m['monto'], 2) ?>
                            </td>
                            <td class="text-end text-muted">
                                $<?= number_format($m['saldo_resultante'] + $total_deuda_inicial, 2) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php endif; ?>

</div>
<?php include __DIR__ . '/../views/partials/footer.php'; ?>
