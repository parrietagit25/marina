<?php
/**
 * Configuración general - Marina
 *
 * XAMPP (sin Docker): no hace falta .env; por defecto /marina, localhost, root, contraseña vacía.
 * Docker: docker-compose inyecta DB_HOST=db, MARINA_URL vacío, etc. dentro del contenedor.
 * Opcional: config.local.php (copiar desde config.local.example.php) para forzar valores en tu PC.
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

define('MARINA_ROOT', dirname(__DIR__));

$marinaLocal = __DIR__ . '/config.local.php';
if (is_file($marinaLocal)) {
    require_once $marinaLocal;
}

if (!defined('MARINA_URL')) {
    $marinaUrlEnv = getenv('MARINA_URL');
    if ($marinaUrlEnv === false) {
        define('MARINA_URL', '/marina');
    } else {
        define('MARINA_URL', rtrim((string) $marinaUrlEnv, '/'));
    }
}

if (!defined('DB_HOST')) {
    define('DB_HOST', getenv('DB_HOST') !== false && getenv('DB_HOST') !== '' ? (string) getenv('DB_HOST') : 'localhost');
}
if (!defined('DB_NAME')) {
    define('DB_NAME', getenv('DB_NAME') !== false && getenv('DB_NAME') !== '' ? (string) getenv('DB_NAME') : 'marina');
}
if (!defined('DB_USER')) {
    define('DB_USER', getenv('DB_USER') !== false && getenv('DB_USER') !== '' ? (string) getenv('DB_USER') : 'root');
}
if (!defined('DB_PASS')) {
    define('DB_PASS', getenv('DB_PASS') !== false ? (string) getenv('DB_PASS') : '');
}
if (!defined('DB_CHARSET')) {
    define('DB_CHARSET', getenv('DB_CHARSET') !== false && getenv('DB_CHARSET') !== '' ? (string) getenv('DB_CHARSET') : 'utf8mb4');
}

// Sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Zona horaria
date_default_timezone_set('America/Argentina/Buenos_Aires');
