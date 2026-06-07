<?php
/**
 * Cron diario: alertas por correo (cuotas, contratos, etc.).
 *
 * cPanel → Cron Jobs → una vez al día (ej. 8:00 a.m.):
 *   0 8 * * * /usr/local/bin/php /home/USUARIO/public_html/cron/alertas_diarias.php
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/alertas_helpers.php';

$pdo = getDb();
$resultado = marina_alertas_ejecutar_diarias($pdo);
$resultado['tarea'] = 'alertas_diarias';

marina_cron_responder($resultado);
