<?php
require_once __DIR__ . '/../app/auth.php';
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $pass  = $_POST['password'] ?? '';
    if (login($email, $pass)) { header('Location: ' . url('dashboard.php')); exit; }
    $err = 'Credenciales inválidas';
}
include __DIR__ . '/../views/partials/header.php';
?>
<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-md-4">
      <div class="card shadow-sm">
        <div class="card-body">
          <h5 class="card-title mb-3">Ingresar</h5>
          <?php if ($err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endif; ?>
          <form method="post" autocomplete="off">
            <div class="mb-3">
              <label class="form-label">Email</label>
              <input type="email" name="email" class="form-control" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Contraseña</label>
              <input type="password" name="password" class="form-control" required>
            </div>
            <button class="btn btn-primary w-100">Entrar</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../views/partials/footer.php'; ?>
