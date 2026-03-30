<?php
$titulo = 'Dashboard';

$pdo = getDb();
$desde = date('Y-m-01');
$hasta = date('Y-m-d');

$aviso = '';

$ingresos_total = 0.0;
$costos_total = 0.0;
$ingresos_por_cuenta = [];
$ingresos_por_dia = [];
$costos_por_dia = [];
$gastos_por_partida = [];

$labelsDias = [];
$cursor = new DateTime($desde);
$end = new DateTime($hasta);
for ($d = clone $cursor; $d <= $end; $d->modify('+1 day')) {
    $labelsDias[] = $d->format('d/m');
    $ingresos_por_dia[$d->format('Y-m-d')] = 0.0;
    $costos_por_dia[$d->format('Y-m-d')] = 0.0;
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
    $ingresos_total = (float) $stTot->fetch(PDO::FETCH_ASSOC)['total'];

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
    $ingresos_total = (float) $stTot->fetch(PDO::FETCH_ASSOC)['total'];

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
        SELECT fecha, COALESCE(SUM(monto_total), 0) AS total
        FROM combustible_despachos
        WHERE fecha BETWEEN ? AND ?
        GROUP BY fecha
    ');
    $stCd->execute([$desde, $hasta]);
    while ($r = $stCd->fetch(PDO::FETCH_ASSOC)) {
        $fk = $r['fecha'];
        $add = (float) $r['total'];
        $ingresos_total += $add;
        if (array_key_exists($fk, $ingresos_por_dia)) {
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
        $ingresos_total += $add;
        if (array_key_exists($fk, $ingresos_por_dia)) {
            $ingresos_por_dia[$fk] += $add;
        }
    }
} catch (Throwable $e) {
    // sin movimientos_bancarios
}

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
        SELECT cd.cuenta_id, CONCAT(b.nombre, ' - ', c.nombre) AS cuenta_nombre, cd.monto_total AS total
        FROM combustible_despachos cd
        JOIN cuentas c ON c.id = cd.cuenta_id
        JOIN bancos b ON b.id = c.banco_id
        WHERE cd.fecha BETWEEN ? AND ?
        UNION ALL
        SELECT mb.cuenta_id, CONCAT(b.nombre, ' - ', c.nombre) AS cuenta_nombre, mb.monto AS total
        FROM movimientos_bancarios mb
        JOIN cuentas c ON c.id = mb.cuenta_id
        JOIN bancos b ON b.id = c.banco_id
        WHERE mb.fecha_movimiento BETWEEN ? AND ?
          AND mb.tipo_movimiento = 'ingreso'
    ) t
    GROUP BY t.cuenta_id, t.cuenta_nombre
    ORDER BY total DESC
    LIMIT 5
";
try {
    $stTop = $pdo->prepare($sqlTopCuentasIng);
    $stTop->execute([$desde, $hasta, $desde, $hasta, $desde, $hasta, $desde, $hasta]);
    $ingresos_por_cuenta = $stTop->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    // mantener $ingresos_por_cuenta de solo cuotas si falla (p. ej. sin combustible)
}

// --- Costos: gastos registrados
$stCostTot = $pdo->prepare("
    SELECT COALESCE(SUM(g.monto),0) AS total
    FROM gastos g
    WHERE g.fecha_gasto BETWEEN ? AND ?
");
$stCostTot->execute([$desde, $hasta]);
$costos_total = (float) $stCostTot->fetch(PDO::FETCH_ASSOC)['total'];

$stCostDia = $pdo->prepare("
    SELECT g.fecha_gasto, SUM(g.monto) AS total
    FROM gastos g
    WHERE g.fecha_gasto BETWEEN ? AND ?
    GROUP BY g.fecha_gasto
    ORDER BY g.fecha_gasto
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
        $costos_total += $add;
        if (array_key_exists($fk, $costos_por_dia)) {
            $costos_por_dia[$fk] += $add;
        }
    }
} catch (Throwable $e) {
    // ignorar
}

$stPartida = $pdo->prepare("
    SELECT p.id, p.nombre AS partida_nombre, SUM(g.monto) AS total
    FROM gastos g
    JOIN partidas p ON g.partida_id = p.id
    WHERE g.fecha_gasto BETWEEN ? AND ?
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

$stCuotasVencidas = $pdo->prepare("
    SELECT COUNT(*) AS total
    FROM cuotas
    WHERE fecha_pago IS NULL
      AND fecha_vencimiento BETWEEN ? AND ?
");
$stCuotasVencidas->execute([$desde, $hasta]);
$cuotas_vencidas_mes = (int) $stCuotasVencidas->fetch(PDO::FETCH_ASSOC)['total'];

$stContratosVencer = $pdo->prepare("
    SELECT COUNT(*) AS total
    FROM contratos
    WHERE activo = 1
      AND fecha_fin BETWEEN ? AND ?
");
$stContratosVencer->execute([$desde, $hasta]);
$contratos_vencer_mes = (int) $stContratosVencer->fetch(PDO::FETCH_ASSOC)['total'];

$combustible_despacho_mes = 0.0;
$combustible_por_tipo_mes = ['diesel' => 0.0, 'gasolina' => 0.0];
try {
    $stComb = $pdo->prepare('
        SELECT COALESCE(SUM(monto_total), 0) AS total
        FROM combustible_despachos
        WHERE fecha BETWEEN ? AND ?
    ');
    $stComb->execute([$desde, $hasta]);
    $combustible_despacho_mes = (float) $stComb->fetchColumn();

    $stCt = $pdo->prepare('
        SELECT LOWER(TRIM(tipo_combustible)) AS t, COALESCE(SUM(monto_total), 0) AS total
        FROM combustible_despachos
        WHERE fecha BETWEEN ? AND ?
        GROUP BY LOWER(TRIM(tipo_combustible))
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

require_once __DIR__ . '/../includes/combustible_helpers.php';
$inv_combustible = marina_combustible_inventario_por_tipo($pdo);

$labelsDiasJs = $labelsDias;
$ingresosSerie = [];
$costosSerie = [];
foreach ($ingresos_por_dia as $fecha => $val) {
    $ingresosSerie[] = (float) ($val ?? 0.0);
    $costosSerie[] = (float) ($costos_por_dia[$fecha] ?? 0.0);
}

require_once __DIR__ . '/../includes/layout.php';
?>

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
        <h1 class="mb-1">Dashboard</h1>
        <div class="text-muted">Resumen <?= fechaFormato($desde) ?> a <?= fechaFormato($hasta) ?> (mes en curso)</div>
    </div>
</div>

<?php if ($aviso): ?>
    <div class="alert alert-warning py-2" role="alert"><?= e($aviso) ?></div>
<?php endif; ?>

<div class="kpi-grid mb-4">
    <div class="kpi-card">
        <div class="kpi-title"><i data-lucide="trending-up" class="menu-ico"></i>Ingresos total</div>
        <div class="kpi-value"><?= dinero((float) $ingresos_total) ?></div>
        <div class="text-muted small mt-1">Cuotas + despacho combustible + ingresos manuales en banco</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-title"><i data-lucide="trending-down" class="menu-ico"></i>Costos total</div>
        <div class="kpi-value"><?= dinero((float) $costos_total) ?></div>
        <div class="text-muted small mt-1">Gastos (partidas) + costos manuales en banco</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-title"><i data-lucide="sigma" class="menu-ico"></i>Diferencia</div>
        <?php $diff = (float) $ingresos_total - (float) $costos_total; ?>
        <div class="kpi-value" style="color:<?= $diff >= 0 ? '#137333' : '#b42318' ?>"><?= dinero($diff) ?></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-title"><i data-lucide="fuel" class="menu-ico"></i>Despacho combustible ($)</div>
        <div class="kpi-value"><?= dinero($combustible_despacho_mes) ?></div>
        <div class="text-muted small mt-1">Ventas por galón en el período</div>
    </div>
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
    <div class="kpi-card">
        <div class="kpi-title"><i data-lucide="clipboard-list" class="menu-ico"></i>Pedidos combustible por pagar</div>
        <div class="kpi-value"><?= (int) $pedidos_combustible_por_pagar ?></div>
        <div class="text-muted small mt-1"><a href="<?= MARINA_URL ?>/index.php?p=combustible-pedidos">Ver pedidos</a></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-title"><i data-lucide="badge-check" class="menu-ico"></i>Cuotas pagadas (mes)</div>
        <div class="kpi-value"><?= (int) $cuotas_pagadas_mes ?></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-title"><i data-lucide="clock-3" class="menu-ico"></i>Cuotas vencidas (mes)</div>
        <div class="kpi-value"><?= (int) $cuotas_vencidas_mes ?></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-title"><i data-lucide="file-warning" class="menu-ico"></i>Contratos por vencer (mes)</div>
        <div class="kpi-value"><?= (int) $contratos_vencer_mes ?></div>
    </div>
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
            <a class="d-block" href="<?= MARINA_URL ?>/index.php?p=reportes">Ingresos y costos</a>
            <a class="d-block" href="<?= MARINA_URL ?>/index.php?p=reporte-ingresos">Reporte ingresos</a>
            <a class="d-block" href="<?= MARINA_URL ?>/index.php?p=reporte-egresos">Reporte egresos</a>
            <a class="d-block" href="<?= MARINA_URL ?>/index.php?p=reporte-ingresos-egresos">Ingresos / egresos</a>
            <a class="d-block" href="<?= MARINA_URL ?>/index.php?p=gastos">Factura / Pagar</a>
            <a class="d-block" href="<?= MARINA_URL ?>/index.php?p=movimiento-bancario">Movimientos bancarios</a>
        </div>
        <div class="col-12 col-md-4">
            <strong class="d-block mb-1">Marina</strong>
            <a class="d-block" href="<?= MARINA_URL ?>/index.php?p=contratos">Contratos</a>
            <a class="d-block" href="<?= MARINA_URL ?>/index.php?p=clientes">Clientes</a>
            <a class="d-block" href="<?= MARINA_URL ?>/index.php?p=reporte-cuotas">Reporte cuotas</a>
        </div>
    </div>
</div>

<div class="charts-grid">
    <div class="card p-3">
        <h2 class="h5 mb-2">Ingresos vs costos por día</h2>
        <p class="text-muted small mb-2">Incluye cuotas, combustible despachado e ingresos manuales; costos incluyen gastos y movimientos tipo costo.</p>
        <canvas id="chartIngresosCostos" height="110"></canvas>
    </div>
    <div class="card p-3">
        <h2 class="h5 mb-2">Ingresos por cuenta (top 5)</h2>
        <p class="text-muted small mb-2">Misma base que el reporte de ingresos y costos (cuotas + combustible + manuales).</p>
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
    Los totales del mes siguen la misma lógica que <a href="<?= MARINA_URL ?>/index.php?p=reportes">Ingresos / Costos</a> y los <a href="<?= MARINA_URL ?>/index.php?p=reporte-ingresos">reportes de ingreso</a>. El egreso por compra de combustible queda en gastos con partida Combustible al recibir el pedido.
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
?>
<script>
(() => {
    const labelsDias = <?= json_encode($labelsDiasJs, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
    const ingresosSerie = <?= json_encode($ingresosSerie, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
    const costosSerie = <?= json_encode($costosSerie, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;

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

    const commonOptions = {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: { display: true },
            tooltip: {
                callbacks: {
                    label: (ctx) => `${ctx.dataset.label}: ${fmt(ctx.raw)}`
                }
            }
        },
        scales: {
            x: { ticks: { maxRotation: 0, autoSkip: true } },
            y: { beginAtZero: true, grid: { drawBorder: false }, ticks: { callback: (value) => fmt(value) } }
        }
    };

    const c1 = document.getElementById('chartIngresosCostos');
    if (c1) {
        new Chart(c1, {
            type: 'line',
            data: {
                labels: labelsDias,
                datasets: [
                    { label: 'Ingresos', data: ingresosSerie, borderWidth: 2, borderColor: 'rgba(13,110,253,1)', backgroundColor: 'rgba(13,110,253,0.15)', tension: 0.25 },
                    { label: 'Costos', data: costosSerie, borderWidth: 2, borderColor: 'rgba(220,53,69,1)', backgroundColor: 'rgba(220,53,69,0.15)', tension: 0.25 }
                ]
            },
            options: commonOptions
        });
    }

    const c2 = document.getElementById('chartIngresosCuenta');
    if (c2) {
        new Chart(c2, {
            type: 'bar',
            data: {
                labels: labelsCuenta,
                datasets: [
                    { label: 'Ingresos', data: dataCuenta, backgroundColor: 'rgba(13,110,253,0.5)', borderColor: 'rgba(13,110,253,1)', borderWidth: 1 }
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
                    { label: 'Gastos', data: dataPartida, backgroundColor: 'rgba(220,53,69,0.5)', borderColor: 'rgba(220,53,69,1)', borderWidth: 1 }
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
