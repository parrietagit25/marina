<?php
/**
 * Reporte Cliente Aislado:
 * solo movimientos bancarios manuales registrados desde Clientes (cliente_id IS NOT NULL).
 */
$titulo = 'Reporte Cliente Aislado';
$pdo = getDb();
require_once __DIR__ . '/../includes/export_excel.php';

$desde = obtener('desde', date('Y-m-01'));
$hasta = obtener('hasta', date('Y-m-d'));
$cliente_id = (int) obtener('cliente_id', 0);
$tipo_movimiento = trim((string) obtener('tipo_movimiento', ''));
$cuenta_id = (int) obtener('cuenta_id', 0);

$clientes = $pdo->query('SELECT id, nombre FROM clientes ORDER BY nombre')->fetchAll(PDO::FETCH_KEY_PAIR);
$cuentas = $pdo->query('
    SELECT c.id, CONCAT(b.nombre, " - ", c.nombre) AS nombre
    FROM cuentas c
    JOIN bancos b ON b.id = c.banco_id
    ORDER BY b.nombre, c.nombre
')->fetchAll(PDO::FETCH_KEY_PAIR);

$sql = "
    SELECT mb.id,
           mb.fecha_movimiento,
           mb.tipo_movimiento,
           mb.monto,
           mb.referencia,
           mb.descripcion,
           cl.nombre AS cliente_nombre,
           cl.dueno_capitan,
           fp.nombre AS forma_pago_nombre
    FROM movimientos_bancarios mb
    JOIN clientes cl ON cl.id = mb.cliente_id
    LEFT JOIN formas_pago fp ON fp.id = mb.forma_pago_id
    WHERE mb.cliente_id IS NOT NULL
      AND mb.fecha_movimiento BETWEEN ? AND ?
";
$params = [$desde, $hasta];

if ($cliente_id > 0) {
    $sql .= ' AND mb.cliente_id = ? ';
    $params[] = $cliente_id;
}
if ($tipo_movimiento === 'ingreso' || $tipo_movimiento === 'costo') {
    $sql .= ' AND mb.tipo_movimiento = ? ';
    $params[] = $tipo_movimiento;
}
if ($cuenta_id > 0) {
    $sql .= ' AND mb.cuenta_id = ? ';
    $params[] = $cuenta_id;
}
$sql .= ' ORDER BY mb.fecha_movimiento DESC, mb.id DESC ';

$st = $pdo->prepare($sql);
$st->execute($params);
$filas = $st->fetchAll(PDO::FETCH_ASSOC);

$totalIngresos = 0.0;
$totalCostos = 0.0;
foreach ($filas as $f) {
    $m = (float) ($f['monto'] ?? 0);
    if (($f['tipo_movimiento'] ?? '') === 'costo') {
        $totalCostos += $m;
    } else {
        $totalIngresos += $m;
    }
}
$diferencia = $totalIngresos - $totalCostos;

if (obtener('export') === 'excel') {
    $rows = [];
    foreach ($filas as $f) {
        $tipoRow = (($f['tipo_movimiento'] ?? '') === 'costo') ? marina_ui_debito() : marina_ui_credito();
        $rows[] = [
            $f['fecha_movimiento'] ?? '',
            $f['cliente_nombre'] ?? '',
            $tipoRow,
            $f['forma_pago_nombre'] ?? '',
            $f['dueno_capitan'] ?? '',
            $f['referencia'] ?? '',
            $f['descripcion'] ?? '',
            (float) ($f['monto'] ?? 0),
        ];
    }
    $pie = [
        ['Total ' . marina_ui_credito(), '', '', '', '', '', '', $totalIngresos],
        ['Total ' . marina_ui_debito(), '', '', '', '', '', '', $totalCostos],
        ['Diferencia', '', '', '', '', '', '', $diferencia],
    ];
    exportarExcel('reporte_cliente_aislado', [
        'Fecha',
        'Cliente',
        'Tipo',
        'Tipo movimiento',
        'Dueño / Capitán',
        'Referencia',
        'Descripcion',
        'Monto',
    ], $rows, $pie, $titulo);
}

require_once __DIR__ . '/../includes/layout.php';
?>
<h1 class="h4 mb-2">Reporte Cliente Aislado</h1>
<p class="text-muted small mb-3">Muestra unicamente movimientos bancarios manuales registrados desde el modulo de <strong>Clientes</strong>.</p>

<form method="get" class="toolbar mb-3">
    <input type="hidden" name="p" value="reporte-cliente-aislado">
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
            <label class="form-label mb-1">Cliente</label>
            <select class="form-select" name="cliente_id">
                <option value="0">Todos</option>
                <?php foreach ($clientes as $cid => $cnom): ?>
                    <option value="<?= (int) $cid ?>" <?= $cliente_id === (int) $cid ? 'selected' : '' ?>><?= e($cnom) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-2">
            <label class="form-label mb-1">Tipo</label>
            <select class="form-select" name="tipo_movimiento">
                <option value="" <?= $tipo_movimiento === '' ? 'selected' : '' ?>>Todos</option>
                <option value="ingreso" <?= $tipo_movimiento === 'ingreso' ? 'selected' : '' ?>><?= e(marina_ui_credito()) ?></option>
                <option value="costo" <?= $tipo_movimiento === 'costo' ? 'selected' : '' ?>><?= e(marina_ui_debito()) ?></option>
            </select>
        </div>
        <div class="col-12 col-md-3">
            <label class="form-label mb-1">Cuenta</label>
            <select class="form-select" name="cuenta_id">
                <option value="0">Todas</option>
                <?php foreach ($cuentas as $cid => $cnom): ?>
                    <option value="<?= (int) $cid ?>" <?= $cuenta_id === (int) $cid ? 'selected' : '' ?>><?= e($cnom) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-auto d-flex flex-wrap gap-2">
            <button type="submit" class="btn btn-primary">Filtrar</button>
            <button type="submit" class="btn btn-success" name="export" value="excel">Exportar Excel</button>
        </div>
    </div>
</form>

<div class="card p-3 mb-3">
    <div class="row g-2 small">
        <div class="col-md-4"><strong>Total <?= e(marina_ui_credito()) ?>:</strong> <?= dinero($totalIngresos) ?></div>
        <div class="col-md-4"><strong>Total <?= e(marina_ui_debito()) ?>:</strong> <?= dinero($totalCostos) ?></div>
        <div class="col-md-4"><strong>Diferencia:</strong> <?= dinero($diferencia) ?></div>
    </div>
</div>

<div class="card p-3">
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Cliente</th>
                    <th>Tipo</th>
                    <th>Tipo movimiento</th>
                    <th>Dueño / Capitán</th>
                    <th>Referencia</th>
                    <th>Descripcion</th>
                    <th class="text-end">Monto</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($filas as $f): ?>
                <?php $tipoRow = (($f['tipo_movimiento'] ?? '') === 'costo') ? marina_ui_debito() : marina_ui_credito(); ?>
                <tr>
                    <td><?= fechaFormato($f['fecha_movimiento'] ?? '') ?></td>
                    <td><?= e($f['cliente_nombre'] ?? '—') ?></td>
                    <td><?= e($tipoRow) ?></td>
                    <td><?= e($f['forma_pago_nombre'] ?? '—') ?></td>
                    <td><?= e($f['dueno_capitan'] ?? '—') ?></td>
                    <td><?= e($f['referencia'] ?? '') ?></td>
                    <td><?= e($f['descripcion'] ?? '') ?></td>
                    <td class="text-end"><?= dinero((float) ($f['monto'] ?? 0)) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($filas)): ?>
                <tr><td colspan="8" class="text-muted">No hay movimientos de clientes con los filtros indicados.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
