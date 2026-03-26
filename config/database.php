<?php
/**
 * Conexión PDO a la base de datos
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/migrate_light.php';

$db = null;

function getDb(): PDO {
    global $db;
    if ($db === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $db = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        marina_ensure_schema($db);
    }
    return $db;
}
