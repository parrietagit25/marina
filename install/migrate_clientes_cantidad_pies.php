<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

$pdo = getDb();
$sql = file_get_contents(__DIR__ . '/../sql/alter_clientes_cantidad_pies.sql');
try {
    $pdo->exec($sql);
    echo "Columna cantidad_pies en clientes lista.\n";
} catch (Throwable $e) {
    if (str_contains($e->getMessage(), 'Duplicate column')) {
        echo "Columna cantidad_pies ya existe.\n";
    } else {
        throw $e;
    }
}
