<?php
/**
 * Facturas de electricidad por contrato y pagos/abonos (ingreso a cuenta del contrato).
 */
declare(strict_types=1);

function marina_electricidad_total_pagado(PDO $pdo, int $facturaId): float
{
    try {
        $st = $pdo->prepare('SELECT COALESCE(SUM(monto), 0) FROM contrato_electricidad_pagos WHERE factura_id = ?');
        $st->execute([$facturaId]);

        return round((float) $st->fetchColumn(), 2);
    } catch (Throwable $e) {
        return 0.0;
    }
}

function marina_electricidad_refrescar_estado(PDO $pdo, int $facturaId): void
{
    try {
        $st = $pdo->prepare('SELECT monto_total FROM contrato_electricidad_facturas WHERE id = ?');
        $st->execute([$facturaId]);
        $m = round((float) $st->fetchColumn(), 2);
        if ($m <= 0) {
            return;
        }
        $p = marina_electricidad_total_pagado($pdo, $facturaId);
        $estado = ($p + 0.009 >= $m) ? 'pagada' : 'pendiente';
        $uid = function_exists('usuarioId') ? usuarioId() : null;
        $pdo->prepare('UPDATE contrato_electricidad_facturas SET estado = ?, updated_by = ? WHERE id = ?')
            ->execute([$estado, $uid, $facturaId]);
    } catch (Throwable $e) {
        // tablas ausentes
    }
}
