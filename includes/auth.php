<?php
/**
 * Auth: sesión y usuario actual
 */
require_once __DIR__ . '/../config/config.php';

function usuarioActual(): ?array {
    return $_SESSION['usuario'] ?? null;
}

function usuarioId(): ?int {
    $u = usuarioActual();
    return $u ? (int) $u['id'] : null;
}

function requiereLogin(): void {
    if (!usuarioActual()) {
        header('Location: ' . MARINA_URL . '/index.php?p=login');
        exit;
    }
}

function cerrarSesion(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
