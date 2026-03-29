<?php
/**
 * Reporte Ingresos — detalle o agrupado por ubicación (grupo/muelle); cuotas sin banco/cuenta en tabla.
 */
$titulo = 'Reporte — Ingreso';
$pdo = getDb();
require_once __DIR__ . '/../includes/reportes_queries.php';
require_once __DIR__ . '/../includes/export_excel.php';

$desde = obtener('desde', date('Y-m-01'));
$hasta = obtener('hasta', date('Y-m-d'));
$cuenta_id = (int) obtener('cuenta_id', 0);
$vista = obtener('vista', 'detallado');
$vista = ($vista === 'agrupado') ? 'agrupado' : 'detallado';

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

$sql = 'SELECT * FROM (' . reportesSqlIngresosDetalle($cuentaCond) . ') x ORDER BY x.fecha, x.cliente_nombre, x.grupo_nombre';
$st = $pdo->prepare($sql);
$st->execute($params);
$filas = $st->fetchAll(PDO::FETCH_ASSOC);

$manuales = [];
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
               'Ingreso' AS tipo_linea,
               CONCAT('Mov. manual — ', fp.nombre) AS concepto,
               '' AS cuenta_nombre,
               mb.cuenta_id,
               '' AS cliente_nombre,
               NULL AS contrato_id,
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

$combIngresos = [];
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
               'Ingreso' AS tipo_linea,
               CONCAT('Combustible — ', UPPER(SUBSTRING(cd.tipo_combustible, 1, 1)), SUBSTRING(cd.tipo_combustible, 2), ' — ', cd.embarcacion) AS concepto,
               '' AS cuenta_nombre,
               cd.cuenta_id,
               cd.embarcacion AS cliente_nombre,
               NULL AS contrato_id,
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

$filas = array_merge($filas, $manuales, $combIngresos);
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
<h1 class="h4 mb-3">Reporte — Ingreso</h1>

<form method="get" class="toolbar mb-3">
    <input type="hidden" name="p" value="reporte-ingresos">
    <div class="row g-2 align-items-end">
        <div class="col-12 col-md-2">
            <label class="form-label mb-1">Desde</label>
            <input type="date" class="form-control" name="desde" value="<?= e($desde) ?>">
        </div>
        <div class="col-12 col-md-2">
            <label class="form-label mb-1">Hasta</label>
            <input type="date" class="form-control" name="hasta" value="<?= e($hasta) ?>">
        </div>
        <div class="col-12 col-md-3">
            <label class="form-label mb-1">Cuenta (filtro acreditación)</label>
            <select class="form-select" name="cuenta_id">
                <option value="0">Todas</option>
                <?php foreach ($cuentasOpts as $cid => $cnom): ?>
                    <option value="<?= (int) $cid ?>" <?= $cuenta_id === (int) $cid ? 'selected' : '' ?>><?= e($cnom) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-3">
            <label class="form-label mb-1">Vista</label>
            <select class="form-select" name="vista">
                <option value="detallado" <?= $vista === 'detallado' ? 'selected' : '' ?>>Detallado</option>
                <option value="agrupado" <?= $vista === 'agrupado' ? 'selected' : '' ?>>Agrupado (totales por grupo)</option>
            </select>
        </div>
        <div class="col-12 col-md-auto">
            <button type="submit" class="btn btn-primary">Filtrar</button>
        </div>
        <div class="col-12 col-md-auto">
            <button type="submit" class="btn btn-success" name="export" value="excel">Exportar Excel</button>
        </div>
    </div>
    <p class="text-muted small mb-0 mt-2">En pagos de cuota, el <strong>grupo</strong> es el nombre del grupo de locales o del muelle; el <strong>slip / inmueble</strong> es el slip o el inmueble del contrato. El filtro por cuenta no se muestra en la tabla (solo acota el período).</p>
</form>

<div class="card p-3 mb-3">
    <strong>Total ingresos:</strong> <?= dinero($total) ?>
</div>

<?php if ($vista === 'agrupado'): ?>
<div class="card p-3">
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Grupo / origen</th>
                    <th class="text-end">Total ingreso</th>
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
                <tr><td colspan="2" class="text-muted">No hay ingresos en el período.</td></tr>
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
                <tr><td colspan="7" class="text-muted">No hay ingresos en el período.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
