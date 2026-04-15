<?php
/**
 * Reportes: visualización de ingresos y costos.
 * Ingresos = cuotas pagadas (acreditadas a cuentas).
 * Costos = gastos por partida.
 */
$titulo = 'Reporte de ingresos y egresos';
require_once __DIR__ . '/../includes/export_excel.php';

$pdo = getDb();
$desde = obtener('desde', date('Y-m-01'));
$hasta = obtener('hasta', date('Y-m-d'));

// Ingresos: suma de movimientos (pago/abono) por fecha_pago.
// Compatibilidad: si una cuota no tiene movimientos en `cuotas_movimientos`, tomamos el pago viejo de `cuotas.fecha_pago`.
// Incluye despachos de combustible acreditados a cuenta (si existe la tabla).
$sqlIngresosCuotas = "
    SELECT t.cuenta_id, t.cuenta_nombre, SUM(t.total) AS total
    FROM (
        SELECT co.cuenta_id, CONCAT(b.nombre, ' - ', c.nombre) AS cuenta_nombre, mo.monto AS total
        FROM cuotas_movimientos mo
        JOIN cuotas cu ON mo.cuota_id = cu.id
        JOIN contratos co ON cu.contrato_id = co.id
        JOIN cuentas c ON co.cuenta_id = c.id
        JOIN bancos b ON c.banco_id = b.id
        WHERE mo.fecha_pago BETWEEN ? AND ?

        UNION ALL

        SELECT co.cuenta_id, CONCAT(b.nombre, ' - ', c.nombre) AS cuenta_nombre, cu.monto AS total
        FROM cuotas cu
        JOIN contratos co ON cu.contrato_id = co.id
        JOIN cuentas c ON co.cuenta_id = c.id
        JOIN bancos b ON c.banco_id = b.id
        WHERE cu.fecha_pago BETWEEN ? AND ?
          AND NOT EXISTS (SELECT 1 FROM cuotas_movimientos x WHERE x.cuota_id = cu.id)
    ) t
    GROUP BY t.cuenta_id, t.cuenta_nombre
";
$sqlIngresosConCombustible = "
    SELECT t.cuenta_id, t.cuenta_nombre, SUM(t.total) AS total
    FROM (
        SELECT co.cuenta_id, CONCAT(b.nombre, ' - ', c.nombre) AS cuenta_nombre, mo.monto AS total
        FROM cuotas_movimientos mo
        JOIN cuotas cu ON mo.cuota_id = cu.id
        JOIN contratos co ON cu.contrato_id = co.id
        JOIN cuentas c ON co.cuenta_id = c.id
        JOIN bancos b ON c.banco_id = b.id
        WHERE mo.fecha_pago BETWEEN ? AND ?

        UNION ALL

        SELECT co.cuenta_id, CONCAT(b.nombre, ' - ', c.nombre) AS cuenta_nombre, cu.monto AS total
        FROM cuotas cu
        JOIN contratos co ON cu.contrato_id = co.id
        JOIN cuentas c ON co.cuenta_id = c.id
        JOIN bancos b ON c.banco_id = b.id
        WHERE cu.fecha_pago BETWEEN ? AND ?
          AND NOT EXISTS (SELECT 1 FROM cuotas_movimientos x WHERE x.cuota_id = cu.id)

        UNION ALL

        SELECT cd.cuenta_id, CONCAT(b.nombre, ' - ', c.nombre) AS cuenta_nombre, cd.monto_total AS total
        FROM combustible_despachos cd
        JOIN cuentas c ON c.id = cd.cuenta_id
        JOIN bancos b ON b.id = c.banco_id
        WHERE cd.fecha BETWEEN ? AND ?
    ) t
    GROUP BY t.cuenta_id, t.cuenta_nombre
";
$ingresos_por_cuenta = [];
try {
    $st = $pdo->prepare($sqlIngresosConCombustible);
    $st->execute([$desde, $hasta, $desde, $hasta, $desde, $hasta]);
    $ingresos_por_cuenta = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $st = $pdo->prepare($sqlIngresosCuotas);
    $st->execute([$desde, $hasta, $desde, $hasta]);
    $ingresos_por_cuenta = $st->fetchAll(PDO::FETCH_ASSOC);
}
try {
    $stEl = $pdo->prepare("
        SELECT ep.cuenta_id, CONCAT(b.nombre, ' - ', c.nombre) AS cuenta_nombre, SUM(ep.monto) AS total
        FROM contrato_electricidad_pagos ep
        JOIN cuentas c ON c.id = ep.cuenta_id
        JOIN bancos b ON b.id = c.banco_id
        WHERE ep.fecha_pago BETWEEN ? AND ?
        GROUP BY ep.cuenta_id, b.nombre, c.nombre
    ");
    $stEl->execute([$desde, $hasta]);
    $porCuenta = [];
    foreach ($ingresos_por_cuenta as $r) {
        $cid = (int) ($r['cuenta_id'] ?? 0);
        if ($cid > 0) {
            $porCuenta[$cid] = ['cuenta_id' => $cid, 'cuenta_nombre' => $r['cuenta_nombre'] ?? '', 'total' => (float) ($r['total'] ?? 0)];
        }
    }
    while ($row = $stEl->fetch(PDO::FETCH_ASSOC)) {
        $cid = (int) ($row['cuenta_id'] ?? 0);
        if ($cid < 1) {
            continue;
        }
        if (!isset($porCuenta[$cid])) {
            $porCuenta[$cid] = ['cuenta_id' => $cid, 'cuenta_nombre' => $row['cuenta_nombre'] ?? '', 'total' => 0.0];
        }
        $porCuenta[$cid]['total'] += (float) ($row['total'] ?? 0);
    }
    $ingresos_por_cuenta = array_values($porCuenta);
} catch (Throwable $e) {
    // sin electricidad
}
$total_ingresos = array_sum(array_column($ingresos_por_cuenta, 'total'));

// Gastos en el período
$st = $pdo->prepare("
    SELECT g.partida_id, p.nombre AS partida_nombre, SUM(gp.monto) AS total
    FROM gasto_pagos gp
    JOIN gastos g ON g.id = gp.gasto_id
    JOIN partidas p ON g.partida_id = p.id
    WHERE gp.fecha_pago BETWEEN ? AND ?
    GROUP BY g.partida_id, p.nombre
    ORDER BY total DESC
");
$st->execute([$desde, $hasta]);
$gastos_por_partida = $st->fetchAll(PDO::FETCH_ASSOC);
$total_gastos = array_sum(array_column($gastos_por_partida, 'total'));

$diferencia = $total_ingresos - $total_gastos;

if (obtener('export') === 'excel') {
    $rows = [];
    foreach ($ingresos_por_cuenta as $r) {
        $rows[] = ['Crédito por cuenta', $r['cuenta_nombre'] ?? '', (float) ($r['total'] ?? 0)];
    }
    foreach ($gastos_por_partida as $r) {
        $rows[] = ['Débito por partida', $r['partida_nombre'] ?? '', (float) ($r['total'] ?? 0)];
    }
    $pie = [
        ['Total créditos', '', $total_ingresos],
        ['Total débitos', '', $total_gastos],
        ['Diferencia (créditos − débitos)', '', $diferencia],
    ];
    exportarExcel('reporte_ingresos_costos', ['Seccion', 'Concepto', 'Total'], $rows, $pie);
}

require_once __DIR__ . '/../includes/layout.php';
?>
<h1 class="h4 mb-2">Reporte de ingresos y egresos</h1>
<p class="text-muted small mb-3">Resumen del período: totales de <strong>ingresos</strong> por cuenta (cuotas, despacho de combustible y electricidad) y de <strong>egresos</strong> por partida (abonos a gastos y pedidos de combustible recibidos). En el detalle numérico de abajo se usan las etiquetas <?= e(marina_ui_credito()) ?> y <?= e(marina_ui_debito()) ?>. <a href="<?= MARINA_URL ?>/index.php?p=reporte-combustible">Reporte detallado de combustible</a></p>
<form method="get" class="toolbar" style="margin-bottom:1rem">
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

<div class="reportes-ingresos-page w-100 mw-100">
<div class="row g-3 mb-3">
    <div class="col-12">
        <div class="card p-4">
            <h2 class="h5 mb-3">Resumen (<?= fechaFormato($desde) ?> – <?= fechaFormato($hasta) ?>)</h2>
            <div class="row g-3">
                <div class="col-12 col-sm-4">
                    <div class="rounded-3 border bg-light p-3 h-100">
                        <div class="small text-muted text-uppercase fw-semibold mb-1">Total créditos</div>
                        <div class="fs-4 fw-bold text-success"><?= dinero($total_ingresos) ?></div>
                    </div>
                </div>
                <div class="col-12 col-sm-4">
                    <div class="rounded-3 border bg-light p-3 h-100">
                        <div class="small text-muted text-uppercase fw-semibold mb-1">Total débitos</div>
                        <div class="fs-4 fw-bold text-danger"><?= dinero($total_gastos) ?></div>
                    </div>
                </div>
                <div class="col-12 col-sm-4">
                    <div class="rounded-3 border bg-light p-3 h-100">
                        <div class="small text-muted text-uppercase fw-semibold mb-1">Diferencia</div>
                        <div class="fs-4 fw-bold <?= $diferencia >= 0 ? 'text-success' : 'text-danger' ?>"><?= dinero($diferencia) ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 gx-lg-4 reportes-creditos-debitos-row mb-3">
    <div class="col-12 col-lg-6 d-flex min-w-0">
        <div class="card p-4 flex-fill w-100 min-w-0">
            <h2 class="h5 mb-3">Créditos por cuenta (cuotas pagadas y despacho combustible)</h2>
            <div class="table-responsive w-100">
                <table class="table table-lg align-middle w-100 mb-0">
                    <thead>
                        <tr><th>Cuenta</th><th class="text-end">Total</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($ingresos_por_cuenta as $row): ?>
                        <tr>
                            <td><?= e($row['cuenta_nombre']) ?></td>
                            <td class="text-end text-nowrap"><?= dinero($row['total']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($ingresos_por_cuenta)): ?>
                        <tr><td colspan="2" class="text-muted">No hay créditos en el período.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-6 d-flex min-w-0">
        <div class="card p-4 flex-fill w-100 min-w-0">
            <h2 class="h5 mb-3">Débitos por partida</h2>
            <div class="table-responsive w-100">
                <table class="table table-lg align-middle w-100 mb-0">
                    <thead>
                        <tr><th>Partida</th><th class="text-end">Total</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($gastos_por_partida as $row): ?>
                        <tr>
                            <td><?= e($row['partida_nombre']) ?></td>
                            <td class="text-end text-nowrap"><?= dinero($row['total']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($gastos_por_partida)): ?>
                        <tr><td colspan="2" class="text-muted">No hay gastos en el período.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
