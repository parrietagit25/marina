<?php
/**
 * Reporte de ocupación: todos los slips e inmuebles; contrato activo, montos y estado de cuotas.
 */
$titulo = 'Reporte de Ocupación';
$pdo = getDb();
require_once __DIR__ . '/../includes/export_excel.php';

$filtro = trim(obtener('ocupacion', ''));
$filtro = in_array($filtro, ['todos', 'ocupado', 'libre'], true) ? $filtro : 'todos';
$tipoFiltro = trim(obtener('tipo', ''));
$tipoFiltro = in_array($tipoFiltro, ['', 'slip', 'inmueble'], true) ? $tipoFiltro : '';
$muelle_id = (int) obtener('muelle_id', 0);
$grupo_id = (int) obtener('grupo_id', 0);

$muellesOpts = $pdo->query('SELECT id, nombre FROM muelles ORDER BY nombre')->fetchAll(PDO::FETCH_KEY_PAIR);
$gruposOpts = $pdo->query('SELECT id, nombre FROM grupos ORDER BY nombre')->fetchAll(PDO::FETCH_KEY_PAIR);

$slips = $pdo->query('
    SELECT s.id, s.nombre AS slip_nombre, s.muelle_id, m.nombre AS muelle_nombre
    FROM slips s
    JOIN muelles m ON m.id = s.muelle_id
    ORDER BY m.nombre, s.nombre
')->fetchAll(PDO::FETCH_ASSOC);

$inmuebles = $pdo->query('
    SELECT i.id, i.nombre AS inmueble_nombre, i.grupo_id, g.nombre AS grupo_nombre
    FROM inmuebles i
    JOIN grupos g ON g.id = i.grupo_id
    ORDER BY g.nombre, i.nombre
')->fetchAll(PDO::FETCH_ASSOC);

/** @return array<int, array<string, mixed>> */
function marina_reporte_ocupacion_contratos_activos(PDO $pdo): array
{
    $porSlip = [];
    $porInmueble = [];
    $st = $pdo->query("
        SELECT co.id, co.slip_id, co.inmueble_id, co.monto_total, co.fecha_inicio, co.fecha_fin,
               cl.nombre AS cliente_nombre
        FROM contratos co
        JOIN clientes cl ON cl.id = co.cliente_id
        WHERE COALESCE(co.estado, 'activo') = 'activo'
          AND (co.slip_id IS NOT NULL OR co.inmueble_id IS NOT NULL)
        ORDER BY co.id DESC
    ");
    while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
        $sid = (int) ($r['slip_id'] ?? 0);
        $iid = (int) ($r['inmueble_id'] ?? 0);
        if ($sid > 0 && !isset($porSlip[$sid])) {
            $porSlip[$sid] = $r;
        }
        if ($iid > 0 && !isset($porInmueble[$iid])) {
            $porInmueble[$iid] = $r;
        }
    }
    return ['slip' => $porSlip, 'inm' => $porInmueble];
}

$maps = marina_reporte_ocupacion_contratos_activos($pdo);
$bySlip = $maps['slip'];
$byInm = $maps['inm'];

$idsContratos = [];
foreach ($bySlip as $r) {
    $idsContratos[] = (int) $r['id'];
}
foreach ($byInm as $r) {
    $idsContratos[] = (int) $r['id'];
}
$idsContratos = array_values(array_unique($idsContratos));

$cuotasPorContrato = [];
if ($idsContratos !== []) {
    $ph = implode(',', array_fill(0, count($idsContratos), '?'));
    $stC = $pdo->prepare("
        SELECT cu.id, cu.contrato_id, cu.monto, cu.fecha_vencimiento, cu.fecha_pago,
               COALESCE(mov.pagado_mov, 0) AS pagado_mov
        FROM cuotas cu
        LEFT JOIN (
            SELECT cuota_id, SUM(monto) AS pagado_mov
            FROM cuotas_movimientos
            WHERE tipo IN ('pago', 'abono')
            GROUP BY cuota_id
        ) mov ON mov.cuota_id = cu.id
        WHERE cu.contrato_id IN ($ph)
        ORDER BY cu.contrato_id, cu.numero_cuota
    ");
    $stC->execute($idsContratos);
    while ($c = $stC->fetch(PDO::FETCH_ASSOC)) {
        $cid = (int) $c['contrato_id'];
        if (!isset($cuotasPorContrato[$cid])) {
            $cuotasPorContrato[$cid] = [];
        }
        $cuotasPorContrato[$cid][] = $c;
    }
}

$hoy = date('Y-m-d');

/**
 * @param list<array<string, mixed>> $cuotas
 * @return array{suma_cuotas: float, pagado: float, saldo: float, vencido: float, por_vencer: float, prox_venc: string, fin_contr: string}
 */
function marina_reporte_ocupacion_metricas_cuotas(array $cuotas, string $hoy, string $fechaFinContr): array
{
    $sumaCuotas = 0.0;
    $sumPagado = 0.0;
    $sumSaldo = 0.0;
    $sumVenc = 0.0;
    $sumPorVencer = 0.0;
    $candidatosProx = [];
    foreach ($cuotas as $c) {
        $monto = (float) ($c['monto'] ?? 0);
        $pagMov = (float) ($c['pagado_mov'] ?? 0);
        $tieneMov = $pagMov > 0.00001;
        if ($tieneMov) {
            $pagado = $pagMov;
        } elseif (!empty($c['fecha_pago'])) {
            $pagado = $monto;
        } else {
            $pagado = 0.0;
        }
        $saldo = max(0, $monto - $pagado);
        $fv = (string) ($c['fecha_vencimiento'] ?? '');
        $sumaCuotas += $monto;
        $sumPagado += min($monto, $pagado);
        $sumSaldo += $saldo;
        if ($saldo > 0.00001) {
            if ($fv === '') {
                $sumVenc += $saldo;
            } elseif ($fv < $hoy) {
                $sumVenc += $saldo;
            } else {
                $sumPorVencer += $saldo;
                $candidatosProx[] = $fv;
            }
        }
    }
    $proxVenc = '';
    if ($candidatosProx !== []) {
        sort($candidatosProx);
        $proxVenc = $candidatosProx[0];
    }

    return [
        'suma_cuotas' => round($sumaCuotas, 2),
        'pagado' => round($sumPagado, 2),
        'saldo' => round($sumSaldo, 2),
        'vencido' => round($sumVenc, 2),
        'por_vencer' => round($sumPorVencer, 2),
        'prox_venc' => $proxVenc,
        'fin_contr' => $fechaFinContr,
    ];
}

/**
 * @param array<string, mixed> $s Fila de slip (join muelles)
 * @return array<string, mixed>
 */
function marina_ocupacion_fila_slip(
    array $s,
    array $bySlip,
    array $cuotasPorContrato,
    string $hoy
): array {
    $idSlip = (int) $s['id'];
    $contr = $bySlip[$idSlip] ?? null;
    $ocupado = $contr !== null;
    $label = trim(($s['muelle_nombre'] ?? '') . ' — ' . ($s['slip_nombre'] ?? ''));
    if (!$ocupado) {
        return [
            'tipo' => 'Marina (slip)',
            'unidad' => $label !== '' ? $label : '—',
            'ocupacion' => 'Libre',
            'contrato_id' => 0,
            'cliente' => '',
            'monto_contrato' => null,
            'suma_cuotas' => null,
            'fin_contrato' => '',
            'prox_venc' => '',
            'pagado' => 0.0,
            'saldo' => 0.0,
            'vencido' => 0.0,
            'por_vencer' => 0.0,
            'muelle_id' => (int) ($s['muelle_id'] ?? 0),
            'muelle_nombre' => (string) ($s['muelle_nombre'] ?? ''),
            'grupo_id' => 0,
            'grupo_nombre' => '',
        ];
    }
    $cid = (int) $contr['id'];
    $cuo = $cuotasPorContrato[$cid] ?? [];
    $m = marina_reporte_ocupacion_metricas_cuotas(
        $cuo,
        $hoy,
        (string) ($contr['fecha_fin'] ?? '')
    );
    return [
        'tipo' => 'Marina (slip)',
        'unidad' => $label,
        'ocupacion' => 'Ocupado',
        'contrato_id' => $cid,
        'cliente' => (string) ($contr['cliente_nombre'] ?? ''),
        'monto_contrato' => (float) ($contr['monto_total'] ?? 0),
        'suma_cuotas' => $m['suma_cuotas'],
        'fin_contrato' => (string) ($contr['fecha_fin'] ?? ''),
        'prox_venc' => $m['prox_venc'],
        'pagado' => $m['pagado'],
        'saldo' => $m['saldo'],
        'vencido' => $m['vencido'],
        'por_vencer' => $m['por_vencer'],
        'muelle_id' => (int) ($s['muelle_id'] ?? 0),
        'muelle_nombre' => (string) ($s['muelle_nombre'] ?? ''),
        'grupo_id' => 0,
        'grupo_nombre' => '',
    ];
}

/**
 * @param array<string, mixed> $i Fila inmueble
 * @return array<string, mixed>
 */
function marina_ocupacion_fila_inmueble(
    array $i,
    array $byInm,
    array $cuotasPorContrato,
    string $hoy
): array {
    $iidU = (int) $i['id'];
    $contr = $byInm[$iidU] ?? null;
    $ocupado = $contr !== null;
    $label = trim(($i['grupo_nombre'] ?? '') . ' — ' . ($i['inmueble_nombre'] ?? ''));
    if (!$ocupado) {
        return [
            'tipo' => 'Inmueble',
            'unidad' => $label !== '' ? $label : '—',
            'ocupacion' => 'Libre',
            'contrato_id' => 0,
            'cliente' => '',
            'monto_contrato' => null,
            'suma_cuotas' => null,
            'fin_contrato' => '',
            'prox_venc' => '',
            'pagado' => 0.0,
            'saldo' => 0.0,
            'vencido' => 0.0,
            'por_vencer' => 0.0,
            'muelle_id' => 0,
            'muelle_nombre' => '',
            'grupo_id' => (int) ($i['grupo_id'] ?? 0),
            'grupo_nombre' => (string) ($i['grupo_nombre'] ?? ''),
        ];
    }
    $cid = (int) $contr['id'];
    $cuo = $cuotasPorContrato[$cid] ?? [];
    $m = marina_reporte_ocupacion_metricas_cuotas(
        $cuo,
        $hoy,
        (string) ($contr['fecha_fin'] ?? '')
    );
    return [
        'tipo' => 'Inmueble',
        'unidad' => $label,
        'ocupacion' => 'Ocupado',
        'contrato_id' => $cid,
        'cliente' => (string) ($contr['cliente_nombre'] ?? ''),
        'monto_contrato' => (float) ($contr['monto_total'] ?? 0),
        'suma_cuotas' => $m['suma_cuotas'],
        'fin_contrato' => (string) ($contr['fecha_fin'] ?? ''),
        'prox_venc' => $m['prox_venc'],
        'pagado' => $m['pagado'],
        'saldo' => $m['saldo'],
        'vencido' => $m['vencido'],
        'por_vencer' => $m['por_vencer'],
        'muelle_id' => 0,
        'muelle_nombre' => '',
        'grupo_id' => (int) ($i['grupo_id'] ?? 0),
        'grupo_nombre' => (string) ($i['grupo_nombre'] ?? ''),
    ];
}

/**
 * @param array<string, mixed> $row
 */
function marina_ocupacion_pasa_filtro_ocupacion(array $row, string $filtro): bool
{
    if ($filtro === 'ocupado') {
        return ($row['ocupacion'] ?? '') === 'Ocupado';
    }
    if ($filtro === 'libre') {
        return ($row['ocupacion'] ?? '') === 'Libre';
    }
    return true;
}

/**
 * @param array<int, array<string, mixed>> $acc
 * @param array<string, mixed> $row
 */
function marina_ocupacion_acumular_en_grupo(array &$acc, int $id, string $nombre, array $row, string $etiquetaAmbito): void
{
    if ($id < 1) {
        return;
    }
    if ($nombre === '') {
        $nombre = '— (#' . (string) $id . ')';
    }
    if (!isset($acc[$id])) {
        $acc[$id] = [
            'ambito' => $etiquetaAmbito,
            'grupo_nombre' => $nombre,
            'n_unidades' => 0,
            'n_libres' => 0,
            'n_ocupados' => 0,
            'sum_monto_contrato' => 0.0,
            'sum_suma_cuotas' => 0.0,
            'sum_pagado' => 0.0,
            'sum_saldo' => 0.0,
            'sum_vencido' => 0.0,
            'sum_por_vencer' => 0.0,
        ];
    }
    $a = &$acc[$id];
    $a['n_unidades']++;
    if (($row['ocupacion'] ?? '') === 'Libre') {
        $a['n_libres']++;
    } else {
        $a['n_ocupados']++;
        $a['sum_monto_contrato'] += (float) ($row['monto_contrato'] ?? 0);
        $a['sum_suma_cuotas'] += (float) ($row['suma_cuotas'] ?? 0);
        $a['sum_pagado'] += (float) ($row['pagado'] ?? 0);
        $a['sum_saldo'] += (float) ($row['saldo'] ?? 0);
        $a['sum_vencido'] += (float) ($row['vencido'] ?? 0);
        $a['sum_por_vencer'] += (float) ($row['por_vencer'] ?? 0);
    }
}

$vista = trim(obtener('vista', 'detalle'));
$vista = in_array($vista, ['detalle', 'grupos'], true) ? $vista : 'detalle';

$filas = [];
$acumMuelle = [];
$acumGrupo = [];

foreach ($slips as $s) {
    if ($muelle_id > 0 && (int) $s['muelle_id'] !== $muelle_id) {
        continue;
    }
    if ($tipoFiltro === 'inmueble') {
        continue;
    }
    $row = marina_ocupacion_fila_slip($s, $bySlip, $cuotasPorContrato, $hoy);
    if ($vista === 'grupos') {
        $mid = (int) $row['muelle_id'];
        if ($mid > 0) {
            marina_ocupacion_acumular_en_grupo($acumMuelle, $mid, (string) $row['muelle_nombre'], $row, 'Marina (muelle)');
        }
    } else {
        if (!marina_ocupacion_pasa_filtro_ocupacion($row, $filtro)) {
            continue;
        }
        unset($row['muelle_id'], $row['muelle_nombre'], $row['grupo_id'], $row['grupo_nombre']);
        $filas[] = $row;
    }
}

foreach ($inmuebles as $i) {
    if ($grupo_id > 0 && (int) $i['grupo_id'] !== $grupo_id) {
        continue;
    }
    if ($tipoFiltro === 'slip') {
        continue;
    }
    $row = marina_ocupacion_fila_inmueble($i, $byInm, $cuotasPorContrato, $hoy);
    if ($vista === 'grupos') {
        $gid = (int) $row['grupo_id'];
        if ($gid > 0) {
            marina_ocupacion_acumular_en_grupo($acumGrupo, $gid, (string) $row['grupo_nombre'], $row, 'Inmueble (grupo)');
        }
    } else {
        if (!marina_ocupacion_pasa_filtro_ocupacion($row, $filtro)) {
            continue;
        }
        unset($row['muelle_id'], $row['muelle_nombre'], $row['grupo_id'], $row['grupo_nombre']);
        $filas[] = $row;
    }
}

$filasGrupos = [];
if ($vista === 'grupos') {
    uasort($acumMuelle, static function ($a, $b) {
        return strnatcasecmp((string) $a['grupo_nombre'], (string) $b['grupo_nombre']);
    });
    uasort($acumGrupo, static function ($a, $b) {
        return strnatcasecmp((string) $a['grupo_nombre'], (string) $b['grupo_nombre']);
    });
    foreach ($acumMuelle as $g) {
        $filasGrupos[] = $g;
    }
    foreach ($acumGrupo as $g) {
        $filasGrupos[] = $g;
    }
}
unset($g);

$totalesGrupos = [
    'n_unidades' => 0,
    'n_libres' => 0,
    'n_ocupados' => 0,
    'sum_monto_contrato' => 0.0,
    'sum_suma_cuotas' => 0.0,
    'sum_pagado' => 0.0,
    'sum_saldo' => 0.0,
    'sum_vencido' => 0.0,
    'sum_por_vencer' => 0.0,
];
if ($vista === 'grupos') {
    foreach ($filasGrupos as $g) {
        $totalesGrupos['n_unidades'] += (int) ($g['n_unidades'] ?? 0);
        $totalesGrupos['n_libres'] += (int) ($g['n_libres'] ?? 0);
        $totalesGrupos['n_ocupados'] += (int) ($g['n_ocupados'] ?? 0);
        $totalesGrupos['sum_monto_contrato'] += (float) ($g['sum_monto_contrato'] ?? 0);
        $totalesGrupos['sum_suma_cuotas'] += (float) ($g['sum_suma_cuotas'] ?? 0);
        $totalesGrupos['sum_pagado'] += (float) ($g['sum_pagado'] ?? 0);
        $totalesGrupos['sum_saldo'] += (float) ($g['sum_saldo'] ?? 0);
        $totalesGrupos['sum_vencido'] += (float) ($g['sum_vencido'] ?? 0);
        $totalesGrupos['sum_por_vencer'] += (float) ($g['sum_por_vencer'] ?? 0);
    }
}
unset($g);

$cntOcup = 0;
$cntLibre = 0;
if ($vista === 'detalle') {
    foreach ($filas as $row) {
        if (($row['ocupacion'] ?? '') === 'Ocupado') {
            $cntOcup++;
        } else {
            $cntLibre++;
        }
    }
} else {
    foreach ($filasGrupos as $g) {
        $cntOcup += (int) ($g['n_ocupados'] ?? 0);
        $cntLibre += (int) ($g['n_libres'] ?? 0);
    }
}

$totalesDetalle = [
    'sum_monto_contrato' => 0.0,
    'sum_suma_cuotas' => 0.0,
    'sum_pagado' => 0.0,
    'sum_saldo' => 0.0,
    'sum_vencido' => 0.0,
    'sum_por_vencer' => 0.0,
];
if ($vista === 'detalle') {
    foreach ($filas as $f) {
        $totalesDetalle['sum_monto_contrato'] += (float) ($f['monto_contrato'] ?? 0);
        $totalesDetalle['sum_suma_cuotas'] += (float) ($f['suma_cuotas'] ?? 0);
        $totalesDetalle['sum_pagado'] += (float) ($f['pagado'] ?? 0);
        $totalesDetalle['sum_saldo'] += (float) ($f['saldo'] ?? 0);
        $totalesDetalle['sum_vencido'] += (float) ($f['vencido'] ?? 0);
        $totalesDetalle['sum_por_vencer'] += (float) ($f['por_vencer'] ?? 0);
    }
}

if (obtener('export') === 'excel') {
    if ($vista === 'grupos') {
        $rowsX = [];
        foreach ($filasGrupos as $g) {
            $rowsX[] = [
                (string) ($g['ambito'] ?? ''),
                (string) ($g['grupo_nombre'] ?? ''),
                (int) ($g['n_unidades'] ?? 0),
                (int) ($g['n_libres'] ?? 0),
                (int) ($g['n_ocupados'] ?? 0),
                (float) ($g['sum_monto_contrato'] ?? 0),
                (float) ($g['sum_suma_cuotas'] ?? 0),
                (float) ($g['sum_pagado'] ?? 0),
                (float) ($g['sum_saldo'] ?? 0),
                (float) ($g['sum_vencido'] ?? 0),
                (float) ($g['sum_por_vencer'] ?? 0),
            ];
        }
        $pie = [
            [
                'Totales (vista agrupada)',
                '',
                (int) $totalesGrupos['n_unidades'],
                (int) $totalesGrupos['n_libres'],
                (int) $totalesGrupos['n_ocupados'],
                (float) $totalesGrupos['sum_monto_contrato'],
                (float) $totalesGrupos['sum_suma_cuotas'],
                (float) $totalesGrupos['sum_pagado'],
                (float) $totalesGrupos['sum_saldo'],
                (float) $totalesGrupos['sum_vencido'],
                (float) $totalesGrupos['sum_por_vencer'],
            ],
        ];
        exportarExcel('reporte_ocupacion_grupos', [
            'Ámbito',
            'Grupo / muelle',
            'Unidades',
            'Libres',
            'Ocupados',
            'Monto contrato ∑',
            'Suma cuotas ∑',
            'Pagado ∑',
            'Falta por pagar ∑',
            'Vencido ∑',
            'Pend. no venc. ∑',
        ], $rowsX, $pie, $titulo . ' — Totales por grupo');
    } else {
        $rowsX = [];
        foreach ($filas as $f) {
            $rowsX[] = [
                $f['tipo'],
                $f['unidad'],
                $f['ocupacion'],
                (int) ($f['contrato_id'] ?? 0),
                (string) ($f['cliente'] ?? ''),
                $f['monto_contrato'] !== null ? (float) $f['monto_contrato'] : '',
                $f['suma_cuotas'] !== null ? (float) $f['suma_cuotas'] : '',
                (string) ($f['fin_contrato'] ?? ''),
                (string) ($f['prox_venc'] ?? ''),
                (float) ($f['pagado'] ?? 0),
                (float) ($f['saldo'] ?? 0),
                (float) ($f['vencido'] ?? 0),
                (float) ($f['por_vencer'] ?? 0),
            ];
        }
        $pie = [
            [
                'Totales (listado actual)',
                '',
                'Ocupados: ' . (string) $cntOcup . ' | Libres: ' . (string) $cntLibre,
                '',
                '',
                (float) $totalesDetalle['sum_monto_contrato'],
                (float) $totalesDetalle['sum_suma_cuotas'],
                '',
                '',
                (float) $totalesDetalle['sum_pagado'],
                (float) $totalesDetalle['sum_saldo'],
                (float) $totalesDetalle['sum_vencido'],
                (float) $totalesDetalle['sum_por_vencer'],
            ],
        ];
        exportarExcel('reporte_ocupacion', [
            'Tipo',
            'Unidad',
            'Ocupación',
            'Contrato',
            'Cliente',
            'Monto contrato',
            'Suma cuotas',
            'Fin contrato',
            'Próx. venc. cuota',
            'Pagado (cuotas)',
            'Falta por pagar',
            'Vencido no pagado',
            'Pend. no vencido',
        ], $rowsX, $pie, $titulo);
    }
}

require_once __DIR__ . '/../includes/layout.php';
?>
<h1 class="h4 mb-2">Reporte de Ocupación</h1>
<p class="text-muted small mb-3">Listado de <strong>todos los slips (marina)</strong> e <strong>inmuebles</strong> (almacenes, habitaciones, depósitos, etc. según cómo estén creados en <em>Grupos / Inmuebles</em>). <strong>Libre</strong> = sin contrato activo; <strong>Ocupado</strong> = contrato con estado activo. Los montos de cuotas usan abonos/pagos y pago legado, igual que el <a href="<?= MARINA_URL ?>/index.php?p=reporte-cuotas">reporte de cuotas</a>. <strong>Falta por pagar</strong> es el saldo total pendiente; <strong>Vencido</strong> es el saldo de cuotas cuya fecha de vencimiento ya pasó; <strong>Pend. no vencido</strong> es saldo con vencimiento hoy o futuro.</p>
<?php if ($vista === 'grupos'): ?>
    <p class="small alert alert-info py-2 mb-3">Vista <strong>Totales por grupo</strong>: se suma todo por <strong>cada muelle</strong> (todos sus slips) y por <strong>cada grupo de inmuebles</strong> (Astillero, Dique seco, etc. según nombres en el sistema), incluyendo <strong>unidades libres y ocupadas</strong>. El filtro de ocupación (libre/ocupado) <strong>no aplica</strong> en esta vista; sí aplican muelle, grupo, y tipo (slips / inmuebles).</p>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<form method="get" class="toolbar mb-3">
    <input type="hidden" name="p" value="reporte-ocupacion">
    <div class="row g-2 align-items-end">
        <div class="col-12 col-md-6 col-lg-3">
            <label class="form-label mb-1">Vista</label>
            <select class="form-select" name="vista" id="ocup-vista">
                <option value="detalle" <?= $vista === 'detalle' ? 'selected' : '' ?>>Unidad a unidad (detalle)</option>
                <option value="grupos" <?= $vista === 'grupos' ? 'selected' : '' ?>>Totales por grupo (muelle / grupo)</option>
            </select>
        </div>
        <div class="col-12 col-md-6 col-lg-2">
            <label class="form-label mb-1">Ocupación <span class="text-muted fw-normal">(solo detalle)</span></label>
            <select class="form-select" <?= $vista === 'grupos' ? 'disabled' : 'name="ocupacion"' ?>>
                <option value="todos" <?= $filtro === 'todos' ? 'selected' : '' ?>>Todos</option>
                <option value="ocupado" <?= $filtro === 'ocupado' ? 'selected' : '' ?>>Solo ocupados</option>
                <option value="libre" <?= $filtro === 'libre' ? 'selected' : '' ?>>Solo libres</option>
            </select>
            <?php if ($vista === 'grupos'): ?><input type="hidden" name="ocupacion" value="<?= e($filtro) ?>"><?php endif; ?>
        </div>
        <div class="col-12 col-md-6 col-lg-2">
            <label class="form-label mb-1">Tipo unidad</label>
            <select class="form-select" name="tipo">
                <option value="" <?= $tipoFiltro === '' ? 'selected' : '' ?>>Todas</option>
                <option value="slip" <?= $tipoFiltro === 'slip' ? 'selected' : '' ?>>Solo slips (marina)</option>
                <option value="inmueble" <?= $tipoFiltro === 'inmueble' ? 'selected' : '' ?>>Solo inmuebles</option>
            </select>
        </div>
        <div class="col-12 col-md-6 col-lg-3">
            <label class="form-label mb-1">Muelle (slips)</label>
            <select class="form-select" name="muelle_id">
                <option value="0">Todos</option>
                <?php foreach ($muellesOpts as $mid => $mnom): ?>
                    <option value="<?= (int) $mid ?>" <?= $muelle_id === (int) $mid ? 'selected' : '' ?>><?= e($mnom) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-6 col-lg-3">
            <label class="form-label mb-1">Grupo (inmuebles)</label>
            <select class="form-select" name="grupo_id">
                <option value="0">Todos</option>
                <?php foreach ($gruposOpts as $gid => $gnom): ?>
                    <option value="<?= (int) $gid ?>" <?= $grupo_id === (int) $gid ? 'selected' : '' ?>><?= e($gnom) ?></option>
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
        <?php if ($vista === 'detalle'): ?>
            <div class="col-md-6"><strong>Filas listadas:</strong> <?= (int) count($filas) ?> (Ocupados: <strong><?= (int) $cntOcup ?></strong> — Libres: <strong><?= (int) $cntLibre ?></strong>)</div>
        <?php else: ?>
            <div class="col-md-8">
                <strong>Grupos en el listado:</strong> <?= (int) count($filasGrupos) ?>
                (Muelles: <strong><?= (int) count($acumMuelle) ?></strong> — Grupos inmueble: <strong><?= (int) count($acumGrupo) ?></strong>).
                Unidades totales en suma: ocupados <strong><?= (int) $cntOcup ?></strong>, libres <strong><?= (int) $cntLibre ?></strong>.
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($vista === 'grupos'): ?>
<div class="table-responsive card p-0 mb-2">
    <table class="table table-hover table-sm align-middle mb-0 no-datatable">
        <thead class="table-light">
            <tr>
                <th>Ámbito</th>
                <th>Grupo / muelle</th>
                <th class="text-end">Unid.</th>
                <th class="text-end">Libres</th>
                <th class="text-end">Ocup.</th>
                <th class="text-end">Monto contrato ∑</th>
                <th class="text-end">Suma cuotas ∑</th>
                <th class="text-end">Pagado ∑</th>
                <th class="text-end">Falta por pagar ∑</th>
                <th class="text-end">Vencido ∑</th>
                <th class="text-end">Pend. no venc. ∑</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($filasGrupos)): ?>
            <tr><td colspan="11" class="text-muted p-3">Sin grupos con los filtros actuales.</td></tr>
        <?php else: ?>
            <?php foreach ($filasGrupos as $f): ?>
            <tr>
                <td class="text-muted small"><?= e((string) $f['ambito']) ?></td>
                <td class="fw-medium"><?= e((string) $f['grupo_nombre']) ?></td>
                <td class="text-end"><?= (int) $f['n_unidades'] ?></td>
                <td class="text-end"><?= (int) $f['n_libres'] ?></td>
                <td class="text-end"><?= (int) $f['n_ocupados'] ?></td>
                <td class="text-end"><?= dinero((float) $f['sum_monto_contrato']) ?></td>
                <td class="text-end"><?= dinero((float) $f['sum_suma_cuotas']) ?></td>
                <td class="text-end"><?= dinero((float) $f['sum_pagado']) ?></td>
                <td class="text-end"><?= dinero((float) $f['sum_saldo']) ?></td>
                <td class="text-end"><?= dinero((float) $f['sum_vencido']) ?></td>
                <td class="text-end"><?= dinero((float) $f['sum_por_vencer']) ?></td>
            </tr>
            <?php endforeach; ?>
            <tr class="table-light fw-bold">
                <td>Total general</td>
                <td>Todos los grupos</td>
                <td class="text-end"><?= (int) $totalesGrupos['n_unidades'] ?></td>
                <td class="text-end"><?= (int) $totalesGrupos['n_libres'] ?></td>
                <td class="text-end"><?= (int) $totalesGrupos['n_ocupados'] ?></td>
                <td class="text-end"><?= dinero((float) $totalesGrupos['sum_monto_contrato']) ?></td>
                <td class="text-end"><?= dinero((float) $totalesGrupos['sum_suma_cuotas']) ?></td>
                <td class="text-end"><?= dinero((float) $totalesGrupos['sum_pagado']) ?></td>
                <td class="text-end"><?= dinero((float) $totalesGrupos['sum_saldo']) ?></td>
                <td class="text-end"><?= dinero((float) $totalesGrupos['sum_vencido']) ?></td>
                <td class="text-end"><?= dinero((float) $totalesGrupos['sum_por_vencer']) ?></td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php else: ?>
<div class="table-responsive card p-0">
    <table class="table table-hover table-sm align-middle mb-0 no-datatable">
        <thead class="table-light">
            <tr>
                <th>Tipo</th>
                <th>Unidad</th>
                <th>Ocupación</th>
                <th>Contrato</th>
                <th>Cliente</th>
                <th class="text-end">Monto contrato</th>
                <th class="text-end">Suma cuotas</th>
                <th>Fin contrato</th>
                <th>Próx. venc. cuota</th>
                <th class="text-end">Pagado (cuotas)</th>
                <th class="text-end">Falta por pagar</th>
                <th class="text-end">Vencido</th>
                <th class="text-end">Pend. no venc.</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($filas)): ?>
            <tr><td colspan="13" class="text-muted p-3">Sin resultados con los filtros actuales.</td></tr>
        <?php else: ?>
            <?php foreach ($filas as $f): ?>
            <tr>
                <td><?= e($f['tipo']) ?></td>
                <td><?= e($f['unidad']) ?></td>
                <td>
                    <?php if ($f['ocupacion'] === 'Libre'): ?>
                        <span class="badge bg-secondary">Libre</span>
                    <?php else: ?>
                        <span class="badge bg-primary">Ocupado</span>
                    <?php endif; ?>
                </td>
                <td><?= (int) $f['contrato_id'] > 0 ? (int) $f['contrato_id'] : '—' ?></td>
                <td><?= $f['ocupacion'] === 'Libre' || trim((string) $f['cliente']) === '' ? '—' : e((string) $f['cliente']) ?></td>
                <td class="text-end"><?= $f['monto_contrato'] !== null ? dinero((float) $f['monto_contrato']) : '—' ?></td>
                <td class="text-end"><?= $f['suma_cuotas'] !== null ? dinero((float) $f['suma_cuotas']) : '—' ?></td>
                <td><?= $f['fin_contrato'] !== '' ? e(fechaFormato($f['fin_contrato'])) : '—' ?></td>
                <td><?= $f['prox_venc'] !== '' ? e(fechaFormato($f['prox_venc'])) : '—' ?></td>
                <td class="text-end"><?= $f['ocupacion'] === 'Ocupado' ? dinero((float) $f['pagado']) : '—' ?></td>
                <td class="text-end"><?= $f['ocupacion'] === 'Ocupado' ? dinero((float) $f['saldo']) : '—' ?></td>
                <td class="text-end"><?= $f['ocupacion'] === 'Ocupado' ? dinero((float) $f['vencido']) : '—' ?></td>
                <td class="text-end"><?= $f['ocupacion'] === 'Ocupado' ? dinero((float) $f['por_vencer']) : '—' ?></td>
            </tr>
            <?php endforeach; ?>
            <tr class="table-light fw-bold">
                <td>Total general</td>
                <td>Unidades listadas</td>
                <td>Ocup.: <?= (int) $cntOcup ?> / Libres: <?= (int) $cntLibre ?></td>
                <td>—</td>
                <td>—</td>
                <td class="text-end"><?= dinero((float) $totalesDetalle['sum_monto_contrato']) ?></td>
                <td class="text-end"><?= dinero((float) $totalesDetalle['sum_suma_cuotas']) ?></td>
                <td>—</td>
                <td>—</td>
                <td class="text-end"><?= dinero((float) $totalesDetalle['sum_pagado']) ?></td>
                <td class="text-end"><?= dinero((float) $totalesDetalle['sum_saldo']) ?></td>
                <td class="text-end"><?= dinero((float) $totalesDetalle['sum_vencido']) ?></td>
                <td class="text-end"><?= dinero((float) $totalesDetalle['sum_por_vencer']) ?></td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
