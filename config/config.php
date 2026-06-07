<?php
/**
 * Configuración general - Marina
 *
 * El entorno se elige en config/environment.php ('local' | 'production').
 * Cada entorno tiene su archivo en config/environments/.
 *
 * Prioridad opcional: config.local.php puede definir constantes y sobrescribir.
 */
declare(strict_types=1);

define('MARINA_ROOT', dirname(__DIR__));

$marinaEnvName = 'local';
$envFile = __DIR__ . '/environment.php';
if (is_file($envFile)) {
    $loaded = require $envFile;
    if (is_string($loaded) && $loaded !== '') {
        $marinaEnvName = $loaded;
    }
}
$marinaEnvName = in_array($marinaEnvName, ['local', 'production'], true) ? $marinaEnvName : 'local';

$envConfig = __DIR__ . '/environments/' . $marinaEnvName . '.php';
if (is_file($envConfig)) {
    require $envConfig;
} else {
    define('MARINA_ENV', $marinaEnvName);
    define('MARINA_DEBUG', $marinaEnvName === 'local');
    define('MARINA_URL', '/marina');
    define('DB_HOST', '127.0.0.1');
    define('DB_NAME', 'marina');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_CHARSET', 'utf8mb4');
}

$marinaLocal = __DIR__ . '/config.local.php';
if (is_file($marinaLocal)) {
    require_once $marinaLocal;
}

if (!defined('MARINA_ENV')) {
    define('MARINA_ENV', $marinaEnvName);
}
if (!defined('MARINA_DEBUG')) {
    define('MARINA_DEBUG', MARINA_ENV === 'local');
}

if (!defined('MARINA_URL')) {
    $marinaUrlEnv = getenv('MARINA_URL');
    if ($marinaUrlEnv !== false && $marinaUrlEnv !== '') {
        define('MARINA_URL', rtrim((string) $marinaUrlEnv, '/'));
    } else {
        define('MARINA_URL', MARINA_ENV === 'production' ? '' : '/marina');
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

error_reporting(E_ALL);
ini_set('display_errors', MARINA_DEBUG ? '1' : '0');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('America/Argentina/Buenos_Aires');
