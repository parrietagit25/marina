<?php
/**
 * Configuración general - Marina
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

define('MARINA_ROOT', dirname(__DIR__));
define('MARINA_URL', '/marina');

// Base de datos
define('DB_HOST', 'localhost');
define('DB_NAME', 'marina');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Zona horaria
date_default_timezone_set('America/Argentina/Buenos_Aires');
