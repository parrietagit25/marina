<?php
/**
 * Reporte de recaudo: cuotas con vencimiento en el rango y saldo pendiente por cobrar.
 * No incluye cuotas ya pagadas (solo el monto que falta por recaudar).
 */
$titulo = 'Reporte de recaudo';
$pdo = getDb();
require_once __DIR__ . '/../includes/export_excel.php';

$desde = trim(obtener('desde', date('Y-m-01')));
$hasta = trim(obtener('hasta', date('Y-m-d')));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) {
    $desde = date('Y-m-01');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
    $hasta = date('Y-m-d');
}
if ($desde > $hasta) {
    [$desde, $hasta] = [$hasta, $desde];
}

$muelle_id = (int) obtener('muelle_id', 0);
$tipoUnidad = trim(obtener('tipo_unidad', ''));
$tipoUnidad = in_array($tipoUnidad, ['', 'slip', 'inmueble'], true) ? $tipoUnidad : '';

$muellesOpts = $pdo->query('SELECT id, nombre FROM muelles ORDER BY nombre')->fetchAll(PDO::FETCH_KEY_PAIR);

$sql = "
    SELECT cu.id AS cuota_id,
           cu.contrato_id,
           cu.numero_cuota,
           cu.monto,
           cu.fecha_vencimiento,
           cu.fecha_pago AS fecha_pago_legacy,
           COALESCE(co.estado, 'activo') AS contrato_estado,
           COALESCE(mov.pagado_mov, 0) AS pagado_mov,
           cl.nombre AS navio,
           NULLIF(TRIM(cl.dueno_capitan), '') AS cliente_nombre,
           mu.nombre AS muelle_nombre,
           sl.nombre AS slip_nombre,
           g.nombre AS grupo_nombre,
           i.nombre AS inmueble_nombre,
           co.muelle_id,
           co.slip_id,
           co.grupo_id,
           co.inmueble_id
    FROM cuotas cu
    JOIN contratos co ON cu.contrato_id = co.id
    JOIN clientes cl ON co.cliente_id = cl.id
    LEFT JOIN (
        SELECT cuota_id, SUM(monto) AS pagado_mov
        FROM cuotas_movimientos
        WHERE tipo IN ('pago', 'abono')
        GROUP BY cuota_id
    ) mov ON mov.cuota_id = cu.id
    LEFT JOIN muelles mu ON mu.id = co.muelle_id
    LEFT JOIN slips sl ON sl.id = co.slip_id
    LEFT JOIN grupos g ON g.id = co.grupo_id
    LEFT JOIN inmuebles i ON i.id = co.inmueble_id
    WHERE cu.fecha_vencimiento BETWEEN ? AND ?
";
$params = [$desde, $hasta];

if ($tipoUnidad === 'slip') {
    $sql .= ' AND co.slip_id IS NOT NULL ';
} elseif ($tipoUnidad === 'inmueble') {
    $sql .= ' AND co.inmueble_id IS NOT NULL ';
}
if ($muelle_id > 0) {
    $sql .= ' AND co.muelle_id = ? ';
    $params[] = $muelle_id;
}

$sql .= '
    ORDER BY
        CASE WHEN COALESCE(mu.nombre, g.nombre, \'\') = \'\' THEN 1 ELSE 0 END,
        COALESCE(mu.nombre, g.nombre, \'\') ASC,
        CASE WHEN COALESCE(sl.nombre, i.nombre, \'\') = \'\' THEN 1 ELSE 0 END,
        COALESCE(sl.nombre, i.nombre, \'\') ASC,
        cu.fecha_vencimiento ASC,
        co.id ASC,
        cu.numero_cuota ASC
';

$st = $pdo->prepare($sql);
$st->execute($params);
$raw = $st->fetchAll(PDO::FETCH_ASSOC);

$hoy = date('Y-m-d');
$filas = [];
$totalRecaudo = 0.0;

foreach ($raw as $r) {
    $monto = round((float) ($r['monto'] ?? 0), 2);
    $pagadoMov = (float) ($r['pagado_mov'] ?? 0);
    if ($pagadoMov > 0.00001) {
        $pagado = round($pagadoMov, 2);
    } elseif (!empty($r['fecha_pago_legacy'])) {
        $pagado = $monto;
    } else {
        $pagado = 0.0;
    }
    $saldo = max(0.0, round($monto - $pagado, 2));
    if ($saldo <= 0.00001) {
        continue;
    }

    $fv = (string) ($r['fecha_vencimiento'] ?? '');
    if ($fv !== '' && $fv < $hoy) {
        $estado = 'Vencida';
    } else {
        $estado = 'Pendiente';
    }

    $muelle = (string) ($r['muelle_nombre'] ?? '');
    $slip = (string) ($r['slip_nombre'] ?? '');
    $esMarina = !empty($r['slip_id']);
    if (!$esMarina) {
        if ($muelle === '' && !empty($r['grupo_nombre'])) {
            $muelle = (string) $r['grupo_nombre'];
        }
        if ($slip === '' && !empty($r['inmueble_nombre'])) {
            $slip = (string) $r['inmueble_nombre'];
        }
    }

    $filas[] = [
        'contrato_id' => (int) $r['contrato_id'],
        'cuota_id' => (int) $r['cuota_id'],
        'numero_cuota' => (int) $r['numero_cuota'],
        'cliente' => (string) ($r['cliente_nombre'] ?? '') !== '' ? (string) $r['cliente_nombre'] : '—',
        'navio' => (string) ($r['navio'] ?? ''),
        'muelle' => $muelle !== '' ? $muelle : '—',
        'slip' => $slip !== '' ? $slip : '—',
        'muelle_orden' => $muelle !== '' ? $muelle : 'zzzz',
        'slip_orden' => $slip !== '' ? $slip : 'zzzz',
        'vencimiento' => $fv,
        'monto_cuota' => $monto,
        'pagado' => $pagado,
        'por_recaudar' => $saldo,
        'estado' => $estado,
        'contrato_estado' => (string) ($r['contrato_estado'] ?? 'activo'),
    ];
    $totalRecaudo += $saldo;
}

usort($filas, static function (array $a, array $b): int {
    $cmp = strnatcasecmp((string) ($a['muelle_orden'] ?? ''), (string) ($b['muelle_orden'] ?? ''));
    if ($cmp !== 0) {
        return $cmp;
    }
    $cmp = strnatcasecmp((string) ($a['slip_orden'] ?? ''), (string) ($b['slip_orden'] ?? ''));
    if ($cmp !== 0) {
        return $cmp;
    }
    $cmp = strcmp((string) ($a['vencimiento'] ?? ''), (string) ($b['vencimiento'] ?? ''));
    if ($cmp !== 0) {
        return $cmp;
    }

    return ((int) ($a['numero_cuota'] ?? 0)) <=> ((int) ($b['numero_cuota'] ?? 0));
});

/**
 * @param list<array<string, mixed>> $filas
 * @return list<array<string, mixed>>
 */
function marina_recaudo_filas_con_subtotales(array $filas): array
{
    if ($filas === []) {
        return [];
    }

    $out = [];
    $bloqueActual = null;
    $buffer = [];

    $cerrarBloque = static function () use (&$out, &$buffer): void {
        if ($buffer === []) {
            return;
        }
        $muelle = (string) ($buffer[0]['muelle'] ?? '—');
        $slip = (string) ($buffer[0]['slip'] ?? '—');
        $sumMonto = 0.0;
        $sumPagado = 0.0;
        $sumRecaudo = 0.0;
        foreach ($buffer as $row) {
            $out[] = ['tipo_fila' => 'dato', 'dato' => $row];
            $sumMonto += (float) ($row['monto_cuota'] ?? 0);
            $sumPagado += (float) ($row['pagado'] ?? 0);
            $sumRecaudo += (float) ($row['por_recaudar'] ?? 0);
        }
        $out[] = [
            'tipo_fila' => 'subtotal',
            'muelle' => $muelle,
            'slip' => $slip,
            'n_cuotas' => count($buffer),
            'sum_monto' => round($sumMonto, 2),
            'sum_pagado' => round($sumPagado, 2),
            'sum_recaudo' => round($sumRecaudo, 2),
        ];
        $out[] = ['tipo_fila' => 'separador'];
        $buffer = [];
    };

    foreach ($filas as $row) {
        $clave = strtolower((string) ($row['muelle_orden'] ?? '')) . '|' . strtolower((string) ($row['slip_orden'] ?? ''));
        if ($bloqueActual !== null && $clave !== $bloqueActual) {
            $cerrarBloque();
        }
        $bloqueActual = $clave;
        $buffer[] = $row;
    }
    $cerrarBloque();

    if ($out !== [] && ($out[array_key_last($out)]['tipo_fila'] ?? '') === 'separador') {
        array_pop($out);
    }

    return $out;
}

$filasRender = marina_recaudo_filas_con_subtotales($filas);

$totalRecaudo = round($totalRecaudo, 2);

if (obtener('export') === 'excel') {
    $rows = [];
    foreach ($filasRender as $item) {
        $tipoFila = (string) ($item['tipo_fila'] ?? 'dato');
        if ($tipoFila === 'separador') {
            continue;
        }
        if ($tipoFila === 'subtotal') {
            $rows[] = [
                '',
                '',
                'Total slip',
                '',
                (string) ($item['muelle'] ?? ''),
                (string) ($item['slip'] ?? ''),
                (int) ($item['n_cuotas'] ?? 0) . ' cuota(s)',
                (float) ($item['sum_monto'] ?? 0),
                (float) ($item['sum_pagado'] ?? 0),
                (float) ($item['sum_recaudo'] ?? 0),
                '',
            ];
            continue;
        }
        $r = $item['dato'] ?? [];
        $rows[] = [
            $r['contrato_id'],
            $r['numero_cuota'],
            $r['cliente'],
            $r['navio'],
            $r['muelle'],
            $r['slip'],
            $r['vencimiento'],
            (float) $r['monto_cuota'],
            (float) $r['pagado'],
            (float) $r['por_recaudar'],
            $r['estado'],
        ];
    }
    $pie = [['Total por recaudar', '', '', '', '', '', '', '', '', (float) $totalRecaudo, '']];
    exportarExcel(
        'reporte_recaudo',
        ['Contrato', 'Cuota', 'Cliente', 'Navío', 'Muelle', 'Slip', 'Vencimiento', 'Monto cuota', 'Pagado', 'Por recaudar', 'Estado'],
        $rows,
        $pie,
        $titulo . ' — ' . fechaFormato($desde) . ' a ' . fechaFormato($hasta)
    );
}

require_once __DIR__ . '/../includes/layout.php';
?>
<h1 class="h4 mb-2">Reporte de recaudo</h1>
<p class="text-muted small mb-3">
    Cuotas con <strong>vencimiento</strong> entre las fechas indicadas que aún tienen <strong>saldo por cobrar</strong>.
    Orden: <strong>muelle</strong> → <strong>slip</strong> → vencimiento. En inmuebles, muelle = grupo e slip = inmueble.
    Las cuotas ya pagadas no aparecen.
</p>

<form method="get" class="toolbar mb-3">
    <input type="hidden" name="p" value="reporte-recaudo">
    <div class="row g-2 align-items-end">
        <div class="col-12 col-md-2">
            <label class="form-label mb-1">Desde</label>
            <input type="date" class="form-control" name="desde" value="<?= e($desde) ?>" required>
        </div>
        <div class="col-12 col-md-2">
            <label class="form-label mb-1">Hasta</label>
            <input type="date" class="form-control" name="hasta" value="<?= e($hasta) ?>" required>
        </div>
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
            <label class="form-label mb-1">Tipo unidad</label>
            <select class="form-select" name="tipo_unidad">
                <option value="" <?= $tipoUnidad === '' ? 'selected' : '' ?>>Marina e inmuebles</option>
                <option value="slip" <?= $tipoUnidad === 'slip' ? 'selected' : '' ?>>Solo slips (marina)</option>
                <option value="inmueble" <?= $tipoUnidad === 'inmueble' ? 'selected' : '' ?>>Solo inmuebles</option>
            </select>
        </div>
        <div class="col-12 col-md-auto">
            <button type="submit" class="btn btn-primary">Consultar</button>
        </div>
        <div class="col-12 col-md-auto">
            <button type="submit" class="btn btn-success" name="export" value="excel">Exportar Excel</button>
        </div>
    </div>
</form>

<div class="row g-3 mb-3">
    <div class="col-12 col-md-4">
        <div class="card p-3 border-0 shadow-sm">
            <div class="text-muted small mb-1">Período</div>
            <div class="fs-6 fw-semibold"><?= fechaFormato($desde) ?> — <?= fechaFormato($hasta) ?></div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card p-3 border-0 shadow-sm">
            <div class="text-muted small mb-1">Cuotas por recaudar</div>
            <div class="fs-5 fw-semibold"><?= count($filas) ?></div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card p-3 border-0 shadow-sm bg-success bg-opacity-10">
            <div class="text-muted small mb-1">Total por recaudar</div>
            <div class="fs-5 fw-bold text-success"><?= dinero($totalRecaudo) ?></div>
        </div>
    </div>
</div>

<div class="card p-3">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 no-datatable" data-export-filename="reporte_recaudo" data-export-sheet="Recaudo">
            <thead class="table-light">
                <tr>
                    <th>Contrato</th>
                    <th>Cuota</th>
                    <th>Cliente</th>
                    <th>Navío</th>
                    <th>Muelle</th>
                    <th>Slip</th>
                    <th>Vencimiento</th>
                    <th class="text-end">Monto cuota</th>
                    <th class="text-end">Pagado</th>
                    <th class="text-end">Por recaudar</th>
                    <th>Estado</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if ($filasRender === []): ?>
                <tr>
                    <td colspan="12" class="text-muted">No hay cuotas con saldo pendiente y vencimiento en este rango.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($filasRender as $item):
                    $tipoFila = (string) ($item['tipo_fila'] ?? 'dato');
                    if ($tipoFila === 'separador'): ?>
                <tr class="reporte-cobranzas-separador" aria-hidden="true">
                    <td colspan="12"></td>
                </tr>
                    <?php continue; endif;
                    if ($tipoFila === 'subtotal'): ?>
                <tr class="reporte-cobranzas-subtotal">
                    <td colspan="7">
                        <strong>Total — <?= e((string) ($item['muelle'] ?? '')) ?> / <?= e((string) ($item['slip'] ?? '')) ?></strong>
                        <span class="text-muted small ms-1">(<?= (int) ($item['n_cuotas'] ?? 0) ?> cuota(s))</span>
                    </td>
                    <td class="text-end"><?= dinero((float) ($item['sum_monto'] ?? 0)) ?></td>
                    <td class="text-end"><?= dinero((float) ($item['sum_pagado'] ?? 0)) ?></td>
                    <td class="text-end"><?= dinero((float) ($item['sum_recaudo'] ?? 0)) ?></td>
                    <td colspan="2"></td>
                </tr>
                    <?php continue; endif;
                    $r = $item['dato'] ?? [];
                    ?>
                <tr>
                    <td>#<?= (int) $r['contrato_id'] ?></td>
                    <td>#<?= (int) $r['numero_cuota'] ?></td>
                    <td><?= e($r['cliente']) ?></td>
                    <td><?= e($r['navio']) ?></td>
                    <td><?= e($r['muelle']) ?></td>
                    <td><?= e($r['slip']) ?></td>
                    <td><?= fechaFormato($r['vencimiento']) ?></td>
                    <td class="text-end"><?= dinero((float) $r['monto_cuota']) ?></td>
                    <td class="text-end"><?= dinero((float) $r['pagado']) ?></td>
                    <td class="text-end fw-semibold text-success"><?= dinero((float) $r['por_recaudar']) ?></td>
                    <td>
                        <?php if ($r['estado'] === 'Vencida'): ?>
                            <span class="badge bg-danger">Vencida</span>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark">Pendiente</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-nowrap">
                        <a class="btn btn-sm btn-outline-primary" href="<?= MARINA_URL ?>/index.php?p=contratos&amp;accion=cuotas&amp;id=<?= (int) $r['contrato_id'] ?>">Cuotas</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <tr class="table-light fw-semibold">
                    <td colspan="9" class="text-end">Total por recaudar</td>
                    <td class="text-end text-success"><?= dinero($totalRecaudo) ?></td>
                    <td colspan="2"></td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
