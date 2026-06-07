<?php
/**
 * Desarrollo local (XAMPP u otro Apache en subcarpeta /marina).
 */
declare(strict_types=1);

define('MARINA_ENV', 'local');
define('MARINA_DEBUG', true);

/** Ruta base de la app (subcarpeta). Vacío solo si la app está en la raíz del dominio. */
define('MARINA_URL', '/marina');

define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'marina');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');
