<?php
// app/auth.php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

/**
 * Devuelve el usuario en sesión o null.
 */
function user() {
    return $_SESSION['user'] ?? null;
}

/**
 * Login por email + password. Carga en sesión:
 *  id, nombre, email, rol_id, role (ENUM), rol_nombre (de tabla roles), activo
 */
function login(string $email, string $password): bool {
    $sql = "SELECT 
                u.id, u.nombre, u.email, u.pass_hash, u.rol_id, u.role, u.activo,
                r.nombre AS rol_nombre
            FROM users u
            LEFT JOIN roles r ON r.id = u.rol_id
            WHERE u.email = ? AND u.activo = 1
            LIMIT 1";
    $stmt = db()->prepare($sql);
    $stmt->execute([$email]);
    $u = $stmt->fetch();

    if (!$u) return false;
    if (!password_verify($password, $u['pass_hash'])) return false;

    // Higiene de sesión
    session_regenerate_id(true);

    $_SESSION['user'] = [
        'id'         => (int)$u['id'],
        'nombre'     => $u['nombre'],
        'email'      => $u['email'],
        'rol_id'     => (int)$u['rol_id'],
        'role'       => $u['role'],        // ENUM en mayúsculas (ADMIN/VENTAS/DEPOSITO/PRODUCCION/CAJA/LECTURA)
        'rol_nombre' => $u['rol_nombre'],  // nombre en tabla roles (admin/ventas/...)
        'activo'     => (int)$u['activo'],
    ];
    return true;
}

/**
 * Cierra sesión.
 */
function logout(): void {
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

/**
 * Exige usuario logueado o redirige a login.
 */
function require_login(): void {
    if (!user()) { header('Location: ' . url('login.php')); exit; }
}

/**
 * ¿Es admin?
 * ESTRICTO: solo si users.role === 'ADMIN' (ENUM).
 * Ignora rol_id y nombres de la tabla roles para evitar falsos positivos.
 */
function is_admin(): bool {
    $u = user();
    if (!$u) return false;
    return !empty($u['role']) && strtoupper($u['role']) === 'ADMIN';
}

/**
 * Chequea si el usuario pertenece a alguno de los roles ENUM indicados.
 * Ejemplos:
 *   check_role('ADMIN', 'VENTAS')
 */
function check_role(string ...$roles): bool {
    $u = user();
    if (!$u) return false;
    $user_enum = strtoupper($u['role'] ?? '');
    foreach ($roles as $r) {
        if ($user_enum === strtoupper($r)) return true;
    }
    return false;
}

/**
 * Requiere que el usuario tenga al menos uno de los roles ENUM dados.
 * Uso:
 *   require_role('ADMIN');
 *   require_role('ADMIN','VENTAS');
 */
function require_role(string ...$roles): void {
    if (!check_role(...$roles)) {
        http_response_code(403);
        exit('403 — Acceso restringido.');
    }
}
