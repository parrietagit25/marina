<?php
/**
 * Reporte Egresos — gastos y movimientos bancarios manuales tipo costo.
 */
$titulo = 'Reporte de egresos';
$pdo = getDb();
require_once __DIR__ . '/../includes/export_excel.php';

$desde = obtener('desde', date('Y-m-01'));
$hasta = obtener('hasta', date('Y-m-d'));
$cuenta_id = (int) obtener('cuenta_id', 0);
$partida_id = (int) obtener('partida_id', 0);
$vista = obtener('vista', 'detallado');
$vista = ($vista === 'agrupado') ? 'agrupado' : 'detallado';

$cuentasOpts = $pdo->query("
    SELECT c.id, CONCAT(b.nombre, ' - ', c.nombre) AS nom
    FROM cuentas c JOIN bancos b ON b.id = c.banco_id
    ORDER BY b.nombre, c.nombre
")->fetchAll(PDO::FETCH_KEY_PAIR);

$partidasOpts = $pdo->query('SELECT id, nombre FROM partidas ORDER BY nombre')->fetchAll(PDO::FETCH_KEY_PAIR);

$gParams = [$desde, $hasta];
$gWhere = '';
if ($cuenta_id > 0) {
    $gWhere .= ' AND gp.cuenta_id = ? ';
    $gParams[] = $cuenta_id;
}
if ($partida_id > 0) {
    $gWhere .= ' AND g.partida_id = ? ';
    $gParams[] = $partida_id;
}

$st = $pdo->prepare("
    SELECT gp.fecha_pago AS fecha,
           'Gasto' AS origen,
           gp.monto,
           CONCAT('Gasto — ', p.nombre) AS concepto,
           p.nombre AS grupo_nombre,
           CONCAT(b.nombre, ' - ', c.nombre) AS cuenta_nombre,
           pr.nombre AS proveedor_nombre,
           fp.nombre AS forma_pago_nombre,
           COALESCE(gp.referencia, '') AS referencia
    FROM gasto_pagos gp
    JOIN gastos g ON g.id = gp.gasto_id
    JOIN partidas p ON p.id = g.partida_id
    JOIN proveedores pr ON pr.id = g.proveedor_id
    LEFT JOIN cuentas c ON c.id = gp.cuenta_id
    LEFT JOIN bancos b ON b.id = c.banco_id
    LEFT JOIN formas_pago fp ON fp.id = gp.forma_pago_id
    WHERE gp.fecha_pago BETWEEN ? AND ?
    $gWhere
");
$st->execute($gParams);
$gastos = $st->fetchAll(PDO::FETCH_ASSOC);

$manuales = [];
try {
    $mParams = [$desde, $hasta];
    $mWhere = " AND mb.tipo_movimiento = 'costo' ";
    if ($cuenta_id > 0) {
        $mWhere .= ' AND mb.cuenta_id = ? ';
        $mParams[] = $cuenta_id;
    }
    $mov = $pdo->prepare("
        SELECT mb.fecha_movimiento AS fecha,
               'Mov. bancario' AS origen,
               mb.monto,
               CONCAT('Manual — ', fp.nombre) AS concepto,
               'Movimientos manuales (costo)' AS grupo_nombre,
               CONCAT(b.nombre, ' - ', c.nombre) AS cuenta_nombre,
               '' AS proveedor_nombre,
               fp.nombre AS forma_pago_nombre,
               COALESCE(mb.referencia, '') AS referencia
        FROM movimientos_bancarios mb
        JOIN cuentas c ON c.id = mb.cuenta_id
        JOIN bancos b ON b.id = c.banco_id
        JOIN formas_pago fp ON fp.id = mb.forma_pago_id
        WHERE mb.fecha_movimiento BETWEEN ? AND ?
          $mWhere
    ");
    $mov->execute($mParams);
    $manuales = $mov->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $manuales = [];
}

$filas = array_merge($gastos, $manuales);
usort($filas, function ($a, $b) {
    $ta = strtotime($a['fecha'] ?? '');
    $tb = strtotime($b['fecha'] ?? '');
    return $ta <=> $tb;
});

$total = array_sum(array_map(static function ($r) {
    return (float) ($r['monto'] ?? 0);
}, $filas));

$agrupadoPorGrupo = [];
if ($vista === 'agrupado') {
    foreach ($filas as $r) {
        $g = trim((string) ($r['grupo_nombre'] ?? ''));
        if ($g === '') {
            $g = 'Sin partida / origen';
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
        exportarExcel('reporte_egresos_agrupado', ['Partida / origen', 'Monto'], $rows, $pie, $titulo . ' — Agrupado (totales por partida / manual)');
    } else {
        $rows = [];
        foreach ($filas as $r) {
            $rows[] = [
                $r['fecha'] ?? '',
                $r['origen'] ?? '',
                $r['concepto'] ?? '',
                $r['proveedor_nombre'] ?? '',
                $r['referencia'] ?? '',
                (float) ($r['monto'] ?? 0),
            ];
        }
        $pie = [['Total', '', '', '', '', $total]];
        exportarExcel('reporte_egresos', ['Fecha', 'Origen', 'Concepto', 'Proveedor', 'Referencia', 'Monto'], $rows, $pie, $titulo . ' — Detallado');
    }
}

require_once __DIR__ . '/../includes/layout.php';
?>
<h1 class="h4 mb-2">Reporte de egresos</h1>
<p class="text-muted small mb-3">Movimientos del período clasificados como <?= e(marina_ui_debito()) ?> (pagos a proveedores y movimientos manuales en banco).</p>

<form method="get" class="toolbar mb-3">
    <input type="hidden" name="p" value="reporte-egresos">
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
            <label class="form-label mb-1">Cuenta</label>
            <select class="form-select" name="cuenta_id">
                <option value="0">Todas</option>
                <?php foreach ($cuentasOpts as $cid => $cnom): ?>
                    <option value="<?= (int) $cid ?>" <?= $cuenta_id === (int) $cid ? 'selected' : '' ?>><?= e($cnom) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-3">
            <label class="form-label mb-1">Partida (solo gastos)</label>
            <select class="form-select" name="partida_id">
                <option value="0">Todas</option>
                <?php foreach ($partidasOpts as $pid => $pnom): ?>
                    <option value="<?= (int) $pid ?>" <?= $partida_id === (int) $pid ? 'selected' : '' ?>><?= e($pnom) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-3">
            <label class="form-label mb-1">Vista</label>
            <select class="form-select" name="vista">
                <option value="detallado" <?= $vista === 'detallado' ? 'selected' : '' ?>>Detallado</option>
                <option value="agrupado" <?= $vista === 'agrupado' ? 'selected' : '' ?>>Agrupado (totales por partida / manual)</option>
            </select>
        </div>
        <div class="col-12 col-md-auto d-flex flex-wrap gap-2">
            <button type="submit" class="btn btn-primary">Filtrar</button>
            <button type="submit" class="btn btn-success" name="export" value="excel">Exportar Excel</button>
        </div>
    </div>
    <p class="text-muted small mb-0 mt-2">Banco, cuenta y forma de pago no se muestran en la tabla; el filtro por cuenta sigue aplicando a gastos y movimientos. En <strong>agrupado</strong>, los gastos se suman por <strong>partida</strong> y los movimientos manuales de costo bajo un solo renglón.</p>
</form>

<div class="card p-3 mb-3">
    <strong>Total débitos:</strong> <?= dinero($total) ?>
</div>

<?php if ($vista === 'agrupado'): ?>
<div class="card p-3">
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Partida / origen</th>
                    <th class="text-end">Total débito</th>
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
                <tr><td colspan="2" class="text-muted">No hay débitos en el período.</td></tr>
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
                    <th>Origen</th>
                    <th>Concepto</th>
                    <th>Proveedor</th>
                    <th>Referencia</th>
                    <th class="text-end">Monto</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($filas as $r): ?>
                <tr>
                    <td><?= fechaFormato($r['fecha']) ?></td>
                    <td><?= e($r['origen'] ?? '') ?></td>
                    <td><?= e($r['concepto'] ?? '') ?></td>
                    <td><?= e($r['proveedor_nombre'] ?? '—') ?></td>
                    <td><?= e($r['referencia'] ?? '') ?></td>
                    <td class="text-end"><?= dinero((float) ($r['monto'] ?? 0)) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($filas)): ?>
                <tr><td colspan="6" class="text-muted">No hay débitos en el período.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
