<?php
/**
 * Ingreso x Día: contratos activos vigentes en la fecha seleccionada.
 * Monto diario = monto_total del contrato ÷ días de estadía (inicio a fin, inclusive).
 */
$titulo = 'Ingreso x Día';
$pdo = getDb();
require_once __DIR__ . '/../includes/export_excel.php';

$fecha = trim(obtener('fecha', date('Y-m-d')));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
    $fecha = date('Y-m-d');
}

$muelle_id = (int) obtener('muelle_id', 0);
$tipoUnidad = trim(obtener('tipo_unidad', ''));
$tipoUnidad = in_array($tipoUnidad, ['', 'slip', 'inmueble'], true) ? $tipoUnidad : '';

$muellesOpts = $pdo->query('SELECT id, nombre FROM muelles ORDER BY nombre')->fetchAll(PDO::FETCH_KEY_PAIR);

/**
 * Días de estadía inclusive entre dos fechas (DATE).
 */
function marina_contrato_dias_estadia(string $fechaInicio, string $fechaFin): int
{
    try {
        $d1 = new DateTime($fechaInicio);
        $d2 = new DateTime($fechaFin);
    } catch (Exception $e) {
        return 0;
    }
    if ($d2 < $d1) {
        return 0;
    }
    return (int) $d1->diff($d2)->days + 1;
}

$sql = "
    SELECT co.id,
           co.fecha_inicio,
           co.fecha_fin,
           co.monto_total,
           cl.nombre AS navio,
           NULLIF(TRIM(cl.dueno_capitan), '') AS cliente_nombre,
           mu.nombre AS muelle_nombre,
           sl.nombre AS slip_nombre,
           g.nombre AS grupo_nombre,
           i.nombre AS inmueble_nombre
    FROM contratos co
    JOIN clientes cl ON cl.id = co.cliente_id
    LEFT JOIN muelles mu ON mu.id = co.muelle_id
    LEFT JOIN slips sl ON sl.id = co.slip_id
    LEFT JOIN grupos g ON g.id = co.grupo_id
    LEFT JOIN inmuebles i ON i.id = co.inmueble_id
    WHERE COALESCE(co.estado, 'activo') = 'activo'
      AND co.fecha_inicio <= ?
      AND co.fecha_fin >= ?
";
$params = [$fecha, $fecha];

if ($tipoUnidad === 'slip') {
    $sql .= ' AND co.slip_id IS NOT NULL ';
} elseif ($tipoUnidad === 'inmueble') {
    $sql .= ' AND co.inmueble_id IS NOT NULL ';
}
if ($muelle_id > 0) {
    $sql .= ' AND co.muelle_id = ? ';
    $params[] = $muelle_id;
}

$sql .= ' ORDER BY mu.nombre, sl.nombre, g.nombre, i.nombre, co.id';

$st = $pdo->prepare($sql);
$st->execute($params);
$filasRaw = $st->fetchAll(PDO::FETCH_ASSOC);

$filas = [];
$totalDia = 0.0;

foreach ($filasRaw as $r) {
    $dias = marina_contrato_dias_estadia((string) $r['fecha_inicio'], (string) $r['fecha_fin']);
    $montoTotal = (float) ($r['monto_total'] ?? 0);
    $montoDia = $dias > 0 ? round($montoTotal / $dias, 2) : 0.0;

    $muelle = (string) ($r['muelle_nombre'] ?? '');
    $slip = (string) ($r['slip_nombre'] ?? '');
    if ($muelle === '' && !empty($r['grupo_nombre'])) {
        $muelle = (string) $r['grupo_nombre'];
    }
    if ($slip === '' && !empty($r['inmueble_nombre'])) {
        $slip = (string) $r['inmueble_nombre'];
    }

    $filas[] = [
        'id' => (int) $r['id'],
        'cliente' => (string) ($r['cliente_nombre'] ?? '') !== '' ? (string) $r['cliente_nombre'] : '—',
        'navio' => (string) ($r['navio'] ?? ''),
        'muelle' => $muelle !== '' ? $muelle : '—',
        'slip' => $slip !== '' ? $slip : '—',
        'fecha_inicio' => $r['fecha_inicio'],
        'fecha_fin' => $r['fecha_fin'],
        'dias_estadia' => $dias,
        'monto_total' => $montoTotal,
        'monto_dia' => $montoDia,
    ];
    $totalDia += $montoDia;
}

$totalDia = round($totalDia, 2);

if (obtener('export') === 'excel') {
    $rows = [];
    foreach ($filas as $r) {
        $rows[] = [
            $r['id'],
            $r['cliente'],
            $r['navio'],
            $r['muelle'],
            $r['slip'],
            $r['fecha_inicio'],
            $r['fecha_fin'],
            $r['dias_estadia'],
            $r['monto_total'],
            $r['monto_dia'],
        ];
    }
    $pie = [['Total ingreso del día', '', '', '', '', '', '', '', '', $totalDia]];
    exportarExcel(
        'ingreso_x_dia',
        ['Contrato', 'Cliente', 'Navío', 'Muelle / Grupo', 'Slip / Inmueble', 'Inicio', 'Fin', 'Días estadía', 'Monto contrato', 'Ingreso del día'],
        $rows,
        $pie,
        $titulo . ' — ' . fechaFormato($fecha)
    );
}

require_once __DIR__ . '/../includes/layout.php';
?>
<h1 class="h4 mb-2">Ingreso x Día</h1>
<p class="text-muted small mb-3">
    Contratos <strong>activos</strong> cuya estadía incluye la fecha elegida.
    El ingreso del día es <strong>monto del contrato ÷ días de estadía</strong> (período del contrato: inicio a fin, ambos días inclusive).
</p>

<form method="get" class="toolbar mb-3">
    <input type="hidden" name="p" value="reporte-ingreso-dia">
    <div class="row g-2 align-items-end">
        <div class="col-12 col-md-3">
            <label class="form-label mb-1">Fecha</label>
            <input type="date" class="form-control" name="fecha" value="<?= e($fecha) ?>" required>
        </div>
        <div class="col-12 col-md-3">
            <label class="form-label mb-1">Muelle</label>
            <select class="form-select" name="muelle_id">
                <option value="0">Todos</option>
                <?php foreach ($muellesOpts as $mid => $mnom): ?>
                    <option value="<?= (int) $mid ?>" <?= $muelle_id === (int) $mid ? 'selected' : '' ?>><?= e($mnom) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-3">
            <label class="form-label mb-1">Tipo unidad</label>
            <select class="form-select" name="tipo_unidad">
                <option value="" <?= $tipoUnidad === '' ? 'selected' : '' ?>>Marina e inmuebles</option>
                <option value="slip" <?= $tipoUnidad === 'slip' ? 'selected' : '' ?>>Solo slips (marina)</option>
                <option value="inmueble" <?= $tipoUnidad === 'inmueble' ? 'selected' : '' ?>>Solo inmuebles</option>
            </select>
        </div>
        <div class="col-12 col-md-auto">
            <button type="submit" class="btn btn-primary">Consultar</button>
        </div>
        <div class="col-12 col-md-auto">
            <button type="submit" class="btn btn-success" name="export" value="excel">Exportar Excel</button>
        </div>
    </div>
</form>

<div class="row g-3 mb-3">
    <div class="col-12 col-md-4">
        <div class="card p-3 border-0 shadow-sm">
            <div class="text-muted small mb-1">Fecha consultada</div>
            <div class="fs-5 fw-semibold"><?= fechaFormato($fecha) ?></div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card p-3 border-0 shadow-sm">
            <div class="text-muted small mb-1">Contratos en ese día</div>
            <div class="fs-5 fw-semibold"><?= count($filas) ?></div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card p-3 border-0 shadow-sm bg-primary bg-opacity-10">
            <div class="text-muted small mb-1">Ingreso total del día</div>
            <div class="fs-5 fw-bold text-primary"><?= dinero($totalDia) ?></div>
        </div>
    </div>
</div>

<div class="card p-3">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Contrato</th>
                    <th>Cliente</th>
                    <th>Navío</th>
                    <th>Muelle</th>
                    <th>Slip</th>
                    <th>Inicio</th>
                    <th>Fin</th>
                    <th class="text-center">Días</th>
                    <th class="text-end">Monto contrato</th>
                    <th class="text-end">Ingreso del día</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($filas as $r): ?>
                <tr>
                    <td>#<?= (int) $r['id'] ?></td>
                    <td><?= e($r['cliente']) ?></td>
                    <td><?= e($r['navio']) ?></td>
                    <td><?= e($r['muelle']) ?></td>
                    <td><?= e($r['slip']) ?></td>
                    <td><?= fechaFormato($r['fecha_inicio']) ?></td>
                    <td><?= fechaFormato($r['fecha_fin']) ?></td>
                    <td class="text-center"><?= (int) $r['dias_estadia'] ?></td>
                    <td class="text-end"><?= dinero((float) $r['monto_total']) ?></td>
                    <td class="text-end fw-semibold"><?= dinero((float) $r['monto_dia']) ?></td>
                    <td>
                        <a class="btn btn-sm btn-outline-primary" href="<?= MARINA_URL ?>/index.php?p=contratos&amp;accion=editar&amp;id=<?= (int) $r['id'] ?>">Ver</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($filas === []): ?>
                <tr>
                    <td colspan="11" class="text-muted">No hay contratos activos vigentes en esta fecha con los filtros aplicados.</td>
                </tr>
            <?php else: ?>
                <tr class="table-light fw-semibold">
                    <td colspan="9" class="text-end">Total ingreso del día</td>
                    <td class="text-end"><?= dinero($totalDia) ?></td>
                    <td></td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
