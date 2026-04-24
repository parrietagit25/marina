<?php
/**
 * Reporte Marina — contratos asignados a muelle/slip.
 */
$titulo = 'Reporte Marina — Contratos';
$pdo = getDb();
require_once __DIR__ . '/../includes/export_excel.php';

$activo = obtener('activo', ''); // '', 1, 0
$muelle_id = (int) obtener('muelle_id', 0);

$muellesOpts = $pdo->query('SELECT id, nombre FROM muelles ORDER BY nombre')->fetchAll(PDO::FETCH_KEY_PAIR);

$sql = "
    SELECT co.id, co.fecha_inicio, co.fecha_fin, co.monto_total, co.activo, COALESCE(co.estado, 'activo') AS estado,
           cl.nombre AS cliente,
           mu.nombre AS muelle_nombre,
           sl.nombre AS slip_nombre
    FROM contratos co
    JOIN clientes cl ON cl.id = co.cliente_id
    LEFT JOIN muelles mu ON mu.id = co.muelle_id
    LEFT JOIN slips sl ON sl.id = co.slip_id
    WHERE co.slip_id IS NOT NULL
";
$params = [];
if ($activo === '1') {
    $sql .= " AND COALESCE(co.estado, 'activo') = 'activo' ";
} elseif ($activo === '0') {
    $sql .= " AND COALESCE(co.estado, 'activo') = 'terminado' ";
}
if ($muelle_id > 0) {
    $sql .= ' AND co.muelle_id = ? ';
    $params[] = $muelle_id;
}
$sql .= ' ORDER BY mu.nombre, sl.nombre, co.id';

$st = $pdo->prepare($sql);
$st->execute($params);
$filas = $st->fetchAll(PDO::FETCH_ASSOC);

if (obtener('export') === 'excel') {
    $rows = [];
    foreach ($filas as $r) {
        $rows[] = [
            (int) $r['id'],
            $r['muelle_nombre'] ?? '',
            $r['slip_nombre'] ?? '',
            $r['cliente'] ?? '',
            $r['fecha_inicio'] ?? '',
            $r['fecha_fin'] ?? '',
            (float) ($r['monto_total'] ?? 0),
            ((string) ($r['estado'] ?? 'activo') === 'activo') ? 'Activo' : 'Liberado',
        ];
    }
    $sumMontos = array_sum(array_map(static function ($r) {
        return (float) ($r['monto_total'] ?? 0);
    }, $filas));
    $pie = [['Total', '', '', '', '', '', $sumMontos, '']];
    exportarExcel('reporte_marina_contratos', ['ID', 'Muelle', 'Slip', 'Cliente', 'Inicio', 'Fin', 'Monto total', 'Estado'], $rows, $pie, $titulo);
}

require_once __DIR__ . '/../includes/layout.php';
?>
<h1 class="h4 mb-3">Reporte Marina — Contratos</h1>

<form method="get" class="toolbar mb-3">
    <input type="hidden" name="p" value="reporte-marina-contratos">
    <div class="row g-2 align-items-end">
        <div class="col-12 col-md-3">
            <label class="form-label mb-1">Muelle</label>
            <select class="form-select" name="muelle_id">
                <option value="0">Todos</option>
                <?php foreach ($muellesOpts as $mid => $mnom): ?>
                    <option value="<?= (int) $mid ?>" <?= $muelle_id === (int) $mid ? 'selected' : '' ?>><?= e($mnom) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-3">
            <label class="form-label mb-1">Estado</label>
            <select class="form-select" name="activo">
                <option value="" <?= $activo === '' ? 'selected' : '' ?>>Todos</option>
                <option value="1" <?= $activo === '1' ? 'selected' : '' ?>>Activos</option>
                <option value="0" <?= $activo === '0' ? 'selected' : '' ?>>Liberados</option>
            </select>
        </div>
        <div class="col-12 col-md-auto">
            <button type="submit" class="btn btn-primary">Filtrar</button>
        </div>
        <div class="col-12 col-md-auto">
            <button type="submit" class="btn btn-success" name="export" value="excel">Exportar Excel</button>
        </div>
    </div>
    <p class="text-muted small mb-0 mt-2">Al <strong>liberar</strong> se conserva el último muelle/slip en el contrato para este reporte; el mapa sigue tomando solo contratos <strong>activos</strong> como ocupados. Contratos liberados muy antiguos pueden tener slip vacío y no listarse.</p>
</form>

<div class="card p-3">
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Muelle</th>
                    <th>Slip</th>
                    <th>Cliente</th>
                    <th>Inicio</th>
                    <th>Fin</th>
                    <th class="text-end">Monto total</th>
                    <th>Estado</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($filas as $r): ?>
                <tr>
                    <td><?= (int) $r['id'] ?></td>
                    <td><?= e($r['muelle_nombre'] ?? '—') ?></td>
                    <td><?= e($r['slip_nombre'] ?? '—') ?></td>
                    <td><?= e($r['cliente'] ?? '') ?></td>
                    <td><?= fechaFormato($r['fecha_inicio']) ?></td>
                    <td><?= fechaFormato($r['fecha_fin']) ?></td>
                    <td class="text-end"><?= dinero((float) ($r['monto_total'] ?? 0)) ?></td>
                    <td><?= (string)($r['estado'] ?? 'activo') === 'activo' ? 'Activo' : 'Liberado' ?></td>
                    <td><a class="btn btn-sm btn-outline-primary" href="<?= MARINA_URL ?>/index.php?p=contratos&amp;accion=editar&amp;id=<?= (int) $r['id'] ?>">Ver</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($filas)): ?>
                <tr><td colspan="9" class="text-muted">No hay contratos con slip asignado.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
