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
