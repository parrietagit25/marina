<?php
/**
 * Estado de cuenta de un contrato: cuotas, movimientos y electricidad.
 */
declare(strict_types=1);

/**
 * @return array<string, mixed>|null
 */
function marina_contrato_estado_cuenta_datos(PDO $pdo, int $contratoId): ?array
{
    if ($contratoId < 1) {
        return null;
    }

    $st = $pdo->prepare("
        SELECT co.id, co.fecha_inicio, co.fecha_fin, co.monto_total, co.observaciones, co.numero_recibo,
               COALESCE(co.estado, 'activo') AS estado,
               cl.nombre AS cliente_nombre,
               cl.dueno_capitan,
               CONCAT(b.nombre, ' - ', cta.nombre) AS cuenta_nombre,
               m.nombre AS muelle_nombre,
               s.nombre AS slip_nombre,
               g.nombre AS grupo_nombre,
               i.nombre AS inmueble_nombre
        FROM contratos co
        JOIN clientes cl ON cl.id = co.cliente_id
        JOIN cuentas cta ON cta.id = co.cuenta_id
        JOIN bancos b ON b.id = cta.banco_id
        LEFT JOIN muelles m ON m.id = co.muelle_id
        LEFT JOIN slips s ON s.id = co.slip_id
        LEFT JOIN grupos g ON g.id = co.grupo_id
        LEFT JOIN inmuebles i ON i.id = co.inmueble_id
        WHERE co.id = ?
        LIMIT 1
    ");
    $st->execute([$contratoId]);
    $co = $st->fetch(PDO::FETCH_ASSOC);
    if (!$co) {
        return null;
    }

    $unidad = '—';
    if (!empty($co['grupo_nombre']) || !empty($co['inmueble_nombre'])) {
        $unidad = trim(($co['grupo_nombre'] ?? '') . ' / ' . ($co['inmueble_nombre'] ?? ''), ' /');
    } elseif (!empty($co['muelle_nombre']) || !empty($co['slip_nombre'])) {
        $unidad = trim(($co['muelle_nombre'] ?? '') . ' / ' . ($co['slip_nombre'] ?? ''), ' /');
    }

    $formasPago = $pdo->query("SELECT id, nombre FROM formas_pago WHERE tipo_movimiento = 'ingreso'")->fetchAll(PDO::FETCH_KEY_PAIR);

    $stCuotas = $pdo->prepare('
        SELECT id, numero_cuota, monto, fecha_vencimiento, fecha_pago, forma_pago_id, referencia
        FROM cuotas
        WHERE contrato_id = ?
        ORDER BY numero_cuota
    ');
    $stCuotas->execute([$contratoId]);
    $cuotasRaw = $stCuotas->fetchAll(PDO::FETCH_ASSOC);

    $movsByCuota = [];
    if ($cuotasRaw !== []) {
        $cuotaIds = array_map(static fn($c) => (int) $c['id'], $cuotasRaw);
        $ph = implode(',', array_fill(0, count($cuotaIds), '?'));
        $stMovs = $pdo->prepare("
            SELECT mo.cuota_id, mo.tipo, mo.monto, mo.fecha_pago, mo.referencia, mo.concepto,
                   fp.nombre AS forma_pago_nombre
            FROM cuotas_movimientos mo
            LEFT JOIN formas_pago fp ON fp.id = mo.forma_pago_id
            WHERE mo.cuota_id IN ($ph)
            ORDER BY mo.fecha_pago ASC, mo.id ASC
        ");
        $stMovs->execute($cuotaIds);
        while ($m = $stMovs->fetch(PDO::FETCH_ASSOC)) {
            $cid = (int) $m['cuota_id'];
            if (!isset($movsByCuota[$cid])) {
                $movsByCuota[$cid] = [];
            }
            $movsByCuota[$cid][] = [
                'tipo' => (string) ($m['tipo'] ?? ''),
                'monto' => round((float) ($m['monto'] ?? 0), 2),
                'fecha_pago' => (string) ($m['fecha_pago'] ?? ''),
                'forma_pago' => (string) ($m['forma_pago_nombre'] ?? '—'),
                'referencia' => (string) ($m['referencia'] ?? ''),
                'concepto' => (string) ($m['concepto'] ?? ''),
            ];
        }
    }

    $cuotas = [];
    $sumCuotas = 0.0;
    $sumPagadoCuotas = 0.0;

    foreach ($cuotasRaw as $c) {
        $cuotaId = (int) $c['id'];
        $monto = round((float) ($c['monto'] ?? 0), 2);
        $sumCuotas += $monto;

        $pagado = 0.0;
        $movimientos = $movsByCuota[$cuotaId] ?? [];
        if ($movimientos !== []) {
            foreach ($movimientos as $mv) {
                $pagado += (float) $mv['monto'];
            }
        } elseif (!empty($c['fecha_pago'])) {
            $pagado = $monto;
            $movimientos = [[
                'tipo' => 'pago',
                'monto' => $monto,
                'fecha_pago' => (string) $c['fecha_pago'],
                'forma_pago' => (string) ($formasPago[(int) ($c['forma_pago_id'] ?? 0)] ?? '—'),
                'referencia' => (string) ($c['referencia'] ?? ''),
                'concepto' => '',
            ]];
        }
        $pagado = round($pagado, 2);
        $saldo = max(0.0, round($monto - $pagado, 2));
        $sumPagadoCuotas += $pagado;

        $estado = $saldo <= 0.00001 ? 'Pagada' : ($pagado > 0.00001 ? 'Parcial' : 'Pendiente');

        $cuotas[] = [
            'id' => $cuotaId,
            'numero' => (int) $c['numero_cuota'],
            'monto' => $monto,
            'vencimiento' => (string) ($c['fecha_vencimiento'] ?? ''),
            'pagado' => $pagado,
            'saldo' => $saldo,
            'estado' => $estado,
            'movimientos' => $movimientos,
        ];
    }

    $electricidad = [];
    $sumFacturadoEle = 0.0;
    $sumPagadoEle = 0.0;

    try {
        $stF = $pdo->prepare('
            SELECT f.id, f.monto_total, f.fecha_factura, f.numero_factura, f.periodo_desde, f.periodo_hasta, f.observaciones,
                   (SELECT COALESCE(SUM(ep.monto), 0) FROM contrato_electricidad_pagos ep WHERE ep.factura_id = f.id) AS total_pagado
            FROM contrato_electricidad_facturas f
            WHERE f.contrato_id = ?
            ORDER BY f.fecha_factura DESC, f.id DESC
        ');
        $stF->execute([$contratoId]);
        $facturas = $stF->fetchAll(PDO::FETCH_ASSOC);

        $idsFacturas = [];
        foreach ($facturas as $f) {
            $fid = (int) ($f['id'] ?? 0);
            if ($fid > 0) {
                $idsFacturas[] = $fid;
            }
        }

        $pagosPorFactura = [];
        if ($idsFacturas !== []) {
            $phF = implode(',', array_fill(0, count($idsFacturas), '?'));
            $stP = $pdo->prepare("
                SELECT ep.factura_id, ep.monto, ep.fecha_pago, ep.referencia, ep.observaciones,
                       CONCAT_WS(' - ', b.nombre, c.nombre) AS cuenta_nombre,
                       fp.nombre AS forma_pago_nombre
                FROM contrato_electricidad_pagos ep
                LEFT JOIN cuentas c ON c.id = ep.cuenta_id
                LEFT JOIN bancos b ON b.id = c.banco_id
                LEFT JOIN formas_pago fp ON fp.id = ep.forma_pago_id
                WHERE ep.factura_id IN ($phF)
                ORDER BY ep.fecha_pago ASC, ep.id ASC
            ");
            $stP->execute($idsFacturas);
            while ($p = $stP->fetch(PDO::FETCH_ASSOC)) {
                $fid = (int) ($p['factura_id'] ?? 0);
                if (!isset($pagosPorFactura[$fid])) {
                    $pagosPorFactura[$fid] = [];
                }
                $pagosPorFactura[$fid][] = [
                    'monto' => round((float) ($p['monto'] ?? 0), 2),
                    'fecha_pago' => (string) ($p['fecha_pago'] ?? ''),
                    'cuenta' => trim((string) ($p['cuenta_nombre'] ?? '')) !== '' ? trim((string) $p['cuenta_nombre']) : '—',
                    'forma_pago' => trim((string) ($p['forma_pago_nombre'] ?? '')) !== '' ? trim((string) $p['forma_pago_nombre']) : '—',
                    'referencia' => (string) ($p['referencia'] ?? ''),
                    'observaciones' => (string) ($p['observaciones'] ?? ''),
                ];
            }
        }

        foreach ($facturas as $f) {
            $fid = (int) ($f['id'] ?? 0);
            $mt = round((float) ($f['monto_total'] ?? 0), 2);
            $tp = round((float) ($f['total_pagado'] ?? 0), 2);
            $saldoF = max(0.0, round($mt - $tp, 2));
            $sumFacturadoEle += $mt;
            $sumPagadoEle += $tp;

            $periodo = '';
            if (!empty($f['periodo_desde']) || !empty($f['periodo_hasta'])) {
                $periodo = trim(fechaFormato($f['periodo_desde'] ?? '') . ' – ' . fechaFormato($f['periodo_hasta'] ?? ''), ' –');
            }

            $electricidad[] = [
                'id' => $fid,
                'numero_factura' => (string) ($f['numero_factura'] ?? ''),
                'fecha_factura' => (string) ($f['fecha_factura'] ?? ''),
                'periodo' => $periodo,
                'monto_total' => $mt,
                'pagado' => $tp,
                'saldo' => $saldoF,
                'estado' => $saldoF > 0.01 ? 'Pendiente' : 'Pagada',
                'observaciones' => (string) ($f['observaciones'] ?? ''),
                'pagos' => $pagosPorFactura[$fid] ?? [],
            ];
        }
    } catch (Throwable $e) {
        $electricidad = [];
    }

    return [
        'contrato' => [
            'id' => (int) $co['id'],
            'cliente' => (string) ($co['cliente_nombre'] ?? ''),
            'dueno_capitan' => (string) ($co['dueno_capitan'] ?? ''),
            'unidad' => $unidad,
            'cuenta' => (string) ($co['cuenta_nombre'] ?? ''),
            'fecha_inicio' => (string) ($co['fecha_inicio'] ?? ''),
            'fecha_fin' => (string) ($co['fecha_fin'] ?? ''),
            'monto_total' => round((float) ($co['monto_total'] ?? 0), 2),
            'estado' => (string) ($co['estado'] ?? 'activo'),
            'numero_recibo' => (string) ($co['numero_recibo'] ?? ''),
            'observaciones' => (string) ($co['observaciones'] ?? ''),
        ],
        'resumen_cuotas' => [
            'total_cuotas' => round($sumCuotas, 2),
            'total_pagado' => round($sumPagadoCuotas, 2),
            'saldo' => max(0.0, round($sumCuotas - $sumPagadoCuotas, 2)),
            'monto_contrato' => round((float) ($co['monto_total'] ?? 0), 2),
        ],
        'cuotas' => $cuotas,
        'resumen_electricidad' => [
            'total_facturado' => round($sumFacturadoEle, 2),
            'total_pagado' => round($sumPagadoEle, 2),
            'saldo' => max(0.0, round($sumFacturadoEle - $sumPagadoEle, 2)),
        ],
        'electricidad' => $electricidad,
    ];
}

function marina_html_btn_estado_cuenta_contrato(int $contratoId): string
{
    return '<button type="button" class="btn btn-outline-dark btn-sm btn-estado-cuenta-contrato" data-contrato-id="' . (int) $contratoId . '">E. Cuentas</button>';
}
