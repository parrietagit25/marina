<?php
/**
 * Bootstrap para scripts cron (CLI o HTTP con token).
 */
declare(strict_types=1);

define('MARINA_CRON', true);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/marketing_helpers.php';
require_once dirname(__DIR__) . '/includes/cron_helpers.php';

if (PHP_SAPI !== 'cli') {
    marina_cron_verificar_acceso(getDb());
}

set_time_limit(120);
ignore_user_abort(true);
