<?php
/**
 * Reportes: visualización de ingresos y costos.
 * Ingresos = cuotas pagadas (acreditadas a cuentas).
 * Costos = gastos por partida.
 */
$titulo = 'Ingresos y costos';
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
$total_ingresos = array_sum(array_column($ingresos_por_cuenta, 'total'));

// Gastos en el período
$st = $pdo->prepare("
    SELECT g.partida_id, p.nombre AS partida_nombre, SUM(g.monto) AS total
    FROM gastos g
    JOIN partidas p ON g.partida_id = p.id
    WHERE g.fecha_gasto BETWEEN ? AND ?
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
        $rows[] = ['Ingreso por cuenta', $r['cuenta_nombre'] ?? '', (float) ($r['total'] ?? 0)];
    }
    foreach ($gastos_por_partida as $r) {
        $rows[] = ['Costo por partida', $r['partida_nombre'] ?? '', (float) ($r['total'] ?? 0)];
    }
    $pie = [
        ['Total ingresos', '', $total_ingresos],
        ['Total costos', '', $total_gastos],
        ['Diferencia (ingresos − costos)', '', $diferencia],
    ];
    exportarExcel('reporte_ingresos_costos', ['Seccion', 'Concepto', 'Total'], $rows, $pie);
}

require_once __DIR__ . '/../includes/layout.php';
?>
<h1>Ingresos y costos</h1>
<p class="text-muted small mb-2">Los despachos de combustible se suman en ingresos por cuenta; los pedidos recibidos generan un gasto (egreso) por el costo total en la partida «Combustible». <a href="<?= MARINA_URL ?>/index.php?p=reporte-combustible">Reporte detallado de combustible</a></p>
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

<div class="reportes-grid">
    <div class="card p-3 mb-3">
        <h2 class="h5 mb-3">Resumen (<?= fechaFormato($desde) ?> – <?= fechaFormato($hasta) ?>)</h2>
        <p><strong>Total ingresos:</strong> <?= dinero($total_ingresos) ?></p>
        <p><strong>Total costos:</strong> <?= dinero($total_gastos) ?></p>
        <p><strong>Diferencia:</strong> <span style="color:<?= $diferencia >= 0 ? 'green' : 'red' ?>"><?= dinero($diferencia) ?></span></p>
    </div>
</div>

<div class="card p-3 mb-3">
    <h2 class="h5 mb-3">Ingresos por cuenta (cuotas pagadas y despacho combustible)</h2>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr><th>Cuenta</th><th>Total</th></tr>
            </thead>
            <tbody>
            <?php foreach ($ingresos_por_cuenta as $row): ?>
                <tr>
                    <td><?= e($row['cuenta_nombre']) ?></td>
                    <td><?= dinero($row['total']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($ingresos_por_cuenta)): ?>
                <tr><td colspan="2">No hay ingresos en el período.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card p-3 mb-3">
    <h2 class="h5 mb-3">Costos por partida</h2>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr><th>Partida</th><th>Total</th></tr>
            </thead>
            <tbody>
            <?php foreach ($gastos_por_partida as $row): ?>
                <tr>
                    <td><?= e($row['partida_nombre']) ?></td>
                    <td><?= dinero($row['total']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($gastos_por_partida)): ?>
                <tr><td colspan="2">No hay gastos en el período.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
