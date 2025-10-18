<?php
require_once __DIR__ . '/../app/auth.php';
require_login();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

/**
 * Determina si el usuario logueado es ADMIN.
 * Acepta:
 *  - users.role === 'ADMIN' (ENUM)
 *  - users.rol_id == id(roles.nombre='admin')
 */
function current_user_is_admin(): bool {
  if (!isset($_SESSION['user'])) return false;
  $u = $_SESSION['user'];

  // Vía ENUM en mayúsculas
  if (!empty($u['role']) && strtoupper($u['role']) === 'ADMIN') return true;

  // Vía rol_id contra tabla roles
  try {
    $st = db()->prepare("SELECT id FROM roles WHERE LOWER(nombre)='admin' LIMIT 1");
    $st->execute();
    $rid = (int)$st->fetchColumn();
    if ($rid && isset($u['rol_id']) && (int)$u['rol_id'] === $rid) return true;
  } catch (Throwable $e) {
    // si falla la DB, negamos por seguridad
  }
  return false;
}

if (!current_user_is_admin()) {
  http_response_code(403);
  exit('403 — Solo ADMIN puede gestionar usuarios.');
}

/* ===== Config / util ===== */
$flash_ok = '';
$flash_err = '';

function map_enum_from_role_name(string $nombre): string {
  // roles.nombre viene en minúsculas; devolvemos ENUM en mayúsculas compatibles con tu esquema
  switch (strtolower(trim($nombre))) {
    case 'admin':      return 'ADMIN';
    case 'ventas':     return 'VENTAS';
    case 'deposito':   return 'DEPOSITO';
    case 'produccion': return 'PRODUCCION';
    case 'caja':       return 'CAJA';
    case 'supervisor': return 'LECTURA'; // ajustar si querés que supervisor mapee a otro enum
    default:           return 'LECTURA';
  }
}

// Traer catálogo de roles (roles.nombre)
$rolesCat = db()->query("SELECT id, nombre FROM roles ORDER BY nombre")->fetchAll();
$rolesById = [];
foreach ($rolesCat as $r) { $rolesById[(int)$r['id']] = $r['nombre']; }

/* ===== Acciones POST ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  if ($action === 'alta_rapida') {
    try {
      $nombre = trim($_POST['nombre'] ?? '');
      $email = trim($_POST['email'] ?? '');
      $username = trim($_POST['username'] ?? ''); // puede que no tengas username; si no existe, podés omitirlo
      $role_name = trim($_POST['role_name'] ?? 'lectura');
      $pass = $_POST['password'] ?? '';
      $activo = 1;

      if ($nombre === '' || $email === '' || $pass === '') {
        throw new Exception('Nombre, Email y Contraseña son obligatorios.');
      }
      if (strlen($pass) < 6) throw new Exception('La contraseña debe tener al menos 6 caracteres.');

      // Resolver rol_id y role(enum)
      $st = db()->prepare("SELECT id, nombre FROM roles WHERE LOWER(nombre)=LOWER(?) LIMIT 1");
      $st->execute([$role_name]);
      $r = $st->fetch();
      if (!$r) throw new Exception('Rol inválido.');
      $rol_id = (int)$r['id'];
      $role_enum = map_enum_from_role_name($r['nombre']);

      // Hash
      $hash = password_hash($pass, PASSWORD_BCRYPT);

      // Insert con pass_hash, rol_id y role enum
      $ins = db()->prepare("INSERT INTO users (nombre, email, pass_hash, rol_id, activo, role)
                            VALUES (?,?,?,?,?,?)");
      $ins->execute([$nombre, $email, $hash, $rol_id, $activo, $role_enum]);

      $flash_ok = "Usuario creado correctamente.";
    } catch (Throwable $e) {
      $flash_err = "No se pudo crear: " . $e->getMessage();
    }
  }

  if ($action === 'toggle_activo') {
    try {
      $id = (int)($_POST['id'] ?? 0);
      if ($id <= 0) throw new Exception('ID inválido');
      // Evitar auto-desactivación
      if ($id === (int)($_SESSION['user']['id'] ?? 0)) {
        throw new Exception('No podés desactivar tu propio usuario.');
      }
      db()->prepare("UPDATE users SET activo = 1 - activo WHERE id=?")->execute([$id]);
      $flash_ok = "Usuario #$id actualizado (activo/inactivo).";
    } catch (Throwable $e) {
      $flash_err = "No se pudo actualizar: " . $e->getMessage();
    }
  }

  if ($action === 'reset_pass') {
    try {
      $id = (int)($_POST['id'] ?? 0);
      $newpass = $_POST['newpass'] ?? '';
      if ($id <= 0) throw new Exception('ID inválido');
      if (strlen($newpass) < 6) throw new Exception('La contraseña debe tener al menos 6 caracteres.');
      $hash = password_hash($newpass, PASSWORD_BCRYPT);
      db()->prepare("UPDATE users SET pass_hash=? WHERE id=?")->execute([$hash, $id]);
      $flash_ok = "Contraseña actualizada para el usuario #$id.";
    } catch (Throwable $e) {
      $flash_err = "No se pudo actualizar la contraseña: " . $e->getMessage();
    }
  }
}

/* ===== Filtros GET y listado ===== */
$q = trim($_GET['q'] ?? '');
$role_name_filter = trim($_GET['role_name'] ?? '');
$activo = $_GET['activo'] ?? '';

$where = [];
$params = [];
if ($q !== '') {
  // Tu esquema no tiene username; filtramos por nombre y email
  $where[] = "(u.nombre LIKE ? OR u.email LIKE ?)";
  $like = "%$q%"; $params[] = $like; $params[] = $like;
}
if ($role_name_filter !== '') {
  // Filtrar por nombre en tabla roles (via join)
  $where[] = "LOWER(r.nombre) = LOWER(?)";
  $params[] = $role_name_filter;
}
if ($activo !== '' && in_array($activo, ['0','1'], true)) {
  $where[] = "u.activo = ?";
  $params[] = (int)$activo;
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// Paginación
$page  = max(1, (int)($_GET['page'] ?? 1));
$limit = 50;
$off   = ($page - 1) * $limit;

// Conteo
$st = db()->prepare("SELECT COUNT(*) total
                     FROM users u
                     LEFT JOIN roles r ON r.id = u.rol_id
                     $whereSql");
$st->execute($params);
$total = (int)$st->fetch()['total'];
$pages = max(1, (int)ceil($total / $limit));

// Listado
$sql = "SELECT u.id, u.nombre, u.email, u.rol_id, u.role, u.activo, u.created_at,
               r.nombre AS rol_nombre
        FROM users u
        LEFT JOIN roles r ON r.id = u.rol_id
        $whereSql
        ORDER BY u.nombre
        LIMIT $limit OFFSET $off";
$st = db()->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

function page_url(int $p): string {
  $qs = $_GET; $qs['page'] = $p;
  return url('usuarios.php') . '?' . http_build_query($qs);
}

include __DIR__ . '/../views/partials/header.php';
include __DIR__ . '/../views/partials/navbar.php';
?>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">Usuarios</h5>
    <a class="btn btn-primary" href="<?= url('usuario_ver.php') ?>">Nuevo usuario</a>
  </div>

  <?php if ($flash_ok): ?><div class="alert alert-success"><?= e($flash_ok) ?></div><?php endif; ?>
  <?php if ($flash_err): ?><div class="alert alert-danger"><?= e($flash_err) ?></div><?php endif; ?>

  <form class="row g-2 mb-3" method="get" action="<?= url('usuarios.php') ?>">
    <div class="col-md-5">
      <input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Buscar por nombre / email">
    </div>
    <div class="col-md-3">
      <select name="role_name" class="form-select">
        <option value="">Todos los roles</option>
        <?php foreach ($rolesCat as $r): ?>
          <option value="<?= e($r['nombre']) ?>" <?= (strtolower($role_name_filter)===strtolower($r['nombre']))?'selected':'' ?>>
            <?= e($r['nombre']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <select name="activo" class="form-select">
        <option value="">Activos e inactivos</option>
        <option value="1" <?= $activo==='1'?'selected':'' ?>>Solo activos</option>
        <option value="0" <?= $activo==='0'?'selected':'' ?>>Solo inactivos</option>
      </select>
    </div>
    <div class="col-md-2 d-grid">
      <button class="btn btn-outline-secondary">Filtrar</button>
    </div>
  </form>

  <!-- Alta rápida -->
  <div class="card shadow-sm mb-4">
    <div class="card-body">
      <h6 class="mb-3">Alta rápida</h6>
      <form class="row g-2" method="post">
        <input type="hidden" name="action" value="alta_rapida">
        <div class="col-md-3">
          <input class="form-control" name="nombre" placeholder="Nombre completo *" required>
        </div>
        <div class="col-md-3">
          <input class="form-control" name="email" placeholder="Email *" required>
        </div>
        <div class="col-md-2">
          <select name="role_name" class="form-select">
            <?php foreach ($rolesCat as $r): ?>
              <option value="<?= e($r['nombre']) ?>"><?= e($r['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <input class="form-control" name="password" type="password" placeholder="Contraseña *" required minlength="6">
        </div>
        <div class="col-12 d-grid d-md-block mt-2">
          <button class="btn btn-primary">Crear</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Listado -->
  <div class="card shadow-sm">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th style="width:70px;">ID</th>
              <th>Nombre</th>
              <th>Email</th>
              <th class="text-center">Rol (roles.nombre)</th>
              <th class="text-center">Role (ENUM)</th>
              <th class="text-center">Activo</th>
              <th>Creado</th>
              <th class="text-end" style="width:300px;">Acciones</th>
            </tr>
          </thead>
          <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="8" class="text-center text-muted py-4">No hay usuarios.</td></tr>
          <?php else: foreach ($rows as $u): ?>
            <tr>
              <td><?= (int)$u['id'] ?></td>
              <td><?= e($u['nombre']) ?></td>
              <td><?= e($u['email']) ?></td>
              <td class="text-center"><span class="badge bg-secondary"><?= e($u['rol_nombre'] ?? '—') ?></span></td>
              <td class="text-center"><span class="badge bg-info text-dark"><?= e($u['role']) ?></span></td>
              <td class="text-center">
                <span class="badge bg-<?= $u['activo']?'success':'secondary' ?>"><?= $u['activo']?'Sí':'No' ?></span>
              </td>
              <td><?= e($u['created_at']) ?></td>
              <td class="text-end">
                <a class="btn btn-sm btn-outline-primary" href="<?= url('usuario_ver.php') . '?id=' . (int)$u['id'] ?>">Editar</a>

                <form method="post" class="d-inline" onsubmit="return confirm('¿Cambiar estado activo de este usuario?');">
                  <input type="hidden" name="action" value="toggle_activo">
                  <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                  <button class="btn btn-sm <?= $u['activo']?'btn-outline-danger':'btn-outline-success' ?>">
                    <?= $u['activo']?'Desactivar':'Activar' ?>
                  </button>
                </form>

                <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#resetModal<?= (int)$u['id'] ?>">
                  Reset pass
                </button>

                <!-- Modal reset -->
                <div class="modal fade" id="resetModal<?= (int)$u['id'] ?>" tabindex="-1">
                  <div class="modal-dialog">
                    <div class="modal-content">
                      <form method="post">
                        <div class="modal-header">
                          <h5 class="modal-title">Resetear contraseña</h5>
                          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                          <input type="hidden" name="action" value="reset_pass">
                          <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                          <label class="form-label">Nueva contraseña</label>
                          <input class="form-control" type="password" name="newpass" required minlength="6">
                          <div class="form-text">Mínimo 6 caracteres.</div>
                        </div>
                        <div class="modal-footer">
                          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                          <button class="btn btn-primary">Actualizar</button>
                        </div>
                      </form>
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
