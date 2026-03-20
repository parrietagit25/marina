<?php
/**
 * Reporte Proveedores — estado de cuenta (gastos / pagos en el período).
 */
$titulo = 'Reporte — Proveedores, estado de cuenta';
$pdo = getDb();
require_once __DIR__ . '/../includes/export_excel.php';

$desde = obtener('desde', date('Y-m-01'));
$hasta = obtener('hasta', date('Y-m-d'));
$proveedor_id = (int) obtener('proveedor_id', 0);

$proveedores = $pdo->query('SELECT id, nombre FROM proveedores ORDER BY nombre')->fetchAll(PDO::FETCH_KEY_PAIR);

$sql = "
    SELECT g.id, g.fecha_gasto, g.monto, g.referencia, g.observaciones,
           pr.id AS proveedor_id, pr.nombre AS proveedor_nombre,
           p.nombre AS partida_nombre,
           CONCAT(b.nombre, ' - ', c.nombre) AS cuenta_nombre,
           fp.nombre AS forma_pago_nombre
    FROM gastos g
    JOIN proveedores pr ON pr.id = g.proveedor_id
    JOIN partidas p ON p.id = g.partida_id
    LEFT JOIN cuentas c ON c.id = g.cuenta_id
    LEFT JOIN bancos b ON b.id = c.banco_id
    LEFT JOIN formas_pago fp ON fp.id = g.forma_pago_id
    WHERE g.fecha_gasto BETWEEN ? AND ?
";
$params = [$desde, $hasta];
if ($proveedor_id > 0) {
    $sql .= ' AND g.proveedor_id = ? ';
    $params[] = $proveedor_id;
}
$sql .= ' ORDER BY pr.nombre, g.fecha_gasto, g.id';

$st = $pdo->prepare($sql);
$st->execute($params);
$movs = $st->fetchAll(PDO::FETCH_ASSOC);

$porProveedor = [];
$totalGeneral = 0.0;
foreach ($movs as $m) {
    $pid = (int) $m['proveedor_id'];
    if (!isset($porProveedor[$pid])) {
        $porProveedor[$pid] = ['nombre' => $m['proveedor_nombre'], 'total' => 0.0, 'items' => []];
    }
    $monto = (float) $m['monto'];
    $porProveedor[$pid]['total'] += $monto;
    $porProveedor[$pid]['items'][] = $m;
    $totalGeneral += $monto;
}

if (obtener('export') === 'excel') {
    $rows = [];
    foreach ($movs as $m) {
        $rows[] = [
            $m['fecha_gasto'] ?? '',
            $m['proveedor_nombre'] ?? '',
            $m['partida_nombre'] ?? '',
            (float) ($m['monto'] ?? 0),
            $m['cuenta_nombre'] ?? '',
            $m['forma_pago_nombre'] ?? '',
            $m['referencia'] ?? '',
            $m['observaciones'] ?? '',
        ];
    }
    exportarExcel('reporte_proveedores_estado_cuenta', ['Fecha', 'Proveedor', 'Partida', 'Monto', 'Cuenta', 'Forma pago', 'Referencia', 'Observaciones'], $rows);
}

require_once __DIR__ . '/../includes/layout.php';
?>
<h1 class="h4 mb-3">Reporte — Proveedores, estado de cuenta</h1>

<form method="get" class="toolbar mb-3">
    <input type="hidden" name="p" value="reporte-proveedores-estado-cuenta">
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
            <label class="form-label mb-1">Proveedor</label>
            <select class="form-select" name="proveedor_id">
                <option value="0">Todos</option>
                <?php foreach ($proveedores as $pid => $pnom): ?>
                    <option value="<?= (int) $pid ?>" <?= $proveedor_id === (int) $pid ? 'selected' : '' ?>><?= e($pnom) ?></option>
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
    <strong>Total período:</strong> <?= dinero($totalGeneral) ?>
    <span class="text-muted small ms-2">(suma de gastos registrados)</span>
</div>

<?php if ($proveedor_id > 0 && !empty($movs)): ?>
    <div class="card p-3 mb-3">
        <h2 class="h6 mb-2">Detalle movimientos</h2>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Partida</th>
                        <th>Monto</th>
                        <th>Cuenta</th>
                        <th>Forma pago</th>
                        <th>Referencia</th>
                        <th>Observaciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($movs as $m): ?>
                    <tr>
                        <td><?= fechaFormato($m['fecha_gasto']) ?></td>
                        <td><?= e($m['partida_nombre']) ?></td>
                        <td><?= dinero((float) $m['monto']) ?></td>
                        <td><?= e($m['cuenta_nombre'] ?? '—') ?></td>
                        <td><?= e($m['forma_pago_nombre'] ?? '—') ?></td>
                        <td><?= e($m['referencia'] ?? '') ?></td>
                        <td><?= e($m['observaciones'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php elseif ($proveedor_id === 0): ?>
    <div class="card p-3">
        <h2 class="h6 mb-3">Resumen por proveedor</h2>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr><th>Proveedor</th><th class="text-end">Total gastos</th></tr>
                </thead>
                <tbody>
                <?php foreach ($porProveedor as $bloque): ?>
                    <tr>
                        <td><?= e($bloque['nombre']) ?></td>
                        <td class="text-end"><?= dinero($bloque['total']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($porProveedor)): ?>
                    <tr><td colspan="2" class="text-muted">No hay movimientos en el período.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <p class="text-muted small mb-0">Seleccione un proveedor para ver el detalle línea a línea.</p>
    </div>
<?php else: ?>
    <div class="alert alert-info">No hay movimientos para el proveedor en el período.</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
