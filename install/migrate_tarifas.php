<?php
/**
 * Crear tabla tarifas (ejecutar una vez desde navegador o CLI).
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

$pdo = getDb();
$sql = file_get_contents(__DIR__ . '/../sql/tarifas.sql');
$pdo->exec($sql);
echo "Tabla tarifas lista.\n";
