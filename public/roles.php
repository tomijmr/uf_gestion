<?php
require_once __DIR__ . '/../app/auth.php';
require_login();
require_role('ADMIN');
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

$flash_ok = '';
$flash_err = '';

// Alta rápida / Borrar
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  if ($action === 'crear') {
    try {
      $nombre = strtolower(trim($_POST['nombre'] ?? ''));
      if ($nombre === '') throw new Exception('El nombre es obligatorio.');
      // nombre único
      $st = db()->prepare("INSERT INTO roles (nombre) VALUES (?)");
      $st->execute([$nombre]);
      $flash_ok = "Rol creado: $nombre.";
    } catch (Throwable $e) {
      $flash_err = "No se pudo crear el rol: " . $e->getMessage();
    }
  }

  if ($action === 'eliminar') {
    try {
      $id = (int)($_POST['id'] ?? 0);
      if ($id <= 0) throw new Exception('ID inválido');

      // proteger: si hay usuarios con este rol, no borrar
      $st = db()->prepare("SELECT COUNT(*) FROM users WHERE rol_id=?");
      $st->execute([$id]);
      $usrs = (int)$st->fetchColumn();
      if ($usrs > 0) {
        throw new Exception("No se puede eliminar: hay $usrs usuario(s) usando este rol.");
      }

      // proteger: no permitir borrar el rol 'admin' por nombre
      $stn = db()->prepare("SELECT nombre FROM roles WHERE id=?");
      $stn->execute([$id]);
      $nombre = strtolower((string)$stn->fetchColumn());
      if ($nombre === 'admin') {
        throw new Exception("No se puede eliminar el rol 'admin'.");
      }

      db()->prepare("DELETE FROM roles WHERE id=?")->execute([$id]);
      $flash_ok = "Rol eliminado.";
    } catch (Throwable $e) {
      $flash_err = "No se pudo eliminar el rol: " . $e->getMessage();
    }
  }
}

// Filtros
$q = trim($_GET['q'] ?? '');

$where = '';
$params = [];
if ($q !== '') {
  $where = "WHERE r.nombre LIKE ?";
  $params[] = "%$q%";
}

// Traer roles + cantidad de usuarios asociados
$sql = "
  SELECT r.id, r.nombre,
         COALESCE(u.cant,0) AS usuarios
  FROM roles r
  LEFT JOIN (
    SELECT rol_id, COUNT(*) AS cant
    FROM users
    GROUP BY rol_id
  ) u ON u.rol_id = r.id
  $where
  ORDER BY r.nombre
";
$st = db()->prepare($sql);
$st->execute($params);
$roles = $st->fetchAll();

include __DIR__ . '/../views/partials/header.php';
include __DIR__ . '/../views/partials/navbar.php';
?>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">Roles</h5>
    <a class="btn btn-primary" href="<?= url('rol_ver.php') ?>">Nuevo rol</a>
  </div>

  <?php if ($flash_ok): ?><div class="alert alert-success"><?= e($flash_ok) ?></div><?php endif; ?>
  <?php if ($flash_err): ?><div class="alert alert-danger"><?= e($flash_err) ?></div><?php endif; ?>

  <form class="row g-2 mb-3" method="get">
    <div class="col-md-6">
      <input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Buscar rol por nombre...">
    </div>
    <div class="col-md-2 d-grid">
      <button class="btn btn-outline-secondary">Filtrar</button>
    </div>
    <div class="col-md-2 d-grid">
      <a class="btn btn-outline-secondary" href="<?= url('roles.php') ?>">Limpiar</a>
    </div>
  </form>

  <!-- Alta rápida -->
  <div class="card shadow-sm mb-4">
    <div class="card-body">
      <h6 class="mb-3">Alta rápida</h6>
      <form class="row g-2" method="post">
        <input type="hidden" name="action" value="crear">
        <div class="col-md-4">
          <input class="form-control" name="nombre" placeholder="Nombre del rol (en minúsculas)" required>
          <div class="form-text">Ej: <code>admin</code>, <code>ventas</code>, <code>deposito</code>, <code>produccion</code>, <code>caja</code>, <code>supervisor</code></div>
        </div>
        <div class="col-md-2 d-grid">
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
              <th style="width:90px;">ID</th>
              <th>Nombre</th>
              <th class="text-center" style="width:150px;">Usuarios</th>
              <th class="text-end" style="width:260px;">Acciones</th>
            </tr>
          </thead>
          <tbody>
          <?php if (!$roles): ?>
            <tr><td colspan="4" class="text-center text-muted py-4">Sin roles.</td></tr>
          <?php else: foreach ($roles as $r): ?>
            <tr>
              <td><?= (int)$r['id'] ?></td>
              <td><span class="badge bg-secondary"><?= e($r['nombre']) ?></span></td>
              <td class="text-center">
                <span class="badge bg-<?= ((int)$r['usuarios']>0?'info':'secondary') ?>">
                  <?= (int)$r['usuarios'] ?>
                </span>
              </td>
              <td class="text-end">
                <a class="btn btn-sm btn-outline-primary" href="<?= url('rol_ver.php') . '?id=' . (int)$r['id'] ?>">Editar</a>

                <form method="post" class="d-inline" onsubmit="return confirm('¿Eliminar rol? Esta acción no se puede deshacer.');">
                  <input type="hidden" name="action" value="eliminar">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <button class="btn btn-sm btn-outline-danger" <?= ((int)$r['usuarios']>0 || strtolower($r['nombre'])==='admin') ? 'disabled' : '' ?>>
                    Eliminar
                  </button>
                </form>
              </td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <p class="small text-muted mt-3 mb-0">
    Nota: los nombres de rol se guardan en minúsculas. En el login y menú se usan roles ENUM (ADMIN, VENTAS, etc.).<br>
    Sugerencia de mapeo: <code>admin→ADMIN</code>, <code>ventas→VENTAS</code>, <code>deposito→DEPOSITO</code>, <code>produccion→PRODUCCION</code>, <code>caja→CAJA</code>, <code>supervisor→LECTURA</code>.
  </p>
</div>
<?php include __DIR__ . '/../views/partials/footer.php'; ?>
