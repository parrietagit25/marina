<?php
/**
 * Reporte de cobranzas: slips e inmuebles con contrato activo; montos de cuotas por vencimiento en el rango.
 */
$titulo = 'Reporte de cobranzas';
$pdo = getDb();
require_once __DIR__ . '/../includes/export_excel.php';

$desde = trim((string) obtener('desde', date('Y-m-01')));
$hasta = trim((string) obtener('hasta', date('Y-m-d')));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) {
    $desde = date('Y-m-01');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
    $hasta = date('Y-m-d');
}
if ($desde > $hasta) {
    [$desde, $hasta] = [$hasta, $desde];
}

$filtro = trim(obtener('ocupacion', ''));
$filtro = in_array($filtro, ['todos', 'ocupado', 'libre'], true) ? $filtro : 'todos';
$tipoAlquiler = trim(obtener('tipo_alquiler', ''));
if ($tipoAlquiler === 'grupos') {
    $tipoAlquiler = 'inmuebles';
}
$tipoAlquiler = in_array($tipoAlquiler, ['', 'marina', 'inmuebles'], true) ? $tipoAlquiler : '';
$tipoFiltro = '';
if ($tipoAlquiler === 'marina') {
    $tipoFiltro = 'slip';
} elseif ($tipoAlquiler === 'inmuebles') {
    $tipoFiltro = 'inmueble';
}
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
 * @return array{pagado: float, saldo: float, vencido: float, por_vencer: float, prox_venc: string, fin_contr: string}
 */
function marina_reporte_ocupacion_metricas_cuotas(array $cuotas, string $hoy, string $fechaFinContr, string $desde, string $hasta): array
{
    $sumPagado = 0.0;
    $sumSaldo = 0.0;
    $sumVenc = 0.0;
    $sumPorVencer = 0.0;
    $candidatosProx = [];
    foreach ($cuotas as $c) {
        $fv = (string) ($c['fecha_vencimiento'] ?? '');
        if ($fv === '' || $fv < $desde || $fv > $hasta) {
            continue;
        }
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
        $sumPagado += min($monto, $pagado);
        $sumSaldo += $saldo;
        if ($saldo > 0.00001) {
            if ($fv < $hoy) {
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
    string $hoy,
    string $desde,
    string $hasta
): array {
    $idSlip = (int) $s['id'];
    $contr = $bySlip[$idSlip] ?? null;
    $ocupado = $contr !== null;
    $label = trim(($s['muelle_nombre'] ?? '') . ' — ' . ($s['slip_nombre'] ?? ''));
    if (!$ocupado) {
        return [
            'tipo' => 'Marina',
            'unidad' => $label !== '' ? $label : '—',
            'ocupacion' => 'Libre',
            'cliente' => '',
            'monto_contrato' => null,
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
        (string) ($contr['fecha_fin'] ?? ''),
        $desde,
        $hasta
    );
    return [
        'tipo' => 'Marina',
        'unidad' => $label,
        'ocupacion' => 'Ocupado',
        'cliente' => (string) ($contr['cliente_nombre'] ?? ''),
        'monto_contrato' => (float) ($contr['monto_total'] ?? 0),
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
    string $hoy,
    string $desde,
    string $hasta
): array {
    $iidU = (int) $i['id'];
    $contr = $byInm[$iidU] ?? null;
    $ocupado = $contr !== null;
    $label = trim(($i['grupo_nombre'] ?? '') . ' — ' . ($i['inmueble_nombre'] ?? ''));
    if (!$ocupado) {
        return [
            'tipo' => 'Inmuebles',
            'unidad' => $label !== '' ? $label : '—',
            'ocupacion' => 'Libre',
            'cliente' => '',
            'monto_contrato' => null,
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
        (string) ($contr['fecha_fin'] ?? ''),
        $desde,
        $hasta
    );
    return [
        'tipo' => 'Inmuebles',
        'unidad' => $label,
        'ocupacion' => 'Ocupado',
        'cliente' => (string) ($contr['cliente_nombre'] ?? ''),
        'monto_contrato' => (float) ($contr['monto_total'] ?? 0),
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
        $a['sum_pagado'] += (float) ($row['pagado'] ?? 0);
        $a['sum_saldo'] += (float) ($row['saldo'] ?? 0);
        $a['sum_vencido'] += (float) ($row['vencido'] ?? 0);
        $a['sum_por_vencer'] += (float) ($row['por_vencer'] ?? 0);
    }
}

/**
 * @param array<string, mixed> $row
 */
function marina_cobranzas_clave_bloque(array $row): string
{
    if (($row['tipo'] ?? '') === 'Marina') {
        $mid = (int) ($row['muelle_id'] ?? 0);

        return 'marina|' . $mid . '|' . strtolower(trim((string) ($row['muelle_nombre'] ?? '')));
    }
    $gid = (int) ($row['grupo_id'] ?? 0);

    return 'inmuebles|' . $gid . '|' . strtolower(trim((string) ($row['grupo_nombre'] ?? '')));
}

/**
 * @param array<string, mixed> $row
 */
function marina_cobranzas_nombre_bloque(array $row): string
{
    if (($row['tipo'] ?? '') === 'Marina') {
        $nom = trim((string) ($row['muelle_nombre'] ?? ''));

        return $nom !== '' ? $nom : 'Marina';
    }
    $nom = trim((string) ($row['grupo_nombre'] ?? ''));

    return $nom !== '' ? $nom : 'Alquiler';
}

/**
 * @param list<array<string, mixed>> $filasBloque
 * @return array{n: int, n_ocup: int, n_libre: int, sum_monto_contrato: float, sum_pagado: float, sum_saldo: float, sum_vencido: float, sum_por_vencer: float}
 */
function marina_cobranzas_totales_bloque(array $filasBloque): array
{
    $t = [
        'n' => 0,
        'n_ocup' => 0,
        'n_libre' => 0,
        'sum_monto_contrato' => 0.0,
        'sum_pagado' => 0.0,
        'sum_saldo' => 0.0,
        'sum_vencido' => 0.0,
        'sum_por_vencer' => 0.0,
    ];
    foreach ($filasBloque as $r) {
        $t['n']++;
        if (($r['ocupacion'] ?? '') === 'Ocupado') {
            $t['n_ocup']++;
            $t['sum_monto_contrato'] += (float) ($r['monto_contrato'] ?? 0);
            $t['sum_pagado'] += (float) ($r['pagado'] ?? 0);
            $t['sum_saldo'] += (float) ($r['saldo'] ?? 0);
            $t['sum_vencido'] += (float) ($r['vencido'] ?? 0);
            $t['sum_por_vencer'] += (float) ($r['por_vencer'] ?? 0);
        } else {
            $t['n_libre']++;
        }
    }

    return $t;
}

/**
 * Ordena por bloque (muelle / grupo) e inserta subtotales y separadores.
 *
 * @param list<array<string, mixed>> $filas
 * @return list<array<string, mixed>>
 */
function marina_cobranzas_filas_detalle_con_subtotales(array $filas): array
{
    if ($filas === []) {
        return [];
    }

    usort($filas, static function (array $a, array $b): int {
        $ta = ($a['tipo'] ?? '') === 'Marina' ? 0 : 1;
        $tb = ($b['tipo'] ?? '') === 'Marina' ? 0 : 1;
        if ($ta !== $tb) {
            return $ta <=> $tb;
        }
        $na = $ta === 0 ? (string) ($a['muelle_nombre'] ?? '') : (string) ($a['grupo_nombre'] ?? '');
        $nb = $tb === 0 ? (string) ($b['muelle_nombre'] ?? '') : (string) ($b['grupo_nombre'] ?? '');
        $cmp = strnatcasecmp($na, $nb);
        if ($cmp !== 0) {
            return $cmp;
        }

        return strnatcasecmp((string) ($a['unidad'] ?? ''), (string) ($b['unidad'] ?? ''));
    });

    $out = [];
    $bloqueActual = null;
    $buffer = [];

    $cerrarBloque = static function () use (&$out, &$buffer): void {
        if ($buffer === []) {
            return;
        }
        $nombreBloque = marina_cobranzas_nombre_bloque($buffer[0]);
        $tipoBloque = (string) ($buffer[0]['tipo'] ?? '');
        foreach ($buffer as $row) {
            $out[] = ['tipo_fila' => 'dato', 'dato' => $row];
        }
        $tot = marina_cobranzas_totales_bloque($buffer);
        $out[] = [
            'tipo_fila' => 'subtotal',
            'bloque_nombre' => $nombreBloque,
            'bloque_tipo' => $tipoBloque,
            'totales' => $tot,
        ];
        $out[] = ['tipo_fila' => 'separador'];
        $buffer = [];
    };

    foreach ($filas as $row) {
        $clave = marina_cobranzas_clave_bloque($row);
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

$vista = trim(obtener('vista', 'detalle'));
$vista = in_array($vista, ['detalle', 'grupos'], true) ? $vista : 'detalle';
if ($tipoAlquiler === 'inmuebles' || $tipoAlquiler === 'marina') {
    $vista = 'detalle';
}

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
    $row = marina_ocupacion_fila_slip($s, $bySlip, $cuotasPorContrato, $hoy, $desde, $hasta);
    if ($vista === 'grupos') {
        $mid = (int) $row['muelle_id'];
        if ($mid > 0) {
            marina_ocupacion_acumular_en_grupo($acumMuelle, $mid, (string) $row['muelle_nombre'], $row, 'Marina');
        }
    } else {
        if (!marina_ocupacion_pasa_filtro_ocupacion($row, $filtro)) {
            continue;
        }
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
    $row = marina_ocupacion_fila_inmueble($i, $byInm, $cuotasPorContrato, $hoy, $desde, $hasta);
    if ($vista === 'grupos') {
        $gid = (int) $row['grupo_id'];
        if ($gid > 0) {
            marina_ocupacion_acumular_en_grupo($acumGrupo, $gid, (string) $row['grupo_nombre'], $row, 'Alquileres');
        }
    } else {
        if (!marina_ocupacion_pasa_filtro_ocupacion($row, $filtro)) {
            continue;
        }
        $filas[] = $row;
    }
}

$filasDetalleRender = ($vista === 'detalle') ? marina_cobranzas_filas_detalle_con_subtotales($filas) : [];

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
    'sum_pagado' => 0.0,
    'sum_saldo' => 0.0,
    'sum_vencido' => 0.0,
    'sum_por_vencer' => 0.0,
];
if ($vista === 'detalle') {
    foreach ($filas as $f) {
        $totalesDetalle['sum_monto_contrato'] += (float) ($f['monto_contrato'] ?? 0);
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
                (float) $totalesGrupos['sum_pagado'],
                (float) $totalesGrupos['sum_saldo'],
                (float) $totalesGrupos['sum_vencido'],
                (float) $totalesGrupos['sum_por_vencer'],
            ],
        ];
        exportarExcel('reporte_cobranzas_grupos', [
            'Ámbito',
            'Alquiler / muelle',
            'Unidades',
            'Libres',
            'Ocupados',
            'Monto contrato ∑',
            'Pagado ∑',
            'Falta por pagar ∑',
            'Vencido ∑',
            'Pend. no venc. ∑',
        ], $rowsX, $pie, $titulo . ' — Totales por alquiler');
    } else {
        $rowsX = [];
        foreach ($filasDetalleRender as $item) {
            $tipoFila = (string) ($item['tipo_fila'] ?? 'dato');
            if ($tipoFila === 'separador') {
                continue;
            }
            if ($tipoFila === 'subtotal') {
                $tot = $item['totales'] ?? [];
                $rowsX[] = [
                    marinaExcelValorTexto((string) ($item['bloque_tipo'] ?? '')),
                    marinaExcelValorTexto('Total — ' . (string) ($item['bloque_nombre'] ?? '')),
                    marinaExcelValorTexto((int) ($tot['n_ocup'] ?? 0) . ' ocup. / ' . (int) ($tot['n_libre'] ?? 0) . ' libres'),
                    '',
                    (float) ($tot['sum_monto_contrato'] ?? 0),
                    '',
                    '',
                    (float) ($tot['sum_pagado'] ?? 0),
                    (float) ($tot['sum_saldo'] ?? 0),
                    (float) ($tot['sum_vencido'] ?? 0),
                    (float) ($tot['sum_por_vencer'] ?? 0),
                ];
                continue;
            }
            $f = $item['dato'] ?? [];
            $rowsX[] = [
                marinaExcelValorTexto((string) ($f['tipo'] ?? '')),
                marinaExcelValorTexto((string) ($f['unidad'] ?? '')),
                marinaExcelValorTexto((string) ($f['ocupacion'] ?? '')),
                marinaExcelValorTexto((string) ($f['cliente'] ?? '')),
                $f['monto_contrato'] !== null ? (float) $f['monto_contrato'] : '',
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
                (float) $totalesDetalle['sum_monto_contrato'],
                '',
                '',
                (float) $totalesDetalle['sum_pagado'],
                (float) $totalesDetalle['sum_saldo'],
                (float) $totalesDetalle['sum_vencido'],
                (float) $totalesDetalle['sum_por_vencer'],
            ],
        ];
        exportarExcel('reporte_cobranzas', [
            'Tipo',
            'Unidad',
            'Ocupación',
            'Cliente',
            'Monto contrato',
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
<h1 class="h4 mb-2">Reporte de cobranzas</h1>
<p class="text-muted small mb-3">Cobranzas por <strong>cuotas con vencimiento</strong> entre <strong><?= e(fechaFormato($desde)) ?></strong> y <strong><?= e(fechaFormato($hasta)) ?></strong>. Incluye slips (<em>Marina</em>) e inmuebles (<em>Alquileres</em>). <strong>Libre</strong> = sin contrato activo; <strong>Ocupado</strong> = contrato activo. Los montos usan abonos/pagos y pago legado, igual que el <a href="<?= MARINA_URL ?>/index.php?p=reporte-cuotas">reporte de cuotas</a>. <strong>Falta por pagar</strong> es el saldo pendiente de esas cuotas; <strong>Vencido</strong> vence antes de hoy; <strong>Pend. no vencido</strong> vence hoy o después.</p>
<?php if ($vista === 'grupos'): ?>
    <p class="small alert alert-info py-2 mb-3">Vista <strong>Totales por alquiler</strong>: suma por <strong>cada muelle</strong> (slips) y por <strong>cada alquiler de inmuebles</strong>. El filtro de ocupación (libre/ocupado) <strong>no aplica</strong> en esta vista; sí aplican fechas, muelle, alquiler y tipo de alquiler.</p>
<?php endif; ?>

<form method="get" class="toolbar mb-3">
    <input type="hidden" name="p" value="reporte-ocupacion">
    <div class="row g-2 align-items-end">
        <div class="col-12 col-md-6 col-lg-2">
            <label class="form-label mb-1">Vencimiento desde</label>
            <input type="date" class="form-control" name="desde" value="<?= e($desde) ?>">
        </div>
        <div class="col-12 col-md-6 col-lg-2">
            <label class="form-label mb-1">Vencimiento hasta</label>
            <input type="date" class="form-control" name="hasta" value="<?= e($hasta) ?>">
        </div>
        <div class="col-12 col-md-6 col-lg-2">
            <label class="form-label mb-1">Tipo de alquiler</label>
            <select class="form-select" name="tipo_alquiler">
                <option value="" <?= $tipoAlquiler === '' ? 'selected' : '' ?>>Todos</option>
                <option value="marina" <?= $tipoAlquiler === 'marina' ? 'selected' : '' ?>>Marina</option>
                <option value="inmuebles" <?= $tipoAlquiler === 'inmuebles' ? 'selected' : '' ?>>Inmuebles</option>
            </select>
        </div>
        <div class="col-12 col-md-6 col-lg-2">
            <label class="form-label mb-1">Vista</label>
            <select class="form-select" name="vista" id="ocup-vista" <?= in_array($tipoAlquiler, ['marina', 'inmuebles'], true) ? 'disabled' : '' ?>>
                <option value="detalle" <?= $vista === 'detalle' ? 'selected' : '' ?>>Unidad a unidad (detalle)</option>
                <option value="grupos" <?= $vista === 'grupos' ? 'selected' : '' ?>>Totales por alquiler (muelle / alquiler)</option>
            </select>
            <?php if (in_array($tipoAlquiler, ['marina', 'inmuebles'], true)): ?>
                <input type="hidden" name="vista" value="<?= e($vista) ?>">
            <?php endif; ?>
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
            <label class="form-label mb-1">Muelle (marina)</label>
            <select class="form-select" name="muelle_id">
                <option value="0">Todos</option>
                <?php foreach ($muellesOpts as $mid => $mnom): ?>
                    <option value="<?= (int) $mid ?>" <?= $muelle_id === (int) $mid ? 'selected' : '' ?>><?= e($mnom) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-6 col-lg-2">
            <label class="form-label mb-1">Alquiler (inmuebles)</label>
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
    <p class="text-muted small mb-0 mt-2"><strong>Marina</strong> solo slips; <strong>Inmuebles</strong> solo unidades de alquiler. Con <strong>Todos</strong> puede elegir vista detalle o agrupada.</p>
</form>

<div class="card p-3 mb-3">
    <div class="row g-2 small">
        <?php if ($vista === 'detalle'): ?>
            <div class="col-md-6"><strong>Filas listadas:</strong> <?= (int) count($filas) ?> (Ocupados: <strong><?= (int) $cntOcup ?></strong> — Libres: <strong><?= (int) $cntLibre ?></strong>)</div>
        <?php else: ?>
            <div class="col-md-8">
                <strong>Alquileres en el listado:</strong> <?= (int) count($filasGrupos) ?>
                (Muelles: <strong><?= (int) count($acumMuelle) ?></strong> — Alquileres inmueble: <strong><?= (int) count($acumGrupo) ?></strong>).
                Unidades totales en suma: ocupados <strong><?= (int) $cntOcup ?></strong>, libres <strong><?= (int) $cntLibre ?></strong>.
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($vista === 'grupos'): ?>
<div class="table-responsive card p-0 mb-2 reporte-cobranzas-table-wrap">
    <table class="table table-hover table-sm align-middle mb-0 no-datatable no-excel-export reporte-cobranzas-tabla">
        <thead class="table-light">
            <tr>
                <th>Ámbito</th>
                <th>Alquiler / muelle</th>
                <th class="text-end">Unid.</th>
                <th class="text-end">Libres</th>
                <th class="text-end">Ocup.</th>
                <th class="text-end">Monto contrato ∑</th>
                <th class="text-end">Pagado ∑</th>
                <th class="text-end">Falta por pagar ∑</th>
                <th class="text-end">Vencido ∑</th>
                <th class="text-end">Pend. no venc. ∑</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($filasGrupos)): ?>
            <tr><td colspan="10" class="text-muted p-3">Sin alquileres con los filtros actuales.</td></tr>
        <?php else: ?>
            <?php foreach ($filasGrupos as $f): ?>
            <tr>
                <td class="text-muted small"><?= e((string) $f['ambito']) ?></td>
                <td class="fw-medium"><?= e((string) $f['grupo_nombre']) ?></td>
                <td class="text-end"><?= (int) $f['n_unidades'] ?></td>
                <td class="text-end"><?= (int) $f['n_libres'] ?></td>
                <td class="text-end"><?= (int) $f['n_ocupados'] ?></td>
                <td class="text-end"><?= dinero((float) $f['sum_monto_contrato']) ?></td>
                <td class="text-end"><?= dinero((float) $f['sum_pagado']) ?></td>
                <td class="text-end"><?= dinero((float) $f['sum_saldo']) ?></td>
                <td class="text-end"><?= dinero((float) $f['sum_vencido']) ?></td>
                <td class="text-end"><?= dinero((float) $f['sum_por_vencer']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot class="reporte-ocupacion-tfoot">
            <tr class="table-secondary">
                <th scope="col">Ámbito</th>
                <th scope="col">Alquiler / muelle</th>
                <th scope="col" class="text-end">Unid.</th>
                <th scope="col" class="text-end">Libres</th>
                <th scope="col" class="text-end">Ocup.</th>
                <th scope="col" class="text-end">Monto contrato ∑</th>
                <th scope="col" class="text-end">Pagado ∑</th>
                <th scope="col" class="text-end">Falta por pagar ∑</th>
                <th scope="col" class="text-end">Vencido ∑</th>
                <th scope="col" class="text-end">Pend. no venc. ∑</th>
            </tr>
            <tr class="table-light fw-bold">
                <td>Total general</td>
                <td>Todos los alquileres</td>
                <td class="text-end"><?= (int) $totalesGrupos['n_unidades'] ?></td>
                <td class="text-end"><?= (int) $totalesGrupos['n_libres'] ?></td>
                <td class="text-end"><?= (int) $totalesGrupos['n_ocupados'] ?></td>
                <td class="text-end"><?= dinero((float) $totalesGrupos['sum_monto_contrato']) ?></td>
                <td class="text-end"><?= dinero((float) $totalesGrupos['sum_pagado']) ?></td>
                <td class="text-end"><?= dinero((float) $totalesGrupos['sum_saldo']) ?></td>
                <td class="text-end"><?= dinero((float) $totalesGrupos['sum_vencido']) ?></td>
                <td class="text-end"><?= dinero((float) $totalesGrupos['sum_por_vencer']) ?></td>
            </tr>
        </tfoot>
        <?php endif; ?>
    </table>
</div>
<?php else: ?>
<div class="table-responsive card p-0 reporte-cobranzas-table-wrap">
    <table class="table table-hover table-sm align-middle mb-0 no-datatable no-excel-export reporte-cobranzas-tabla">
        <thead class="table-light">
            <tr>
                <th>Tipo</th>
                <th>Unidad</th>
                <th>Ocupación</th>
                <th>Cliente</th>
                <th class="text-end">Monto contrato</th>
                <th>Fin contrato</th>
                <th>Próx. venc. cuota</th>
                <th class="text-end">Pagado (cuotas)</th>
                <th class="text-end">Falta por pagar</th>
                <th class="text-end">Vencido</th>
                <th class="text-end">Pend. no venc.</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($filasDetalleRender)): ?>
            <tr><td colspan="11" class="text-muted p-3">Sin resultados con los filtros actuales.</td></tr>
        <?php else: ?>
            <?php foreach ($filasDetalleRender as $item):
                $tipoFila = (string) ($item['tipo_fila'] ?? 'dato');
                if ($tipoFila === 'separador'): ?>
            <tr class="reporte-cobranzas-separador" aria-hidden="true">
                <td colspan="11"></td>
            </tr>
                <?php continue; endif;
                if ($tipoFila === 'subtotal'):
                    $tot = $item['totales'] ?? [];
                    $etiqBloque = ((string) ($item['bloque_tipo'] ?? '')) === 'Marina' ? 'muelle' : 'alquiler';
                    ?>
            <tr class="reporte-cobranzas-subtotal">
                <td colspan="4">
                    <strong>Total <?= e($etiqBloque) ?> — <?= e((string) ($item['bloque_nombre'] ?? '')) ?></strong>
                    <span class="text-muted small ms-1">(<?= (int) ($tot['n'] ?? 0) ?> unid.; <?= (int) ($tot['n_ocup'] ?? 0) ?> ocup. / <?= (int) ($tot['n_libre'] ?? 0) ?> libres)</span>
                </td>
                <td class="text-end"><?= dinero((float) ($tot['sum_monto_contrato'] ?? 0)) ?></td>
                <td>—</td>
                <td>—</td>
                <td class="text-end"><?= dinero((float) ($tot['sum_pagado'] ?? 0)) ?></td>
                <td class="text-end"><?= dinero((float) ($tot['sum_saldo'] ?? 0)) ?></td>
                <td class="text-end"><?= dinero((float) ($tot['sum_vencido'] ?? 0)) ?></td>
                <td class="text-end"><?= dinero((float) ($tot['sum_por_vencer'] ?? 0)) ?></td>
            </tr>
                <?php continue; endif;
                $f = $item['dato'] ?? [];
                ?>
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
                <td><?= $f['ocupacion'] === 'Libre' || trim((string) $f['cliente']) === '' ? '—' : e((string) $f['cliente']) ?></td>
                <td class="text-end"><?= $f['monto_contrato'] !== null ? dinero((float) $f['monto_contrato']) : '—' ?></td>
                <td><?= $f['fin_contrato'] !== '' ? e(fechaFormato($f['fin_contrato'])) : '—' ?></td>
                <td><?= $f['prox_venc'] !== '' ? e(fechaFormato($f['prox_venc'])) : '—' ?></td>
                <td class="text-end"><?= $f['ocupacion'] === 'Ocupado' ? dinero((float) $f['pagado']) : '—' ?></td>
                <td class="text-end"><?= $f['ocupacion'] === 'Ocupado' ? dinero((float) $f['saldo']) : '—' ?></td>
                <td class="text-end"><?= $f['ocupacion'] === 'Ocupado' ? dinero((float) $f['vencido']) : '—' ?></td>
                <td class="text-end"><?= $f['ocupacion'] === 'Ocupado' ? dinero((float) $f['por_vencer']) : '—' ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot class="reporte-ocupacion-tfoot">
            <tr class="table-secondary">
                <th scope="col">Tipo</th>
                <th scope="col">Unidad</th>
                <th scope="col">Ocupación</th>
                <th scope="col">Cliente</th>
                <th scope="col" class="text-end">Monto contrato</th>
                <th scope="col">Fin contrato</th>
                <th scope="col">Próx. venc. cuota</th>
                <th scope="col" class="text-end">Pagado (cuotas)</th>
                <th scope="col" class="text-end">Falta por pagar</th>
                <th scope="col" class="text-end">Vencido</th>
                <th scope="col" class="text-end">Pend. no venc.</th>
            </tr>
            <tr class="table-light fw-bold">
                <td>Total general</td>
                <td>Unidades listadas</td>
                <td>Ocup.: <?= (int) $cntOcup ?> / Libres: <?= (int) $cntLibre ?></td>
                <td>—</td>
                <td class="text-end"><?= dinero((float) $totalesDetalle['sum_monto_contrato']) ?></td>
                <td>—</td>
                <td>—</td>
                <td class="text-end"><?= dinero((float) $totalesDetalle['sum_pagado']) ?></td>
                <td class="text-end"><?= dinero((float) $totalesDetalle['sum_saldo']) ?></td>
                <td class="text-end"><?= dinero((float) $totalesDetalle['sum_vencido']) ?></td>
                <td class="text-end"><?= dinero((float) $totalesDetalle['sum_por_vencer']) ?></td>
            </tr>
        </tfoot>
        <?php endif; ?>
    </table>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
