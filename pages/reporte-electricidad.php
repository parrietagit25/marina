<?php
/**
 * Reporte de electricidad por contratos: facturas, saldos, pagos y abonos; quién completó pago y quién debe.
 */
$titulo = 'Reporte de electricidad (contratos)';
$pdo = getDb();
require_once __DIR__ . '/../includes/export_excel.php';

$desdeFact = obtener('desde_fact', date('Y-m-01'));
$hastaFact = obtener('hasta_fact', date('Y-m-d'));
$desdePago = obtener('desde_pago', date('Y-m-01'));
$hastaPago = obtener('hasta_pago', date('Y-m-d'));
$contrato_id = (int) obtener('contrato_id', 0);
$estadoCobro = trim(obtener('estado_cobro', ''));
$estadoCobro = in_array($estadoCobro, ['con_saldo', 'al_dia'], true) ? $estadoCobro : '';
$tipoUnidad = trim(obtener('tipo_unidad', ''));
$tipoUnidad = in_array($tipoUnidad, ['slip', 'inmueble'], true) ? $tipoUnidad : '';

$facturas = [];
$movimientos = [];
$sinTabla = false;

try {
    $wFact = ['f.fecha_factura BETWEEN ? AND ?'];
    $pFact = [$desdeFact, $hastaFact];
    if ($contrato_id > 0) {
        $wFact[] = 'f.contrato_id = ?';
        $pFact[] = $contrato_id;
    }
    if ($tipoUnidad === 'slip') {
        $wFact[] = 'co.slip_id IS NOT NULL';
    } elseif ($tipoUnidad === 'inmueble') {
        $wFact[] = 'co.inmueble_id IS NOT NULL';
    }
    if ($estadoCobro === 'con_saldo') {
        $wFact[] = 'f.monto_total > (SELECT COALESCE(SUM(ep.monto), 0) FROM contrato_electricidad_pagos ep WHERE ep.factura_id = f.id) + 0.005';
    } elseif ($estadoCobro === 'al_dia') {
        $wFact[] = 'f.monto_total <= (SELECT COALESCE(SUM(ep.monto), 0) FROM contrato_electricidad_pagos ep WHERE ep.factura_id = f.id) + 0.005';
    }

    $st = $pdo->prepare('
        SELECT f.id AS factura_id,
               f.contrato_id,
               f.monto_total,
               f.fecha_factura,
               f.numero_factura,
               f.periodo_desde,
               f.periodo_hasta,
               f.estado AS estado_db,
               f.observaciones,
               cl.nombre AS cliente,
               (SELECT COALESCE(SUM(ep.monto), 0) FROM contrato_electricidad_pagos ep WHERE ep.factura_id = f.id) AS total_pagado,
               CASE
                   WHEN co.slip_id IS NOT NULL THEN CONCAT(m.nombre, " — ", s.nombre)
                   WHEN co.inmueble_id IS NOT NULL THEN CONCAT(g.nombre, " — ", i.nombre)
                   ELSE "—"
               END AS unidad
        FROM contrato_electricidad_facturas f
        JOIN contratos co ON f.contrato_id = co.id
        JOIN clientes cl ON cl.id = co.cliente_id
        LEFT JOIN muelles m ON m.id = co.muelle_id
        LEFT JOIN slips s ON s.id = co.slip_id
        LEFT JOIN grupos g ON g.id = co.grupo_id
        LEFT JOIN inmuebles i ON i.id = co.inmueble_id
        WHERE ' . implode(' AND ', $wFact) . '
        ORDER BY f.fecha_factura DESC, f.id DESC
    ');
    $st->execute($pFact);
    $facturas = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $sinTabla = true;
    $facturas = [];
}

foreach ($facturas as &$f) {
    $mt = round((float) ($f['monto_total'] ?? 0), 2);
    $tp = round((float) ($f['total_pagado'] ?? 0), 2);
    $f['saldo'] = round($mt - $tp, 2);
    $f['situacion'] = $f['saldo'] > 0.01 ? 'Pendiente' : 'Pagada';
}
unset($f);

try {
    $wM = ['ep.fecha_pago BETWEEN ? AND ?'];
    $pM = [$desdePago, $hastaPago];
    if ($contrato_id > 0) {
        $wM[] = 'f.contrato_id = ?';
        $pM[] = $contrato_id;
    }
    if ($tipoUnidad === 'slip') {
        $wM[] = 'co.slip_id IS NOT NULL';
    } elseif ($tipoUnidad === 'inmueble') {
        $wM[] = 'co.inmueble_id IS NOT NULL';
    }
    $stM = $pdo->prepare('
        SELECT ep.id AS pago_id,
               ep.monto,
               ep.fecha_pago,
               ep.referencia,
               ep.observaciones,
               f.id AS factura_id,
               f.numero_factura,
               f.monto_total AS monto_factura,
               f.fecha_factura,
               f.contrato_id,
               cl.nombre AS cliente,
               CONCAT(b.nombre, " - ", c.nombre) AS cuenta,
               COALESCE(fp.nombre, "—") AS forma_pago,
               CASE
                   WHEN co.slip_id IS NOT NULL THEN CONCAT(m.nombre, " — ", s.nombre)
                   WHEN co.inmueble_id IS NOT NULL THEN CONCAT(g.nombre, " — ", i.nombre)
                   ELSE "—"
               END AS unidad
        FROM contrato_electricidad_pagos ep
        JOIN contrato_electricidad_facturas f ON f.id = ep.factura_id
        JOIN contratos co ON f.contrato_id = co.id
        JOIN clientes cl ON cl.id = co.cliente_id
        LEFT JOIN cuentas c ON c.id = ep.cuenta_id
        LEFT JOIN bancos b ON b.id = c.banco_id
        LEFT JOIN formas_pago fp ON fp.id = ep.forma_pago_id
        LEFT JOIN muelles m ON m.id = co.muelle_id
        LEFT JOIN slips s ON s.id = co.slip_id
        LEFT JOIN grupos g ON g.id = co.grupo_id
        LEFT JOIN inmuebles i ON i.id = co.inmueble_id
        WHERE ' . implode(' AND ', $wM) . '
        ORDER BY ep.fecha_pago DESC, ep.id DESC
    ');
    $stM->execute($pM);
    $movimientos = $stM->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    if (!$sinTabla) {
        $movimientos = [];
    }
}

$totFacturado = 0.0;
$totPagadoFact = 0.0;
$totSaldoPend = 0.0;
$cntPend = 0;
$cntAlDia = 0;
foreach ($facturas as $f) {
    $mt = (float) ($f['monto_total'] ?? 0);
    $tp = (float) ($f['total_pagado'] ?? 0);
    $sd = (float) ($f['saldo'] ?? 0);
    $totFacturado += $mt;
    $totPagadoFact += $tp;
    if ($sd > 0.01) {
        $totSaldoPend += $sd;
        $cntPend++;
    } else {
        $cntAlDia++;
    }
}

$totMovimientos = 0.0;
foreach ($movimientos as $m) {
    $totMovimientos += (float) ($m['monto'] ?? 0);
}

if (obtener('export') === 'excel' && !$sinTabla) {
    $sec = trim(obtener('sec', 'resumen'));
    if ($sec === 'movimientos') {
        $rows = [];
        foreach ($movimientos as $m) {
            $rows[] = [
                (int) $m['pago_id'],
                (int) $m['contrato_id'],
                $m['cliente'] ?? '',
                $m['unidad'] ?? '',
                (int) $m['factura_id'],
                (string) ($m['numero_factura'] ?? ''),
                (string) ($m['fecha_factura'] ?? ''),
                (string) ($m['fecha_pago'] ?? ''),
                (float) ($m['monto'] ?? 0),
                (float) ($m['monto_factura'] ?? 0),
                (string) ($m['cuenta'] ?? ''),
                (string) ($m['forma_pago'] ?? ''),
                (string) ($m['referencia'] ?? ''),
            ];
        }
        $pie = [
            [
                'Total (mov. listados)',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                $totMovimientos,
                '',
                '',
                '',
                '',
            ],
        ];
        $tit = $titulo . ' — Pagos y abonos (fecha pago: ' . $desdePago . ' a ' . $hastaPago . ')';
        exportarExcel('reporte_electricidad_movimientos', [
            'N° pago', 'Contrato', 'Cliente', 'Unidad', 'N° fact. eléct.',
            'N.º factura', 'F. factura', 'F. pago', 'Monto pago', 'Monto factura',
            'Cuenta', 'Forma pago', 'Referencia',
        ], $rows, $pie, $tit);
    } else {
        $rows = [];
        foreach ($facturas as $f) {
            $rows[] = [
                (int) $f['factura_id'],
                (int) $f['contrato_id'],
                $f['cliente'] ?? '',
                $f['unidad'] ?? '',
                (string) $f['fecha_factura'],
                (string) ($f['numero_factura'] ?? ''),
                (float) ($f['monto_total'] ?? 0),
                (float) ($f['total_pagado'] ?? 0),
                (float) ($f['saldo'] ?? 0),
                (string) $f['situacion'],
            ];
        }
        $pie = [
            [
                'Total / resumen',
                '',
                '',
                '',
                '',
                '',
                $totFacturado,
                $totPagadoFact,
                $totSaldoPend,
                'Pend.: ' . (string) $cntPend . ' / Pag.: ' . (string) $cntAlDia,
            ],
        ];
        $tit = $titulo . ' — Facturas (fecha factura: ' . $desdeFact . ' a ' . $hastaFact . ')';
        exportarExcel('reporte_electricidad_facturas', [
            'N° fact. eléct.',
            'Contrato',
            'Cliente',
            'Unidad',
            'F. factura',
            'N.º factura',
            'Monto factura',
            'Pagado',
            'Saldo',
            'Situación',
        ], $rows, $pie, $tit);
    }
}

require_once __DIR__ . '/../includes/layout.php';
?>
<?php if ($sinTabla): ?>
    <h1 class="h4 mb-3">Reporte de electricidad (contratos)</h1>
    <div class="alert alert-warning">No se encontraron las tablas de electricidad en la base de datos. Ejecute el instalador o la migración.</div>
<?php else: ?>
    <h1 class="h4 mb-2">Reporte de electricidad (contratos)</h1>
    <p class="text-muted small mb-3">Solo <strong>facturas de electricidad ligadas a contratos</strong> (módulo Contrato → Electricidad). La primera tabla filtra por <strong>fecha de la factura</strong>; la de movimientos por <strong>fecha de pago o abono</strong>. <strong>Cliente</strong> es el titular del contrato. <strong>Pagada</strong> = monto de factura cubierto por abonos; <strong>Pendiente</strong> = aún hay saldo.</p>

    <form method="get" class="toolbar mb-3">
        <input type="hidden" name="p" value="reporte-electricidad">
        <div class="row g-3">
            <div class="col-12">
                <div class="fw-bold small text-muted">Facturación (tabla de facturas)</div>
            </div>
            <div class="col-12 col-md-6 col-lg-3">
                <label class="form-label mb-1">Factura desde</label>
                <input type="date" class="form-control" name="desde_fact" value="<?= e($desdeFact) ?>">
            </div>
            <div class="col-12 col-md-6 col-lg-3">
                <label class="form-label mb-1">Factura hasta</label>
                <input type="date" class="form-control" name="hasta_fact" value="<?= e($hastaFact) ?>">
            </div>
            <div class="col-12 col-md-6 col-lg-3">
                <label class="form-label mb-1">Cobro en facturas</label>
                <select class="form-select" name="estado_cobro">
                    <option value="" <?= $estadoCobro === '' ? 'selected' : '' ?>>Todas</option>
                    <option value="con_saldo" <?= $estadoCobro === 'con_saldo' ? 'selected' : '' ?>>Pendiente de pago (con saldo)</option>
                    <option value="al_dia" <?= $estadoCobro === 'al_dia' ? 'selected' : '' ?>>Pagada (sin saldo)</option>
                </select>
            </div>
            <div class="col-12 col-md-6 col-lg-3">
                <label class="form-label mb-1">Unidad (tipo)</label>
                <select class="form-select" name="tipo_unidad">
                    <option value="" <?= $tipoUnidad === '' ? 'selected' : '' ?>>Todas</option>
                    <option value="slip" <?= $tipoUnidad === 'slip' ? 'selected' : '' ?>>Marina (slip)</option>
                    <option value="inmueble" <?= $tipoUnidad === 'inmueble' ? 'selected' : '' ?>>Inmueble</option>
                </select>
            </div>
            <div class="col-12 col-md-6 col-lg-3">
                <label class="form-label mb-1">Contrato (opcional)</label>
                <input type="number" min="0" class="form-control" name="contrato_id" value="<?= $contrato_id > 0 ? (string) (int) $contrato_id : '' ?>" placeholder="# contrato">
            </div>
            <div class="col-12"><hr class="my-0"></div>
            <div class="col-12">
                <div class="fw-bold small text-muted">Cobros (tabla de pagos y abonos)</div>
            </div>
            <div class="col-12 col-md-6 col-lg-3">
                <label class="form-label mb-1">Pago / abono desde</label>
                <input type="date" class="form-control" name="desde_pago" value="<?= e($desdePago) ?>">
            </div>
            <div class="col-12 col-md-6 col-lg-3">
                <label class="form-label mb-1">Pago / abono hasta</label>
                <input type="date" class="form-control" name="hasta_pago" value="<?= e($hastaPago) ?>">
            </div>
        </div>
        <div class="row g-2 align-items-end mt-2">
            <div class="col-12 d-flex flex-wrap gap-2">
                <button type="submit" class="btn btn-primary">Aplicar filtros</button>
                <a class="btn btn-success" href="<?= e(MARINA_URL . '/index.php?' . http_build_query([
                    'p' => 'reporte-electricidad',
                    'export' => 'excel',
                    'sec' => 'resumen',
                    'desde_fact' => $desdeFact,
                    'hasta_fact' => $hastaFact,
                    'desde_pago' => $desdePago,
                    'hasta_pago' => $hastaPago,
                    'estado_cobro' => $estadoCobro,
                    'tipo_unidad' => $tipoUnidad,
                    'contrato_id' => (string) $contrato_id,
                ])) ?>">Excel: facturación (resumen)</a>
                <a class="btn btn-success" href="<?= e(MARINA_URL . '/index.php?' . http_build_query([
                    'p' => 'reporte-electricidad',
                    'export' => 'excel',
                    'sec' => 'movimientos',
                    'desde_fact' => $desdeFact,
                    'hasta_fact' => $hastaFact,
                    'desde_pago' => $desdePago,
                    'hasta_pago' => $hastaPago,
                    'estado_cobro' => $estadoCobro,
                    'tipo_unidad' => $tipoUnidad,
                    'contrato_id' => (string) $contrato_id,
                ])) ?>">Excel: pagos y abonos</a>
            </div>
        </div>
    </form>

    <h2 class="h6 mt-4 mb-2">Facturación: quién no ha completado el pago</h2>
    <div class="card p-3 mb-3">
        <div class="row g-2 small">
            <div class="col-md-4"><strong>Total facturado (rango):</strong> <?= dinero($totFacturado) ?></div>
            <div class="col-md-4"><strong>Total cobrado (abonos):</strong> <?= dinero($totPagadoFact) ?></div>
            <div class="col-md-4"><strong>Saldo pendiente sumado:</strong> <?= dinero($totSaldoPend) ?></div>
        </div>
        <p class="text-muted small mb-0 mt-2">Facturados al día: <strong><?= (int) $cntAlDia ?></strong> &nbsp;|&nbsp; Con saldo: <strong><?= (int) $cntPend ?></strong> (cliente = titular del contrato en cada fila)</p>
    </div>
    <div class="table-responsive card p-0 mb-4">
        <table class="table table-hover table-sm align-middle mb-0 no-datatable">
            <thead class="table-light">
                <tr>
                    <th>Fact. eléct.</th>
                    <th>Contrato</th>
                    <th>Cliente</th>
                    <th>Unidad</th>
                    <th>F. factura</th>
                    <th>N.º factura</th>
                    <th class="text-end">Monto</th>
                    <th class="text-end">Pagado</th>
                    <th class="text-end">Saldo</th>
                    <th>Situación</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($facturas)): ?>
                <tr><td colspan="10" class="text-muted p-3">Ninguna factura en el rango / filtros.</td></tr>
            <?php else: ?>
                <?php foreach ($facturas as $f): ?>
                <tr>
                    <td><?= (int) $f['factura_id'] ?></td>
                    <td><?= (int) $f['contrato_id'] ?></td>
                    <td><?= e((string) $f['cliente']) ?></td>
                    <td><?= e((string) $f['unidad']) ?></td>
                    <td><?= e(fechaFormato($f['fecha_factura'] ?? null)) ?></td>
                    <td><?= e((string) ($f['numero_factura'] ?? '')) ?></td>
                    <td class="text-end"><?= dinero($f['monto_total'] ?? 0) ?></td>
                    <td class="text-end"><?= dinero($f['total_pagado'] ?? 0) ?></td>
                    <td class="text-end"><?= dinero($f['saldo'] ?? 0) ?></td>
                    <td><span class="badge bg-<?= $f['situacion'] === 'Pagada' ? 'success' : 'warning' ?> text-dark"><?= e($f['situacion']) ?></span></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <h2 class="h6 mt-2 mb-2">Pagos y abonos registrados</h2>
    <p class="text-muted small">Cada línea es un acreditado a la cuenta; puede ser pago completo o abono. Rango: <strong><?= e(fechaFormato($desdePago)) ?> — <?= e(fechaFormato($hastaPago)) ?></strong> · Suma: <strong><?= dinero($totMovimientos) ?></strong></p>
    <div class="table-responsive card p-0">
        <table class="table table-hover table-sm align-middle mb-0 no-datatable">
            <thead class="table-light">
                <tr>
                    <th>Contrato</th>
                    <th>Cliente</th>
                    <th>Unidad</th>
                    <th>Fact. eléct.</th>
                    <th>F. pago</th>
                    <th class="text-end">Monto pago</th>
                    <th class="text-end">Monto fact.</th>
                    <th>Cuenta</th>
                    <th>Forma pago</th>
                    <th>Referencia</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($movimientos)): ?>
                <tr><td colspan="10" class="text-muted p-3">Ningún pago o abono en el rango / filtros.</td></tr>
            <?php else: ?>
                <?php foreach ($movimientos as $m): ?>
                <tr>
                    <td><?= (int) $m['contrato_id'] ?></td>
                    <td><?= e((string) $m['cliente']) ?></td>
                    <td><?= e((string) $m['unidad']) ?></td>
                    <td><?= (int) $m['factura_id'] ?><?= ($m['numero_factura'] !== null && (string) $m['numero_factura'] !== '') ? ' (' . e((string) $m['numero_factura']) . ')' : '' ?></td>
                    <td><?= e(fechaFormato($m['fecha_pago'] ?? null)) ?></td>
                    <td class="text-end"><?= dinero($m['monto'] ?? 0) ?></td>
                    <td class="text-end text-muted small"><?= dinero($m['monto_factura'] ?? 0) ?></td>
                    <td><?= e((string) $m['cuenta']) ?></td>
                    <td><?= e((string) $m['forma_pago']) ?></td>
                    <td><?= e((string) ($m['referencia'] ?? '')) ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
