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
        'notas' => trim($_POST['notas'] ?? ''),
    ];

    try {
        if ($id > 0) {
            // actualizar
            $sql = "UPDATE customers 
                    SET nombre=?, gym=?, cuit_dni=?, telefono=?, email=?, direccion=?, 
                        condicion_iva=?, limite_credito=?, notas=? 
                    WHERE id=?";
            db()->prepare($sql)->execute([
                $data['nombre'], $data['gym'], $data['cuit_dni'], $data['telefono'], $data['email'],
                $data['direccion'], $data['condicion_iva'], $data['limite_credito'], $data['notas'], $id
            ]);
        } else {
            // insertar
            $sql = "INSERT INTO customers 
                    (nombre, gym, cuit_dni, telefono, email, direccion, condicion_iva, limite_credito, notas) 
                    VALUES (?,?,?,?,?,?,?,?,?)";
            db()->prepare($sql)->execute([
                $data['nombre'], $data['gym'], $data['cuit_dni'], $data['telefono'], $data['email'],
                $data['direccion'], $data['condicion_iva'], $data['limite_credito'], $data['notas']
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
      <label class="form-label">Notas</label>
      <textarea name="notas" class="form-control" rows="2"><?= e($row['notas']) ?></textarea>
    </div>

    <div class="col-12">
      <button class="btn btn-primary">Guardar</button>
    </div>
  </form>
</div>
<?php include __DIR__ . '/../views/partials/footer.php'; ?>
