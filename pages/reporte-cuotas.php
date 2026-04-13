<?php
/**
 * Reporte Cuotas: listado con estado de pago (movimientos + fallback).
 */
$titulo = 'Reporte — Cuotas';
$pdo = getDb();
require_once __DIR__ . '/../includes/export_excel.php';

$desde = obtener('desde', date('Y-m-01'));
$hasta = obtener('hasta', date('Y-m-d'));
$estado = trim(obtener('estado', '')); // '', pagada, pendiente
$contrato_id = (int) obtener('contrato_id', 0);

$sql = "
    SELECT cu.id AS cuota_id,
           cu.contrato_id,
           cu.numero_cuota,
           cu.monto,
           cu.fecha_vencimiento,
           cu.fecha_pago AS fecha_pago_legacy,
           cl.nombre AS cliente,
           COALESCE(co.estado, 'activo') AS contrato_estado,
           COALESCE(SUM(CASE WHEN mo.tipo IN ('pago','abono') THEN mo.monto ELSE 0 END), 0) AS pagado_mov
    FROM cuotas cu
    JOIN contratos co ON cu.contrato_id = co.id
    JOIN clientes cl ON co.cliente_id = cl.id
    LEFT JOIN cuotas_movimientos mo ON mo.cuota_id = cu.id
    WHERE cu.fecha_vencimiento BETWEEN ? AND ?
";
$params = [$desde, $hasta];
if ($contrato_id > 0) {
    $sql .= ' AND cu.contrato_id = ? ';
    $params[] = $contrato_id;
}
$sql .= ' GROUP BY cu.id, cu.contrato_id, cu.numero_cuota, cu.monto, cu.fecha_vencimiento, cu.fecha_pago, cl.nombre, COALESCE(co.estado, \'activo\') ';
$sql .= ' HAVING 1=1 ';

$st = $pdo->prepare($sql);
$st->execute($params);
$raw = $st->fetchAll(PDO::FETCH_ASSOC);

$filas = [];
foreach ($raw as $r) {
    $monto = (float) $r['monto'];
    $pagadoMov = (float) $r['pagado_mov'];
    $tieneMovs = $pagadoMov > 0.00001;
    if ($tieneMovs) {
        $pagado = $pagadoMov;
    } elseif (!empty($r['fecha_pago_legacy'])) {
        $pagado = $monto;
    } else {
        $pagado = 0.0;
    }
    $saldo = max(0, $monto - $pagado);
    $pagada = $saldo <= 0.00001;
    $estadoFila = $pagada ? 'Pagada' : 'Pendiente';

    $fv = $r['fecha_vencimiento'] ?? '';
    $hoy = date('Y-m-d');
    if ($fv && $fv < $hoy && !$pagada) {
        $estadoFila = 'Vencida';
    }

    if ($estado === 'pagada' && !$pagada) {
        continue;
    }
    if ($estado === 'pendiente' && $pagada) {
        continue;
    }
    if ($estado === 'vencida' && $estadoFila !== 'Vencida') {
        continue;
    }

    $filas[] = [
        'cuota_id' => (int) $r['cuota_id'],
        'contrato_id' => (int) $r['contrato_id'],
        'numero_cuota' => (int) $r['numero_cuota'],
        'cliente' => $r['cliente'],
        'monto' => $monto,
        'pagado' => $pagado,
        'saldo' => $saldo,
        'fecha_vencimiento' => $fv,
        'estado' => $estadoFila,
        'contrato_estado' => (string) ($r['contrato_estado'] ?? 'activo'),
    ];
}

$totalMonto = array_sum(array_column($filas, 'monto'));
$totalPagado = array_sum(array_column($filas, 'pagado'));
$totalSaldo = array_sum(array_column($filas, 'saldo'));

if (obtener('export') === 'excel') {
    $rows = [];
    foreach ($filas as $f) {
        $rows[] = [
            (int) $f['contrato_id'],
            (int) $f['numero_cuota'],
            $f['cliente'],
            (float) $f['monto'],
            (float) $f['pagado'],
            (float) $f['saldo'],
            $f['fecha_vencimiento'],
            $f['estado'],
            ($f['contrato_estado'] ?? 'activo') === 'activo' ? 'Activo' : 'Terminado',
        ];
    }
    $pie = [[
        'Totales',
        '',
        '',
        $totalMonto,
        $totalPagado,
        $totalSaldo,
        '',
        '',
        '',
    ]];
    exportarExcel('reporte_cuotas', ['Contrato', 'Cuota', 'Cliente', 'Monto', 'Pagado', 'Saldo', 'Vencimiento', 'Estado', 'Contrato'], $rows, $pie);
}

require_once __DIR__ . '/../includes/layout.php';
?>
<h1 class="h4 mb-3">Reporte — Cuotas</h1>

<form method="get" class="toolbar mb-3">
    <input type="hidden" name="p" value="reporte-cuotas">
    <div class="row g-2 align-items-end">
        <div class="col-12 col-md-2">
            <label class="form-label mb-1">Vence desde</label>
            <input type="date" class="form-control" name="desde" value="<?= e($desde) ?>">
        </div>
        <div class="col-12 col-md-2">
            <label class="form-label mb-1">Vence hasta</label>
            <input type="date" class="form-control" name="hasta" value="<?= e($hasta) ?>">
        </div>
        <div class="col-12 col-md-2">
            <label class="form-label mb-1">Estado</label>
            <select class="form-select" name="estado">
                <option value="" <?= $estado === '' ? 'selected' : '' ?>>Todos</option>
                <option value="pagada" <?= $estado === 'pagada' ? 'selected' : '' ?>>Pagada</option>
                <option value="pendiente" <?= $estado === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                <option value="vencida" <?= $estado === 'vencida' ? 'selected' : '' ?>>Vencida (no pagada)</option>
            </select>
        </div>
        <div class="col-12 col-md-2">
            <label class="form-label mb-1">Contrato #</label>
            <input type="number" min="0" class="form-control" name="contrato_id" value="<?= $contrato_id > 0 ? (int) $contrato_id : '' ?>" placeholder="Opcional">
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
    <div class="row g-2 small">
        <div class="col-md-4"><strong>Total monto cuotas (filtradas):</strong> <?= dinero($totalMonto) ?></div>
        <div class="col-md-4"><strong>Total pagado:</strong> <?= dinero($totalPagado) ?></div>
        <div class="col-md-4"><strong>Total saldo:</strong> <?= dinero($totalSaldo) ?></div>
    </div>
    <p class="text-muted small mb-0 mt-2">Filtro por rango de <strong>fecha de vencimiento</strong>. Estado “vencida” = vencimiento antes del “hasta” y aún con saldo.</p>
</div>

<div class="card p-3">
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Contrato</th>
                    <th>Cuota</th>
                    <th>Cliente</th>
                    <th>Monto</th>
                    <th>Pagado</th>
                    <th>Saldo</th>
                    <th>Vencimiento</th>
                    <th>Estado</th>
                    <th>Estado contrato</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($filas as $f): ?>
                <tr>
                    <td><a href="<?= MARINA_URL ?>/index.php?p=contratos&amp;accion=cuotas&amp;id=<?= (int) $f['contrato_id'] ?>">#<?= (int) $f['contrato_id'] ?></a></td>
                    <td>#<?= (int) $f['numero_cuota'] ?></td>
                    <td><?= e($f['cliente']) ?></td>
                    <td><?= dinero($f['monto']) ?></td>
                    <td><?= dinero($f['pagado']) ?></td>
                    <td><?= dinero($f['saldo']) ?></td>
                    <td><?= fechaFormato($f['fecha_vencimiento']) ?></td>
                    <td>
                        <?php
                        $badge = 'bg-secondary';
                        if ($f['estado'] === 'Pagada') {
                            $badge = 'bg-success';
                        } elseif ($f['estado'] === 'Vencida') {
                            $badge = 'bg-danger';
                        } elseif ($f['estado'] === 'Pendiente') {
                            $badge = 'bg-warning text-dark';
                        }
                        ?>
                        <span class="badge <?= $badge ?>"><?= e($f['estado']) ?></span>
                    </td>
                    <td><?= ($f['contrato_estado'] ?? 'activo') === 'activo' ? 'Activo' : 'Terminado' ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($filas)): ?>
                <tr>
                    <td class="text-muted">No hay registros con los filtros indicados.</td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
