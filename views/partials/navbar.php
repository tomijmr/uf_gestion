<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../app/helpers.php';
require_once __DIR__ . '/../../app/auth.php';

$user = user();
$user_name = $user['nombre'] ?? 'Invitado';
$role_enum = strtoupper($user['role'] ?? '');
$role_name = $role_enum ?: strtoupper($user['rol_nombre'] ?? '');

/** Helper: ¿tiene alguno de estos roles? (usa check_role() del auth) */
if (!function_exists('can')) {
  function can(...$roles): bool {
    return function_exists('check_role') ? check_role(...$roles) : false;
  }
}

/** Definición de menú por roles (fácil de ajustar) */
$MENU = [
  ['label' => 'Dashboard', 'href' => url('dashboard.php'), 'roles' => ['*']],
  
  // Categoría: Clientes
  [
    'label' => 'Clientes',
    'id'    => 'navClientes',
    'roles' => ['ADMIN','VENTAS','LECTURA','RRHH','DEPOSITO'],
    'items' => [
      ['label' => 'Clientes',     'href' => url('clientes.php'),     'roles' => ['ADMIN','VENTAS','LECTURA','RRHH','DEPOSITO']],
      ['label' => 'Presupuestos', 'href' => url('presupuestos.php'), 'roles' => ['ADMIN','VENTAS','RRHH','DEPOSITO']],
      ['label' => 'Pedidos',      'href' => url('pedidos.php'),      'roles' => ['ADMIN','VENTAS','RRHH','DEPOSITO']],
    ]
  ],

  // Categoría: Maquinas (Inventario)
  [
    'label' => 'Maquinas',
    'id'    => 'navMaquinas',
    'roles' => ['ADMIN','PRODUCCION','DEPOSITO','LECTURA','RRHH'],
    'items' => [
      ['label' => 'Materiales',     'href' => url('productos.php'),            'roles' => ['ADMIN','PRODUCCION','DEPOSITO','LECTURA','RRHH']], 
      ['label' => 'Stock',          'href' => url('stock.php'),                'roles' => ['ADMIN','DEPOSITO','RRHH']],
      ['label' => 'Stock MP',       'href' => url('materias_primas_stock.php'),'roles' => ['ADMIN','DEPOSITO','RRHH']],
      ['label' => 'Reportes Stock', 'href' => url('stock_reportes.php'),       'roles' => ['ADMIN','DEPOSITO','RRHH']],
      ['label' => 'Maquinas',       'href' => url('productos_terminados.php'), 'roles' => ['ADMIN','PRODUCCION','DEPOSITO','LECTURA','RRHH']],
    ]
  ],

  // Producción (Solo)
  ['label' => 'Producción', 'href' => url('panel_produccion.php'), 'roles' => ['ADMIN','PRODUCCION','DEPOSITO','RRHH']],

  // Categoría: Caja
  [
    'label' => 'Caja',
    'id'    => 'navCaja',
    'roles' => ['ADMIN','CAJA','RRHH', 'DEPOSITO'],
    'items' => [
      ['label' => 'Caja',      'href' => url('caja.php'),      'roles' => ['ADMIN','CAJA']],
      ['label' => 'Compras',   'href' => url('compras.php'),   'roles' => ['ADMIN','CAJA','RRHH','DEPOSITO']],
    ]
  ],

  // Empleados (Solo)
  ['label' => 'Empleados', 'href' => url('empleados.php'), 'roles' => ['ADMIN','CAJA','RRHH']],

  // Nuevo: Reportes Agrupados
  [
    'label' => 'Reportes',
    'id'    => 'navReportes',
    'roles' => ['ADMIN','DEPOSITO','CAJA','RRHH'], // Unión de permisos relevantes
    'items' => [
      ['label' => 'Reportes Stock', 'href' => url('stock_reportes.php'),      'roles' => ['ADMIN','DEPOSITO','RRHH']],
      ['label' => 'Reporte Caja',   'href' => url('caja.php?tab=reportes'),   'roles' => ['ADMIN','CAJA']],
      ['label' => 'Reporte Compras','href' => url('compras.php'),             'roles' => ['ADMIN','CAJA','RRHH','DEPOSITO']],
    ]
  ],
];

// Note: Se ha cambiado el nombre del item de menú de 'Productos' a 'Materias Primas' y se agregó 'Productos Terminados'.

?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
  <div class="container-fluid">
    <a class="navbar-brand fw-bold" href="<?= url('dashboard.php') ?>">Universal Fitness SA</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navbarMain">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <?php foreach ($MENU as $item): ?>
          <?php
            // Lógica unificada de roles
            $has_role = in_array('*', $item['roles'] ?? [], true) ? (bool)$user : can(...($item['roles'] ?? []));
            if (!$has_role) continue;

            // Verificamos si es un dropdown (tiene 'items')
            if (isset($item['items']) && is_array($item['items'])):
          ?>
            <li class="nav-item dropdown">
              <a class="nav-link dropdown-toggle" href="#" id="<?= $item['id'] ?? 'navDrop' ?>" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                <?= e($item['label']) ?>
              </a>
              <ul class="dropdown-menu" aria-labelledby="<?= $item['id'] ?? 'navDrop' ?>">
                <?php foreach ($item['items'] as $sub): ?>
                  <?php
                    $sub_allowed = in_array('*', $sub['roles'] ?? [], true) ? (bool)$user : can(...($sub['roles'] ?? []));
                    if (!$sub_allowed) continue;
                  ?>
                  <li><a class="dropdown-item" href="<?= $sub['href'] ?>"><?= e($sub['label']) ?></a></li>
                <?php endforeach; ?>
              </ul>
            </li>
          <?php else: ?>
            <li class="nav-item">
              <a class="nav-link" href="<?= $item['href'] ?>"><?= e($item['label']) ?></a>
            </li>
          <?php endif; ?>
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