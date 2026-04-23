<?php
/**
 * Reporte Inmuebles — contratos asignados a grupo/inmueble.
 */
$titulo = 'Reporte Inmuebles — Contratos';
$pdo = getDb();
require_once __DIR__ . '/../includes/export_excel.php';

$activo = obtener('activo', '');
$grupo_id = (int) obtener('grupo_id', 0);

$gruposOpts = $pdo->query('SELECT id, nombre FROM grupos ORDER BY nombre')->fetchAll(PDO::FETCH_KEY_PAIR);

$sql = "
    SELECT co.id, co.fecha_inicio, co.fecha_fin, co.monto_total, co.activo, COALESCE(co.estado, 'activo') AS estado,
           cl.nombre AS cliente,
           gr.nombre AS grupo_nombre,
           im.nombre AS inmueble_nombre
    FROM contratos co
    JOIN clientes cl ON cl.id = co.cliente_id
    LEFT JOIN grupos gr ON gr.id = co.grupo_id
    LEFT JOIN inmuebles im ON im.id = co.inmueble_id
    WHERE co.inmueble_id IS NOT NULL
";
$params = [];
if ($activo === '1') {
    $sql .= " AND COALESCE(co.estado, 'activo') = 'activo' ";
} elseif ($activo === '0') {
    $sql .= " AND COALESCE(co.estado, 'activo') = 'terminado' ";
}
if ($grupo_id > 0) {
    $sql .= ' AND co.grupo_id = ? ';
    $params[] = $grupo_id;
}
$sql .= ' ORDER BY gr.nombre, im.nombre, co.id';

$st = $pdo->prepare($sql);
$st->execute($params);
$filas = $st->fetchAll(PDO::FETCH_ASSOC);

if (obtener('export') === 'excel') {
    $rows = [];
    foreach ($filas as $r) {
        $rows[] = [
            (int) $r['id'],
            $r['grupo_nombre'] ?? '',
            $r['inmueble_nombre'] ?? '',
            $r['cliente'] ?? '',
            $r['fecha_inicio'] ?? '',
            $r['fecha_fin'] ?? '',
            (float) ($r['monto_total'] ?? 0),
            ((string) ($r['estado'] ?? 'activo') === 'activo') ? 'Activo' : 'Terminado',
        ];
    }
    $sumMontos = array_sum(array_map(static function ($r) {
        return (float) ($r['monto_total'] ?? 0);
    }, $filas));
    $pie = [['Total', '', '', '', '', '', $sumMontos, '']];
    exportarExcel('reporte_inmuebles_contratos', ['ID', 'Grupo', 'Inmueble', 'Cliente', 'Inicio', 'Fin', 'Monto total', 'Estado'], $rows, $pie);
}

require_once __DIR__ . '/../includes/layout.php';
?>
<h1 class="h4 mb-3">Reporte Inmuebles — Contratos</h1>

<form method="get" class="toolbar mb-3">
    <input type="hidden" name="p" value="reporte-inmuebles-contratos">
    <div class="row g-2 align-items-end">
        <div class="col-12 col-md-3">
            <label class="form-label mb-1">Grupo</label>
            <select class="form-select" name="grupo_id">
                <option value="0">Todos</option>
                <?php foreach ($gruposOpts as $gid => $gnom): ?>
                    <option value="<?= (int) $gid ?>" <?= $grupo_id === (int) $gid ? 'selected' : '' ?>><?= e($gnom) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-3">
            <label class="form-label mb-1">Estado</label>
            <select class="form-select" name="activo">
                <option value="" <?= $activo === '' ? 'selected' : '' ?>>Todos</option>
                <option value="1" <?= $activo === '1' ? 'selected' : '' ?>>Activos</option>
                <option value="0" <?= $activo === '0' ? 'selected' : '' ?>>Terminados</option>
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

<div class="card p-3">
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Grupo</th>
                    <th>Inmueble</th>
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
                    <td><?= e($r['grupo_nombre'] ?? '—') ?></td>
                    <td><?= e($r['inmueble_nombre'] ?? '—') ?></td>
                    <td><?= e($r['cliente'] ?? '') ?></td>
                    <td><?= fechaFormato($r['fecha_inicio']) ?></td>
                    <td><?= fechaFormato($r['fecha_fin']) ?></td>
                    <td class="text-end"><?= dinero((float) ($r['monto_total'] ?? 0)) ?></td>
                    <td><?= (string)($r['estado'] ?? 'activo') === 'activo' ? 'Activo' : 'Terminado' ?></td>
                    <td><a class="btn btn-sm btn-outline-primary" href="<?= MARINA_URL ?>/index.php?p=contratos&amp;accion=editar&amp;id=<?= (int) $r['id'] ?>">Ver</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($filas)): ?>
                <tr><td colspan="9" class="text-muted">No hay contratos con inmueble asignado.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
