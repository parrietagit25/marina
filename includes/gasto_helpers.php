<?php
/**
 * Facturas de gasto (gastos) y abonos (gasto_pagos): totales, estado, sync combustible.
 */
declare(strict_types=1);

function marina_gasto_total_pagado(PDO $pdo, int $gastoId): float {
    try {
        $st = $pdo->prepare('SELECT COALESCE(SUM(monto), 0) FROM gasto_pagos WHERE gasto_id = ?');
        $st->execute([$gastoId]);
        return round((float) $st->fetchColumn(), 2);
    } catch (Throwable $e) {
        return 0.0;
    }
}

/** Actualiza gastos.estado según suma de abonos vs monto de la factura. */
function marina_gasto_refrescar_estado(PDO $pdo, int $gastoId): void {
    try {
        $st = $pdo->prepare('SELECT monto FROM gastos WHERE id = ?');
        $st->execute([$gastoId]);
        $m = round((float) $st->fetchColumn(), 2);
        if ($m <= 0) {
            return;
        }
        $p = marina_gasto_total_pagado($pdo, $gastoId);
        $estado = ($p + 0.009 >= $m) ? 'pagada' : 'pendiente';
        $pdo->prepare('UPDATE gastos SET estado = ? WHERE id = ?')->execute([$estado, $gastoId]);
    } catch (Throwable $e) {
        // sin columna estado o tabla pagos
    }
}

/**
 * Un solo abono que reemplaza los anteriores (pedido combustible = un egreso alineado al costo).
 */
function marina_gasto_sync_pago_unico(
    PDO $pdo,
    int $gastoId,
    float $monto,
    string $fechaPago,
    ?int $cuentaId,
    ?int $formaPagoId,
    ?string $referencia,
    ?string $observaciones,
    $createdBy,
    $updatedBy
): void {
    $pdo->prepare('DELETE FROM gasto_pagos WHERE gasto_id = ?')->execute([$gastoId]);
    $pdo->prepare('
        INSERT INTO gasto_pagos (gasto_id, monto, fecha_pago, cuenta_id, forma_pago_id, referencia, observaciones, created_by, updated_by)
        VALUES (?,?,?,?,?,?,?,?,?)
    ')->execute([
        $gastoId,
        round($monto, 2),
        $fechaPago,
        $cuentaId !== null && $cuentaId > 0 ? $cuentaId : null,
        $formaPagoId !== null && $formaPagoId > 0 ? $formaPagoId : null,
        $referencia !== null && $referencia !== '' ? $referencia : null,
        $observaciones !== null && $observaciones !== '' ? $observaciones : null,
        $createdBy,
        $updatedBy,
    ]);
    marina_gasto_refrescar_estado($pdo, $gastoId);
}

/**
 * Migración única: copia pagos desde columnas legacy de gastos y marca facturas como pagadas si aplica.
 */
function marina_gasto_migrar_legacy_si_falta(PDO $pdo): void {
    try {
        $chk = $pdo->prepare("SELECT valor FROM marina_config WHERE clave = 'migration_gasto_pagos_factura_v1' LIMIT 1");
        $chk->execute();
        if ($chk->fetchColumn()) {
            return;
        }
    } catch (Throwable $e) {
        return;
    }
    try {
        $pdo->beginTransaction();
        $pdo->exec("
            INSERT INTO gasto_pagos (gasto_id, monto, fecha_pago, cuenta_id, forma_pago_id, referencia, observaciones, created_by, updated_by)
            SELECT g.id, g.monto, g.fecha_gasto, g.cuenta_id, g.forma_pago_id, g.referencia, g.observaciones, g.created_by, g.updated_by
            FROM gastos g
        ");
        $pdo->exec("
            UPDATE gastos g
            SET g.estado = IF(
                (SELECT COALESCE(SUM(p.monto), 0) FROM gasto_pagos p WHERE p.gasto_id = g.id) + 0.009 >= g.monto,
                'pagada',
                'pendiente'
            )
        ");
        $pdo->prepare("INSERT INTO marina_config (clave, valor) VALUES ('migration_gasto_pagos_factura_v1', '1')
            ON DUPLICATE KEY UPDATE valor = '1'")->execute();
        $pdo->exec('UPDATE gastos SET cuenta_id = NULL, forma_pago_id = NULL');
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }
}
