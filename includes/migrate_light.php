<?php
/**
 * Ajustes incrementales de esquema (idempotente; ignora si la columna ya existe).
 */
declare(strict_types=1);

function marina_ensure_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $stmts = [
        "ALTER TABLE contratos ADD COLUMN numero_recibo VARCHAR(100) NULL DEFAULT NULL COMMENT 'Número de recibo al cliente' AFTER observaciones",
        "ALTER TABLE cuotas_movimientos ADD COLUMN concepto VARCHAR(255) NULL DEFAULT NULL COMMENT 'Término / descripción del pago de cuota' AFTER referencia",
    ];
    foreach ($stmts as $sql) {
        try {
            $pdo->exec($sql);
        } catch (Throwable $e) {
            // Columna duplicada u otro entorno ya migrado
        }
    }
}
