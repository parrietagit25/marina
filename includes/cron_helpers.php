<?php
/**
 * Tareas programadas (cron) — Namecheap / cPanel.
 */
declare(strict_types=1);

function marina_cron_token(PDO $pdo): string
{
    return trim(marina_config_valor($pdo, 'cron_token', ''));
}

function marina_cron_generar_token(): string
{
    return bin2hex(random_bytes(16));
}

/** Solo CLI o HTTP con token válido en ?token= */
function marina_cron_verificar_acceso(PDO $pdo): void
{
    if (PHP_SAPI === 'cli') {
        return;
    }

    $expected = marina_cron_token($pdo);
    if ($expected === '') {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Cron token no configurado. Genérelo en Configuración.\n";
        exit(1);
    }

    $got = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
    if ($got === '' || !hash_equals($expected, $got)) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Acceso denegado.\n";
        exit(1);
    }
}

function marina_cron_responder(array $resultado, bool $json = false): void
{
    if ($json || (isset($_GET['format']) && $_GET['format'] === 'json')) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($resultado, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        return;
    }

    header('Content-Type: text/plain; charset=utf-8');
    echo "Marina cron OK\n";
    foreach ($resultado as $clave => $valor) {
        if (is_array($valor)) {
            echo $clave . ': ' . json_encode($valor, JSON_UNESCAPED_UNICODE) . "\n";
        } else {
            echo $clave . ': ' . $valor . "\n";
        }
    }
}

/**
 * Ruta absoluta del proyecto en el servidor (para comandos cPanel).
 */
function marina_cron_ruta_proyecto(): string
{
    return str_replace('\\', '/', MARINA_ROOT);
}
