<?php
/**
 * Reporte de ingresos y egresos — solo combustible (detalle en dos tablas).
 */
$titulo = 'Ingresos y egresos — Combustible';
require_once __DIR__ . '/../includes/export_excel.php';
require_once __DIR__ . '/../includes/combustible_helpers.php';

$pdo = getDb();
$desde = obtener('desde', date('Y-m-01'));
$hasta = obtener('hasta', date('Y-m-d'));
$partida_combustible_id = marina_combustible_partida_id($pdo);
$labCred = marina_ui_credito();
$labDeb = marina_ui_debito();

$creditos = [];
try {
    $st = $pdo->prepare("
        SELECT p.fecha_pago AS fecha,
               p.monto,
               cd.embarcacion,
               cd.tipo_combustible,
               cd.gls
        FROM combustible_despacho_pagos p
        JOIN combustible_despachos cd ON cd.id = p.despacho_id
        WHERE p.fecha_pago BETWEEN ? AND ?
        ORDER BY p.fecha_pago, p.id
    ");
    $st->execute([$desde, $hasta]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $creditos[] = [
            'fecha' => $r['fecha'],
            'embarcacion' => (string) ($r['embarcacion'] ?? ''),
            'tipo' => MARINA_COMB_TIPOS[$r['tipo_combustible'] ?? ''] ?? (string) ($r['tipo_combustible'] ?? ''),
            'gls' => (float) ($r['gls'] ?? 0),
            'monto' => (float) ($r['monto'] ?? 0),
        ];
    }
} catch (Throwable $e) {
    // sin módulo combustible
}

$debitos = [];
if ($partida_combustible_id > 0) {
    $st = $pdo->prepare("
        SELECT gp.fecha_pago AS fecha,
               gp.monto,
               pr.nombre AS proveedor,
               COALESCE(NULLIF(TRIM(g.observaciones), ''), CONCAT('Gasto #', g.id)) AS concepto
        FROM gasto_pagos gp
        JOIN gastos g ON g.id = gp.gasto_id
        JOIN proveedores pr ON pr.id = g.proveedor_id
        WHERE gp.fecha_pago BETWEEN ? AND ?
          AND g.partida_id = ?
        ORDER BY gp.fecha_pago, gp.id
    ");
    $st->execute([$desde, $hasta, $partida_combustible_id]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $debitos[] = [
            'fecha' => $r['fecha'],
            'proveedor' => (string) ($r['proveedor'] ?? ''),
            'concepto' => (string) ($r['concepto'] ?? ''),
            'monto' => (float) ($r['monto'] ?? 0),
        ];
    }
}

$totCred = array_sum(array_column($creditos, 'monto'));
$totDeb = array_sum(array_column($debitos, 'monto'));
$neto = $totCred - $totDeb;

if (obtener('export') === 'excel') {
    $rows = [];
    foreach ($creditos as $r) {
        $rows[] = [
            $r['fecha'],
            $r['embarcacion'],
            $r['tipo'],
            (float) $r['gls'],
            (float) $r['monto'],
        ];
    }
    $rows[] = ['', '', '', '', ''];
    foreach ($debitos as $r) {
        $rows[] = [
            $r['fecha'],
            $r['proveedor'],
            $r['concepto'],
            '',
            (float) $r['monto'],
        ];
    }
    $pie = [
        ['Total créditos', '', '', '', $totCred],
        ['Total débitos', '', '', '', $totDeb],
        ['Neto', '', '', '', $neto],
    ];
    exportarExcel(
        'reporte_combustible_ingresos_egresos',
        ['Fecha', 'Embarcación / Proveedor', 'Tipo / Concepto', 'GLS', 'Monto'],
        $rows,
        $pie,
        $titulo
    );
}

require_once __DIR__ . '/../includes/layout.php';
?>
<h1 class="h4 mb-2">Ingresos y egresos — Combustible</h1>
<p class="text-muted small mb-3">Cobros de despacho (<?= e($labCred) ?>) y pagos de compras (<?= e($labDeb) ?>). <a href="<?= MARINA_URL ?>/index.php?p=reporte-combustible">Reporte operativo</a>.</p>
<form method="get" class="toolbar mb-3">
    <input type="hidden" name="p" value="reportes">
    <div class="row g-2 align-items-end">
        <div class="col-12 col-md-3">
            <label class="form-label mb-1">Desde</label>
            <input type="date" class="form-control" name="desde" value="<?= e($desde) ?>">
        </div>
        <div class="col-12 col-md-3">
            <label class="form-label mb-1">Hasta</label>
            <input type="date" class="form-control" name="hasta" value="<?= e($hasta) ?>">
        </div>
        <div class="col-12 col-md-auto">
            <button type="submit" class="btn btn-primary">Filtrar</button>
        </div>
        <div class="col-12 col-md-auto">
            <button type="submit" class="btn btn-success" name="export" value="excel">Exportar Excel</button>
        </div>
    </div>
</form>

<div class="row g-3 mb-3">
    <div class="col-12 col-lg-6">
        <div class="card p-3 h-100">
            <h2 class="h6 mb-3"><?= e($labCred) ?>s — cobros de despacho</h2>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 no-datatable">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Embarcación</th>
                            <th>Tipo</th>
                            <th class="text-end">GLS</th>
                            <th class="text-end">Monto</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($creditos as $r): ?>
                        <tr>
                            <td><?= fechaFormato($r['fecha']) ?></td>
                            <td><?= e($r['embarcacion'] ?: '—') ?></td>
                            <td><?= e($r['tipo'] ?: '—') ?></td>
                            <td class="text-end"><?= number_format($r['gls'], 3, '.', ',') ?></td>
                            <td class="text-end text-nowrap"><?= dinero($r['monto']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($creditos)): ?>
                        <tr><td colspan="5" class="text-muted">No hay cobros en el período.</td></tr>
                    <?php endif; ?>
                    </tbody>
                    <?php if (!empty($creditos)): ?>
                    <tfoot class="table-light">
                        <tr>
                            <th colspan="4" class="text-end">Total</th>
                            <th class="text-end text-success"><?= dinero($totCred) ?></th>
                        </tr>
                    </tfoot>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-6">
        <div class="card p-3 h-100">
            <h2 class="h6 mb-3"><?= e($labDeb) ?>s — pagos de compras</h2>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 no-datatable">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Proveedor</th>
                            <th>Concepto</th>
                            <th class="text-end">Monto</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($debitos as $r): ?>
                        <tr>
                            <td><?= fechaFormato($r['fecha']) ?></td>
                            <td><?= e($r['proveedor'] ?: '—') ?></td>
                            <td><?= e($r['concepto']) ?></td>
                            <td class="text-end text-nowrap"><?= dinero($r['monto']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($debitos)): ?>
                        <tr><td colspan="4" class="text-muted">No hay pagos en el período.</td></tr>
                    <?php endif; ?>
                    </tbody>
                    <?php if (!empty($debitos)): ?>
                    <tfoot class="table-light">
                        <tr>
                            <th colspan="3" class="text-end">Total</th>
                            <th class="text-end text-danger"><?= dinero($totDeb) ?></th>
                        </tr>
                    </tfoot>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($creditos) || !empty($debitos)): ?>
<div class="card p-3">
    <div class="row g-2 small">
        <div class="col-md-4"><strong>Total créditos:</strong> <?= dinero($totCred) ?></div>
        <div class="col-md-4"><strong>Total débitos:</strong> <?= dinero($totDeb) ?></div>
        <div class="col-md-4"><strong>Neto:</strong> <span class="<?= $neto >= 0 ? 'text-success' : 'text-danger' ?>"><?= dinero($neto) ?></span></div>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
