<?php
require_once __DIR__ . '/../app/auth.php';
require_login();
require_role('ADMIN');
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

$flash_ok = '';
$flash_err = '';

$id = (int)($_GET['id'] ?? 0);

// Cargar rol (si existe)
$rol = null;
if ($id > 0) {
  $st = db()->prepare("SELECT id, nombre FROM roles WHERE id=?");
  $st->execute([$id]);
  $rol = $st->fetch();
  if (!$rol) { http_response_code(404); exit('Rol no encontrado'); }
}

// Acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  if ($action === 'guardar') {
    try {
      $nombre = strtolower(trim($_POST['nombre'] ?? ''));
      if ($nombre === '') throw new Exception('El nombre es obligatorio.');

      if ($id > 0) {
        // proteger renombrar admin a algo raro
        if (strtolower($rol['nombre']) === 'admin' && $nombre !== 'admin') {
          throw new Exception("No se puede renombrar el rol 'admin'.");
        }
        $st = db()->prepare("UPDATE roles SET nombre=? WHERE id=?");
        $st->execute([$nombre, $id]);
        $flash_ok = 'Rol actualizado.';
        // refrescar $rol
        $rol['nombre'] = $nombre;
      } else {
        $st = db()->prepare("INSERT INTO roles (nombre) VALUES (?)");
        $st->execute([$nombre]);
        $id = (int)db()->lastInsertId();
        $rol = ['id' => $id, 'nombre' => $nombre];
        $flash_ok = 'Rol creado.';
      }
    } catch (Throwable $e) {
      $flash_err = 'No se pudo guardar: ' . $e->getMessage();
    }
  }

  // Migración de usuarios a otro rol
  if ($action === 'migrar_usuarios') {
    try {
      if ($id <= 0) throw new Exception('Rol inválido.');
      $dest_id = (int)($_POST['dest_rol_id'] ?? 0);
      if ($dest_id <= 0) throw new Exception('Debés seleccionar rol destino.');
      if ($dest_id === $id) throw new Exception('El rol destino debe ser diferente.');

      // No permitir migrar desde admin hacia otro, salvo que vos lo quieras explícitamente
      if (strtolower($rol['nombre']) === 'admin') {
        throw new Exception("No se permite migrar usuarios fuera del rol 'admin' desde aquí.");
      }

      // Actualizar users.rol_id; OJO: users.role (ENUM) no cambia acá.
      $up = db()->prepare("UPDATE users SET rol_id=? WHERE rol_id=?");
      $up->execute([$dest_id, $id]);

      $flash_ok = 'Usuarios migrados al rol destino.';
    } catch (Throwable $e) {
      $flash_err = 'No se pudo migrar: ' . $e->getMessage();
    }
  }
}

// Listado de todos los roles (para combo destino y navegación)
$cat = db()->query("SELECT id, nombre FROM roles ORDER BY nombre")->fetchAll();

// Usuarios asociados a este rol
$usuarios = [];
if ($id > 0) {
  $su = db()->prepare("SELECT id, nombre, email, role, rol_id FROM users WHERE rol_id=? ORDER BY nombre");
  $su->execute([$id]);
  $usuarios = $su->fetchAll();
}

include __DIR__ . '/../views/partials/header.php';
include __DIR__ . '/../views/partials/navbar.php';
?>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><?= $id ? 'Editar rol' : 'Nuevo rol' ?></h5>
    <a class="btn btn-outline-secondary" href="<?= url('roles.php') ?>">Volver</a>
  </div>

  <?php if ($flash_ok): ?><div class="alert alert-success"><?= e($flash_ok) ?></div><?php endif; ?>
  <?php if ($flash_err): ?><div class="alert alert-danger"><?= e($flash_err) ?></div><?php endif; ?>

  <div class="card shadow-sm mb-4">
    <div class="card-body">
      <form class="row g-3" method="post">
        <input type="hidden" name="action" value="guardar">
        <div class="col-md-6">
          <label class="form-label">Nombre *</label>
          <input class="form-control" name="nombre" required value="<?= e($rol['nombre'] ?? '') ?>" placeholder="ej: ventas, deposito, produccion, caja, supervisor">
          <div class="form-text">
            Se recomienda minúsculas. Recordá que el menú usa roles ENUM: ADMIN, VENTAS, DEPOSITO, PRODUCCION, CAJA, LECTURA.
          </div>
        </div>
        <div class="col-12">
          <button class="btn btn-primary">Guardar</button>
        </div>
      </form>
    </div>
  </div>

  <?php if ($id > 0): ?>
  <div class="card shadow-sm mb-4">
    <div class="card-body">
      <h6 class="mb-3">Usuarios con este rol (rol_id = <?= (int)$id ?>)</h6>
      <?php if (!$usuarios): ?>
        <p class="text-muted mb-0">No hay usuarios asociados.</p>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm align-middle">
            <thead class="table-light">
              <tr>
                <th style="width:90px;">ID</th>
                <th>Nombre</th>
                <th>Email</th>
                <th class="text-center">role (ENUM)</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($usuarios as $u): ?>
              <tr>
                <td><?= (int)$u['id'] ?></td>
                <td><?= e($u['nombre']) ?></td>
                <td><?= e($u['email']) ?></td>
                <td class="text-center"><span class="badge bg-secondary"><?= e($u['role']) ?></span></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>

      <?php if ($usuarios): ?>
      <hr>
      <form class="row g-2" method="post" onsubmit="return confirm('¿Migrar todos los usuarios de este rol al rol seleccionado?');">
        <input type="hidden" name="action" value="migrar_usuarios">
        <div class="col-md-5">
          <label class="form-label">Migrar usuarios a</label>
          <select name="dest_rol_id" class="form-select" required>
            <option value="">— Seleccionar rol destino —</option>
            <?php foreach ($cat as $r): if ((int)$r['id']===$id) continue; ?>
              <option value="<?= (int)$r['id'] ?>"><?= e($r['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
          <div class="form-text">
            Esto solo cambia <code>users.rol_id</code>. El <code>users.role</code> (ENUM) no se modifica aquí.
          </div>
        </div>
        <div class="col-md-3 d-grid">
          <label class="form-label d-none d-md-block">&nbsp;</label>
          <button class="btn btn-outline-primary">Migrar usuarios</button>
        </div>
      </form>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <div class="alert alert-warning">
    <strong>Importante:</strong> El sistema de menús y permisos usa el <em>ENUM</em> de <code>users.role</code> (ADMIN/VENTAS/DEPOSITO/PRODUCCION/CAJA/LECTURA).
    Los nombres en <code>roles.nombre</code> son de referencia. Convención sugerida:
    <code>admin→ADMIN</code>,
    <code>ventas→VENTAS</code>,
    <code>deposito→DEPOSITO</code>,
    <code>produccion→PRODUCCION</code>,
    <code>caja→CAJA</code>,
    <code>supervisor→LECTURA</code>.
  </div>

</div>
<?php include __DIR__ . '/../views/partials/footer.php'; ?>
