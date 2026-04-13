<?php
/**
 * Catálogo, precios vigentes, gasto (egreso) al recibir pedido y utilidades de inventario.
 */
declare(strict_types=1);

const MARINA_COMB_TIPOS = ['diesel' => 'Diesel', 'gasolina' => 'Gasolina'];

function marina_combustible_seed_catalog(PDO $pdo): void
{
    $uid = null;
    try {
        if (function_exists('usuarioId')) {
            $uid = usuarioId();
        }
    } catch (Throwable $e) {
        $uid = null;
    }

    $has = (int) $pdo->query("SELECT COUNT(*) FROM partidas WHERE nombre = 'Combustible'")->fetchColumn();
    if ($has === 0) {
        $pdo->prepare('INSERT INTO partidas (parent_id, nombre, created_by, updated_by) VALUES (NULL, ?, ?, ?)')
            ->execute(['Combustible', $uid, $uid]);
    }

    $hasP = (int) $pdo->query("SELECT COUNT(*) FROM proveedores WHERE nombre = 'Combustible (compras)'")->fetchColumn();
    if ($hasP === 0) {
        $pdo->prepare('INSERT INTO proveedores (nombre, created_by, updated_by) VALUES (?, ?, ?)')
            ->execute(['Combustible (compras)', $uid, $uid]);
    }

    $hoy = date('Y-m-d');
    foreach (['diesel', 'gasolina'] as $tipo) {
        $st = $pdo->prepare('SELECT COUNT(*) FROM combustible_precios WHERE tipo_combustible = ?');
        $st->execute([$tipo]);
        if ((int) $st->fetchColumn() === 0) {
            $pdo->prepare('INSERT INTO combustible_precios (tipo_combustible, precio_compra_galon, precio_venta_galon, vigente_desde, created_by, updated_by) VALUES (?,?,?,?,?,?)')
                ->execute([$tipo, 0, 0, $hoy, $uid, $uid]);
        }
    }
}

function marina_combustible_partida_id(PDO $pdo): int
{
    static $id = null;
    if ($id !== null) {
        return $id;
    }
    $st = $pdo->query("SELECT id FROM partidas WHERE nombre = 'Combustible' ORDER BY id LIMIT 1");
    $row = $st ? $st->fetchColumn() : false;
    $id = $row ? (int) $row : 0;
    return $id;
}

function marina_combustible_proveedor_id(PDO $pdo): int
{
    static $id = null;
    if ($id !== null) {
        return $id;
    }
    $st = $pdo->query("SELECT id FROM proveedores WHERE nombre = 'Combustible (compras)' ORDER BY id LIMIT 1");
    $row = $st ? $st->fetchColumn() : false;
    $id = $row ? (int) $row : 0;
    return $id;
}

/**
 * @return array<string, array{compra: float, venta: float}>
 */
function marina_combustible_precios_vigentes(PDO $pdo): array
{
    $out = [
        'diesel' => ['compra' => 0.0, 'venta' => 0.0],
        'gasolina' => ['compra' => 0.0, 'venta' => 0.0],
    ];
    try {
        foreach (['diesel', 'gasolina'] as $tipo) {
            $st = $pdo->prepare("
                SELECT precio_compra_galon, precio_venta_galon
                FROM combustible_precios
                WHERE tipo_combustible = ?
                ORDER BY vigente_desde DESC, id DESC
                LIMIT 1
            ");
            $st->execute([$tipo]);
            $r = $st->fetch(PDO::FETCH_ASSOC);
            if ($r) {
                $out[$tipo]['compra'] = (float) $r['precio_compra_galon'];
                $out[$tipo]['venta'] = (float) $r['precio_venta_galon'];
            }
        }
    } catch (Throwable $e) {
        // tablas aún no creadas
    }
    return $out;
}

/**
 * @return array<string, float> gls en inventario por tipo
 */
function marina_combustible_inventario_por_tipo(PDO $pdo): array
{
    $inv = ['diesel' => 0.0, 'gasolina' => 0.0];
    try {
        $st = $pdo->query("
            SELECT tipo_combustible, COALESCE(SUM(gls_recibido), 0) AS r
            FROM combustible_pedidos
            WHERE fecha_recibido IS NOT NULL AND gls_recibido IS NOT NULL AND gls_recibido > 0
            GROUP BY tipo_combustible
        ");
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $t = strtolower((string) ($row['tipo_combustible'] ?? ''));
            if (isset($inv[$t])) {
                $inv[$t] += (float) $row['r'];
            }
        }
        $st2 = $pdo->query('SELECT tipo_combustible, COALESCE(SUM(gls), 0) AS d FROM combustible_despachos GROUP BY tipo_combustible');
        while ($row = $st2->fetch(PDO::FETCH_ASSOC)) {
            $t = strtolower((string) ($row['tipo_combustible'] ?? ''));
            if (isset($inv[$t])) {
                $inv[$t] -= (float) $row['d'];
            }
        }
    } catch (Throwable $e) {
        return $inv;
    }
    try {
        $st3 = $pdo->query('SELECT tipo_combustible, COALESCE(SUM(gls_delta), 0) AS a FROM combustible_ajustes GROUP BY tipo_combustible');
        while ($row = $st3->fetch(PDO::FETCH_ASSOC)) {
            $t = strtolower((string) ($row['tipo_combustible'] ?? ''));
            if (isset($inv[$t])) {
                $inv[$t] += (float) $row['a'];
            }
        }
    } catch (Throwable $e) {
        // tabla combustible_ajustes aún no existe
    }
    return $inv;
}

/**
 * Inventario disponible para validar un ajuste de salida al editar (excluye el efecto del registro actual).
 */
function marina_combustible_inventario_efectivo_para_ajuste(PDO $pdo, string $tipo, ?int $editAjusteId): float
{
    $tipo = strtolower($tipo);
    $inv = marina_combustible_inventario_por_tipo($pdo);
    $base = $inv[$tipo] ?? 0.0;
    if ($editAjusteId !== null && $editAjusteId > 0) {
        try {
            $st = $pdo->prepare('SELECT gls_delta FROM combustible_ajustes WHERE id = ?');
            $st->execute([$editAjusteId]);
            $old = $st->fetchColumn();
            if ($old !== false) {
                $base -= (float) $old;
            }
        } catch (Throwable $e) {
            // ignorar
        }
    }
    return $base;
}

function marina_combustible_actualizar_estado_pedido(PDO $pdo, int $pedidoId): void
{
    $st = $pdo->prepare('SELECT costo_total FROM combustible_pedidos WHERE id = ?');
    $st->execute([$pedidoId]);
    $costo = (float) ($st->fetchColumn() ?: 0);
    $st2 = $pdo->prepare('SELECT COALESCE(SUM(monto), 0) FROM combustible_pedido_pagos WHERE pedido_id = ?');
    $st2->execute([$pedidoId]);
    $pagado = (float) $st2->fetchColumn();
    $estado = ($pagado + 0.009 >= $costo && $costo > 0) ? 'pagado' : 'por_pagar';
    $pdo->prepare('UPDATE combustible_pedidos SET estado_pago = ? WHERE id = ?')->execute([$estado, $pedidoId]);
}

/**
 * Quita gastos vinculados a abonos (modelo antiguo) para no duplicar con el gasto del pedido.
 */
function marina_combustible_limpiar_gastos_abonos_pedido(PDO $pdo, int $pedidoId): void
{
    $st = $pdo->prepare('SELECT id, gasto_id FROM combustible_pedido_pagos WHERE pedido_id = ? AND gasto_id IS NOT NULL');
    $st->execute([$pedidoId]);
    while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
        $gid = (int) ($row['gasto_id'] ?? 0);
        if ($gid > 0) {
            try {
                $pdo->prepare('DELETE FROM gastos WHERE id = ?')->execute([$gid]);
            } catch (Throwable $e) {
                // ya eliminado
            }
        }
        $pdo->prepare('UPDATE combustible_pedido_pagos SET gasto_id = NULL WHERE id = ?')->execute([(int) $row['id']]);
    }
}

/**
 * Egreso contable: un gasto por el costo total cuando hay recepción (fecha + GLS + costo).
 * Los abonos solo llevan control de pago, no generan gastos adicionales.
 */
function marina_combustible_sync_pedido_gasto(PDO $pdo, int $pedidoId): void
{
    marina_combustible_limpiar_gastos_abonos_pedido($pdo, $pedidoId);

    $st = $pdo->prepare('SELECT * FROM combustible_pedidos WHERE id = ?');
    $st->execute([$pedidoId]);
    $p = $st->fetch(PDO::FETCH_ASSOC);
    if (!$p) {
        return;
    }

    $partidaId = marina_combustible_partida_id($pdo);
    $provId = marina_combustible_proveedor_id($pdo);
    if ($partidaId < 1 || $provId < 1) {
        return;
    }

    $fechaRec = $p['fecha_recibido'] ?? null;
    $glsRec = isset($p['gls_recibido']) && $p['gls_recibido'] !== null && $p['gls_recibido'] !== '' ? (float) $p['gls_recibido'] : 0.0;
    $costo = (float) ($p['costo_total'] ?? 0);
    $gastoId = !empty($p['gasto_id']) ? (int) $p['gasto_id'] : 0;
    $uid = function_exists('usuarioId') ? usuarioId() : null;

    $debeGasto = ($fechaRec !== null && $fechaRec !== '' && $glsRec > 0 && $costo > 0.0001);

    if (!$debeGasto) {
        if ($gastoId > 0) {
            try {
                $pdo->prepare('DELETE FROM gastos WHERE id = ?')->execute([$gastoId]);
            } catch (Throwable $e) {
                // ya eliminado
            }
            try {
                $pdo->prepare('UPDATE combustible_pedidos SET gasto_id = NULL WHERE id = ?')->execute([$pedidoId]);
            } catch (Throwable $e) {
                // sin columna gasto_id
            }
        }
        return;
    }

    $cuentaId = !empty($p['cuenta_id']) ? (int) $p['cuenta_id'] : null;
    if ($cuentaId !== null && $cuentaId <= 0) {
        $cuentaId = null;
    }
    $tipo = (string) ($p['tipo_combustible'] ?? '');
    $fact = trim((string) ($p['numero_factura'] ?? ''));
    $obs = 'Pedido combustible #' . $pedidoId . ' — ' . $tipo . ' — ' . $glsRec . ' GLS recibidos';
    $fechaGasto = (string) $fechaRec;

    require_once __DIR__ . '/gasto_helpers.php';

    if ($gastoId > 0) {
        $pdo->prepare('UPDATE gastos SET partida_id=?, proveedor_id=?, cuenta_id=NULL, forma_pago_id=NULL, monto=?, fecha_gasto=?, referencia=?, observaciones=?, updated_by=? WHERE id=?')
            ->execute([$partidaId, $provId, $costo, $fechaGasto, $fact !== '' ? $fact : null, $obs, $uid, $gastoId]);
        marina_gasto_sync_pago_unico(
            $pdo,
            $gastoId,
            $costo,
            $fechaGasto,
            $cuentaId,
            null,
            $fact !== '' ? $fact : null,
            $obs,
            $uid,
            $uid
        );
    } else {
        $pdo->prepare('INSERT INTO gastos (partida_id, proveedor_id, cuenta_id, forma_pago_id, monto, fecha_gasto, referencia, observaciones, created_by, updated_by, estado) VALUES (?,?,NULL,NULL,?,?,?,?,?,?,\'pagada\')')
            ->execute([$partidaId, $provId, $costo, $fechaGasto, $fact !== '' ? $fact : null, $obs, $uid, $uid]);
        $gid = (int) $pdo->lastInsertId();
        marina_gasto_sync_pago_unico(
            $pdo,
            $gid,
            $costo,
            $fechaGasto,
            $cuentaId,
            null,
            $fact !== '' ? $fact : null,
            $obs,
            $uid,
            $uid
        );
        try {
            $pdo->prepare('UPDATE combustible_pedidos SET gasto_id = ? WHERE id = ?')->execute([$gid, $pedidoId]);
        } catch (Throwable $e) {
            // sin columna gasto_id: el gasto existe en reportes
        }
    }
}

function marina_combustible_eliminar_pedido(PDO $pdo, int $pedidoId): void
{
    marina_combustible_limpiar_gastos_abonos_pedido($pdo, $pedidoId);

    $gastoPedido = 0;
    try {
        $st = $pdo->prepare('SELECT gasto_id FROM combustible_pedidos WHERE id = ?');
        $st->execute([$pedidoId]);
        $gastoPedido = (int) ($st->fetchColumn() ?: 0);
    } catch (Throwable $e) {
        // sin columna
    }
    if ($gastoPedido > 0) {
        try {
            $pdo->prepare('DELETE FROM gastos WHERE id = ?')->execute([$gastoPedido]);
        } catch (Throwable $e) {
            // ya eliminado
        }
    }
    $pdo->prepare('DELETE FROM combustible_pedidos WHERE id = ?')->execute([$pedidoId]);
}
