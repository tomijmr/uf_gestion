<?php
require_once __DIR__ . '/../app/auth.php';
require_login();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

function current_user_is_admin(): bool {
  if (!isset($_SESSION['user'])) return false;
  $u = $_SESSION['user'];
  if (!empty($u['role']) && strtoupper($u['role']) === 'ADMIN') return true;
  try {
    $st = db()->prepare("SELECT id FROM roles WHERE LOWER(nombre)='admin' LIMIT 1");
    $st->execute();
    $rid = (int)$st->fetchColumn();
    if ($rid && isset($u['rol_id']) && (int)$u['rol_id'] === $rid) return true;
  } catch (Throwable $e) {}
  return false;
}

if (!current_user_is_admin()) {
  http_response_code(403);
  exit('403 — Solo ADMIN puede gestionar usuarios.');
}

$flash_ok = '';
$flash_err = '';

function map_enum_from_role_name(string $nombre): string {
  switch (strtolower(trim($nombre))) {
    case 'admin':      return 'ADMIN';
    case 'ventas':     return 'VENTAS';
    case 'deposito':   return 'DEPOSITO';
    case 'produccion': return 'PRODUCCION';
    case 'caja':       return 'CAJA';
    case 'supervisor': return 'LECTURA';
    default:           return 'LECTURA';
  }
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// catálogo de roles
$rolesCat = db()->query("SELECT id, nombre FROM roles ORDER BY nombre")->fetchAll();

// -------------------- POST --------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  if ($action === 'save_user') {
    try {
      $nombre = trim($_POST['nombre'] ?? '');
      $email = trim($_POST['email'] ?? '');
      $role_name = trim($_POST['role_name'] ?? '');
      $activo = isset($_POST['activo']) ? 1 : 0;

      if ($nombre === '' || $email === '') throw new Exception('Nombre y Email son obligatorios.');

      // resolver rol elegido
      $st = db()->prepare("SELECT id, nombre FROM roles WHERE id=? LIMIT 1");
      $st->execute([(int)($_POST['rol_id'] ?? 0)]);
      $r = $st->fetch();
      if (!$r) {
        // si no vino rol_id válido, probamos por nombre
        if ($role_name === '') throw new Exception('Debe seleccionar un rol.');
        $st2 = db()->prepare("SELECT id, nombre FROM roles WHERE LOWER(nombre)=LOWER(?) LIMIT 1");
        $st2->execute([$role_name]);
        $r = $st2->fetch();
        if (!$r) throw new Exception('Rol inválido.');
      }
      $rol_id = (int)$r['id'];
      $role_enum = map_enum_from_role_name($r['nombre']);

      if ($id > 0) {
        // prevenir auto-desactivación
        if ($id === (int)($_SESSION['user']['id'] ?? 0) && $activo === 0) {
          throw new Exception('No podés desactivar tu propio usuario.');
        }
        $up = db()->prepare("UPDATE users SET nombre=?, email=?, rol_id=?, role=?, activo=? WHERE id=?");
        $up->execute([$nombre, $email, $rol_id, $role_enum, $activo, $id]);
      } else {
        $pass = $_POST['password'] ?? '';
        if (strlen($pass) < 6) throw new Exception('Contraseña mínima 6 caracteres.');
        $hash = password_hash($pass, PASSWORD_BCRYPT);
        $ins = db()->prepare("INSERT INTO users (nombre,email,pass_hash,rol_id,role,activo) VALUES (?,?,?,?,?,?)");
        $ins->execute([$nombre, $email, $hash, $rol_id, $role_enum, $activo]);
        $id = (int)db()->lastInsertId();
      }

      $flash_ok = 'Usuario guardado correctamente.';
    } catch (Throwable $e) {
      $flash_err = 'No se pudo guardar: ' . $e->getMessage();
    }
  }

  if ($action === 'change_pass') {
    try {
      if ($id <= 0) throw new Exception('Usuario inválido.');
      $p1 = $_POST['newpass'] ?? '';
      $p2 = $_POST['newpass2'] ?? '';
      if ($p1 === '' || $p2 === '') throw new Exception('Debe completar ambas contraseñas.');
      if ($p1 !== $p2) throw new Exception('Las contraseñas no coinciden.');
      if (strlen($p1) < 6) throw new Exception('Mínimo 6 caracteres.');
      $hash = password_hash($p1, PASSWORD_BCRYPT);
      db()->prepare("UPDATE users SET pass_hash=? WHERE id=?")->execute([$hash, $id]);
      $flash_ok = 'Contraseña actualizada.';
    } catch (Throwable $e) {
      $flash_err = 'No se pudo actualizar la contraseña: ' . $e->getMessage();
    }
  }
}

// -------------------- GET: cargar usuario --------------------
$row = [
  'nombre' => '',
  'email' => '',
  'rol_id' => null,
  'role' => 'LECTURA',
  'activo' => 1,
];
if ($id > 0) {
  $st = db()->prepare("SELECT * FROM users WHERE id=?");
  $st->execute([$id]);
  $dbRow = $st->fetch();
  if ($dbRow) $row = $dbRow;
}

include __DIR__ . '/../views/partials/header.php';
include __DIR__ . '/../views/partials/navbar.php';
?>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><?= $id ? 'Editar usuario' : 'Nuevo usuario' ?></h5>
    <a class="btn btn-outline-secondary" href="<?= url('usuarios.php') ?>">Volver</a>
  </div>

  <?php if ($flash_ok): ?><div class="alert alert-success"><?= e($flash_ok) ?></div><?php endif; ?>
  <?php if ($flash_err): ?><div class="alert alert-danger"><?= e($flash_err) ?></div><?php endif; ?>

  <div class="card shadow-sm mb-4">
    <div class="card-body">
      <form class="row g-3" method="post">
        <input type="hidden" name="action" value="save_user">
        <div class="col-md-4">
          <label class="form-label">Nombre *</label>
          <input class="form-control" name="nombre" required value="<?= e($row['nombre']) ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Email *</label>
          <input class="form-control" name="email" required value="<?= e($row['email']) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Rol</label>
          <select class="form-select" name="rol_id">
            <option value="">— Seleccionar —</option>
            <?php foreach ($rolesCat as $r): ?>
              <option value="<?= (int)$r['id'] ?>" <?= ((int)$row['rol_id']===(int)$r['id'])?'selected':'' ?>>
                <?= e($r['nombre']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <input type="hidden" name="role_name" value="">
        </div>
        <div class="col-md-2">
          <label class="form-label d-block">Activo</label>
          <input type="checkbox" name="activo" <?= $row['activo'] ? 'checked' : '' ?>>
        </div>

        <?php if ($id === 0): ?>
        <div class="col-md-3">
          <label class="form-label">Contraseña *</label>
          <input class="form-control" type="password" name="password" required minlength="6">
        </div>
        <?php endif; ?>

        <div class="col-12">
          <button class="btn btn-primary">Guardar</button>
        </div>
      </form>
    </div>
  </div>

  <?php if ($id > 0): ?>
  <div class="card shadow-sm">
    <div class="card-body">
      <h6 class="mb-3">Cambiar contraseña</h6>
      <form class="row g-2" method="post">
        <input type="hidden" name="action" value="change_pass">
        <div class="col-md-4">
          <input class="form-control" type="password" name="newpass" placeholder="Nueva contraseña" minlength="6" required>
        </div>
        <div class="col-md-4">
          <input class="form-control" type="password" name="newpass2" placeholder="Repetir contraseña" minlength="6" required>
        </div>
        <div class="col-md-4 d-grid">
          <button class="btn btn-outline-primary">Actualizar contraseña</button>
        </div>
      </form>
      <p class="small text-muted mt-2 mb-0">Recomendación: usá una contraseña segura y única.</p>
    </div>
  </div>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/../views/partials/footer.php'; ?>
