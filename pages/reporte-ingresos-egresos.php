<?php
/**
 * Reporte Ingresos / Egresos — detalle sin banco/cuenta en ingresos; opción agrupado (ingresos por grupo).
 */
$titulo = 'Reporte — Ingresos / Egresos';
$pdo = getDb();
require_once __DIR__ . '/../includes/reportes_queries.php';
require_once __DIR__ . '/../includes/export_excel.php';

$desde = obtener('desde', date('Y-m-01'));
$hasta = obtener('hasta', date('Y-m-d'));
$cuenta_id = (int) obtener('cuenta_id', 0);
$vista = obtener('vista', 'detallado');
$vista = ($vista === 'agrupado') ? 'agrupado' : 'detallado';

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

$sqlIng = 'SELECT fecha, monto, concepto, referencia, cliente_nombre, grupo_nombre, slip_o_inmueble FROM (' . reportesSqlIngresosDetalle($cuentaCond) . ') z';
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
               COALESCE(mb.referencia, '') AS referencia,
               '' AS cliente_nombre,
               'Movimientos manuales' AS grupo_nombre,
               '' AS slip_o_inmueble
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

$combIng = [];
try {
    $cp = [$desde, $hasta];
    $cc = '';
    if ($cuenta_id > 0) {
        $cc = ' AND cd.cuenta_id = ? ';
        $cp[] = $cuenta_id;
    }
    $cst = $pdo->prepare("
        SELECT cd.fecha AS fecha,
               cd.monto_total AS monto,
               CONCAT('Combustible — ', cd.embarcacion) AS concepto,
               '' AS referencia,
               cd.embarcacion AS cliente_nombre,
               'Combustible (despacho)' AS grupo_nombre,
               '' AS slip_o_inmueble
        FROM combustible_despachos cd
        JOIN cuentas c ON c.id = cd.cuenta_id
        JOIN bancos b ON b.id = c.banco_id
        WHERE cd.fecha BETWEEN ? AND ?
        $cc
    ");
    $cst->execute($cp);
    $combIng = $cst->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $combIng = [];
}

$eleIng = [];
try {
    $ep = [$desde, $hasta];
    $ec = '';
    if ($cuenta_id > 0) {
        $ec = ' AND ep.cuenta_id = ? ';
        $ep[] = $cuenta_id;
    }
    $stEl = $pdo->prepare("
        SELECT ep.fecha_pago AS fecha,
               ep.monto AS monto,
               CONCAT('Electricidad — Contrato #', co.id) AS concepto,
               TRIM(COALESCE(NULLIF(ep.referencia, ''), '')) AS referencia,
               cl.nombre AS cliente_nombre,
               'Electricidad (contrato)' AS grupo_nombre,
               '' AS slip_o_inmueble
        FROM contrato_electricidad_pagos ep
        JOIN contrato_electricidad_facturas f ON f.id = ep.factura_id
        JOIN contratos co ON co.id = f.contrato_id
        JOIN clientes cl ON cl.id = co.cliente_id
        WHERE ep.fecha_pago BETWEEN ? AND ?
          $ec
    ");
    $stEl->execute($ep);
    $eleIng = $stEl->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $eleIng = [];
}

$todasIng = array_merge($ingFilas, $manIng, $combIng, $eleIng);

$agrupadoIngresos = [];
foreach ($todasIng as $r) {
    $g = trim((string) ($r['grupo_nombre'] ?? ''));
    if ($g === '') {
        $g = 'Sin ubicación';
    }
    if (!isset($agrupadoIngresos[$g])) {
        $agrupadoIngresos[$g] = 0.0;
    }
    $agrupadoIngresos[$g] += (float) ($r['monto'] ?? 0);
}
uksort($agrupadoIngresos, 'strnatcasecmp');

$gParams = [$desde, $hasta];
$gWhere = '';
if ($cuenta_id > 0) {
    $gWhere = ' AND gp.cuenta_id = ? ';
    $gParams[] = $cuenta_id;
}
$st = $pdo->prepare("
    SELECT gp.fecha_pago AS fecha,
           gp.monto AS monto,
           CONCAT('Gasto — ', p.nombre) AS concepto,
           COALESCE(gp.referencia, '') AS referencia,
           pr.nombre AS proveedor_nombre
    FROM gasto_pagos gp
    JOIN gastos g ON g.id = gp.gasto_id
    JOIN partidas p ON p.id = g.partida_id
    JOIN proveedores pr ON pr.id = g.proveedor_id
    WHERE gp.fecha_pago BETWEEN ? AND ?
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
               COALESCE(mb.referencia, '') AS referencia,
               '' AS proveedor_nombre
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

$lineasIng = [];
foreach ($todasIng as $r) {
    $lineasIng[] = [
        'fecha' => $r['fecha'],
        'monto' => (float) $r['monto'],
        'concepto' => $r['concepto'] ?? '',
        'grupo' => $r['grupo_nombre'] ?? '',
        'slip' => $r['slip_o_inmueble'] ?? '',
        'tercero' => $r['cliente_nombre'] ?? '',
        'referencia' => $r['referencia'] ?? '',
    ];
}
usort($lineasIng, function ($a, $b) {
    return strcmp((string) $a['fecha'], (string) $b['fecha']);
});

$lineasEgr = [];
foreach (array_merge($gastos, $manEgr) as $r) {
    $lineasEgr[] = [
        'fecha' => $r['fecha'],
        'monto' => (float) $r['monto'],
        'concepto' => $r['concepto'] ?? '',
        'tercero' => $r['proveedor_nombre'] ?? '',
        'referencia' => $r['referencia'] ?? '',
    ];
}
usort($lineasEgr, function ($a, $b) {
    return strcmp((string) $a['fecha'], (string) $b['fecha']);
});

$totIng = array_sum(array_map(static function ($x) {
    return $x['monto'];
}, $lineasIng));
$totEgr = array_sum(array_map(static function ($x) {
    return $x['monto'];
}, $lineasEgr));

$lineas = [];
foreach ($lineasIng as $L) {
    $lineas[] = array_merge($L, ['naturaleza' => 'Ingreso']);
}
foreach ($lineasEgr as $L) {
    $lineas[] = array_merge($L, ['naturaleza' => 'Egreso', 'grupo' => '', 'slip' => '']);
}
usort($lineas, function ($a, $b) {
    return strcmp((string) $a['fecha'], (string) $b['fecha']);
});

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
    if ($vista === 'agrupado') {
        $rows = [];
        foreach ($agrupadoIngresos as $nom => $m) {
            $rows[] = ['Ingreso (por grupo)', $nom, '', '', (float) $m];
        }
        foreach ($lineasEgr as $E) {
            $rows[] = [
                'Egreso',
                $E['fecha'] ?? '',
                $E['concepto'] ?? '',
                $E['tercero'] ?? '',
                (float) ($E['monto'] ?? 0),
            ];
        }
        $pie = [
            ['Totales', 'Ingresos', '', '', $totIng],
            ['Totales', 'Egresos', '', '', $totEgr],
            ['Neto', '', '', '', $totIng - $totEgr],
        ];
        exportarExcel('reporte_ingresos_egresos', ['Tipo', 'Grupo o fecha', 'Concepto', 'Proveedor / —', 'Monto'], $rows, $pie);
    } else {
        $rows = [];
        foreach ($lineas as $L) {
            $rows[] = [
                $L['fecha'] ?? '',
                $L['naturaleza'] ?? '',
                $L['grupo'] ?? '',
                $L['slip'] ?? '',
                $L['concepto'] ?? '',
                $L['tercero'] ?? '',
                $L['referencia'] ?? '',
                (float) ($L['monto'] ?? 0),
                (float) ($L['acumulado'] ?? 0),
            ];
        }
        $pie = [
            ['Totales ingresos', '', '', '', '', '', '', $totIng, ''],
            ['Totales egresos', '', '', '', '', '', '', $totEgr, ''],
            ['Neto del período', '', '', '', '', '', '', $totIng - $totEgr, ''],
            ['Saldo acumulado final', '', '', '', '', '', '', '', $acum],
        ];
        exportarExcel('reporte_ingresos_egresos', ['Fecha', 'Naturaleza', 'Grupo', 'Slip / inmueble', 'Concepto', 'Cliente / Proveedor', 'Referencia', 'Monto', 'Acumulado'], $rows, $pie);
    }
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
        <div class="col-12 col-md-2">
            <label class="form-label mb-1">Desde</label>
            <input type="date" class="form-control" name="desde" value="<?= e($desde) ?>">
        </div>
        <div class="col-12 col-md-2">
            <label class="form-label mb-1">Hasta</label>
            <input type="date" class="form-control" name="hasta" value="<?= e($hasta) ?>">
        </div>
        <div class="col-12 col-md-3">
            <label class="form-label mb-1">Cuenta (filtro)</label>
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
                <option value="detallado" <?= $vista === 'detallado' ? 'selected' : '' ?>>Detallado (línea a línea)</option>
                <option value="agrupado" <?= $vista === 'agrupado' ? 'selected' : '' ?>>Agrupado (ingresos por grupo + egresos en detalle)</option>
            </select>
        </div>
        <div class="col-12 col-md-auto">
            <button type="submit" class="btn btn-primary">Filtrar</button>
        </div>
        <div class="col-12 col-md-auto">
            <button type="submit" class="btn btn-success" name="export" value="excel">Exportar Excel</button>
        </div>
    </div>
    <p class="text-muted small mb-0 mt-2">Los ingresos por cuota muestran <strong>grupo</strong> (muelle o grupo de locales) y <strong>slip / inmueble</strong>. No se listan banco ni cuenta. En vista agrupada no hay columna acumulada día a día.</p>
</form>

<div class="card p-3 mb-3">
    <div class="row g-2 small">
        <div class="col-md-4"><strong>Total ingresos:</strong> <?= dinero($totIng) ?></div>
        <div class="col-md-4"><strong>Total egresos:</strong> <?= dinero($totEgr) ?></div>
        <div class="col-md-4"><strong>Neto:</strong> <span class="<?= ($totIng - $totEgr) >= 0 ? 'text-success' : 'text-danger' ?>"><?= dinero($totIng - $totEgr) ?></span></div>
    </div>
    <?php if ($vista === 'detallado'): ?>
        <p class="text-muted small mb-0 mt-2">Acumulado = neto dentro del período (ordenado por fecha).</p>
    <?php endif; ?>
</div>

<?php if ($vista === 'agrupado'): ?>
<div class="card p-3 mb-3">
    <h2 class="h6 mb-3">Ingresos por grupo / origen</h2>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Grupo / origen</th>
                    <th class="text-end">Total</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($agrupadoIngresos as $nom => $m): ?>
                <tr>
                    <td><?= e($nom) ?></td>
                    <td class="text-end"><?= dinero($m) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($agrupadoIngresos)): ?>
                <tr><td colspan="2" class="text-muted">No hay ingresos en el período.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<div class="card p-3">
    <h2 class="h6 mb-3">Egresos del período</h2>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Concepto</th>
                    <th>Proveedor</th>
                    <th>Referencia</th>
                    <th class="text-end">Monto</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($lineasEgr as $E): ?>
                <tr>
                    <td><?= fechaFormato($E['fecha']) ?></td>
                    <td><?= e($E['concepto']) ?></td>
                    <td><?= e($E['tercero'] ?: '—') ?></td>
                    <td><?= e($E['referencia']) ?></td>
                    <td class="text-end"><?= dinero($E['monto']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($lineasEgr)): ?>
                <tr><td colspan="5" class="text-muted">No hay egresos en el período.</td></tr>
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
                    <th>Naturaleza</th>
                    <th>Grupo</th>
                    <th>Slip / inmueble</th>
                    <th>Concepto</th>
                    <th>Cliente / Proveedor</th>
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
                    <td><?= e($L['grupo'] ?: '—') ?></td>
                    <td><?= e($L['slip'] ?: '—') ?></td>
                    <td><?= e($L['concepto']) ?></td>
                    <td><?= e($L['tercero'] ?: '—') ?></td>
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
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
