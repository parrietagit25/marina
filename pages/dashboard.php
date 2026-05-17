<?php
$titulo = 'Dashboard';

$pdo = getDb();
$desde = date('Y-m-01');
$hasta = date('Y-m-d');

$aviso = '';

$ingresos_total = 0.0;
$ingresos_cuotas = 0.0;
$ingresos_combustible = 0.0;
$ingresos_manuales = 0.0;
$ingresos_electricidad = 0.0;
$costos_total = 0.0;
$costos_gastos = 0.0;
$costos_combustible = 0.0;
$costos_manuales = 0.0;
$ingresos_por_cuenta = [];
$ingresos_por_dia = [];
$ingresos_cuotas_dia = [];
$ingresos_combustible_dia = [];
$costos_por_dia = [];
$costos_combustible_dia = [];
$gastos_por_partida = [];

$labelsDias = [];
$cursor = new DateTime($desde);
$end = new DateTime($hasta);
for ($d = clone $cursor; $d <= $end; $d->modify('+1 day')) {
    $fk = $d->format('Y-m-d');
    $labelsDias[] = $d->format('d/m');
    $ingresos_por_dia[$fk] = 0.0;
    $ingresos_cuotas_dia[$fk] = 0.0;
    $ingresos_combustible_dia[$fk] = 0.0;
    $costos_por_dia[$fk] = 0.0;
    $costos_combustible_dia[$fk] = 0.0;
}

// --- Ingresos por cuotas (movimientos + compatibilidad fecha_pago)
try {
    $stTot = $pdo->prepare("
        SELECT COALESCE(SUM(mo.monto),0) AS total
        FROM cuotas_movimientos mo
        WHERE mo.fecha_pago BETWEEN ? AND ?
          AND mo.tipo IN ('pago','abono')
    ");
    $stTot->execute([$desde, $hasta]);
    $ingresos_cuotas = (float) $stTot->fetch(PDO::FETCH_ASSOC)['total'];

    $stDia = $pdo->prepare("
        SELECT mo.fecha_pago, SUM(mo.monto) AS total
        FROM cuotas_movimientos mo
        WHERE mo.fecha_pago BETWEEN ? AND ?
          AND mo.tipo IN ('pago','abono')
        GROUP BY mo.fecha_pago
        ORDER BY mo.fecha_pago
    ");
    $stDia->execute([$desde, $hasta]);
    while ($r = $stDia->fetch(PDO::FETCH_ASSOC)) {
        $fk = $r['fecha_pago'];
        if (array_key_exists($fk, $ingresos_por_dia)) {
            $ingresos_cuotas_dia[$fk] = (float) $r['total'];
            $ingresos_por_dia[$fk] = (float) $r['total'];
        }
    }

    $stCuenta = $pdo->prepare("
        SELECT co.cuenta_id,
               CONCAT(b.nombre, ' - ', c.nombre) AS cuenta_nombre,
               SUM(mo.monto) AS total
        FROM cuotas_movimientos mo
        JOIN cuotas cu ON mo.cuota_id = cu.id
        JOIN contratos co ON cu.contrato_id = co.id
        JOIN cuentas c ON co.cuenta_id = c.id
        JOIN bancos b ON c.banco_id = b.id
        WHERE mo.fecha_pago BETWEEN ? AND ?
          AND mo.tipo IN ('pago','abono')
        GROUP BY co.cuenta_id, cuenta_nombre
        ORDER BY total DESC
        LIMIT 5
    ");
    $stCuenta->execute([$desde, $hasta]);
    $ingresos_por_cuenta = $stCuenta->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $aviso = 'Aviso: no se encontraron movimientos de cuotas. Se usa compatibilidad con `cuotas.fecha_pago` para los ingresos.';

    $stTot = $pdo->prepare("
        SELECT COALESCE(SUM(cu.monto),0) AS total
        FROM cuotas cu
        WHERE cu.fecha_pago IS NOT NULL
          AND cu.fecha_pago BETWEEN ? AND ?
    ");
    $stTot->execute([$desde, $hasta]);
    $ingresos_cuotas = (float) $stTot->fetch(PDO::FETCH_ASSOC)['total'];

    $stDia = $pdo->prepare("
        SELECT cu.fecha_pago, SUM(cu.monto) AS total
        FROM cuotas cu
        WHERE cu.fecha_pago IS NOT NULL
          AND cu.fecha_pago BETWEEN ? AND ?
        GROUP BY cu.fecha_pago
        ORDER BY cu.fecha_pago
    ");
    $stDia->execute([$desde, $hasta]);
    while ($r = $stDia->fetch(PDO::FETCH_ASSOC)) {
        $fk = $r['fecha_pago'];
        if (array_key_exists($fk, $ingresos_por_dia)) {
            $ingresos_cuotas_dia[$fk] = (float) $r['total'];
            $ingresos_por_dia[$fk] = (float) $r['total'];
        }
    }

    $stCuenta = $pdo->prepare("
        SELECT co.cuenta_id,
               CONCAT(b.nombre, ' - ', c.nombre) AS cuenta_nombre,
               SUM(cu.monto) AS total
        FROM cuotas cu
        JOIN contratos co ON cu.contrato_id = co.id
        JOIN cuentas c ON co.cuenta_id = c.id
        JOIN bancos b ON c.banco_id = b.id
        WHERE cu.fecha_pago IS NOT NULL
          AND cu.fecha_pago BETWEEN ? AND ?
        GROUP BY co.cuenta_id, cuenta_nombre
        ORDER BY total DESC
        LIMIT 5
    ");
    try {
        $stCuenta->execute([$desde, $hasta]);
        $ingresos_por_cuenta = $stCuenta->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e2) {
        $ingresos_por_cuenta = [];
    }
}

// --- Ingresos: despachos combustible + movimientos bancarios manuales (mismo criterio que reportes / reporte ingresos)
try {
    $stCd = $pdo->prepare('
        SELECT fecha_pago AS fecha, COALESCE(SUM(monto), 0) AS total
        FROM combustible_despacho_pagos
        WHERE fecha_pago BETWEEN ? AND ?
        GROUP BY fecha_pago
    ');
    $stCd->execute([$desde, $hasta]);
    while ($r = $stCd->fetch(PDO::FETCH_ASSOC)) {
        $fk = $r['fecha'];
        $add = (float) $r['total'];
        $ingresos_combustible += $add;
        if (array_key_exists($fk, $ingresos_por_dia)) {
            $ingresos_combustible_dia[$fk] += $add;
            $ingresos_por_dia[$fk] += $add;
        }
    }
} catch (Throwable $e) {
    // sin tabla combustible
}

try {
    $stMb = $pdo->prepare("
        SELECT fecha_movimiento, COALESCE(SUM(monto), 0) AS total
        FROM movimientos_bancarios
        WHERE fecha_movimiento BETWEEN ? AND ?
          AND tipo_movimiento = 'ingreso'
        GROUP BY fecha_movimiento
    ");
    $stMb->execute([$desde, $hasta]);
    while ($r = $stMb->fetch(PDO::FETCH_ASSOC)) {
        $fk = $r['fecha_movimiento'];
        $add = (float) $r['total'];
        $ingresos_manuales += $add;
        if (array_key_exists($fk, $ingresos_por_dia)) {
            $ingresos_por_dia[$fk] += $add;
        }
    }
} catch (Throwable $e) {
    // sin movimientos_bancarios
}

try {
    $stElIng = $pdo->prepare('
        SELECT fecha_pago AS fecha, COALESCE(SUM(monto), 0) AS total
        FROM contrato_electricidad_pagos
        WHERE fecha_pago BETWEEN ? AND ?
        GROUP BY fecha_pago
    ');
    $stElIng->execute([$desde, $hasta]);
    while ($r = $stElIng->fetch(PDO::FETCH_ASSOC)) {
        $fk = $r['fecha'];
        $add = (float) $r['total'];
        $ingresos_electricidad += $add;
        if (array_key_exists($fk, $ingresos_por_dia)) {
            $ingresos_por_dia[$fk] += $add;
        }
    }
} catch (Throwable $e) {
    // sin electricidad
}

// Total créditos del dashboard: marina (cuotas, electricidad, manuales); combustible va aparte
$ingresos_total = $ingresos_cuotas + $ingresos_manuales + $ingresos_electricidad;

// --- Top cuentas por ingreso: cuotas + combustible + manuales (alineado con pantalla Ingresos / Costos)
$sqlTopCuentasIng = "
    SELECT t.cuenta_id, t.cuenta_nombre, SUM(t.total) AS total
    FROM (
        SELECT co.cuenta_id, CONCAT(b.nombre, ' - ', c.nombre) AS cuenta_nombre, mo.monto AS total
        FROM cuotas_movimientos mo
        JOIN cuotas cu ON mo.cuota_id = cu.id
        JOIN contratos co ON cu.contrato_id = co.id
        JOIN cuentas c ON co.cuenta_id = c.id
        JOIN bancos b ON c.banco_id = b.id
        WHERE mo.fecha_pago BETWEEN ? AND ?
          AND mo.tipo IN ('pago','abono')
        UNION ALL
        SELECT co.cuenta_id, CONCAT(b.nombre, ' - ', c.nombre) AS cuenta_nombre, cu.monto AS total
        FROM cuotas cu
        JOIN contratos co ON cu.contrato_id = co.id
        JOIN cuentas c ON co.cuenta_id = c.id
        JOIN bancos b ON c.banco_id = b.id
        WHERE cu.fecha_pago BETWEEN ? AND ?
          AND NOT EXISTS (SELECT 1 FROM cuotas_movimientos x WHERE x.cuota_id = cu.id)
        UNION ALL
        SELECT p.cuenta_id, CONCAT(b.nombre, ' - ', c.nombre) AS cuenta_nombre, p.monto AS total
        FROM combustible_despacho_pagos p
        JOIN cuentas c ON c.id = p.cuenta_id
        JOIN bancos b ON b.id = c.banco_id
        WHERE p.fecha_pago BETWEEN ? AND ?
        UNION ALL
        SELECT mb.cuenta_id, CONCAT(b.nombre, ' - ', c.nombre) AS cuenta_nombre, mb.monto AS total
        FROM movimientos_bancarios mb
        JOIN cuentas c ON c.id = mb.cuenta_id
        JOIN bancos b ON b.id = c.banco_id
        WHERE mb.fecha_movimiento BETWEEN ? AND ?
          AND mb.tipo_movimiento = 'ingreso'
        UNION ALL
        SELECT ep.cuenta_id, CONCAT(b.nombre, ' - ', c.nombre) AS cuenta_nombre, ep.monto AS total
        FROM contrato_electricidad_pagos ep
        JOIN cuentas c ON c.id = ep.cuenta_id
        JOIN bancos b ON b.id = c.banco_id
        WHERE ep.fecha_pago BETWEEN ? AND ?
    ) t
    GROUP BY t.cuenta_id, t.cuenta_nombre
    ORDER BY total DESC
    LIMIT 5
";
try {
    $stTop = $pdo->prepare($sqlTopCuentasIng);
    $stTop->execute([$desde, $hasta, $desde, $hasta, $desde, $hasta, $desde, $hasta, $desde, $hasta]);
    $ingresos_por_cuenta = $stTop->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    // mantener $ingresos_por_cuenta de solo cuotas si falla (p. ej. sin combustible)
}

// --- Costos: abonos a facturas de gasto (fecha de pago)
$stCostTot = $pdo->prepare("
    SELECT COALESCE(SUM(gp.monto), 0) AS total
    FROM gasto_pagos gp
    WHERE gp.fecha_pago BETWEEN ? AND ?
");
$stCostTot->execute([$desde, $hasta]);
$costos_gastos = (float) $stCostTot->fetch(PDO::FETCH_ASSOC)['total'];

require_once __DIR__ . '/../includes/combustible_helpers.php';
$partida_combustible_id = marina_combustible_partida_id($pdo);

if ($partida_combustible_id > 0) {
    try {
        $stCombDeb = $pdo->prepare('
            SELECT COALESCE(SUM(gp.monto), 0) AS total
            FROM gasto_pagos gp
            JOIN gastos g ON g.id = gp.gasto_id
            WHERE gp.fecha_pago BETWEEN ? AND ?
              AND g.partida_id = ?
        ');
        $stCombDeb->execute([$desde, $hasta, $partida_combustible_id]);
        $costos_combustible = (float) $stCombDeb->fetchColumn();

        $stCombDebDia = $pdo->prepare('
            SELECT gp.fecha_pago AS fecha_gasto, SUM(gp.monto) AS total
            FROM gasto_pagos gp
            JOIN gastos g ON g.id = gp.gasto_id
            WHERE gp.fecha_pago BETWEEN ? AND ?
              AND g.partida_id = ?
            GROUP BY gp.fecha_pago
        ');
        $stCombDebDia->execute([$desde, $hasta, $partida_combustible_id]);
        while ($r = $stCombDebDia->fetch(PDO::FETCH_ASSOC)) {
            $fk = $r['fecha_gasto'];
            if (array_key_exists($fk, $costos_combustible_dia)) {
                $costos_combustible_dia[$fk] = (float) $r['total'];
            }
        }
    } catch (Throwable $e) {
        $costos_combustible = 0.0;
    }
}

$stCostDia = $pdo->prepare("
    SELECT gp.fecha_pago AS fecha_gasto, SUM(gp.monto) AS total
    FROM gasto_pagos gp
    WHERE gp.fecha_pago BETWEEN ? AND ?
    GROUP BY gp.fecha_pago
    ORDER BY gp.fecha_pago
");
$stCostDia->execute([$desde, $hasta]);
while ($r = $stCostDia->fetch(PDO::FETCH_ASSOC)) {
    $fk = $r['fecha_gasto'];
    if (array_key_exists($fk, $costos_por_dia)) {
        $costos_por_dia[$fk] = (float) $r['total'];
    }
}

// --- Costos: movimientos bancarios tipo costo (como reporte egresos)
try {
    $stMc = $pdo->prepare("
        SELECT fecha_movimiento, COALESCE(SUM(monto), 0) AS total
        FROM movimientos_bancarios
        WHERE fecha_movimiento BETWEEN ? AND ?
          AND tipo_movimiento = 'costo'
        GROUP BY fecha_movimiento
    ");
    $stMc->execute([$desde, $hasta]);
    while ($r = $stMc->fetch(PDO::FETCH_ASSOC)) {
        $fk = $r['fecha_movimiento'];
        $add = (float) $r['total'];
        $costos_manuales += $add;
        if (array_key_exists($fk, $costos_por_dia)) {
            $costos_por_dia[$fk] += $add;
        }
    }
} catch (Throwable $e) {
    // ignorar
}

// Total débitos del dashboard: gastos sin partida combustible + manuales
$costos_total = max(0.0, $costos_gastos - $costos_combustible) + $costos_manuales;

$stPartida = $pdo->prepare("
    SELECT p.id, p.nombre AS partida_nombre, SUM(gp.monto) AS total
    FROM gasto_pagos gp
    JOIN gastos g ON g.id = gp.gasto_id
    JOIN partidas p ON g.partida_id = p.id
    WHERE gp.fecha_pago BETWEEN ? AND ?
    GROUP BY p.id, p.nombre
    ORDER BY total DESC
    LIMIT 5
");
$stPartida->execute([$desde, $hasta]);
$gastos_por_partida = $stPartida->fetchAll(PDO::FETCH_ASSOC);

// --- KPIs operativos
$stCuotasPagadas = $pdo->prepare("
    SELECT COUNT(*) AS total
    FROM cuotas
    WHERE fecha_pago IS NOT NULL
      AND fecha_pago BETWEEN ? AND ?
");
$stCuotasPagadas->execute([$desde, $hasta]);
$cuotas_pagadas_mes = (int) $stCuotasPagadas->fetch(PDO::FETCH_ASSOC)['total'];

require_once __DIR__ . '/../includes/reportes_queries.php';
$idsCuotasVencidas = reportes_ids_cuotas_por_estado_vencimiento(
    $pdo,
    $desde,
    $hasta,
    'vencida',
    0,
    '',
    0,
    0,
    0,
    0
);
$cuotas_vencidas_mes = count($idsCuotasVencidas);

$stContratosVencer = $pdo->prepare("
    SELECT COUNT(*) AS total
    FROM contratos
    WHERE COALESCE(estado, 'activo') = 'activo'
      AND fecha_fin BETWEEN ? AND ?
");
$stContratosVencer->execute([$desde, $hasta]);
$contratos_vencer_mes = (int) $stContratosVencer->fetch(PDO::FETCH_ASSOC)['total'];

$combustible_despacho_mes = $ingresos_combustible;
$combustible_por_tipo_mes = ['diesel' => 0.0, 'gasolina' => 0.0];
try {
    $stCt = $pdo->prepare('
        SELECT LOWER(TRIM(d.tipo_combustible)) AS t, COALESCE(SUM(p.monto), 0) AS total
        FROM combustible_despacho_pagos p
        JOIN combustible_despachos d ON d.id = p.despacho_id
        WHERE p.fecha_pago BETWEEN ? AND ?
        GROUP BY LOWER(TRIM(d.tipo_combustible))
    ');
    $stCt->execute([$desde, $hasta]);
    while ($row = $stCt->fetch(PDO::FETCH_ASSOC)) {
        $tk = (string) ($row['t'] ?? '');
        if (isset($combustible_por_tipo_mes[$tk])) {
            $combustible_por_tipo_mes[$tk] = (float) $row['total'];
        }
    }
} catch (Throwable $e) {
    // sin módulo combustible
}

$pedidos_combustible_por_pagar = 0;
try {
    $pedidos_combustible_por_pagar = (int) $pdo->query("
        SELECT COUNT(*) FROM combustible_pedidos WHERE estado_pago = 'por_pagar'
    ")->fetchColumn();
} catch (Throwable $e) {
    $pedidos_combustible_por_pagar = 0;
}

$inv_combustible = marina_combustible_inventario_por_tipo($pdo);

$labelsDiasJs = $labelsDias;
$ingresosSerie = [];
$ingresosCuotasSerie = [];
$ingresosCombSerie = [];
$costosSerie = [];
$costosCombSerie = [];
foreach ($ingresos_por_dia as $fecha => $val) {
    $combDia = (float) ($ingresos_combustible_dia[$fecha] ?? 0.0);
    $debCombDia = (float) ($costos_combustible_dia[$fecha] ?? 0.0);
    $ingresosSerie[] = (float) ($val ?? 0.0) - $combDia;
    $ingresosCuotasSerie[] = (float) ($ingresos_cuotas_dia[$fecha] ?? 0.0);
    $ingresosCombSerie[] = $combDia;
    $costosSerie[] = (float) ($costos_por_dia[$fecha] ?? 0.0) - $debCombDia;
    $costosCombSerie[] = $debCombDia;
}

$costos_otros_gastos = max(0.0, $costos_gastos - $costos_combustible);
$diff = $ingresos_total - $costos_total;

$urlBase = MARINA_URL . '/index.php';
$urlCreditos = $urlBase . '?p=reporte-ingresos&desde=' . rawurlencode($desde) . '&hasta=' . rawurlencode($hasta);
$urlCreditosComb = $urlBase . '?p=reporte-combustible&desde=' . rawurlencode($desde) . '&hasta=' . rawurlencode($hasta);
$urlDebitos = $urlBase . '?p=reporte-egresos&desde=' . rawurlencode($desde) . '&hasta=' . rawurlencode($hasta);
$urlDebitosComb = $urlBase . '?p=reporte-egresos&desde=' . rawurlencode($desde) . '&hasta=' . rawurlencode($hasta)
    . ($partida_combustible_id > 0 ? '&partida_id=' . $partida_combustible_id : '');
$urlCuotasPagadas = $urlBase . '?p=reporte-cuotas&desde=' . rawurlencode($desde) . '&hasta=' . rawurlencode($hasta)
    . '&estado=pagada&fecha_campo=pago';
$urlCuotasVencidas = $urlBase . '?p=reporte-cuotas&desde=' . rawurlencode($desde) . '&hasta=' . rawurlencode($hasta)
    . '&estado=vencida';
$urlCuotasPendMes = $urlBase . '?p=reporte-cuotas&desde=' . rawurlencode($desde) . '&hasta=' . rawurlencode($hasta)
    . '&estado=pendiente';
$urlContratosVencer = $urlBase . '?p=contratos&fin_desde=' . rawurlencode($desde) . '&fin_hasta=' . rawurlencode($hasta) . '&solo_activos=1';

require_once __DIR__ . '/../includes/layout.php';
?>

<div class="dashboard-page"><div class="dashboard-hero card border-0 mb-4"><div class="card-body p-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
    <div>
        <h1 class="dashboard-title mb-1">Dashboard</h1>
        <p class="text-muted mb-0">Resumen del <?= fechaFormato($desde) ?> al <?= fechaFormato($hasta) ?> · mes en curso</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-outline-primary btn-sm" href="<?= e($urlCreditos) ?>">Reporte ingresos</a>
        <a class="btn btn-outline-secondary btn-sm" href="<?= MARINA_URL ?>/index.php?p=reportes&amp;desde=<?= e($desde) ?>&amp;hasta=<?= e($hasta) ?>">Resumen I/E</a>
    </div>
</div>
</div>

<?php if ($aviso): ?>
    <div class="alert alert-warning py-2" role="alert"><?= e($aviso) ?></div>
<?php endif; ?>

<div class="kpi-grid mb-4">
    <a class="kpi-card kpi-card--link" href="<?= e($urlCreditos) ?>">
        <div class="kpi-title"><i data-lucide="trending-up" class="menu-ico"></i>Total créditos</div>
        <div class="kpi-value text-success"><?= dinero((float) $ingresos_total) ?></div>
        <ul class="kpi-breakdown list-unstyled small mb-0 mt-2">
            <li><span>Cuotas</span><strong><?= dinero($ingresos_cuotas) ?></strong></li>
            <?php if ($ingresos_electricidad > 0): ?><li><span>Electricidad</span><strong><?= dinero($ingresos_electricidad) ?></strong></li><?php endif; ?>
            <?php if ($ingresos_manuales > 0): ?><li><span>Manuales</span><strong><?= dinero($ingresos_manuales) ?></strong></li><?php endif; ?>
        </ul>
        <p class="text-muted small mb-0 mt-2">Sin combustible (ver tarjeta aparte).</p>
        <span class="kpi-card-hint">Ver reporte →</span>
    </a>
    <a class="kpi-card kpi-card--link" href="<?= e($urlDebitos) ?>">
        <div class="kpi-title"><i data-lucide="trending-down" class="menu-ico"></i>Total débitos</div>
        <div class="kpi-value text-danger"><?= dinero((float) $costos_total) ?></div>
        <ul class="kpi-breakdown list-unstyled small mb-0 mt-2">
            <li><span>Otras partidas</span><strong><?= dinero($costos_otros_gastos) ?></strong></li>
            <?php if ($costos_manuales > 0): ?><li><span>Manuales</span><strong><?= dinero($costos_manuales) ?></strong></li><?php endif; ?>
        </ul>
        <p class="text-muted small mb-0 mt-2">Sin combustible (ver tarjeta aparte).</p>
        <span class="kpi-card-hint">Ver reporte →</span>
    </a>
    <div class="kpi-card">
        <div class="kpi-title"><i data-lucide="sigma" class="menu-ico"></i>Diferencia</div>
        <div class="kpi-value" style="color:<?= $diff >= 0 ? '#0f9f64' : '#d14343' ?>"><?= dinero($diff) ?></div>
    </div>
    <a class="kpi-card kpi-card--link" href="<?= e($urlCreditosComb) ?>">
        <div class="kpi-title"><i data-lucide="fuel" class="menu-ico"></i>Crédito combustible</div>
        <div class="kpi-value"><?= dinero($combustible_despacho_mes) ?></div>
        <span class="kpi-card-hint">Reporte combustible →</span>
    </a>
    <a class="kpi-card kpi-card--link" href="<?= e($urlDebitosComb) ?>">
        <div class="kpi-title"><i data-lucide="truck" class="menu-ico"></i>Débito combustible</div>
        <div class="kpi-value"><?= dinero($costos_combustible) ?></div>
        <span class="kpi-card-hint">Egresos combustible →</span>
    </a>
    <div class="kpi-card">
        <div class="kpi-title"><i data-lucide="droplets" class="menu-ico"></i>Inventario combustible</div>
        <div class="text-start">
            <?php foreach (MARINA_COMB_TIPOS as $k => $lab): ?>
                <div class="<?= $k === 'gasolina' ? 'mt-2' : '' ?>">
                    <div class="text-muted small mb-0"><?= e($lab) ?></div>
                    <div class="kpi-value lh-sm"><?= number_format($inv_combustible[$k] ?? 0, 3, '.', ',') ?> <span class="fs-6 fw-normal text-muted">gal</span></div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="text-muted small mt-2"><a href="<?= MARINA_URL ?>/index.php?p=combustible-ajuste">Ajustes</a> de inventario incluidos</div>
    </div>
    <a class="kpi-card kpi-card--link" href="<?= MARINA_URL ?>/index.php?p=combustible-pedidos">
        <div class="kpi-title"><i data-lucide="clipboard-list" class="menu-ico"></i>Pedidos por pagar</div>
        <div class="kpi-value"><?= (int) $pedidos_combustible_por_pagar ?></div>
        <span class="kpi-card-hint">Ver pedidos →</span>
    </a>
    <a class="kpi-card kpi-card--link" href="<?= e($urlCuotasPagadas) ?>">
        <div class="kpi-title"><i data-lucide="badge-check" class="menu-ico"></i>Cuotas pagadas</div>
        <div class="kpi-value"><?= (int) $cuotas_pagadas_mes ?></div>
        <span class="kpi-card-hint">Ver en reporte →</span>
    </a>
    <a class="kpi-card kpi-card--link" href="<?= e($urlCuotasVencidas) ?>">
        <div class="kpi-title"><i data-lucide="clock-3" class="menu-ico"></i>Cuotas vencidas</div>
        <div class="kpi-value text-danger"><?= (int) $cuotas_vencidas_mes ?></div>
        <span class="kpi-card-hint">Ver en reporte →</span>
    </a>
    <a class="kpi-card kpi-card--link" href="<?= e($urlContratosVencer) ?>">
        <div class="kpi-title"><i data-lucide="file-warning" class="menu-ico"></i>Contratos por vencer</div>
        <div class="kpi-value"><?= (int) $contratos_vencer_mes ?></div>
        <span class="kpi-card-hint">Ver contratos →</span>
    </a>
</div>

<div class="card p-3 mb-4">
    <h2 class="h6 mb-3 text-muted">Accesos rápidos</h2>
    <div class="row g-2 small">
        <div class="col-12 col-md-4">
            <strong class="d-block mb-1">Combustible</strong>
            <a class="d-block" href="<?= MARINA_URL ?>/index.php?p=combustible-pedidos">Pedidos</a>
            <a class="d-block" href="<?= MARINA_URL ?>/index.php?p=combustible-despacho">Despacho</a>
            <a class="d-block" href="<?= MARINA_URL ?>/index.php?p=combustible-ajuste">Ajuste de inventario</a>
            <a class="d-block" href="<?= MARINA_URL ?>/index.php?p=combustible-precios">Precio por galón</a>
            <a class="d-block" href="<?= MARINA_URL ?>/index.php?p=reporte-combustible">Reporte combustible</a>
        </div>
        <div class="col-12 col-md-4">
            <strong class="d-block mb-1">Finanzas y reportes</strong>
            <a class="d-block" href="<?= MARINA_URL ?>/index.php?p=reportes">Reporte de ingresos y egresos</a>
            <a class="d-block" href="<?= MARINA_URL ?>/index.php?p=reporte-ingresos">Reporte de ingreso</a>
            <a class="d-block" href="<?= MARINA_URL ?>/index.php?p=reporte-egresos">Reporte de egresos</a>
            <a class="d-block" href="<?= MARINA_URL ?>/index.php?p=reporte-ingresos-egresos">Reporte de ingresos y egresos</a>
            <a class="d-block" href="<?= MARINA_URL ?>/index.php?p=gastos">Factura / Pagar</a>
            <a class="d-block" href="<?= MARINA_URL ?>/index.php?p=movimiento-bancario">Movimientos bancarios</a>
        </div>
        <div class="col-12 col-md-4">
            <strong class="d-block mb-1">Marina</strong>
            <a class="d-block" href="<?= MARINA_URL ?>/index.php?p=contratos">Contratos</a>
            <a class="d-block" href="<?= MARINA_URL ?>/index.php?p=clientes">Clientes</a>
            <a class="d-block" href="<?= MARINA_URL ?>/index.php?p=reporte-cuotas">Reporte de cuotas</a>
        </div>
    </div>
</div>

<div class="dashboard-section-title mb-2">Gráficas del período</div>
<div class="charts-grid dashboard-charts">
    <div class="card chart-card chart-card--wide p-3">
        <h2 class="h5 mb-2">Créditos vs débitos por día</h2>
        <p class="text-muted small mb-2">Línea «Total créditos/débitos» sin combustible. Barras naranjas/verdes: combustible y cuotas por día.</p>
        <canvas id="chartIngresosCostos" height="120"></canvas>
    </div>
    <div class="card chart-card p-3">
        <h2 class="h5 mb-2">Composición de créditos</h2>
        <canvas id="chartCreditosMix" height="140"></canvas>
    </div>
    <div class="card chart-card p-3">
        <h2 class="h5 mb-2">Composición de débitos</h2>
        <canvas id="chartDebitosMix" height="140"></canvas>
    </div>
    <div class="card p-3">
        <h2 class="h5 mb-2">Créditos por cuenta (top 5)</h2>
        <p class="text-muted small mb-2">Misma base que el reporte de créditos y débitos (cuotas + combustible + manuales).</p>
        <canvas id="chartIngresosCuenta" height="110"></canvas>
    </div>
    <div class="card p-3">
        <h2 class="h5 mb-2">Gastos por partida (top 5)</h2>
        <canvas id="chartGastosPartida" height="110"></canvas>
    </div>
    <div class="card p-3">
        <h2 class="h5 mb-2">Despacho combustible por tipo ($)</h2>
        <canvas id="chartCombustibleTipo" height="160"></canvas>
    </div>
    <div class="card p-3">
        <h2 class="h5 mb-2">Cuotas: pagadas vs vencidas (mes)</h2>
        <canvas id="chartCuotasEstado" height="160"></canvas>
    </div>
</div>

<div class="mt-4 text-muted small">
    Los totales del mes siguen la misma lógica que el <a href="<?= MARINA_URL ?>/index.php?p=reportes">reporte de ingresos y egresos</a> y el <a href="<?= MARINA_URL ?>/index.php?p=reporte-ingresos">reporte de ingreso</a>. El <?= e(marina_ui_debito()) ?> por compra de combustible queda en gastos con partida Combustible al recibir el pedido.
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" crossorigin="anonymous"></script>
<?php
$labelsCuenta = array_map(static function ($r) {
    return (string) ($r['cuenta_nombre'] ?? '');
}, $ingresos_por_cuenta);
$dataCuenta = array_map(static function ($r) {
    return (float) ($r['total'] ?? 0.0);
}, $ingresos_por_cuenta);

$labelsPartida = array_map(static function ($r) {
    return (string) ($r['partida_nombre'] ?? '');
}, $gastos_por_partida);
$dataPartida = array_map(static function ($r) {
    return (float) ($r['total'] ?? 0.0);
}, $gastos_por_partida);

$labelsCombTipo = [];
$dataCombTipo = [];
foreach (MARINA_COMB_TIPOS as $k => $lab) {
    $labelsCombTipo[] = $lab;
    $dataCombTipo[] = (float) ($combustible_por_tipo_mes[$k] ?? 0.0);
}

$creditosMixLabels = [];
$creditosMixData = [];
foreach ([
    'Cuotas' => $ingresos_cuotas,
    'Electricidad' => $ingresos_electricidad,
    'Manuales' => $ingresos_manuales,
] as $lab => $val) {
    if ($val > 0.009) {
        $creditosMixLabels[] = $lab;
        $creditosMixData[] = round($val, 2);
    }
}
$debitosMixLabels = [];
$debitosMixData = [];
foreach ([
    'Otras partidas' => $costos_otros_gastos,
    'Manuales' => $costos_manuales,
] as $lab => $val) {
    if ($val > 0.009) {
        $debitosMixLabels[] = $lab;
        $debitosMixData[] = round($val, 2);
    }
}
?>
<script>
(() => {
    const labelsDias = <?= json_encode($labelsDiasJs, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
    const ingresosSerie = <?= json_encode($ingresosSerie, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
    const ingresosCuotasSerie = <?= json_encode($ingresosCuotasSerie, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
    const ingresosCombSerie = <?= json_encode($ingresosCombSerie, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
    const costosSerie = <?= json_encode($costosSerie, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
    const costosCombSerie = <?= json_encode($costosCombSerie, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;

    const creditosMixLabels = <?= json_encode($creditosMixLabels, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
    const creditosMixData = <?= json_encode($creditosMixData, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
    const debitosMixLabels = <?= json_encode($debitosMixLabels, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
    const debitosMixData = <?= json_encode($debitosMixData, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;

    const labelsCuenta = <?= json_encode($labelsCuenta, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
    const dataCuenta = <?= json_encode($dataCuenta, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;

    const labelsPartida = <?= json_encode($labelsPartida, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
    const dataPartida = <?= json_encode($dataPartida, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;

    const labelsCombTipo = <?= json_encode($labelsCombTipo, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
    const dataCombTipo = <?= json_encode($dataCombTipo, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;

    const cuotasPagadas = <?= (int) $cuotas_pagadas_mes ?>;
    const cuotasVencidas = <?= (int) $cuotas_vencidas_mes ?>;

    function fmt(v) {
        const n = Number(v);
        if (!isFinite(n)) return v;
        return n.toLocaleString('es-ES', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    const palette = {
        credit: 'rgba(15, 159, 100, 1)',
        creditSoft: 'rgba(15, 159, 100, 0.2)',
        debit: 'rgba(209, 67, 67, 1)',
        debitSoft: 'rgba(209, 67, 67, 0.2)',
        comb: 'rgba(253, 126, 20, 1)',
        combSoft: 'rgba(253, 126, 20, 0.65)',
        brand: 'rgba(10, 61, 98, 1)'
    };

    const commonOptions = {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: { display: true, labels: { boxWidth: 12, usePointStyle: true } },
            tooltip: {
                callbacks: {
                    label: (ctx) => `${ctx.dataset.label}: ${fmt(ctx.raw)}`
                }
            }
        },
        scales: {
            x: { ticks: { maxRotation: 0, autoSkip: true }, grid: { display: false } },
            y: { beginAtZero: true, grid: { color: 'rgba(148,163,184,0.2)' }, ticks: { callback: (value) => fmt(value) } }
        }
    };

    const doughnutOpts = {
        responsive: true,
        maintainAspectRatio: true,
        cutout: '62%',
        plugins: {
            legend: { position: 'bottom', labels: { boxWidth: 10, usePointStyle: true } },
            tooltip: {
                callbacks: {
                    label: (ctx) => `${ctx.label}: ${fmt(ctx.raw)}`
                }
            }
        }
    };

    const c1 = document.getElementById('chartIngresosCostos');
    if (c1) {
        new Chart(c1, {
            type: 'bar',
            data: {
                labels: labelsDias,
                datasets: [
                    { type: 'bar', label: 'Cuotas', data: ingresosCuotasSerie, backgroundColor: palette.creditSoft, borderColor: palette.credit, borderWidth: 1, stack: 'cred' },
                    { type: 'bar', label: 'Combustible', data: ingresosCombSerie, backgroundColor: palette.combSoft, borderColor: palette.comb, borderWidth: 1, stack: 'cred' },
                    { type: 'line', label: 'Total créditos', data: ingresosSerie, borderWidth: 2.5, borderColor: palette.brand, backgroundColor: 'transparent', tension: 0.3, yAxisID: 'y' },
                    { type: 'line', label: 'Total débitos', data: costosSerie, borderWidth: 2, borderColor: palette.debit, backgroundColor: 'transparent', tension: 0.3, borderDash: [6, 4] }
                ]
            },
            options: {
                ...commonOptions,
                scales: {
                    x: { stacked: true, grid: { display: false } },
                    y: { stacked: false, beginAtZero: true, grid: { color: 'rgba(148,163,184,0.2)' }, ticks: { callback: (v) => fmt(v) } }
                }
            }
        });
    }

    const cMixC = document.getElementById('chartCreditosMix');
    if (cMixC && creditosMixData.length) {
        new Chart(cMixC, {
            type: 'doughnut',
            data: {
                labels: creditosMixLabels,
                datasets: [{ data: creditosMixData, backgroundColor: ['#0f9f64', '#fd7e14', '#0ea5a6', '#0a3d62'], borderWidth: 0 }]
            },
            options: doughnutOpts
        });
    }

    const cMixD = document.getElementById('chartDebitosMix');
    if (cMixD && debitosMixData.length) {
        new Chart(cMixD, {
            type: 'doughnut',
            data: {
                labels: debitosMixLabels,
                datasets: [{ data: debitosMixData, backgroundColor: ['#64748b', '#fd7e14', '#d14343'], borderWidth: 0 }]
            },
            options: doughnutOpts
        });
    }

    const c2 = document.getElementById('chartIngresosCuenta');
    if (c2) {
        new Chart(c2, {
            type: 'bar',
            data: {
                labels: labelsCuenta,
                datasets: [
                    { label: 'Créditos', data: dataCuenta, backgroundColor: 'rgba(15,159,100,0.55)', borderColor: palette.credit, borderWidth: 1, borderRadius: 6 }
                ]
            },
            options: commonOptions
        });
    }

    const c3 = document.getElementById('chartGastosPartida');
    if (c3) {
        new Chart(c3, {
            type: 'bar',
            data: {
                labels: labelsPartida,
                datasets: [
                    { label: 'Gastos', data: dataPartida, backgroundColor: 'rgba(209,67,67,0.5)', borderColor: palette.debit, borderWidth: 1, borderRadius: 6 }
                ]
            },
            options: commonOptions
        });
    }

    const cComb = document.getElementById('chartCombustibleTipo');
    if (cComb) {
        new Chart(cComb, {
            type: 'doughnut',
            data: {
                labels: labelsCombTipo,
                datasets: [
                    {
                        label: 'Monto',
                        data: dataCombTipo,
                        backgroundColor: ['rgba(13,110,253,0.75)', 'rgba(253,126,20,0.75)'],
                        borderColor: ['rgba(13,110,253,1)', 'rgba(253,126,20,1)'],
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                aspectRatio: 1.8,
                plugins: {
                    legend: { position: 'bottom' },
                    tooltip: {
                        callbacks: {
                            label: (ctx) => {
                                const v = ctx.raw;
                                return ctx.label + ': ' + fmt(v);
                            }
                        }
                    }
                }
            }
        });
    }

    const c4 = document.getElementById('chartCuotasEstado');
    if (c4) {
        new Chart(c4, {
            type: 'doughnut',
            data: {
                labels: ['Pagadas', 'Vencidas'],
                datasets: [
                    {
                        data: [cuotasPagadas, cuotasVencidas],
                        backgroundColor: ['rgba(25,135,84,0.75)', 'rgba(220,53,69,0.75)'],
                        borderColor: ['rgba(25,135,84,1)', 'rgba(220,53,69,1)'],
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                aspectRatio: 1.8,
                plugins: { legend: { position: 'bottom' } }
            }
        });
    }
})();
</script>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
