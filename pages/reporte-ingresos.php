<?php
/**
 * Reporte Ingresos — detalle de ingresos por cuotas (y fallback legacy).
 */
$titulo = 'Reporte — Ingreso';
$pdo = getDb();
require_once __DIR__ . '/../includes/reportes_queries.php';
require_once __DIR__ . '/../includes/export_excel.php';

$desde = obtener('desde', date('Y-m-01'));
$hasta = obtener('hasta', date('Y-m-d'));
$cuenta_id = (int) obtener('cuenta_id', 0);

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

$sql = 'SELECT * FROM (' . reportesSqlIngresosDetalle($cuentaCond) . ') x ORDER BY x.fecha, x.contrato_id, x.numero_cuota';
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
               CONCAT(b.nombre, ' - ', c.nombre) AS cuenta_nombre,
               mb.cuenta_id,
               '' AS cliente_nombre,
               NULL AS contrato_id,
               NULL AS numero_cuota,
               COALESCE(mb.referencia, '') AS referencia,
               fp.nombre AS forma_pago_nombre
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

$filas = array_merge($filas, $manuales);
usort($filas, function ($a, $b) {
    return strcmp((string) ($a['fecha'] ?? ''), (string) ($b['fecha'] ?? ''));
});

$total = array_sum(array_map(static function ($r) {
    return (float) ($r['monto'] ?? 0);
}, $filas));

if (obtener('export') === 'excel') {
    $rows = [];
    foreach ($filas as $r) {
        $rows[] = [
            $r['fecha'] ?? '',
            $r['cliente_nombre'] ?? '',
            $r['concepto'] ?? '',
            $r['cuenta_nombre'] ?? '',
            $r['forma_pago_nombre'] ?? '',
            $r['referencia'] ?? '',
            (float) ($r['monto'] ?? 0),
        ];
    }
    exportarExcel('reporte_ingresos', ['Fecha', 'Cliente', 'Concepto', 'Cuenta', 'Forma pago', 'Referencia', 'Monto'], $rows);
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
        <div class="col-12 col-md-3">
            <label class="form-label mb-1">Desde</label>
            <input type="date" class="form-control" name="desde" value="<?= e($desde) ?>">
        </div>
        <div class="col-12 col-md-3">
            <label class="form-label mb-1">Hasta</label>
            <input type="date" class="form-control" name="hasta" value="<?= e($hasta) ?>">
        </div>
        <div class="col-12 col-md-3">
            <label class="form-label mb-1">Cuenta (acreditación)</label>
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
    <strong>Total ingresos:</strong> <?= dinero($total) ?>
</div>

<div class="card p-3">
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Cliente</th>
                    <th>Concepto</th>
                    <th>Cuenta</th>
                    <th>Forma pago</th>
                    <th>Referencia</th>
                    <th class="text-end">Monto</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($filas as $r): ?>
                <tr>
                    <td><?= fechaFormato($r['fecha']) ?></td>
                    <td><?= e($r['cliente_nombre'] ?? '') ?></td>
                    <td><?= e($r['concepto'] ?? '') ?></td>
                    <td><?= e($r['cuenta_nombre'] ?? '') ?></td>
                    <td><?= e($r['forma_pago_nombre'] ?? '—') ?></td>
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
