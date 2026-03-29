<?php
/**
 * Reporte Estado de cuenta — cuentas bancarias (movimientos del período).
 * Cuenta: solo en cabecera. Referencia en ingresos por cuota: recibo del contrato (con respaldo en movimiento).
 */
$titulo = 'Reporte — Estado de cuenta bancarias';
$pdo = getDb();
require_once __DIR__ . '/../includes/export_excel.php';

$desde = obtener('desde', date('Y-m-01'));
$hasta = obtener('hasta', date('Y-m-d'));
$cuenta_id = (int) obtener('cuenta_id', 0);

$cuentasOpts = $pdo->query("
    SELECT c.id, CONCAT(b.nombre, ' - ', c.nombre) AS nom
    FROM cuentas c JOIN bancos b ON b.id = c.banco_id
    ORDER BY b.nombre, c.nombre
")->fetchAll(PDO::FETCH_KEY_PAIR);

$cuentaTitulo = '';
if ($cuenta_id > 0) {
    $cuentaTitulo = $cuentasOpts[$cuenta_id] ?? '';
}

$params = [$desde, $hasta];
$cuentaFiltro = '';
if ($cuenta_id > 0) {
    $cuentaFiltro = ' AND co.cuenta_id = ? ';
    $params[] = $cuenta_id;
}

$ing = $pdo->prepare("
    SELECT mo.fecha_pago AS fecha,
           'Ingreso' AS tipo,
           mo.monto AS monto,
           COALESCE(NULLIF(TRIM(mo.concepto), ''), CONCAT('Cuota #', cu.numero_cuota, ' — Contrato #', co.id)) AS concepto,
           TRIM(COALESCE(NULLIF(co.numero_recibo, ''), NULLIF(mo.referencia, ''), '')) AS referencia,
           '' AS descripcion_extra
    FROM cuotas_movimientos mo
    JOIN cuotas cu ON mo.cuota_id = cu.id
    JOIN contratos co ON cu.contrato_id = co.id
    WHERE mo.fecha_pago BETWEEN ? AND ?
      AND mo.tipo IN ('pago','abono')
      $cuentaFiltro
");
$ing->execute($params);
$ingresos = $ing->fetchAll(PDO::FETCH_ASSOC);

$ingComb = [];
try {
    $icParams = [$desde, $hasta];
    $icFiltro = '';
    if ($cuenta_id > 0) {
        $icFiltro = ' AND cd.cuenta_id = ? ';
        $icParams[] = $cuenta_id;
    }
    $ic = $pdo->prepare("
        SELECT cd.fecha AS fecha,
               'Ingreso' AS tipo,
               cd.monto_total AS monto,
               CONCAT('Combustible — ', UPPER(SUBSTRING(cd.tipo_combustible, 1, 1)), SUBSTRING(cd.tipo_combustible, 2), ' — ', cd.embarcacion) AS concepto,
               '' AS referencia,
               CONCAT('GLS: ', cd.gls) AS descripcion_extra
        FROM combustible_despachos cd
        WHERE cd.fecha BETWEEN ? AND ?
        $icFiltro
    ");
    $ic->execute($icParams);
    $ingComb = $ic->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $ingComb = [];
}

$gParams = [$desde, $hasta];
$gFiltro = '';
if ($cuenta_id > 0) {
    $gFiltro = ' AND g.cuenta_id = ? ';
    $gParams[] = $cuenta_id;
}
$cos = $pdo->prepare("
    SELECT g.fecha_gasto AS fecha,
           'Egreso' AS tipo,
           g.monto AS monto,
           CONCAT('Gasto — ', p.nombre) AS concepto,
           COALESCE(g.referencia, '') AS referencia,
           COALESCE(g.observaciones, '') AS descripcion_extra
    FROM gastos g
    JOIN partidas p ON p.id = g.partida_id
    WHERE g.fecha_gasto BETWEEN ? AND ?
      $gFiltro
");
$cos->execute($gParams);
$gastos = $cos->fetchAll(PDO::FETCH_ASSOC);

$manuales = [];
try {
    $mParams = [$desde, $hasta];
    $mFiltro = '';
    if ($cuenta_id > 0) {
        $mFiltro = ' AND mb.cuenta_id = ? ';
        $mParams[] = $cuenta_id;
    }
    $mov = $pdo->prepare("
        SELECT mb.fecha_movimiento AS fecha,
               CASE WHEN mb.tipo_movimiento = 'costo' THEN 'Egreso' ELSE 'Ingreso' END AS tipo,
               mb.monto AS monto,
               CONCAT('Mov. manual — ', fp.nombre) AS concepto,
               COALESCE(mb.referencia, '') AS referencia,
               COALESCE(mb.descripcion, '') AS descripcion_extra
        FROM movimientos_bancarios mb
        JOIN formas_pago fp ON fp.id = mb.forma_pago_id
        WHERE mb.fecha_movimiento BETWEEN ? AND ?
          $mFiltro
    ");
    $mov->execute($mParams);
    $manuales = $mov->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $manuales = [];
}

$movs = array_merge($ingresos, $ingComb, $gastos, $manuales);
usort($movs, function ($a, $b) {
    $ta = strtotime($a['fecha'] ?? '');
    $tb = strtotime($b['fecha'] ?? '');
    if ($ta === $tb) {
        return 0;
    }
    return $ta < $tb ? -1 : 1;
});

$acum = 0.0;
foreach ($movs as &$m) {
    $val = (float) ($m['monto'] ?? 0);
    if (($m['tipo'] ?? '') === 'Egreso') {
        $acum -= $val;
    } else {
        $acum += $val;
    }
    $m['acumulado'] = $acum;
}
unset($m);

$totIng = 0.0;
$totEgr = 0.0;
foreach ($movs as $m) {
    $val = (float) ($m['monto'] ?? 0);
    if (($m['tipo'] ?? '') === 'Egreso') {
        $totEgr += $val;
    } else {
        $totIng += $val;
    }
}

if (obtener('export') === 'excel') {
    $rows = [];
    foreach ($movs as $m) {
        $rows[] = [
            $m['fecha'] ?? '',
            $m['tipo'] ?? '',
            $m['concepto'] ?? '',
            $m['referencia'] ?? '',
            $m['descripcion_extra'] ?? '',
            (float) ($m['monto'] ?? 0),
            (float) ($m['acumulado'] ?? 0),
        ];
    }
    $neto = $totIng - $totEgr;
    $acumFinal = $movs === [] ? 0.0 : (float) ($movs[array_key_last($movs)]['acumulado'] ?? 0);
    $pie = [
        ['Total ingresos del período', '', '', '', '', $totIng, ''],
        ['Total egresos del período', '', '', '', '', $totEgr, ''],
        ['Neto del período', '', '', '', '', $neto, $acumFinal],
    ];
    exportarExcel('reporte_estado_cuenta_bancaria', ['Fecha', 'Tipo', 'Concepto', 'Referencia', 'Notas', 'Monto', 'Acumulado'], $rows, $pie);
}

$netoPeriodo = $totIng - $totEgr;
$acumFinal = $movs === [] ? 0.0 : (float) ($movs[array_key_last($movs)]['acumulado'] ?? 0);

require_once __DIR__ . '/../includes/layout.php';
?>
<h1 class="h4 mb-3">Reporte — Estado de cuenta bancarias</h1>

<form method="get" class="toolbar mb-3">
    <input type="hidden" name="p" value="reporte-estado-cuenta-bancarias">
    <div class="row g-2 align-items-end">
        <div class="col-12 col-md-3">
            <label class="form-label mb-1">Desde</label>
            <input type="date" class="form-control" name="desde" value="<?= e($desde) ?>">
        </div>
        <div class="col-12 col-md-3">
            <label class="form-label mb-1">Hasta</label>
            <input type="date" class="form-control" name="hasta" value="<?= e($hasta) ?>">
        </div>
        <div class="col-12 col-md-3">
            <label class="form-label mb-1">Cuenta</label>
            <select class="form-select" name="cuenta_id">
                <option value="0">Todas</option>
                <?php foreach ($cuentasOpts as $cid => $cnom): ?>
                    <option value="<?= (int) $cid ?>" <?= $cuenta_id === (int) $cid ? 'selected' : '' ?>><?= e($cnom) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-auto">
            <button type="submit" class="btn btn-primary">Filtrar</button>
        </div>
        <div class="col-12 col-md-auto">
            <button type="submit" class="btn btn-success" name="export" value="excel">Exportar Excel</button>
        </div>
    </div>
</form>

<div class="card p-3 mb-3">
    <div class="row g-2 small">
        <div class="col-md-12">
            <strong>Cuenta del reporte:</strong>
            <?php if ($cuenta_id > 0 && $cuentaTitulo !== ''): ?>
                <?= e($cuentaTitulo) ?>
            <?php else: ?>
                <span class="text-muted">Todas las cuentas (los movimientos pueden corresponder a distintas cuentas según su origen).</span>
            <?php endif; ?>
        </div>
        <div class="col-md-4"><strong>Total ingresos:</strong> <?= dinero($totIng) ?></div>
        <div class="col-md-4"><strong>Total egresos:</strong> <?= dinero($totEgr) ?></div>
        <div class="col-md-4"><strong>Neto período:</strong> <span class="<?= ($totIng - $totEgr) >= 0 ? 'text-success' : 'text-danger' ?>"><?= dinero($totIng - $totEgr) ?></span></div>
    </div>
    <p class="text-muted small mb-0 mt-2">El acumulado es dentro del período filtrado (no incluye saldo anterior). En ingresos por cuota, la <strong>referencia</strong> muestra el <strong>número de recibo del contrato</strong> cuando está cargado en el contrato.</p>
</div>

<div class="card p-3">
    <div class="table-responsive">
        <table class="table table-hover align-middle no-datatable">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Tipo</th>
                    <th style="min-width:200px">Concepto</th>
                    <th>Referencia</th>
                    <th>Notas</th>
                    <th class="text-end">Monto</th>
                    <th class="text-end">Acumulado</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($movs as $m): ?>
                <?php
                $tipo = $m['tipo'] ?? '';
                $color = $tipo === 'Ingreso' ? '#137333' : '#b42318';
                ?>
                <tr>
                    <td><?= fechaFormato($m['fecha']) ?></td>
                    <td><span class="fw-semibold" style="color:<?= $color ?>"><?= e($tipo) ?></span></td>
                    <td><?= e($m['concepto'] ?? '') ?></td>
                    <td><?= e($m['referencia'] ?? '') ?></td>
                    <td><?= e($m['descripcion_extra'] ?? '') ?></td>
                    <td class="text-end"><?= dinero((float) ($m['monto'] ?? 0)) ?></td>
                    <td class="text-end"><?= dinero((float) ($m['acumulado'] ?? 0)) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($movs)): ?>
                <tr>
                    <td class="text-muted">No hay movimientos en el período.</td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
            <?php else: ?>
                <tr class="table-light fw-semibold border-top border-2">
                    <td colspan="5" class="text-end">Total ingresos del período</td>
                    <td class="text-end text-success"><?= dinero($totIng) ?></td>
                    <td class="text-end text-muted">—</td>
                </tr>
                <tr class="table-light fw-semibold">
                    <td colspan="5" class="text-end">Total egresos del período</td>
                    <td class="text-end text-danger"><?= dinero($totEgr) ?></td>
                    <td class="text-end text-muted">—</td>
                </tr>
                <tr class="table-secondary fw-bold">
                    <td colspan="5" class="text-end">Neto del período</td>
                    <td class="text-end <?= $netoPeriodo >= 0 ? 'text-success' : 'text-danger' ?>"><?= dinero($netoPeriodo) ?></td>
                    <td class="text-end"><?= dinero($acumFinal) ?></td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
