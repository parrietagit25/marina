<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

$pdo = getDb();
$sql = file_get_contents(__DIR__ . '/../sql/alter_clientes_tipo_embarcacion.sql');
try {
    $pdo->exec($sql);
    echo "Columna tipo_embarcacion lista.\n";
} catch (Throwable $e) {
    if (str_contains($e->getMessage(), 'Duplicate column')) {
        echo "Columna tipo_embarcacion ya existe.\n";
    } else {
        throw $e;
    }
}
