<?php
/**
 * Helpers generales
 */

function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function redirigir(string $url, int $codigo = 302): void {
    header('Location: ' . $url, true, $codigo);
    exit;
}

function fechaFormato(?string $fecha, string $formato = 'd/m/Y'): string {
    if ($fecha === null || $fecha === '') return '';
    $t = strtotime($fecha);
    return $t ? date($formato, $t) : $fecha;
}

function fechaHoraFormato(?string $fecha, string $formato = 'd/m/Y H:i'): string {
    if ($fecha === null || $fecha === '') return '';
    $t = strtotime($fecha);
    return $t ? date($formato, $t) : $fecha;
}

function dinero(float $n): string {
    return number_format($n, 2, ',', '.');
}

function obtener(string $key, $default = '') {
    return $_GET[$key] ?? $_POST[$key] ?? $default;
}

function enviado(): bool {
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

/** Porcentaje del tamaño base del texto (100 = tamaño actual del sistema). Rango 80–125. */
function marina_config_font_size_percent(PDO $pdo): int {
    try {
        $st = $pdo->prepare("SELECT valor FROM marina_config WHERE clave = 'font_size_percent' LIMIT 1");
        $st->execute();
        $v = $st->fetchColumn();
        if ($v !== false && $v !== null && $v !== '') {
            return max(80, min(125, (int) $v));
        }
    } catch (Throwable $e) {
        // tabla ausente en instalaciones muy antiguas
    }
    return 100;
}

require_once __DIR__ . '/eliminar_dependencias.php';
