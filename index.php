<?php
/**
 * Front controller - enrutador por ?p=
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/funciones.php';
require_once __DIR__ . '/includes/auth.php';

$p = trim($_GET['p'] ?? 'dashboard');
$p = preg_replace('/[^a-z0-9_-]/', '', $p) ?: 'dashboard';

$pagina = __DIR__ . '/pages/' . $p . '.php';

if (!is_file($pagina)) {
    $pagina = __DIR__ . '/pages/dashboard.php';
}

// Login y logout no requieren sesión
if ($p !== 'login' && $p !== 'logout') {
    requiereLogin();
}

require $pagina;
