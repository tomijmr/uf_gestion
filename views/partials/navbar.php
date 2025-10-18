<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../app/helpers.php';
require_once __DIR__ . '/../../app/auth.php';

$user = user();
$user_name = $user['nombre'] ?? 'Invitado';
$role_enum = strtoupper($user['role'] ?? '');
$role_name = $role_enum ?: strtoupper($user['rol_nombre'] ?? '');

/** Helper: ¿tiene alguno de estos roles? (usa check_role() del auth) */
function can(...$roles): bool {
  return function_exists('check_role') ? check_role(...$roles) : false;
}

/** Definición de menú por roles (fácil de ajustar) */
$MENU = [
  ['label' => 'Dashboard',  'href' => url('dashboard.php'),  'roles' => ['*']], // * = todos logueados
  ['label' => 'Clientes',   'href' => url('clientes.php'),   'roles' => ['ADMIN','VENTAS','LECTURA']],
  ['label' => 'Pedidos',    'href' => url('pedidos.php'),    'roles' => ['ADMIN','VENTAS']],
  ['label' => 'Productos',  'href' => url('productos.php'),  'roles' => ['ADMIN','PRODUCCION','DEPOSITO','LECTURA']],
  ['label' => 'Stock',      'href' => url('stock.php'),      'roles' => ['ADMIN','DEPOSITO']],
  ['label' => 'Producción', 'href' => url('op.php'), 'roles' => ['ADMIN','PRODUCCION','DEPOSITO']],
  ['label' => 'Compras',       'href' => url('compras.php'),       'roles' => ['ADMIN','CAJA']],
  ['label' => 'Caja',       'href' => url('caja.php'),       'roles' => ['ADMIN','CAJA']],
];

?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
  <div class="container-fluid">
    <a class="navbar-brand fw-bold" href="<?= url('dashboard.php') ?>">UF - ERP</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navbarMain">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <?php foreach ($MENU as $item): ?>
          <?php
            $allowed = in_array('*', $item['roles'], true) ? (bool)$user : can(...$item['roles']);
            if (!$allowed) continue;
          ?>
          <li class="nav-item">
            <a class="nav-link" href="<?= $item['href'] ?>"><?= e($item['label']) ?></a>
          </li>
        <?php endforeach; ?>

        <?php if (function_exists('is_admin') && is_admin()): ?>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" id="adminMenu" role="button" data-bs-toggle="dropdown">
              Administración
            </a>
            <ul class="dropdown-menu">
              <li><a class="dropdown-item" href="<?= url('usuarios.php') ?>">Usuarios</a></li>
              <li><a class="dropdown-item" href="<?= url('roles.php') ?>">Roles</a></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item" href="<?= url('auditoria.php') ?>">Auditoría</a></li>
            </ul>
          </li>
        <?php endif; ?>
      </ul>

      <ul class="navbar-nav ms-auto">
        <li class="nav-item">
          <span class="navbar-text text-white me-3">
            <?= e($user_name) ?>
            <?php if ($role_name): ?>
              <small class="text-muted">(<?= e($role_name) ?>)</small>
            <?php endif; ?>
          </span>
        </li>
        <li class="nav-item">
          <a class="btn btn-outline-light btn-sm" href="<?= url('logout.php') ?>">Salir</a>
        </li>
      </ul>
    </div>
  </div>
</nav>
