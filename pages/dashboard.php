<?php
$titulo = 'Dashboard';

$pdo = getDb();
$desde = date('Y-m-01');
$hasta = date('Y-m-d'); // mes a la fecha

$aviso = '';

$ingresos_total = 0.0;
$costos_total = 0.0;
$ingresos_por_cuenta = [];
$ingresos_por_dia = [];
$costos_por_dia = [];
$gastos_por_partida = [];

// Rango de días para el gráfico
$labelsDias = [];
$cursor = new DateTime($desde);
$end = new DateTime($hasta);
for ($d = clone $cursor; $d <= $end; $d->modify('+1 day')) {
    $labelsDias[] = $d->format('d/m');
    $ingresos_por_dia[$d->format('Y-m-d')] = 0.0;
    $costos_por_dia[$d->format('Y-m-d')] = 0.0;
}

// --- Ingresos: desde cuotas_movimientos (fallback a cuotas.fecha_pago si falta la tabla)
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
        $ingresos_por_dia[$r['fecha_pago']] = (float) $r['total'];
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
        $ingresos_por_dia[$r['fecha_pago']] = (float) $r['total'];
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

// --- Costos (gastos) + gráfico diario + top por partida
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
    $costos_por_dia[$r['fecha_gasto']] = (float) $r['total'];
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

// --- KPIs: cuotas pagadas/vencidas y contratos por vencer
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

// Preparar datasets para JS (manteniendo orden por día)
$labelsDiasJs = $labelsDias;
$ingresosSerie = [];
$costosSerie = [];
foreach ($ingresos_por_dia as $fecha => $val) {
    $ingresosSerie[] = (float) ($val ?? 0.0);
    $costosSerie[] = (float) ($costos_por_dia[$fecha] ?? 0.0);
}

require_once __DIR__ . '/../includes/layout.php';
?>

<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <h1 class="mb-1">Dashboard</h1>
        <div class="text-muted">Resumen <?= fechaFormato($desde) ?> a <?= fechaFormato($hasta) ?></div>
    </div>
</div>

<?php if ($aviso): ?>
    <div class="error" role="alert" style="margin-bottom: 1rem;"><?= e($aviso) ?></div>
<?php endif; ?>

<div class="kpi-grid mb-4">
    <div class="kpi-card">
        <div class="kpi-title"><i data-lucide="trending-up" class="menu-ico"></i>Ingresos total</div>
        <div class="kpi-value"><?= dinero((float) $ingresos_total) ?></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-title"><i data-lucide="trending-down" class="menu-ico"></i>Costos total</div>
        <div class="kpi-value"><?= dinero((float) $costos_total) ?></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-title"><i data-lucide="sigma" class="menu-ico"></i>Diferencia</div>
        <?php $diff = (float)$ingresos_total - (float)$costos_total; ?>
        <div class="kpi-value" style="color:<?= $diff >= 0 ? '#137333' : '#b42318' ?>"><?= dinero($diff) ?></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-title"><i data-lucide="badge-check" class="menu-ico"></i>Cuotas pagadas (mes)</div>
        <div class="kpi-value"><?= (int)$cuotas_pagadas_mes ?></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-title"><i data-lucide="clock-3" class="menu-ico"></i>Cuotas vencidas (mes)</div>
        <div class="kpi-value"><?= (int)$cuotas_vencidas_mes ?></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-title"><i data-lucide="file-warning" class="menu-ico"></i>Contratos por vencer (mes)</div>
        <div class="kpi-value"><?= (int)$contratos_vencer_mes ?></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-title"><i data-lucide="wallet" class="menu-ico"></i>Gasto mensual (partidas)</div>
        <div class="kpi-value"><?= dinero((float)$costos_total) ?></div>
    </div>
</div>

<div class="charts-grid">
    <div class="card p-3">
        <h2 class="h5 mb-2">Ingresos vs Costos por día</h2>
        <canvas id="chartIngresosCostos" height="110"></canvas>
    </div>
    <div class="card p-3">
        <h2 class="h5 mb-2">Ingresos por cuenta (top 5)</h2>
        <canvas id="chartIngresosCuenta" height="110"></canvas>
    </div>
    <div class="card p-3">
        <h2 class="h5 mb-2">Gastos por partida (top 5)</h2>
        <canvas id="chartGastosPartida" height="110"></canvas>
    </div>
    <div class="card p-3">
        <h2 class="h5 mb-2">Cuotas: pagadas vs vencidas</h2>
        <canvas id="chartCuotasEstado" height="160"></canvas>
    </div>
</div>

<div class="mt-4 text-muted small">
    Tip: puedes ver el detalle en <a href="<?= MARINA_URL ?>/index.php?p=reportes">Ingresos / Costos</a> y en <a href="<?= MARINA_URL ?>/index.php?p=contratos">Contratos</a>.
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" crossorigin="anonymous"></script>
<?php
// Preparación de datos de “top N” para las gráficas
$labelsCuenta = array_map(fn($r) => (string) ($r['cuenta_nombre'] ?? ''), $ingresos_por_cuenta);
$dataCuenta = array_map(fn($r) => (float) ($r['total'] ?? 0.0), $ingresos_por_cuenta);

$labelsPartida = array_map(fn($r) => (string) ($r['partida_nombre'] ?? ''), $gastos_por_partida);
$dataPartida = array_map(fn($r) => (float) ($r['total'] ?? 0.0), $gastos_por_partida);
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

    const cuotasPagadas = <?= (int)$cuotas_pagadas_mes ?>;
    const cuotasVencidas = <?= (int)$cuotas_vencidas_mes ?>;

    function fmt(v) {
        const n = Number(v);
        if (!isFinite(n)) return v;
        return n.toLocaleString('es-ES', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    const commonOptions = {
        responsive: true,
        // Mantener relación de aspecto para evitar estiramientos raros
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

    // Ingresos vs Costos por día
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

    // Ingresos por cuenta (top 5)
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

    // Gastos por partida (top 5)
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

    // Cuotas: pagadas vs vencidas
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
