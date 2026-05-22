<?php
/**
 * Front controller - enrutador por ?p=
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/funciones.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/permisos.php';

$p = trim($_GET['p'] ?? 'dashboard');
$p = preg_replace('/[^a-z0-9_-]/', '', $p) ?: 'dashboard';

$pagina = __DIR__ . '/pages/' . $p . '.php';

if (!is_file($pagina)) {
    $p = 'dashboard';
    $pagina = __DIR__ . '/pages/dashboard.php';
}

// Login y logout no requieren sesión
if ($p !== 'login' && $p !== 'logout') {
    requiereLogin();
    if (empty($_SESSION['permisos']) && usuarioId()) {
        marina_permisos_hidratar_sesion(getDb(), usuarioId());
    }
    marina_permiso_verificar_pagina($p);
    marina_permiso_bloquear_acciones_globales();
    require_once __DIR__ . '/includes/auditoria.php';
    marina_auditoria_desde_request(getDb(), $p);
}

require $pagina;
