<?php
/**
 * Fragmentos SQL reutilizables para reportes (ingresos por cuotas + fallback legacy).
 */
declare(strict_types=1);

/**
 * Devuelve SQL para líneas de ingreso por movimientos de cuota + fallback fecha_pago.
 * Parámetros: [desde, hasta] x2 para el UNION interno, más filtros opcionales al final.
 */
function reportesSqlIngresosDetalle(string $cuentaCond = '', string $extraWhereMov = '', string $extraWhereLegacy = ''): string
{
    return "
        SELECT mo.fecha_pago AS fecha,
               mo.monto AS monto,
               'Ingreso' AS tipo_linea,
               COALESCE(NULLIF(TRIM(mo.concepto), ''), CONCAT('Cuota #', cu.numero_cuota, ' — Contrato #', co.id)) AS concepto,
               CONCAT(b.nombre, ' - ', c.nombre) AS cuenta_nombre,
               co.cuenta_id,
               cl.nombre AS cliente_nombre,
               co.id AS contrato_id,
               cu.numero_cuota,
               TRIM(COALESCE(NULLIF(co.numero_recibo, ''), NULLIF(mo.referencia, ''), '')) AS referencia,
               fp.nombre AS forma_pago_nombre
        FROM cuotas_movimientos mo
        JOIN cuotas cu ON mo.cuota_id = cu.id
        JOIN contratos co ON cu.contrato_id = co.id
        JOIN clientes cl ON co.cliente_id = cl.id
        JOIN cuentas c ON co.cuenta_id = c.id
        JOIN bancos b ON c.banco_id = b.id
        LEFT JOIN formas_pago fp ON mo.forma_pago_id = fp.id
        WHERE mo.fecha_pago BETWEEN ? AND ?
          AND mo.tipo IN ('pago','abono')
          $cuentaCond
          $extraWhereMov

        UNION ALL

        SELECT cu.fecha_pago AS fecha,
               cu.monto AS monto,
               'Ingreso' AS tipo_linea,
               CONCAT('Cuota #', cu.numero_cuota, ' — Contrato #', co.id) AS concepto,
               CONCAT(b.nombre, ' - ', c.nombre) AS cuenta_nombre,
               co.cuenta_id,
               cl.nombre AS cliente_nombre,
               co.id AS contrato_id,
               cu.numero_cuota,
               TRIM(COALESCE(NULLIF(co.numero_recibo, ''), NULLIF(cu.referencia, ''), '')) AS referencia,
               fp.nombre AS forma_pago_nombre
        FROM cuotas cu
        JOIN contratos co ON cu.contrato_id = co.id
        JOIN clientes cl ON co.cliente_id = cl.id
        JOIN cuentas c ON co.cuenta_id = c.id
        JOIN bancos b ON c.banco_id = b.id
        LEFT JOIN formas_pago fp ON cu.forma_pago_id = fp.id
        WHERE cu.fecha_pago BETWEEN ? AND ?
          AND NOT EXISTS (SELECT 1 FROM cuotas_movimientos x WHERE x.cuota_id = cu.id)
          $cuentaCond
          $extraWhereLegacy
    ";
}
