<?php
/**
 * Comprobaciones antes de DELETE: devuelve mensaje en español o null si no hay bloqueo.
 */
declare(strict_types=1);

function marinaBloqueoEliminarUsuario(PDO $pdo, int $id): ?string
{
    if ($id < 1) {
        return 'Usuario no válido.';
    }
    $items = [
        'otros usuarios (creado/modificado por él)' => ['SELECT COUNT(*) FROM usuarios WHERE id <> ? AND (created_by = ? OR updated_by = ?)', [$id, $id, $id]],
        'bancos' => ['SELECT COUNT(*) FROM bancos WHERE created_by = ? OR updated_by = ?', [$id, $id]],
        'cuentas' => ['SELECT COUNT(*) FROM cuentas WHERE created_by = ? OR updated_by = ?', [$id, $id]],
        'clientes' => ['SELECT COUNT(*) FROM clientes WHERE created_by = ? OR updated_by = ?', [$id, $id]],
        'muelles' => ['SELECT COUNT(*) FROM muelles WHERE created_by = ? OR updated_by = ?', [$id, $id]],
        'grupos' => ['SELECT COUNT(*) FROM grupos WHERE created_by = ? OR updated_by = ?', [$id, $id]],
        'slips' => ['SELECT COUNT(*) FROM slips WHERE created_by = ? OR updated_by = ?', [$id, $id]],
        'inmuebles' => ['SELECT COUNT(*) FROM inmuebles WHERE created_by = ? OR updated_by = ?', [$id, $id]],
        'tipos de movimiento / formas de pago' => ['SELECT COUNT(*) FROM formas_pago WHERE created_by = ? OR updated_by = ?', [$id, $id]],
        'partidas' => ['SELECT COUNT(*) FROM partidas WHERE created_by = ? OR updated_by = ?', [$id, $id]],
        'proveedores' => ['SELECT COUNT(*) FROM proveedores WHERE created_by = ? OR updated_by = ?', [$id, $id]],
        'contratos' => ['SELECT COUNT(*) FROM contratos WHERE created_by = ? OR updated_by = ?', [$id, $id]],
        'cuotas' => ['SELECT COUNT(*) FROM cuotas WHERE created_by = ? OR updated_by = ?', [$id, $id]],
        'gastos' => ['SELECT COUNT(*) FROM gastos WHERE created_by = ? OR updated_by = ?', [$id, $id]],
    ];
    $bloqueos = marinaEjecutarChequeosDependencia($pdo, $items);
    try {
        $st = $pdo->prepare('SELECT COUNT(*) FROM cuotas_movimientos WHERE created_by = ? OR updated_by = ?');
        $st->execute([$id, $id]);
        if ((int) $st->fetchColumn() > 0) {
            $bloqueos[] = 'movimientos de cuotas';
        }
    } catch (Throwable $e) {
    }
    $st = $pdo->prepare('SELECT COUNT(*) FROM movimientos_bancarios WHERE created_by = ? OR updated_by = ?');
    try {
        $st->execute([$id, $id]);
        if ((int) $st->fetchColumn() > 0) {
            $bloqueos[] = 'movimientos bancarios manuales';
        }
    } catch (Throwable $e) {
        // tabla opcional
    }
    return marinaMensajeBloqueoLista('usuario', $bloqueos);
}

/**
 * @param array<string, array{0: string, 1: array<int|string>}> $items
 * @return list<string>
 */
function marinaEjecutarChequeosDependencia(PDO $pdo, array $items): array
{
    $bloqueos = [];
    foreach ($items as $etiqueta => [$sql, $params]) {
        try {
            $st = $pdo->prepare($sql);
            $st->execute($params);
            if ((int) $st->fetchColumn() > 0) {
                $bloqueos[] = $etiqueta;
            }
        } catch (Throwable $e) {
            continue;
        }
    }
    return $bloqueos;
}

/**
 * @param list<string> $bloqueos
 */
function marinaMensajeBloqueoLista(string $entidad, array $bloqueos): ?string
{
    if ($bloqueos === []) {
        return null;
    }
    $lista = implode(', ', $bloqueos);

    return 'No se puede eliminar el ' . $entidad . ': hay datos que dependen de él o registros donde figura como creador o editor. Ámbitos: ' . $lista . '.';
}

function marinaBloqueoEliminarCuenta(PDO $pdo, int $id): ?string
{
    if ($id < 1) {
        return 'Cuenta no válida.';
    }
    $bloqueos = [];
    $st = $pdo->prepare('SELECT COUNT(*) FROM contratos WHERE cuenta_id = ?');
    $st->execute([$id]);
    if ((int) $st->fetchColumn() > 0) {
        $bloqueos[] = 'contratos que usan esta cuenta para pagos';
    }
    try {
        $st = $pdo->prepare('SELECT COUNT(*) FROM movimientos_bancarios WHERE cuenta_id = ?');
        $st->execute([$id]);
        if ((int) $st->fetchColumn() > 0) {
            $bloqueos[] = 'movimientos bancarios manuales';
        }
    } catch (Throwable $e) {
    }
    try {
        $st = $pdo->prepare('SELECT COUNT(*) FROM gasto_pagos WHERE cuenta_id = ?');
        $st->execute([$id]);
        if ((int) $st->fetchColumn() > 0) {
            $bloqueos[] = 'abonos de facturas (gastos)';
        }
    } catch (Throwable $e) {
    }
    try {
        $st = $pdo->prepare('SELECT COUNT(*) FROM contrato_electricidad_pagos WHERE cuenta_id = ?');
        $st->execute([$id]);
        if ((int) $st->fetchColumn() > 0) {
            $bloqueos[] = 'pagos de electricidad (contratos)';
        }
    } catch (Throwable $e) {
    }
    if ($bloqueos === []) {
        return null;
    }

    return 'No se puede eliminar la cuenta: ' . implode('; ', $bloqueos) . '.';
}

function marinaBloqueoEliminarCliente(PDO $pdo, int $id): ?string
{
    if ($id < 1) {
        return 'Cliente no válido.';
    }
    $st = $pdo->prepare('SELECT COUNT(*) FROM contratos WHERE cliente_id = ?');
    $st->execute([$id]);
    if ((int) $st->fetchColumn() > 0) {
        return 'No se puede eliminar el cliente: tiene contratos asociados. Cancele o reasigne los contratos primero.';
    }

    return null;
}

function marinaBloqueoEliminarFormaPago(PDO $pdo, int $id): ?string
{
    if ($id < 1) {
        return 'Tipo de movimiento no válido.';
    }
    try {
        $st = $pdo->prepare('SELECT COUNT(*) FROM movimientos_bancarios WHERE forma_pago_id = ?');
        $st->execute([$id]);
        if ((int) $st->fetchColumn() > 0) {
            return 'No se puede eliminar: hay movimientos bancarios manuales que usan este tipo de movimiento. Cambie la forma de pago en esos movimientos o elimínelos primero.';
        }
    } catch (Throwable $e) {
    }
    try {
        $st = $pdo->prepare('SELECT COUNT(*) FROM gasto_pagos WHERE forma_pago_id = ?');
        $st->execute([$id]);
        if ((int) $st->fetchColumn() > 0) {
            return 'No se puede eliminar: hay abonos de facturas (gastos) que usan esta forma de pago. Cambie la forma de pago en esos abonos primero.';
        }
    } catch (Throwable $e) {
    }
    try {
        $st = $pdo->prepare('SELECT COUNT(*) FROM contrato_electricidad_pagos WHERE forma_pago_id = ?');
        $st->execute([$id]);
        if ((int) $st->fetchColumn() > 0) {
            return 'No se puede eliminar: hay pagos de electricidad que usan esta forma de pago. Cambie la forma de pago en esos registros primero.';
        }
    } catch (Throwable $e) {
    }

    return null;
}

function marinaBloqueoEliminarProveedor(PDO $pdo, int $id): ?string
{
    if ($id < 1) {
        return 'Proveedor no válido.';
    }
    $st = $pdo->prepare('SELECT COUNT(*) FROM gastos WHERE proveedor_id = ?');
    $st->execute([$id]);
    if ((int) $st->fetchColumn() > 0) {
        return 'No se puede eliminar el proveedor: hay gastos registrados con él. Elimine o reasigne esos gastos primero.';
    }

    return null;
}

function marinaBloqueoEliminarGrupo(PDO $pdo, int $id): ?string
{
    if ($id < 1) {
        return 'Grupo no válido.';
    }
    $bloqueos = [];
    $st = $pdo->prepare('SELECT COUNT(*) FROM inmuebles WHERE grupo_id = ?');
    $st->execute([$id]);
    if ((int) $st->fetchColumn() > 0) {
        $bloqueos[] = 'inmuebles del grupo';
    }
    $st = $pdo->prepare('SELECT COUNT(*) FROM contratos WHERE grupo_id = ?');
    $st->execute([$id]);
    if ((int) $st->fetchColumn() > 0) {
        $bloqueos[] = 'contratos que usan este grupo';
    }
    if ($bloqueos === []) {
        return null;
    }

    return 'No se puede eliminar el grupo: aún hay ' . implode(' y ', $bloqueos) . '.';
}

function marinaBloqueoEliminarInmueble(PDO $pdo, int $id): ?string
{
    if ($id < 1) {
        return 'Inmueble no válido.';
    }
    $st = $pdo->prepare('SELECT COUNT(*) FROM contratos WHERE inmueble_id = ?');
    $st->execute([$id]);
    if ((int) $st->fetchColumn() > 0) {
        return 'No se puede eliminar el inmueble: hay contratos vinculados. Cambie el inmueble en los contratos o délos de baja primero.';
    }

    return null;
}

function marinaBloqueoEliminarSlip(PDO $pdo, int $id): ?string
{
    if ($id < 1) {
        return 'Slip no válido.';
    }
    $st = $pdo->prepare('SELECT COUNT(*) FROM contratos WHERE slip_id = ?');
    $st->execute([$id]);
    if ((int) $st->fetchColumn() > 0) {
        return 'No se puede eliminar el slip: hay contratos que lo usan. Reasigne el slip o los contratos primero.';
    }

    return null;
}

function marinaBloqueoEliminarPartida(PDO $pdo, int $id): ?string
{
    if ($id < 1) {
        return 'Partida no válida.';
    }
    $st = $pdo->prepare('SELECT COUNT(*) FROM partidas WHERE parent_id = ?');
    $st->execute([$id]);
    if ((int) $st->fetchColumn() > 0) {
        return 'No se puede eliminar la partida: tiene subpartidas. Elimine o reubique las subpartidas primero.';
    }
    $st = $pdo->prepare('SELECT COUNT(*) FROM gastos WHERE partida_id = ?');
    $st->execute([$id]);
    if ((int) $st->fetchColumn() > 0) {
        return 'No se puede eliminar la partida: hay gastos registrados en ella. Elimine o mueva esos gastos primero.';
    }

    return null;
}

function marinaMensajeErrorIntegridad(Throwable $e): string
{
    if ($e instanceof \PDOException && (int) ($e->errorInfo[1] ?? 0) === 1451) {
        return 'No se puede eliminar: otros registros del sistema dependen de este dato (restricción de integridad).';
    }

    return 'No se pudo completar la eliminación. Compruebe que no queden datos vinculados.';
}
