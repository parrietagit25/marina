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

/** Precio de venta por galón vigente en una fecha (vigente_desde <= fecha). */
function marina_combustible_precio_venta_en_fecha(PDO $pdo, string $tipo, string $fecha): ?float
{
    $tipo = strtolower(trim($tipo));
    if (!isset(MARINA_COMB_TIPOS[$tipo]) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
        return null;
    }
    try {
        $st = $pdo->prepare('
            SELECT precio_venta_galon
            FROM combustible_precios
            WHERE tipo_combustible = ? AND vigente_desde <= ?
            ORDER BY vigente_desde DESC, id DESC
            LIMIT 1
        ');
        $st->execute([$tipo, $fecha]);
        $v = $st->fetchColumn();
        return $v !== false ? (float) $v : null;
    } catch (Throwable $e) {
        return null;
    }
}

/** Precio/galón a mostrar en despachos (guardado, histórico o derivado de monto/GLS). */
function marina_combustible_despacho_precio_galon(array $row, ?PDO $pdo = null): ?float
{
    if (array_key_exists('precio_venta_galon', $row) && $row['precio_venta_galon'] !== null && $row['precio_venta_galon'] !== '') {
        return (float) $row['precio_venta_galon'];
    }
    if ($pdo !== null && !empty($row['tipo_combustible']) && !empty($row['fecha'])) {
        $hist = marina_combustible_precio_venta_en_fecha($pdo, (string) $row['tipo_combustible'], (string) $row['fecha']);
        if ($hist !== null) {
            return $hist;
        }
    }
    $gls = (float) ($row['gls'] ?? 0);
    $monto = (float) ($row['monto_total'] ?? 0);
    if ($gls > 0 && $monto >= 0) {
        return round($monto / $gls, 4);
    }
    return null;
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
/**
 * GLS disponibles para un despacho (mismo tipo que el registro a editar suma de vuelta su consumo).
 */
function marina_combustible_disponible_despacho_tipo(PDO $pdo, string $tipoNuevo, ?int $editDespachoId): float
{
    $tipoNuevo = strtolower(trim($tipoNuevo));
    $inv = marina_combustible_inventario_por_tipo($pdo);
    $avail = $inv[$tipoNuevo] ?? 0.0;
    if ($editDespachoId === null || $editDespachoId < 1) {
        return $avail;
    }
    try {
        $st = $pdo->prepare('SELECT tipo_combustible, gls FROM combustible_despachos WHERE id = ?');
        $st->execute([$editDespachoId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $oldT = strtolower(trim((string) ($row['tipo_combustible'] ?? '')));
            $oldG = (float) ($row['gls'] ?? 0);
            if ($oldT === $tipoNuevo) {
                $avail += $oldG;
            }
        }
    } catch (Throwable $e) {
        // ignorar
    }

    return $avail;
}

/**
 * Una sola vez: crea un cobro por cada despacho previo al modelo factura+cobros (sin tabla de pagos).
 * Después de ejecutarse no vuelve a insertar: las facturas nuevas solo cobran desde la pantalla «Cobrar».
 */
function marina_combustible_migrar_despacho_pagos_legacy(PDO $pdo): void
{
    try {
        $chk = $pdo->prepare("SELECT 1 FROM marina_config WHERE clave = 'migration_combustible_despacho_cobros_v1' LIMIT 1");
        $chk->execute();
        if ($chk->fetchColumn()) {
            return;
        }

        $pdo->exec("
            INSERT INTO combustible_despacho_pagos (despacho_id, tipo, monto, fecha_pago, cuenta_id, forma_pago_id, referencia, concepto, created_by, updated_by)
            SELECT d.id, 'pago', d.monto_total, d.fecha, d.cuenta_id, NULL, NULL, 'Migración: cobro único (sistema anterior)', d.created_by, d.updated_by
            FROM combustible_despachos d
            WHERE d.cuenta_id IS NOT NULL
              AND d.monto_total > 0
              AND NOT EXISTS (SELECT 1 FROM combustible_despacho_pagos p WHERE p.despacho_id = d.id)
        ");

        $pdo->exec("INSERT IGNORE INTO marina_config (clave, valor) VALUES ('migration_combustible_despacho_cobros_v1', '1')");
    } catch (Throwable $e) {
        // tabla inexistente o permisos
    }
}

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
 * Copia abonos del pedido a gasto_pagos (egreso bancario). Solo los abonos mueven la cuenta.
 */
function marina_combustible_sync_abonos_a_gasto_pagos(PDO $pdo, int $pedidoId): void
{
    require_once __DIR__ . '/gasto_helpers.php';

    $gastoId = 0;
    try {
        $st = $pdo->prepare('SELECT gasto_id FROM combustible_pedidos WHERE id = ?');
        $st->execute([$pedidoId]);
        $gastoId = (int) ($st->fetchColumn() ?: 0);
    } catch (Throwable $e) {
        return;
    }
    if ($gastoId < 1) {
        return;
    }

    $pdo->prepare('DELETE FROM gasto_pagos WHERE gasto_id = ?')->execute([$gastoId]);

    $st2 = $pdo->prepare('
        SELECT monto, fecha_pago, cuenta_id, forma_pago_id, referencia
        FROM combustible_pedido_pagos
        WHERE pedido_id = ?
        ORDER BY fecha_pago, id
    ');
    $st2->execute([$pedidoId]);
    $uid = function_exists('usuarioId') ? usuarioId() : null;
    $obs = 'Abono pedido combustible #' . $pedidoId;

    while ($row = $st2->fetch(PDO::FETCH_ASSOC)) {
        $ref = trim((string) ($row['referencia'] ?? ''));
        $pdo->prepare('
            INSERT INTO gasto_pagos (gasto_id, monto, fecha_pago, cuenta_id, forma_pago_id, referencia, observaciones, created_by, updated_by)
            VALUES (?,?,?,?,?,?,?,?,?)
        ')->execute([
            $gastoId,
            round((float) ($row['monto'] ?? 0), 2),
            $row['fecha_pago'],
            !empty($row['cuenta_id']) ? (int) $row['cuenta_id'] : null,
            !empty($row['forma_pago_id']) ? (int) $row['forma_pago_id'] : null,
            $ref !== '' ? $ref : null,
            $obs,
            $uid,
            $uid,
        ]);
    }

    marina_gasto_refrescar_estado($pdo, $gastoId);
}

/**
 * Factura de gasto al recibir pedido (pendiente). El banco se afecta solo con abonos en combustible_pedido_pagos.
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

    $tipo = (string) ($p['tipo_combustible'] ?? '');
    $fact = trim((string) ($p['numero_factura'] ?? ''));
    $obs = 'Pedido combustible #' . $pedidoId . ' — ' . $tipo . ' — ' . $glsRec . ' GLS recibidos';
    $fechaGasto = (string) $fechaRec;

    if ($gastoId > 0) {
        $pdo->prepare('UPDATE gastos SET partida_id=?, proveedor_id=?, cuenta_id=NULL, forma_pago_id=NULL, monto=?, fecha_gasto=?, referencia=?, observaciones=?, estado=\'pendiente\', updated_by=? WHERE id=?')
            ->execute([$partidaId, $provId, $costo, $fechaGasto, $fact !== '' ? $fact : null, $obs, $uid, $gastoId]);
    } else {
        $pdo->prepare('INSERT INTO gastos (partida_id, proveedor_id, cuenta_id, forma_pago_id, monto, fecha_gasto, referencia, observaciones, created_by, updated_by, estado) VALUES (?,?,NULL,NULL,?,?,?,?,?,?,\'pendiente\')')
            ->execute([$partidaId, $provId, $costo, $fechaGasto, $fact !== '' ? $fact : null, $obs, $uid, $uid]);
        $gastoId = (int) $pdo->lastInsertId();
        try {
            $pdo->prepare('UPDATE combustible_pedidos SET gasto_id = ? WHERE id = ?')->execute([$gastoId, $pedidoId]);
        } catch (Throwable $e) {
            // sin columna gasto_id: el gasto existe en reportes
        }
    }

    marina_combustible_sync_abonos_a_gasto_pagos($pdo, $pedidoId);
}

/** Una vez: egreso bancario solo desde abonos, no al recibir el pedido. */
function marina_combustible_migrar_pedido_banco_solo_abonos(PDO $pdo): void
{
    try {
        $chk = $pdo->prepare("SELECT 1 FROM marina_config WHERE clave = 'migration_comb_pedido_banco_abonos_v1' LIMIT 1");
        $chk->execute();
        if ($chk->fetchColumn()) {
            return;
        }
    } catch (Throwable $e) {
        return;
    }
    try {
        $ids = $pdo->query('SELECT id FROM combustible_pedidos WHERE gasto_id IS NOT NULL AND gasto_id > 0')->fetchAll(PDO::FETCH_COLUMN);
        foreach ($ids as $pid) {
            marina_combustible_sync_abonos_a_gasto_pagos($pdo, (int) $pid);
        }
        $pdo->prepare("INSERT INTO marina_config (clave, valor) VALUES ('migration_comb_pedido_banco_abonos_v1', '1')
            ON DUPLICATE KEY UPDATE valor = '1'")->execute();
    } catch (Throwable $e) {
        // ignorar
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

/** @return ''|'pagado'|'por_pagar' */
function marina_combustible_filtro_estado_desde_request(): string
{
    $estado = strtolower(trim((string) ($_GET['estado'] ?? '')));

    return in_array($estado, ['pagado', 'por_pagar'], true) ? $estado : '';
}

/** Query string (desde, hasta, estado) para enlaces y redirecciones del listado. */
function marina_combustible_filtro_qs_from_get(?array $get = null): string
{
    $get = $get ?? $_GET;
    $desde = trim((string) ($get['desde'] ?? date('Y-m-01')));
    $hasta = trim((string) ($get['hasta'] ?? date('Y-m-d')));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) {
        $desde = date('Y-m-01');
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
        $hasta = date('Y-m-d');
    }
    if ($desde > $hasta) {
        [$desde, $hasta] = [$hasta, $desde];
    }
    $estado = strtolower(trim((string) ($get['estado'] ?? '')));
    $estado = in_array($estado, ['pagado', 'por_pagar'], true) ? $estado : '';
    $params = ['desde' => $desde, 'hasta' => $hasta];
    if ($estado !== '') {
        $params['estado'] = $estado;
    }

    return http_build_query($params);
}

function marina_combustible_etiqueta_estado_filtro(string $estado): string
{
    return match ($estado) {
        'pagado' => 'Pagado',
        'por_pagar' => 'Por pagar',
        default => 'Todos',
    };
}

function marina_combustible_despacho_esta_pagado(float $montoTotal, float $pagado): bool
{
    return $montoTotal > 0 && $pagado + 0.009 >= $montoTotal;
}

function marina_combustible_sql_filtro_estado_pedido(string $estado, string $alias = 'p'): string
{
    if ($estado === 'pagado') {
        return " AND {$alias}.estado_pago = 'pagado'";
    }
    if ($estado === 'por_pagar') {
        return " AND {$alias}.estado_pago = 'por_pagar'";
    }

    return '';
}

function marina_combustible_sql_filtro_estado_despacho(string $estado, string $aliasDesp = 'd', string $aliasPagos = 'p'): string
{
    if ($estado === 'pagado') {
        return " AND {$aliasDesp}.monto_total > 0 AND COALESCE({$aliasPagos}.pagado, 0) + 0.009 >= {$aliasDesp}.monto_total";
    }
    if ($estado === 'por_pagar') {
        return " AND ({$aliasDesp}.monto_total <= 0 OR COALESCE({$aliasPagos}.pagado, 0) + 0.009 < {$aliasDesp}.monto_total)";
    }

    return '';
}
