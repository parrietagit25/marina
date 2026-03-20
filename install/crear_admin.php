<?php
/**
 * Crea el usuario admin (ejecutar una vez después de importar schema.sql)
 * Uso: php crear_admin.php   o desde navegador: /marina/install/crear_admin.php
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/database.php';

$email = 'admin@marina.local';
$password = 'admin123';
$nombre = 'Administrador';

try {
    $pdo = getDb();
    $st = $pdo->query("SELECT id FROM usuarios WHERE email = " . $pdo->quote($email));
    if ($st->fetch()) {
        echo "El usuario admin ya existe.\n";
        exit(0);
    }
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $pdo->prepare('INSERT INTO usuarios (nombre, email, password_hash, rol) VALUES (?, ?, ?, ?)')
        ->execute([$nombre, $email, $hash, 'admin']);
    echo "Usuario admin creado: $email / $password\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Asegúrate de haber creado la base de datos 'marina' e importado sql/schema.sql\n";
    exit(1);
}
