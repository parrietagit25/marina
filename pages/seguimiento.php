<?php
/**
 * Seguimiento de actividad por usuario (dashboard + registro).
 */
$titulo = 'Seguimiento';

require_once __DIR__ . '/../includes/auditoria.php';

$pdo = getDb();
$desde = trim((string) ($_GET['desde'] ?? date('Y-m-01')));
$hasta = trim((string) ($_GET['hasta'] ?? date('Y-m-d')));
$usuarioFiltro = (int) ($_GET['usuario_id'] ?? 0);

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) {
    $desde = date('Y-m-01');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
    $hasta = date('Y-m-d');
}
if ($desde > $hasta) {
    [$desde, $hasta] = [$hasta, $desde];
}

$datos = marina_seguimiento_datos($pdo, $desde, $hasta, $usuarioFiltro);
$topUsuario = $datos['por_usuario'][0] ?? null;

$usuariosLista = [];
$stU = $pdo->query('SELECT id, nombre FROM usuarios WHERE activo = 1 ORDER BY nombre');
while ($u = $stU->fetch(PDO::FETCH_ASSOC)) {
    $usuariosLista[] = $u;
}

$labelsDias = [];
$serieDias = [];
foreach ($datos['por_dia'] as $d) {
    $labelsDias[] = fechaFormato($d['fecha']);
    $serieDias[] = (int) $d['total'];
}

$labelsUsuarios = [];
$serieUsuarios = [];
foreach ($datos['por_usuario'] as $u) {
    $labelsUsuarios[] = $u['nombre'];
    $serieUsuarios[] = (int) $u['total'];
}

$labelsModulo = [];
$serieModulo = [];
foreach ($datos['por_modulo'] as $m) {
    $labelsModulo[] = $m['modulo'];
    $serieModulo[] = (int) $m['total'];
}

$accionBadge = static function (string $accion): string {
    $a = strtolower($accion);
    if ($a === 'eliminar') {
        return 'danger';
    }
    if ($a === 'crear' || $a === 'login') {
        return 'success';
    }
    if (str_contains($a, 'editar') || str_contains($a, 'guardar')) {
        return 'primary';
    }

    return 'secondary';
};
?>

<?php require_once __DIR__ . '/../includes/layout.php'; ?>

<h1>Seguimiento de actividad</h1>
<p class="text-muted">Registros por usuario: creaciones, modificaciones, eliminaciones y acciones del sistema. Incluye historial de tablas con <em>creado por</em> y el registro en tiempo real a partir de ahora.</p>

<form method="get" class="row g-2 align-items-end mb-4">
    <input type="hidden" name="p" value="seguimiento">
    <div class="col-auto">
        <label class="form-label small mb-0">Desde</label>
        <input type="date" name="desde" class="form-control form-control-sm" value="<?= e($desde) ?>">
    </div>
    <div class="col-auto">
        <label class="form-label small mb-0">Hasta</label>
        <input type="date" name="hasta" class="form-control form-control-sm" value="<?= e($hasta) ?>">
    </div>
    <div class="col-auto">
        <label class="form-label small mb-0">Usuario</label>
        <select name="usuario_id" class="form-select form-select-sm">
            <option value="0">Todos</option>
            <?php foreach ($usuariosLista as $u): ?>
            <option value="<?= (int) $u['id'] ?>" <?= $usuarioFiltro === (int) $u['id'] ? 'selected' : '' ?>><?= e($u['nombre']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-auto">
        <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
    </div>
</form>

<div class="kpi-grid mb-4" style="grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));">
    <div class="kpi-card">
        <div class="kpi-title"><i data-lucide="list-checks" class="menu-ico"></i>Total registros</div>
        <div class="kpi-value"><?= (int) $datos['total'] ?></div>
        <p class="text-muted small mb-0">En el período seleccionado</p>
    </div>
    <div class="kpi-card">
        <div class="kpi-title"><i data-lucide="users" class="menu-ico"></i>Usuarios activos</div>
        <div class="kpi-value"><?= count($datos['por_usuario']) ?></div>
        <p class="text-muted small mb-0">Con al menos un registro</p>
    </div>
    <div class="kpi-card">
        <div class="kpi-title"><i data-lucide="trophy" class="menu-ico"></i>Más registros</div>
        <div class="kpi-value" style="font-size:1.1rem;"><?= $topUsuario ? e($topUsuario['nombre']) : '—' ?></div>
        <p class="text-muted small mb-0"><?= $topUsuario ? (int) $topUsuario['total'] . ' acciones' : 'Sin datos' ?></p>
    </div>
</div>

<div class="charts-grid dashboard-charts dashboard-charts--ocupacion mb-4">
    <div class="card chart-card chart-card--wide p-3">
        <h2 class="h5 mb-2">Actividad por día</h2>
        <div class="chart-canvas-wrap chart-canvas-wrap--bar">
            <canvas id="chartSeguimientoDia"></canvas>
        </div>
    </div>
    <div class="card chart-card p-3">
        <h2 class="h5 mb-2">Por módulo</h2>
        <div class="chart-canvas-wrap chart-canvas-wrap--doughnut">
            <canvas id="chartSeguimientoModulo"></canvas>
        </div>
    </div>
    <div class="card chart-card chart-card--wide p-3">
        <h2 class="h5 mb-2">Registros por usuario (top 15)</h2>
        <div class="chart-canvas-wrap" style="height: <?= max(220, min(420, 28 * max(1, count($datos['por_usuario'])))) ?>px">
            <canvas id="chartSeguimientoUsuario"></canvas>
        </div>
    </div>
</div>

<div class="card p-3">
    <h2 class="h5 mb-3">Detalle de registros</h2>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle">
            <thead>
                <tr>
                    <th>Fecha y hora</th>
                    <th>Usuario</th>
                    <th>Módulo</th>
                    <th>Acción</th>
                    <th>Descripción</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($datos['eventos'] === []): ?>
                <tr><td colspan="5" class="text-muted">No hay registros en este período.</td></tr>
            <?php else: ?>
                <?php foreach ($datos['eventos'] as $ev): ?>
                <tr>
                    <td class="text-nowrap"><?= e(fechaHoraFormato($ev['fecha'])) ?></td>
                    <td><?= e($ev['usuario_nombre'] !== '' ? $ev['usuario_nombre'] : ('Usuario #' . $ev['usuario_id'])) ?></td>
                    <td><?= e($ev['modulo']) ?></td>
                    <td><span class="badge bg-<?= e($accionBadge($ev['accion'])) ?>"><?= e($ev['accion']) ?></span></td>
                    <td><?= e($ev['descripcion']) ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if (count($datos['eventos']) >= 400): ?>
    <p class="text-muted small mb-0">Mostrando los últimos 400 registros. Acote el rango de fechas o filtre por usuario.</p>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" crossorigin="anonymous"></script>
<script>
(function() {
    const labelsDias = <?= json_encode($labelsDias, JSON_UNESCAPED_UNICODE) ?>;
    const serieDias = <?= json_encode($serieDias) ?>;
    const labelsUsuarios = <?= json_encode($labelsUsuarios, JSON_UNESCAPED_UNICODE) ?>;
    const serieUsuarios = <?= json_encode($serieUsuarios) ?>;
    const labelsModulo = <?= json_encode($labelsModulo, JSON_UNESCAPED_UNICODE) ?>;
    const serieModulo = <?= json_encode($serieModulo) ?>;

    const barOpts = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, ticks: { precision: 0 } },
            x: { grid: { display: false } }
        }
    };

    const cDia = document.getElementById('chartSeguimientoDia');
    if (cDia && labelsDias.length) {
        new Chart(cDia, {
            type: 'line',
            data: {
                labels: labelsDias,
                datasets: [{
                    label: 'Registros',
                    data: serieDias,
                    borderColor: 'rgba(14, 165, 233, 1)',
                    backgroundColor: 'rgba(14, 165, 233, 0.15)',
                    fill: true,
                    tension: 0.3
                }]
            },
            options: barOpts
        });
    }

    const cMod = document.getElementById('chartSeguimientoModulo');
    if (cMod && labelsModulo.length) {
        new Chart(cMod, {
            type: 'doughnut',
            data: {
                labels: labelsModulo,
                datasets: [{
                    data: serieModulo,
                    backgroundColor: [
                        '#0f9f64', '#0ea5e9', '#fd7e14', '#64748b', '#d14343',
                        '#8b5cf6', '#eab308', '#14b8a6', '#f472b6', '#84cc16'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom', labels: { boxWidth: 10 } } }
            }
        });
    }

    const cUsr = document.getElementById('chartSeguimientoUsuario');
    if (cUsr && labelsUsuarios.length) {
        new Chart(cUsr, {
            type: 'bar',
            data: {
                labels: labelsUsuarios,
                datasets: [{
                    label: 'Registros',
                    data: serieUsuarios,
                    backgroundColor: 'rgba(15, 159, 100, 0.75)',
                    borderRadius: 4
                }]
            },
            options: {
                ...barOpts,
                indexAxis: 'y'
            }
        });
    }
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
