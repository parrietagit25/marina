<?php
/**
 * Reporte de cuotas: contratos de marina (slips) e inmuebles, con filtros por fechas, estado, muelle/grupo/slip/inmueble.
 */
$titulo = 'Reporte de cuotas';
$pdo = getDb();
require_once __DIR__ . '/../includes/export_excel.php';

$desde = obtener('desde', date('Y-m-01'));
$hasta = obtener('hasta', date('Y-m-d'));
$estado = trim(obtener('estado', ''));
$contrato_id = (int) obtener('contrato_id', 0);
$tipo_unidad = trim(obtener('tipo_unidad', ''));
$muelle_id = (int) obtener('muelle_id', 0);
$grupo_id = (int) obtener('grupo_id', 0);
$slip_id = (int) obtener('slip_id', 0);
$inmueble_id = (int) obtener('inmueble_id', 0);

$muellesOpts = $pdo->query('SELECT id, nombre FROM muelles ORDER BY nombre')->fetchAll(PDO::FETCH_KEY_PAIR);
$gruposOpts = $pdo->query('SELECT id, nombre FROM grupos ORDER BY nombre')->fetchAll(PDO::FETCH_KEY_PAIR);
$slipsOpts = $pdo->query('
    SELECT s.id, CONCAT(m.nombre, " — ", s.nombre) AS nom
    FROM slips s JOIN muelles m ON s.muelle_id = m.id
    ORDER BY m.nombre, s.nombre
')->fetchAll(PDO::FETCH_KEY_PAIR);
$inmueblesOpts = $pdo->query('
    SELECT i.id, CONCAT(g.nombre, " — ", i.nombre) AS nom
    FROM inmuebles i JOIN grupos g ON i.grupo_id = g.id
    ORDER BY g.nombre, i.nombre
')->fetchAll(PDO::FETCH_KEY_PAIR);

$sql = "
    SELECT cu.id AS cuota_id,
           cu.contrato_id,
           cu.numero_cuota,
           cu.monto,
           cu.fecha_vencimiento,
           cu.fecha_pago AS fecha_pago_legacy,
           cl.nombre AS cliente,
           COALESCE(co.estado, 'activo') AS contrato_estado,
           COALESCE(mov.pagado_mov, 0) AS pagado_mov,
           co.slip_id,
           co.inmueble_id,
           co.muelle_id,
           co.grupo_id,
           m.nombre AS muelle_nombre,
           s.nombre AS slip_nombre,
           g.nombre AS grupo_nombre,
           i.nombre AS inmueble_nombre
    FROM cuotas cu
    JOIN contratos co ON cu.contrato_id = co.id
    JOIN clientes cl ON co.cliente_id = cl.id
    LEFT JOIN (
        SELECT cuota_id, SUM(monto) AS pagado_mov
        FROM cuotas_movimientos
        WHERE tipo IN ('pago','abono')
        GROUP BY cuota_id
    ) mov ON mov.cuota_id = cu.id
    LEFT JOIN muelles m ON co.muelle_id = m.id
    LEFT JOIN slips s ON co.slip_id = s.id
    LEFT JOIN grupos g ON co.grupo_id = g.id
    LEFT JOIN inmuebles i ON co.inmueble_id = i.id
    WHERE cu.fecha_vencimiento BETWEEN ? AND ?
";
$params = [$desde, $hasta];

if ($contrato_id > 0) {
    $sql .= ' AND cu.contrato_id = ? ';
    $params[] = $contrato_id;
}
if ($tipo_unidad === 'slip') {
    $sql .= ' AND co.slip_id IS NOT NULL ';
} elseif ($tipo_unidad === 'inmueble') {
    $sql .= ' AND co.inmueble_id IS NOT NULL ';
}
if ($muelle_id > 0) {
    $sql .= ' AND co.muelle_id = ? ';
    $params[] = $muelle_id;
}
if ($grupo_id > 0) {
    $sql .= ' AND co.grupo_id = ? ';
    $params[] = $grupo_id;
}
if ($slip_id > 0) {
    $sql .= ' AND co.slip_id = ? ';
    $params[] = $slip_id;
}
if ($inmueble_id > 0) {
    $sql .= ' AND co.inmueble_id = ? ';
    $params[] = $inmueble_id;
}

$sql .= ' ORDER BY cu.fecha_vencimiento ASC, cu.contrato_id ASC, cu.numero_cuota ASC ';

$st = $pdo->prepare($sql);
$st->execute($params);
$raw = $st->fetchAll(PDO::FETCH_ASSOC);

$hoy = date('Y-m-d');
$filas = [];
foreach ($raw as $r) {
    $monto = (float) $r['monto'];
    $pagadoMov = (float) $r['pagado_mov'];
    $tieneMovs = $pagadoMov > 0.00001;
    if ($tieneMovs) {
        $pagado = $pagadoMov;
    } elseif (!empty($r['fecha_pago_legacy'])) {
        $pagado = $monto;
    } else {
        $pagado = 0.0;
    }
    $saldo = max(0, $monto - $pagado);
    $pagada = $saldo <= 0.00001;
    $fv = (string) ($r['fecha_vencimiento'] ?? '');

    if ($pagada) {
        $estadoFila = 'Pagada';
    } elseif ($fv !== '' && $fv < $hoy) {
        $estadoFila = 'Vencida';
    } else {
        $estadoFila = 'Pendiente';
    }

    if ($estado === 'pagada' && !$pagada) {
        continue;
    }
    if ($estado === 'pendiente' && $pagada) {
        continue;
    }
    if ($estado === 'pendiente_sin_vencer' && ($pagada || $estadoFila !== 'Pendiente')) {
        continue;
    }
    if ($estado === 'vencida' && $estadoFila !== 'Vencida') {
        continue;
    }

    $slipId = (int) ($r['slip_id'] ?? 0);
    $inmId = (int) ($r['inmueble_id'] ?? 0);
    if ($slipId > 0) {
        $tipoContrato = 'Marina (slip)';
        $unidad = trim(($r['muelle_nombre'] ?? '') . ' / ' . ($r['slip_nombre'] ?? ''));
    } elseif ($inmId > 0) {
        $tipoContrato = 'Inmueble';
        $unidad = trim(($r['grupo_nombre'] ?? '') . ' / ' . ($r['inmueble_nombre'] ?? ''));
    } else {
        $tipoContrato = '—';
        $unidad = '—';
    }

    $filas[] = [
        'cuota_id' => (int) $r['cuota_id'],
        'contrato_id' => (int) $r['contrato_id'],
        'numero_cuota' => (int) $r['numero_cuota'],
        'cliente' => $r['cliente'],
        'monto' => $monto,
        'pagado' => $pagado,
        'saldo' => $saldo,
        'fecha_vencimiento' => $fv,
        'estado' => $estadoFila,
        'contrato_estado' => (string) ($r['contrato_estado'] ?? 'activo'),
        'tipo_contrato' => $tipoContrato,
        'unidad' => $unidad !== '' ? $unidad : '—',
    ];
}

$totalMonto = array_sum(array_column($filas, 'monto'));
$totalPagado = array_sum(array_column($filas, 'pagado'));
$totalSaldo = array_sum(array_column($filas, 'saldo'));

if (obtener('export') === 'excel') {
    $rows = [];
    foreach ($filas as $f) {
        $rows[] = [
            (int) $f['contrato_id'],
            (int) $f['numero_cuota'],
            $f['cliente'],
            $f['tipo_contrato'],
            $f['unidad'],
            (float) $f['monto'],
            (float) $f['pagado'],
            (float) $f['saldo'],
            $f['fecha_vencimiento'],
            $f['estado'],
            ($f['contrato_estado'] ?? 'activo') === 'activo' ? 'Activo' : 'Terminado',
        ];
    }
    $pie = [[
        'Totales',
        '',
        '',
        '',
        '',
        $totalMonto,
        $totalPagado,
        $totalSaldo,
        '',
        '',
        '',
    ]];
    exportarExcel('reporte_cuotas', [
        'Contrato',
        'Cuota',
        'Cliente',
        'Tipo',
        'Unidad (muelle/slip o grupo/inmueble)',
        'Monto',
        'Pagado',
        'Saldo',
        'Vencimiento',
        'Estado cuota',
        'Estado contrato',
    ], $rows, $pie);
}

require_once __DIR__ . '/../includes/layout.php';
?>
<h1 class="h4 mb-2">Reporte de cuotas</h1>
<p class="text-muted small mb-3">Cuotas de contratos de <strong>marina</strong> (muelle/slip) e <strong>inmuebles</strong> (grupo/inmueble). El rango aplica a la <strong>fecha de vencimiento</strong> de cada cuota. Estados: <strong>Pagada</strong> (cubierta por movimientos o pago único antiguo), <strong>Pendiente</strong> (saldo y aún no vence), <strong>Vencida</strong> (saldo y vencimiento anterior a hoy).</p>

<form method="get" class="toolbar mb-3">
    <input type="hidden" name="p" value="reporte-cuotas">
    <div class="row g-2 align-items-end">
        <div class="col-12 col-md-6 col-lg-2">
            <label class="form-label mb-1">Vence desde</label>
            <input type="date" class="form-control" name="desde" value="<?= e($desde) ?>">
        </div>
        <div class="col-12 col-md-6 col-lg-2">
            <label class="form-label mb-1">Vence hasta</label>
            <input type="date" class="form-control" name="hasta" value="<?= e($hasta) ?>">
        </div>
        <div class="col-12 col-md-6 col-lg-2">
            <label class="form-label mb-1">Estado cuota</label>
            <select class="form-select" name="estado">
                <option value="" <?= $estado === '' ? 'selected' : '' ?>>Todos</option>
                <option value="pagada" <?= $estado === 'pagada' ? 'selected' : '' ?>>Pagada</option>
                <option value="pendiente" <?= $estado === 'pendiente' ? 'selected' : '' ?>>Pendiente (incluye vencidas)</option>
                <option value="pendiente_sin_vencer" <?= $estado === 'pendiente_sin_vencer' ? 'selected' : '' ?>>Pendiente (no vencida)</option>
                <option value="vencida" <?= $estado === 'vencida' ? 'selected' : '' ?>>Vencida</option>
            </select>
        </div>
        <div class="col-12 col-md-6 col-lg-2">
            <label class="form-label mb-1">Tipo unidad</label>
            <select class="form-select" name="tipo_unidad">
                <option value="" <?= $tipo_unidad === '' ? 'selected' : '' ?>>Todos</option>
                <option value="slip" <?= $tipo_unidad === 'slip' ? 'selected' : '' ?>>Marina (slip)</option>
                <option value="inmueble" <?= $tipo_unidad === 'inmueble' ? 'selected' : '' ?>>Inmueble</option>
            </select>
        </div>
        <div class="col-12 col-md-6 col-lg-2">
            <label class="form-label mb-1">Muelle</label>
            <select class="form-select" name="muelle_id">
                <option value="0">Todos</option>
                <?php foreach ($muellesOpts as $mid => $mnom): ?>
                    <option value="<?= (int) $mid ?>" <?= $muelle_id === (int) $mid ? 'selected' : '' ?>><?= e($mnom) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-6 col-lg-2">
            <label class="form-label mb-1">Grupo</label>
            <select class="form-select" name="grupo_id">
                <option value="0">Todos</option>
                <?php foreach ($gruposOpts as $gid => $gnom): ?>
                    <option value="<?= (int) $gid ?>" <?= $grupo_id === (int) $gid ? 'selected' : '' ?>><?= e($gnom) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-6 col-lg-3">
            <label class="form-label mb-1">Slip</label>
            <select class="form-select" name="slip_id">
                <option value="0">Todos</option>
                <?php foreach ($slipsOpts as $sid => $snom): ?>
                    <option value="<?= (int) $sid ?>" <?= $slip_id === (int) $sid ? 'selected' : '' ?>><?= e($snom) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-6 col-lg-3">
            <label class="form-label mb-1">Inmueble</label>
            <select class="form-select" name="inmueble_id">
                <option value="0">Todos</option>
                <?php foreach ($inmueblesOpts as $iid => $inom): ?>
                    <option value="<?= (int) $iid ?>" <?= $inmueble_id === (int) $iid ? 'selected' : '' ?>><?= e($inom) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-6 col-lg-2">
            <label class="form-label mb-1">Contrato #</label>
            <input type="number" min="0" class="form-control" name="contrato_id" value="<?= $contrato_id > 0 ? (int) $contrato_id : '' ?>" placeholder="Opcional">
        </div>
        <div class="col-12 col-md-auto d-flex flex-wrap gap-2">
            <button type="submit" class="btn btn-primary">Filtrar</button>
            <button type="submit" class="btn btn-success" name="export" value="excel">Exportar Excel</button>
        </div>
    </div>
</form>

<div class="card p-3 mb-3">
    <div class="row g-2 small">
        <div class="col-md-4"><strong>Total monto (filtrado):</strong> <?= dinero($totalMonto) ?></div>
        <div class="col-md-4"><strong>Total pagado:</strong> <?= dinero($totalPagado) ?></div>
        <div class="col-md-4"><strong>Total saldo:</strong> <?= dinero($totalSaldo) ?></div>
    </div>
    <p class="text-muted small mb-0 mt-2"><strong>Pendiente (incluye vencidas)</strong>: cuota con saldo (no pagada por completo). <strong>Pendiente (no vencida)</strong>: saldo y fecha de vencimiento ≥ hoy. <strong>Vencida</strong>: saldo y vencimiento anterior a hoy (<?= fechaFormato($hoy) ?>).</p>
</div>

<div class="card p-3">
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Contrato</th>
                    <th>Cuota</th>
                    <th>Cliente</th>
                    <th>Tipo</th>
                    <th>Unidad</th>
                    <th class="text-end">Monto</th>
                    <th class="text-end">Pagado</th>
                    <th class="text-end">Saldo</th>
                    <th>Vencimiento</th>
                    <th>Estado cuota</th>
                    <th>Estado contrato</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($filas as $f): ?>
                <tr>
                    <td><a href="<?= MARINA_URL ?>/index.php?p=contratos&amp;accion=cuotas&amp;id=<?= (int) $f['contrato_id'] ?>">#<?= (int) $f['contrato_id'] ?></a></td>
                    <td>#<?= (int) $f['numero_cuota'] ?></td>
                    <td><?= e($f['cliente']) ?></td>
                    <td><?= e($f['tipo_contrato']) ?></td>
                    <td><?= e($f['unidad']) ?></td>
                    <td class="text-end"><?= dinero($f['monto']) ?></td>
                    <td class="text-end"><?= dinero($f['pagado']) ?></td>
                    <td class="text-end"><?= dinero($f['saldo']) ?></td>
                    <td><?= fechaFormato($f['fecha_vencimiento']) ?></td>
                    <td>
                        <?php
                        $badge = 'bg-secondary';
                        if ($f['estado'] === 'Pagada') {
                            $badge = 'bg-success';
                        } elseif ($f['estado'] === 'Vencida') {
                            $badge = 'bg-danger';
                        } elseif ($f['estado'] === 'Pendiente') {
                            $badge = 'bg-warning text-dark';
                        }
                        ?>
                        <span class="badge <?= $badge ?>"><?= e($f['estado']) ?></span>
                    </td>
                    <td><?= ($f['contrato_estado'] ?? 'activo') === 'activo' ? 'Activo' : 'Terminado' ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($filas)): ?>
                <tr>
                    <td class="text-muted" colspan="11">No hay registros con los filtros indicados.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
