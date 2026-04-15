<?php
/**
 * Reporte Ingresos — detalle o agrupado por ubicación (grupo/muelle); cuotas sin banco/cuenta en tabla.
 */
$titulo = 'Reporte de ingreso';
$pdo = getDb();
require_once __DIR__ . '/../includes/reportes_queries.php';
require_once __DIR__ . '/../includes/export_excel.php';

$desde = obtener('desde', date('Y-m-01'));
$hasta = obtener('hasta', date('Y-m-d'));
$cuenta_id = (int) obtener('cuenta_id', 0);
$vista = obtener('vista', 'detallado');
$vista = ($vista === 'agrupado') ? 'agrupado' : 'detallado';

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

$fragCo = reportes_sql_fragment_filtros_contrato_co($contrato_id, $tipo_unidad, $muelle_id, $grupo_id, $slip_id, $inmueble_id);
$allowedCuotaIds = reportes_ids_cuotas_por_estado_vencimiento(
    $pdo,
    $desde,
    $hasta,
    $estado,
    $contrato_id,
    $tipo_unidad,
    $muelle_id,
    $grupo_id,
    $slip_id,
    $inmueble_id
);
$omitirSinContrato = ($fragCo !== '' || $allowedCuotaIds !== null);

$cuentaCond = '';
$params = [$desde, $hasta];
if ($cuenta_id > 0) {
    $cuentaCond = ' AND co.cuenta_id = ? ';
    $params[] = $cuenta_id;
}
$params[] = $desde;
$params[] = $hasta;
if ($cuenta_id > 0) {
    $params[] = $cuenta_id;
}

$sql = 'SELECT * FROM (' . reportesSqlIngresosDetalle($cuentaCond, $fragCo, $fragCo) . ') x ORDER BY x.fecha, x.cliente_nombre, x.grupo_nombre';
$st = $pdo->prepare($sql);
$st->execute($params);
$filas = $st->fetchAll(PDO::FETCH_ASSOC);

$labCred = marina_ui_credito();

$manuales = [];
$combIngresos = [];
$eleIng = [];

if (!$omitirSinContrato) {
    try {
        $mParams = [$desde, $hasta];
        $mCuenta = '';
        if ($cuenta_id > 0) {
            $mCuenta = ' AND mb.cuenta_id = ? ';
            $mParams[] = $cuenta_id;
        }
        $mov = $pdo->prepare("
        SELECT mb.fecha_movimiento AS fecha,
               mb.monto AS monto,
               '{$labCred}' AS tipo_linea,
               CONCAT('Mov. manual — ', fp.nombre) AS concepto,
               '' AS cuenta_nombre,
               mb.cuenta_id,
               '' AS cliente_nombre,
               NULL AS contrato_id,
               NULL AS cuota_id,
               NULL AS numero_cuota,
               COALESCE(mb.referencia, '') AS referencia,
               fp.nombre AS forma_pago_nombre,
               'Movimientos manuales' AS grupo_nombre,
               '' AS slip_o_inmueble
        FROM movimientos_bancarios mb
        JOIN cuentas c ON c.id = mb.cuenta_id
        JOIN bancos b ON b.id = c.banco_id
        JOIN formas_pago fp ON fp.id = mb.forma_pago_id
        WHERE mb.fecha_movimiento BETWEEN ? AND ?
          AND mb.tipo_movimiento = 'ingreso'
          $mCuenta
    ");
        $mov->execute($mParams);
        $manuales = $mov->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $manuales = [];
    }

    try {
        $cParams = [$desde, $hasta];
        $cCuenta = '';
        if ($cuenta_id > 0) {
            $cCuenta = ' AND cd.cuenta_id = ? ';
            $cParams[] = $cuenta_id;
        }
        $combSt = $pdo->prepare("
        SELECT cd.fecha AS fecha,
               cd.monto_total AS monto,
               '{$labCred}' AS tipo_linea,
               CONCAT('Combustible — ', UPPER(SUBSTRING(cd.tipo_combustible, 1, 1)), SUBSTRING(cd.tipo_combustible, 2), ' — ', cd.embarcacion) AS concepto,
               '' AS cuenta_nombre,
               cd.cuenta_id,
               cd.embarcacion AS cliente_nombre,
               NULL AS contrato_id,
               NULL AS cuota_id,
               NULL AS numero_cuota,
               '' AS referencia,
               '' AS forma_pago_nombre,
               'Combustible (despacho)' AS grupo_nombre,
               '' AS slip_o_inmueble
        FROM combustible_despachos cd
        JOIN cuentas c ON c.id = cd.cuenta_id
        JOIN bancos b ON b.id = c.banco_id
        WHERE cd.fecha BETWEEN ? AND ?
        $cCuenta
    ");
        $combSt->execute($cParams);
        $combIngresos = $combSt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $combIngresos = [];
    }
}

if ($allowedCuotaIds === null && (!$omitirSinContrato || $fragCo !== '')) {
    try {
        $ep = [$desde, $hasta];
        $ec = '';
        if ($cuenta_id > 0) {
            $ec = ' AND ep.cuenta_id = ? ';
            $ep[] = $cuenta_id;
        }
        $coFragEl = $fragCo;
        $stEl = $pdo->prepare("
        SELECT ep.fecha_pago AS fecha,
               ep.monto AS monto,
               '{$labCred}' AS tipo_linea,
               CONCAT('Electricidad — Contrato #', co.id) AS concepto,
               CONCAT(b.nombre, ' - ', c.nombre) AS cuenta_nombre,
               ep.cuenta_id,
               cl.nombre AS cliente_nombre,
               co.id AS contrato_id,
               NULL AS cuota_id,
               NULL AS numero_cuota,
               TRIM(COALESCE(NULLIF(co.numero_recibo, ''), NULLIF(ep.referencia, ''), '')) AS referencia,
               fp.nombre AS forma_pago_nombre,
               'Electricidad (contrato)' AS grupo_nombre,
               '' AS slip_o_inmueble
        FROM contrato_electricidad_pagos ep
        JOIN contrato_electricidad_facturas f ON f.id = ep.factura_id
        JOIN contratos co ON co.id = f.contrato_id
        JOIN clientes cl ON cl.id = co.cliente_id
        JOIN cuentas c ON c.id = ep.cuenta_id
        JOIN bancos b ON b.id = c.banco_id
        LEFT JOIN formas_pago fp ON fp.id = ep.forma_pago_id
        WHERE ep.fecha_pago BETWEEN ? AND ?
          $ec
          $coFragEl
    ");
        $stEl->execute($ep);
        $eleIng = $stEl->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $eleIng = [];
    }
}

$filas = array_merge($filas, $manuales, $combIngresos, $eleIng);

if ($allowedCuotaIds !== null) {
    $set = array_flip(array_map('intval', $allowedCuotaIds));
    $filas = array_values(array_filter($filas, static function (array $r) use ($set): bool {
        $cid = (int) ($r['cuota_id'] ?? 0);

        return $cid > 0 && isset($set[$cid]);
    }));
}
usort($filas, function ($a, $b) {
    return strcmp((string) ($a['fecha'] ?? ''), (string) ($b['fecha'] ?? ''));
});

$total = array_sum(array_map(static function ($r) {
    return (float) ($r['monto'] ?? 0);
}, $filas));

$agrupadoPorGrupo = [];
if ($vista === 'agrupado') {
    foreach ($filas as $r) {
        $g = trim((string) ($r['grupo_nombre'] ?? ''));
        if ($g === '') {
            $g = 'Sin ubicación';
        }
        if (!isset($agrupadoPorGrupo[$g])) {
            $agrupadoPorGrupo[$g] = 0.0;
        }
        $agrupadoPorGrupo[$g] += (float) ($r['monto'] ?? 0);
    }
    uksort($agrupadoPorGrupo, 'strnatcasecmp');
}

if (obtener('export') === 'excel') {
    if ($vista === 'agrupado') {
        $rows = [];
        foreach ($agrupadoPorGrupo as $nom => $m) {
            $rows[] = [$nom, $m];
        }
        $pie = [['Total', $total]];
        exportarExcel('reporte_ingresos_agrupado', ['Grupo / origen', 'Monto'], $rows, $pie);
    } else {
        $rows = [];
        foreach ($filas as $r) {
            $rows[] = [
                $r['fecha'] ?? '',
                $r['cliente_nombre'] ?? '',
                $r['grupo_nombre'] ?? '',
                $r['slip_o_inmueble'] ?? '',
                $r['concepto'] ?? '',
                $r['referencia'] ?? '',
                (float) ($r['monto'] ?? 0),
            ];
        }
        $pie = [['Total', '', '', '', '', '', $total]];
        exportarExcel('reporte_ingresos', ['Fecha', 'Cliente', 'Grupo', 'Slip / inmueble', 'Concepto', 'Referencia', 'Monto'], $rows, $pie);
    }
}

$cuentasOpts = $pdo->query("
    SELECT c.id, CONCAT(b.nombre, ' - ', c.nombre) AS nom
    FROM cuentas c JOIN bancos b ON b.id = c.banco_id
    ORDER BY b.nombre, c.nombre
")->fetchAll(PDO::FETCH_KEY_PAIR);

require_once __DIR__ . '/../includes/layout.php';
?>
<h1 class="h4 mb-2">Reporte de ingreso</h1>
<p class="text-muted small mb-3">Movimientos del período clasificados como <?= e(marina_ui_credito()) ?> (cuotas, combustible, electricidad y registros manuales en banco). Las fechas <strong>desde / hasta</strong> aplican a la <strong>fecha de pago acreditado</strong>. El filtro <strong>estado cuota</strong> usa el mismo criterio que el reporte de cuotas: <strong>vencimiento</strong> en ese rango y estado (pagada / pendiente / vencida).</p>

<form method="get" class="toolbar mb-3">
    <input type="hidden" name="p" value="reporte-ingresos">
    <div class="row g-2 align-items-end">
        <div class="col-12 col-md-6 col-lg-2">
            <label class="form-label mb-1">Pago desde</label>
            <input type="date" class="form-control" name="desde" value="<?= e($desde) ?>">
        </div>
        <div class="col-12 col-md-6 col-lg-2">
            <label class="form-label mb-1">Pago hasta</label>
            <input type="date" class="form-control" name="hasta" value="<?= e($hasta) ?>">
        </div>
        <div class="col-12 col-md-6 col-lg-2">
            <label class="form-label mb-1">Estado cuota <span class="text-muted fw-normal">(por vencimiento)</span></label>
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
        <div class="col-12 col-md-6 col-lg-3">
            <label class="form-label mb-1">Cuenta (filtro acreditación)</label>
            <select class="form-select" name="cuenta_id">
                <option value="0">Todas</option>
                <?php foreach ($cuentasOpts as $cid => $cnom): ?>
                    <option value="<?= (int) $cid ?>" <?= $cuenta_id === (int) $cid ? 'selected' : '' ?>><?= e($cnom) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-6 col-lg-2">
            <label class="form-label mb-1">Vista</label>
            <select class="form-select" name="vista">
                <option value="detallado" <?= $vista === 'detallado' ? 'selected' : '' ?>>Detallado</option>
                <option value="agrupado" <?= $vista === 'agrupado' ? 'selected' : '' ?>>Agrupado (totales por grupo)</option>
            </select>
        </div>
        <div class="col-12 col-md-auto d-flex flex-wrap gap-2">
            <button type="submit" class="btn btn-primary">Filtrar</button>
            <button type="submit" class="btn btn-success" name="export" value="excel">Exportar Excel</button>
        </div>
    </div>
    <p class="text-muted small mb-0 mt-2">Con <strong>muelle, grupo, slip, inmueble, contrato o estado cuota</strong> solo se listan líneas ligadas a contratos (no aparecen movimientos manuales ni combustible). Con <strong>estado cuota</strong> tampoco electricidad. El <strong>grupo</strong> en cuotas es el grupo de locales o el muelle; el filtro por cuenta no se muestra en la tabla.</p>
</form>

<div class="card p-3 mb-3">
    <strong>Total créditos:</strong> <?= dinero($total) ?>
</div>

<?php if ($vista === 'agrupado'): ?>
<div class="card p-3">
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Grupo / origen</th>
                    <th class="text-end">Total crédito</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($agrupadoPorGrupo as $nom => $m): ?>
                <tr>
                    <td><?= e($nom) ?></td>
                    <td class="text-end"><?= dinero($m) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($agrupadoPorGrupo)): ?>
                <tr><td colspan="2" class="text-muted">No hay créditos en el período.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php else: ?>
<div class="card p-3">
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Cliente</th>
                    <th>Grupo</th>
                    <th>Slip / inmueble</th>
                    <th>Concepto</th>
                    <th>Referencia</th>
                    <th class="text-end">Monto</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($filas as $r): ?>
                <tr>
                    <td><?= fechaFormato($r['fecha']) ?></td>
                    <td><?= e($r['cliente_nombre'] ?? '') ?></td>
                    <td><?= e($r['grupo_nombre'] ?? '') ?></td>
                    <td><?= e($r['slip_o_inmueble'] ?? '') ?: '—' ?></td>
                    <td><?= e($r['concepto'] ?? '') ?></td>
                    <td><?= e($r['referencia'] ?? '') ?></td>
                    <td class="text-end"><?= dinero((float) ($r['monto'] ?? 0)) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($filas)): ?>
                <tr><td colspan="7" class="text-muted">No hay créditos en el período.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
