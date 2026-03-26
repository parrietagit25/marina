<?php
/**
 * Reporte Ingresos / Egresos — línea de tiempo combinada del período.
 */
$titulo = 'Reporte — Ingresos / Egresos';
$pdo = getDb();
require_once __DIR__ . '/../includes/reportes_queries.php';
require_once __DIR__ . '/../includes/export_excel.php';

$desde = obtener('desde', date('Y-m-01'));
$hasta = obtener('hasta', date('Y-m-d'));
$cuenta_id = (int) obtener('cuenta_id', 0);

$cuentaCond = '';
$paramsIng = [$desde, $hasta];
if ($cuenta_id > 0) {
    $cuentaCond = ' AND co.cuenta_id = ? ';
    $paramsIng[] = $cuenta_id;
}
$paramsIng[] = $desde;
$paramsIng[] = $hasta;
if ($cuenta_id > 0) {
    $paramsIng[] = $cuenta_id;
}

$sqlIng = 'SELECT fecha, monto, concepto, cuenta_nombre, referencia, forma_pago_nombre, cliente_nombre FROM (' . reportesSqlIngresosDetalle($cuentaCond) . ') z';
$st = $pdo->prepare($sqlIng);
$st->execute($paramsIng);
$ingFilas = $st->fetchAll(PDO::FETCH_ASSOC);

$manIng = [];
try {
    $mp = [$desde, $hasta];
    $mc = '';
    if ($cuenta_id > 0) {
        $mc = ' AND mb.cuenta_id = ? ';
        $mp[] = $cuenta_id;
    }
    $mov = $pdo->prepare("
        SELECT mb.fecha_movimiento AS fecha,
               mb.monto AS monto,
               CONCAT('Mov. manual — ', fp.nombre) AS concepto,
               CONCAT(b.nombre, ' - ', c.nombre) AS cuenta_nombre,
               COALESCE(mb.referencia, '') AS referencia,
               fp.nombre AS forma_pago_nombre,
               '' AS cliente_nombre
        FROM movimientos_bancarios mb
        JOIN cuentas c ON c.id = mb.cuenta_id
        JOIN bancos b ON b.id = c.banco_id
        JOIN formas_pago fp ON fp.id = mb.forma_pago_id
        WHERE mb.fecha_movimiento BETWEEN ? AND ?
          AND mb.tipo_movimiento = 'ingreso'
          $mc
    ");
    $mov->execute($mp);
    $manIng = $mov->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $manIng = [];
}

$gParams = [$desde, $hasta];
$gWhere = '';
if ($cuenta_id > 0) {
    $gWhere = ' AND g.cuenta_id = ? ';
    $gParams[] = $cuenta_id;
}
$st = $pdo->prepare("
    SELECT g.fecha_gasto AS fecha,
           g.monto AS monto,
           CONCAT('Gasto — ', p.nombre) AS concepto,
           CONCAT(b.nombre, ' - ', c.nombre) AS cuenta_nombre,
           COALESCE(g.referencia, '') AS referencia,
           fp.nombre AS forma_pago_nombre,
           pr.nombre AS cliente_nombre
    FROM gastos g
    JOIN partidas p ON p.id = g.partida_id
    JOIN proveedores pr ON pr.id = g.proveedor_id
    LEFT JOIN cuentas c ON c.id = g.cuenta_id
    LEFT JOIN bancos b ON b.id = c.banco_id
    LEFT JOIN formas_pago fp ON fp.id = g.forma_pago_id
    WHERE g.fecha_gasto BETWEEN ? AND ?
    $gWhere
");
$st->execute($gParams);
$gastos = $st->fetchAll(PDO::FETCH_ASSOC);

$manEgr = [];
try {
    $mp = [$desde, $hasta];
    $mc = '';
    if ($cuenta_id > 0) {
        $mc = ' AND mb.cuenta_id = ? ';
        $mp[] = $cuenta_id;
    }
    $mov = $pdo->prepare("
        SELECT mb.fecha_movimiento AS fecha,
               mb.monto AS monto,
               CONCAT('Mov. manual — ', fp.nombre) AS concepto,
               CONCAT(b.nombre, ' - ', c.nombre) AS cuenta_nombre,
               COALESCE(mb.referencia, '') AS referencia,
               fp.nombre AS forma_pago_nombre,
               '' AS cliente_nombre
        FROM movimientos_bancarios mb
        JOIN cuentas c ON c.id = mb.cuenta_id
        JOIN bancos b ON b.id = c.banco_id
        JOIN formas_pago fp ON fp.id = mb.forma_pago_id
        WHERE mb.fecha_movimiento BETWEEN ? AND ?
          AND mb.tipo_movimiento = 'costo'
          $mc
    ");
    $mov->execute($mp);
    $manEgr = $mov->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $manEgr = [];
}

$lineas = [];
foreach (array_merge($ingFilas, $manIng) as $r) {
    $lineas[] = [
        'fecha' => $r['fecha'],
        'naturaleza' => 'Ingreso',
        'monto' => (float) $r['monto'],
        'concepto' => $r['concepto'] ?? '',
        'cuenta_nombre' => $r['cuenta_nombre'] ?? '',
        'tercero' => $r['cliente_nombre'] ?? '',
        'referencia' => $r['referencia'] ?? '',
        'forma_pago' => $r['forma_pago_nombre'] ?? '',
    ];
}
foreach (array_merge($gastos, $manEgr) as $r) {
    $lineas[] = [
        'fecha' => $r['fecha'],
        'naturaleza' => 'Egreso',
        'monto' => (float) $r['monto'],
        'concepto' => $r['concepto'] ?? '',
        'cuenta_nombre' => $r['cuenta_nombre'] ?? '',
        'tercero' => $r['cliente_nombre'] ?? '',
        'referencia' => $r['referencia'] ?? '',
        'forma_pago' => $r['forma_pago_nombre'] ?? '',
    ];
}

usort($lineas, function ($a, $b) {
    return strcmp((string) $a['fecha'], (string) $b['fecha']);
});

$totIng = 0.0;
$totEgr = 0.0;
foreach ($lineas as $L) {
    if ($L['naturaleza'] === 'Ingreso') {
        $totIng += $L['monto'];
    } else {
        $totEgr += $L['monto'];
    }
}

$acum = 0.0;
foreach ($lineas as &$L) {
    if ($L['naturaleza'] === 'Ingreso') {
        $acum += $L['monto'];
    } else {
        $acum -= $L['monto'];
    }
    $L['acumulado'] = $acum;
}
unset($L);

if (obtener('export') === 'excel') {
    $rows = [];
    foreach ($lineas as $L) {
        $rows[] = [
            $L['fecha'] ?? '',
            $L['naturaleza'] ?? '',
            $L['concepto'] ?? '',
            $L['tercero'] ?? '',
            $L['cuenta_nombre'] ?? '',
            $L['forma_pago'] ?? '',
            $L['referencia'] ?? '',
            (float) ($L['monto'] ?? 0),
            (float) ($L['acumulado'] ?? 0),
        ];
    }
    $pie = [
        ['Totales ingresos', '', '', '', '', '', '', $totIng, ''],
        ['Totales egresos', '', '', '', '', '', '', $totEgr, ''],
        ['Neto del período (ingresos − egresos)', '', '', '', '', '', '', $totIng - $totEgr, ''],
        ['Saldo acumulado final', '', '', '', '', '', '', '', $acum],
    ];
    exportarExcel('reporte_ingresos_egresos', ['Fecha', 'Naturaleza', 'Concepto', 'Cliente/Proveedor', 'Cuenta', 'Forma pago', 'Referencia', 'Monto', 'Acumulado'], $rows, $pie);
}

$cuentasOpts = $pdo->query("
    SELECT c.id, CONCAT(b.nombre, ' - ', c.nombre) AS nom
    FROM cuentas c JOIN bancos b ON b.id = c.banco_id
    ORDER BY b.nombre, c.nombre
")->fetchAll(PDO::FETCH_KEY_PAIR);

require_once __DIR__ . '/../includes/layout.php';
?>
<h1 class="h4 mb-3">Reporte — Ingresos / Egresos</h1>

<form method="get" class="toolbar mb-3">
    <input type="hidden" name="p" value="reporte-ingresos-egresos">
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
        <div class="col-md-4"><strong>Total ingresos:</strong> <?= dinero($totIng) ?></div>
        <div class="col-md-4"><strong>Total egresos:</strong> <?= dinero($totEgr) ?></div>
        <div class="col-md-4"><strong>Neto:</strong> <span class="<?= ($totIng - $totEgr) >= 0 ? 'text-success' : 'text-danger' ?>"><?= dinero($totIng - $totEgr) ?></span></div>
    </div>
    <p class="text-muted small mb-0 mt-2">Acumulado = neto dentro del período (ordenado por fecha).</p>
</div>

<div class="card p-3">
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Naturaleza</th>
                    <th>Concepto</th>
                    <th>Cliente / Proveedor</th>
                    <th>Cuenta</th>
                    <th>Forma pago</th>
                    <th>Referencia</th>
                    <th class="text-end">Monto</th>
                    <th class="text-end">Acumulado</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($lineas as $L): ?>
                <?php $col = $L['naturaleza'] === 'Ingreso' ? '#137333' : '#b42318'; ?>
                <tr>
                    <td><?= fechaFormato($L['fecha']) ?></td>
                    <td><span class="fw-semibold" style="color:<?= $col ?>"><?= e($L['naturaleza']) ?></span></td>
                    <td><?= e($L['concepto']) ?></td>
                    <td><?= e($L['tercero'] ?: '—') ?></td>
                    <td><?= e($L['cuenta_nombre'] ?: '—') ?></td>
                    <td><?= e($L['forma_pago'] ?: '—') ?></td>
                    <td><?= e($L['referencia']) ?></td>
                    <td class="text-end"><?= dinero($L['monto']) ?></td>
                    <td class="text-end"><?= dinero($L['acumulado']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($lineas)): ?>
                <tr><td colspan="9" class="text-muted">No hay movimientos en el período.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
