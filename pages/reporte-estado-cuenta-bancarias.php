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

$labCred = marina_ui_credito();
$labDeb = marina_ui_debito();

$params = [$desde, $hasta];
$cuentaFiltro = '';
if ($cuenta_id > 0) {
    $cuentaFiltro = ' AND co.cuenta_id = ? ';
    $params[] = $cuenta_id;
}

$ing = $pdo->prepare("
    SELECT mo.fecha_pago AS fecha,
           '{$labCred}' AS tipo,
           mo.monto AS monto,
           COALESCE(NULLIF(TRIM(mo.concepto), ''), CONCAT('Cuota #', cu.numero_cuota, ' — Contrato #', co.id)) AS concepto,
           TRIM(COALESCE(NULLIF(co.numero_recibo, ''), NULLIF(mo.referencia, ''), '')) AS referencia,
           COALESCE(cl.nombre, '') AS cliente_o_proveedor
    FROM cuotas_movimientos mo
    JOIN cuotas cu ON mo.cuota_id = cu.id
    JOIN contratos co ON cu.contrato_id = co.id
    JOIN clientes cl ON co.cliente_id = cl.id
    WHERE mo.fecha_pago BETWEEN ? AND ?
      AND mo.tipo IN ('pago','abono')
      $cuentaFiltro
");
$ing->execute($params);
$ingresos = $ing->fetchAll(PDO::FETCH_ASSOC);

$ingEleParams = [$desde, $hasta];
$ingEleFiltro = '';
if ($cuenta_id > 0) {
    $ingEleFiltro = ' AND ep.cuenta_id = ? ';
    $ingEleParams[] = $cuenta_id;
}
try {
    $ingEle = $pdo->prepare("
        SELECT ep.fecha_pago AS fecha,
               '{$labCred}' AS tipo,
               ep.monto AS monto,
               CONCAT('Electricidad — Contrato #', co.id) AS concepto,
               TRIM(COALESCE(NULLIF(co.numero_recibo, ''), NULLIF(ep.referencia, ''), '')) AS referencia,
               COALESCE(cl.nombre, '') AS cliente_o_proveedor
        FROM contrato_electricidad_pagos ep
        JOIN contrato_electricidad_facturas f ON f.id = ep.factura_id
        JOIN contratos co ON co.id = f.contrato_id
        JOIN clientes cl ON cl.id = co.cliente_id
        WHERE ep.fecha_pago BETWEEN ? AND ?
          $ingEleFiltro
    ");
    $ingEle->execute($ingEleParams);
    $ingresos = array_merge($ingresos, $ingEle->fetchAll(PDO::FETCH_ASSOC));
} catch (Throwable $e) {
    // sin tablas de electricidad
}

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
               '{$labCred}' AS tipo,
               cd.monto_total AS monto,
               CONCAT('Combustible — ', UPPER(SUBSTRING(cd.tipo_combustible, 1, 1)), SUBSTRING(cd.tipo_combustible, 2), ' — ', cd.embarcacion) AS concepto,
               '' AS referencia,
               '' AS cliente_o_proveedor
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
    $gFiltro = ' AND gp.cuenta_id = ? ';
    $gParams[] = $cuenta_id;
}
$cos = $pdo->prepare("
    SELECT gp.fecha_pago AS fecha,
           '{$labDeb}' AS tipo,
           gp.monto AS monto,
           CONCAT('Gasto — ', p.nombre) AS concepto,
           COALESCE(gp.referencia, '') AS referencia,
           COALESCE(pr.nombre, '') AS cliente_o_proveedor
    FROM gasto_pagos gp
    JOIN gastos g ON g.id = gp.gasto_id
    JOIN partidas p ON p.id = g.partida_id
    JOIN proveedores pr ON pr.id = g.proveedor_id
    WHERE gp.fecha_pago BETWEEN ? AND ?
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
               CASE WHEN mb.tipo_movimiento = 'costo' THEN '{$labDeb}' ELSE '{$labCred}' END AS tipo,
               mb.monto AS monto,
               CONCAT('Mov. manual — ', fp.nombre) AS concepto,
               COALESCE(mb.referencia, '') AS referencia,
               '' AS cliente_o_proveedor
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
    if (($m['tipo'] ?? '') === $labDeb) {
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
    if (($m['tipo'] ?? '') === $labDeb) {
        $totEgr += $val;
    } else {
        $totIng += $val;
    }
}

if (obtener('export') === 'excel') {
    $rows = [];
    foreach ($movs as $m) {
        $tipoRow = $m['tipo'] ?? '';
        $valRow = (float) ($m['monto'] ?? 0);
        $isCredRow = ($tipoRow === $labCred);
        $rows[] = [
            $m['fecha'] ?? '',
            $m['concepto'] ?? '',
            $m['referencia'] ?? '',
            $m['cliente_o_proveedor'] ?? '',
            $isCredRow ? $valRow : '',
            $isCredRow ? '' : $valRow,
            (float) ($m['acumulado'] ?? 0),
        ];
    }
    $neto = $totIng - $totEgr;
    $acumFinal = $movs === [] ? 0.0 : (float) ($movs[array_key_last($movs)]['acumulado'] ?? 0);
    $pie = [
        ['Total créditos del período', '', '', '', $totIng, '', ''],
        ['Total débitos del período', '', '', '', '', $totEgr, ''],
        [
            'Neto del período',
            '',
            '',
            '',
            $neto >= 0 ? $neto : '',
            $neto < 0 ? abs($neto) : '',
            $acumFinal,
        ],
    ];
    $excelTitulo = $titulo;
    if ($cuenta_id > 0 && $cuentaTitulo !== '') {
        $excelTitulo .= ' — ' . $cuentaTitulo;
    }
    exportarExcel('reporte_estado_cuenta_bancaria', ['Fecha', 'Concepto', 'Referencia', 'Cliente / Proveedor', 'Crédito', 'Débito', 'Acumulado'], $rows, $pie, $excelTitulo);
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
        <div class="col-md-4"><strong>Total créditos:</strong> <?= dinero($totIng) ?></div>
        <div class="col-md-4"><strong>Total débitos:</strong> <?= dinero($totEgr) ?></div>
        <div class="col-md-4"><strong>Neto período:</strong> <span class="<?= ($totIng - $totEgr) >= 0 ? 'text-success' : 'text-danger' ?>"><?= dinero($totIng - $totEgr) ?></span></div>
    </div>
    <p class="text-muted small mb-0 mt-2">El acumulado es dentro del período filtrado (no incluye saldo anterior). En créditos por cuota, la <strong>referencia</strong> muestra el <strong>número de recibo del contrato</strong> cuando está cargado en el contrato.</p>
</div>

<div class="card p-3">
    <div class="table-responsive">
        <table class="table table-hover align-middle no-datatable">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th style="min-width:200px">Concepto</th>
                    <th>Referencia</th>
                    <th>Cliente / Proveedor</th>
                    <th class="text-end">Crédito</th>
                    <th class="text-end">Débito</th>
                    <th class="text-end">Acumulado</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($movs as $m): ?>
                <?php
                $tipo = $m['tipo'] ?? '';
                $valM = (float) ($m['monto'] ?? 0);
                $esCred = ($tipo === $labCred);
                ?>
                <tr>
                    <td><?= fechaFormato($m['fecha']) ?></td>
                    <td><?= e($m['concepto'] ?? '') ?></td>
                    <td><?= e($m['referencia'] ?? '') ?></td>
                    <td><?= e($m['cliente_o_proveedor'] ?? '') ?: '—' ?></td>
                    <td class="text-end <?= $esCred ? 'text-success' : 'text-muted' ?>"><?= $esCred ? dinero($valM) : '—' ?></td>
                    <td class="text-end <?= !$esCred ? 'text-danger' : 'text-muted' ?>"><?= !$esCred ? dinero($valM) : '—' ?></td>
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
                    <td colspan="4" class="text-end">Total créditos del período</td>
                    <td class="text-end text-success"><?= dinero($totIng) ?></td>
                    <td class="text-end text-muted">—</td>
                    <td class="text-end text-muted">—</td>
                </tr>
                <tr class="table-light fw-semibold">
                    <td colspan="4" class="text-end">Total débitos del período</td>
                    <td class="text-end text-muted">—</td>
                    <td class="text-end text-danger"><?= dinero($totEgr) ?></td>
                    <td class="text-end text-muted">—</td>
                </tr>
                <tr class="table-secondary fw-bold">
                    <td colspan="4" class="text-end">Neto del período</td>
                    <?php if ($netoPeriodo >= 0): ?>
                        <td class="text-end text-success"><?= dinero($netoPeriodo) ?></td>
                        <td class="text-end text-muted">—</td>
                    <?php else: ?>
                        <td class="text-end text-muted">—</td>
                        <td class="text-end text-danger"><?= dinero(abs($netoPeriodo)) ?></td>
                    <?php endif; ?>
                    <td class="text-end"><?= dinero($acumFinal) ?></td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
