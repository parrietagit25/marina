<?php
/**
 * Fragmentos SQL reutilizables para reportes (ingresos por cuotas + fallback legacy).
 */
declare(strict_types=1);

require_once __DIR__ . '/funciones.php';

/**
 * Filtros de contrato (misma lógica que reporte de cuotas), para anexar en SQL con alias `co`.
 * Solo literales enteros en SQL (ids validados).
 */
function reportes_sql_fragment_filtros_contrato_co(
    int $contrato_id,
    string $tipo_unidad,
    int $muelle_id,
    int $grupo_id,
    int $slip_id,
    int $inmueble_id
): string {
    $s = '';
    if ($contrato_id > 0) {
        $s .= ' AND co.id = ' . $contrato_id;
    }
    if ($tipo_unidad === 'slip') {
        $s .= ' AND co.slip_id IS NOT NULL';
    } elseif ($tipo_unidad === 'inmueble') {
        $s .= ' AND co.inmueble_id IS NOT NULL';
    }
    if ($muelle_id > 0) {
        $s .= ' AND co.muelle_id = ' . $muelle_id;
    }
    if ($grupo_id > 0) {
        $s .= ' AND co.grupo_id = ' . $grupo_id;
    }
    if ($slip_id > 0) {
        $s .= ' AND co.slip_id = ' . $slip_id;
    }
    if ($inmueble_id > 0) {
        $s .= ' AND co.inmueble_id = ' . $inmueble_id;
    }

    return $s;
}

/**
 * IDs de cuotas cuyo vencimiento cae en el rango y cumplen estado (pagada / pendiente / vencida / pendiente_sin_vencer).
 * Misma regla que el reporte de cuotas. null = no aplicar filtro por estado.
 *
 * @return list<int>|null
 */
function reportes_ids_cuotas_por_estado_vencimiento(
    PDO $pdo,
    string $desdeVenc,
    string $hastaVenc,
    string $estado,
    int $contrato_id,
    string $tipo_unidad,
    int $muelle_id,
    int $grupo_id,
    int $slip_id,
    int $inmueble_id
): ?array {
    $estado = trim($estado);
    if ($estado === '') {
        return null;
    }
    $frag = reportes_sql_fragment_filtros_contrato_co($contrato_id, $tipo_unidad, $muelle_id, $grupo_id, $slip_id, $inmueble_id);
    $sql = "
        SELECT cu.id AS cuota_id,
               cu.monto,
               cu.fecha_vencimiento,
               cu.fecha_pago AS fecha_pago_legacy,
               COALESCE(mov.pagado_mov, 0) AS pagado_mov
        FROM cuotas cu
        JOIN contratos co ON cu.contrato_id = co.id
        LEFT JOIN (
            SELECT cuota_id, SUM(monto) AS pagado_mov
            FROM cuotas_movimientos
            WHERE tipo IN ('pago','abono')
            GROUP BY cuota_id
        ) mov ON mov.cuota_id = cu.id
        WHERE cu.fecha_vencimiento BETWEEN ? AND ?
        $frag
    ";
    $st = $pdo->prepare($sql);
    $st->execute([$desdeVenc, $hastaVenc]);
    $raw = $st->fetchAll(PDO::FETCH_ASSOC);
    $hoy = date('Y-m-d');
    $ids = [];
    foreach ($raw as $r) {
        $monto = (float) ($r['monto'] ?? 0);
        $pagadoMov = (float) ($r['pagado_mov'] ?? 0);
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
        $fv = (string) ($r['fecha_vencimiento'] ?? '');
        if ($pagada) {
            $estadoFila = 'Pagada';
        } elseif ($fv !== '' && $fv < $hoy) {
            $estadoFila = 'Vencida';
        } else {
            $estadoFila = 'Pendiente';
        }
        $ok = true;
        if ($estado === 'pagada' && !$pagada) {
            $ok = false;
        }
        if ($estado === 'pendiente' && $pagada) {
            $ok = false;
        }
        if ($estado === 'pendiente_sin_vencer' && ($pagada || $estadoFila !== 'Pendiente')) {
            $ok = false;
        }
        if ($estado === 'vencida' && $estadoFila !== 'Vencida') {
            $ok = false;
        }
        if ($ok) {
            $ids[] = (int) ($r['cuota_id'] ?? 0);
        }
    }

    return $ids;
}

/**
 * Devuelve SQL para líneas de ingreso por movimientos de cuota + fallback fecha_pago.
 * Parámetros: [desde, hasta] x2 para el UNION interno, más filtros opcionales al final.
 */
function reportesSqlIngresosDetalle(string $cuentaCond = '', string $extraWhereMov = '', string $extraWhereLegacy = ''): string
{
    $labCred = marina_ui_credito();
    return "
        SELECT mo.fecha_pago AS fecha,
               mo.monto AS monto,
               '{$labCred}' AS tipo_linea,
               COALESCE(NULLIF(TRIM(mo.concepto), ''), 'Pago de cuota') AS concepto,
               CONCAT(b.nombre, ' - ', c.nombre) AS cuenta_nombre,
               co.cuenta_id,
               cl.nombre AS cliente_nombre,
               co.id AS contrato_id,
               cu.id AS cuota_id,
               cu.numero_cuota,
               TRIM(COALESCE(NULLIF(co.numero_recibo, ''), NULLIF(mo.referencia, ''), '')) AS referencia,
               fp.nombre AS forma_pago_nombre,
               COALESCE(NULLIF(gr.nombre, ''), NULLIF(mu.nombre, ''), 'Sin ubicación') AS grupo_nombre,
               TRIM(CONCAT_WS(' — ',
                   NULLIF(TRIM(COALESCE(sl.nombre, '')), ''),
                   NULLIF(TRIM(COALESCE(inm.nombre, '')), '')
               )) AS slip_o_inmueble
        FROM cuotas_movimientos mo
        JOIN cuotas cu ON mo.cuota_id = cu.id
        JOIN contratos co ON cu.contrato_id = co.id
        JOIN clientes cl ON co.cliente_id = cl.id
        JOIN cuentas c ON co.cuenta_id = c.id
        JOIN bancos b ON c.banco_id = b.id
        LEFT JOIN formas_pago fp ON mo.forma_pago_id = fp.id
        LEFT JOIN grupos gr ON co.grupo_id = gr.id
        LEFT JOIN muelles mu ON co.muelle_id = mu.id
        LEFT JOIN slips sl ON co.slip_id = sl.id
        LEFT JOIN inmuebles inm ON co.inmueble_id = inm.id
        WHERE mo.fecha_pago BETWEEN ? AND ?
          AND mo.tipo IN ('pago','abono')
          $cuentaCond
          $extraWhereMov

        UNION ALL

        SELECT cu.fecha_pago AS fecha,
               cu.monto AS monto,
               '{$labCred}' AS tipo_linea,
               'Pago de cuota' AS concepto,
               CONCAT(b.nombre, ' - ', c.nombre) AS cuenta_nombre,
               co.cuenta_id,
               cl.nombre AS cliente_nombre,
               co.id AS contrato_id,
               cu.id AS cuota_id,
               cu.numero_cuota,
               TRIM(COALESCE(NULLIF(co.numero_recibo, ''), NULLIF(cu.referencia, ''), '')) AS referencia,
               fp.nombre AS forma_pago_nombre,
               COALESCE(NULLIF(gr.nombre, ''), NULLIF(mu.nombre, ''), 'Sin ubicación') AS grupo_nombre,
               TRIM(CONCAT_WS(' — ',
                   NULLIF(TRIM(COALESCE(sl.nombre, '')), ''),
                   NULLIF(TRIM(COALESCE(inm.nombre, '')), '')
               )) AS slip_o_inmueble
        FROM cuotas cu
        JOIN contratos co ON cu.contrato_id = co.id
        JOIN clientes cl ON co.cliente_id = cl.id
        JOIN cuentas c ON co.cuenta_id = c.id
        JOIN bancos b ON c.banco_id = b.id
        LEFT JOIN formas_pago fp ON cu.forma_pago_id = fp.id
        LEFT JOIN grupos gr ON co.grupo_id = gr.id
        LEFT JOIN muelles mu ON co.muelle_id = mu.id
        LEFT JOIN slips sl ON co.slip_id = sl.id
        LEFT JOIN inmuebles inm ON co.inmueble_id = inm.id
        WHERE cu.fecha_pago BETWEEN ? AND ?
          AND NOT EXISTS (SELECT 1 FROM cuotas_movimientos x WHERE x.cuota_id = cu.id)
          $cuentaCond
          $extraWhereLegacy
    ";
}
