<?php
/**
 * Contratos: cliente, cuenta, muelle, slip, período, monto total.
 * Crear/editar/eliminar con modales. Cuotas en página aparte.
 */
$titulo = 'Contratos';

$pdo = getDb();
$accion = obtener('accion');
$id = (int) obtener('id');
$mensaje = '';

if ($accion === 'eliminar' && $id > 0 && enviado()) {
    $stDel = $pdo->prepare('SELECT COALESCE(estado, \'activo\') FROM contratos WHERE id = ?');
    $stDel->execute([$id]);
    $estDel = (string) ($stDel->fetchColumn() ?: '');
    if ($estDel === '') {
        redirigir(MARINA_URL . '/index.php?p=contratos&err=' . rawurlencode('Contrato no encontrado.'));
    }
    if ($estDel !== 'activo') {
        redirigir(MARINA_URL . '/index.php?p=contratos&accion=cuotas&id=' . $id . '&err=' . rawurlencode('No se puede eliminar un contrato terminado; solo puede consultar cuotas y movimientos.'));
    }
    try {
        $pdo->prepare('DELETE FROM contratos WHERE id = ?')->execute([$id]);
        redirigir(MARINA_URL . '/index.php?p=contratos&ok=' . rawurlencode('Contrato eliminado'));
    } catch (Throwable $e) {
        redirigir(MARINA_URL . '/index.php?p=contratos&err=' . rawurlencode(marinaMensajeErrorIntegridad($e)));
    }
}

if ($accion === 'liberar' && $id > 0 && enviado()) {
    $libErr = marina_contrato_liberar($pdo, $id);
    if ($libErr !== null) {
        redirigir(MARINA_URL . '/index.php?p=contratos&err=' . rawurlencode($libErr));
    }
    redirigir(MARINA_URL . '/index.php?p=contratos&ok=' . rawurlencode('Contrato liberado: unidad disponible y estado en Terminado.'));
}

if (enviado() && ($accion === 'crear' || $accion === 'editar')) {
    $uid = usuarioId();
    $cliente_id = (int) ($_POST['cliente_id'] ?? 0);
    $cuenta_id = (int) ($_POST['cuenta_id'] ?? 0);
    $muelle_id = (int) ($_POST['muelle_id'] ?? 0);
    $slip_id = (int) ($_POST['slip_id'] ?? 0);
    $grupo_id = (int) ($_POST['grupo_id'] ?? 0);
    $inmueble_id = (int) ($_POST['inmueble_id'] ?? 0);
    $fecha_inicio = trim($_POST['fecha_inicio'] ?? '');
    $fecha_fin = trim($_POST['fecha_fin'] ?? '');
    $monto_total = (float) str_replace(',', '.', $_POST['monto_total'] ?? 0);
    $observaciones = trim($_POST['observaciones'] ?? '');
    $numero_recibo = trim($_POST['numero_recibo'] ?? '');

    $tieneUnidadMuelleSlip = ($muelle_id > 0 && $slip_id > 0);
    $tieneUnidadInmueble = ($grupo_id > 0 && $inmueble_id > 0);
    if ($cliente_id < 1 || $cuenta_id < 1 || $fecha_inicio === '' || $fecha_fin === '' || $monto_total <= 0 || (!$tieneUnidadMuelleSlip && !$tieneUnidadInmueble)) {
        $mensaje = 'Complete campos obligatorios (cliente, cuenta, fechas, monto y al menos una unidad: muelle/slip o grupo/inmueble).';
    } else {
        if ($accion === 'editar' && $id > 0) {
            $stEst = $pdo->prepare('SELECT estado FROM contratos WHERE id = ?');
            $stEst->execute([$id]);
            $estAct = (string) ($stEst->fetchColumn() ?: 'activo');
            if ($estAct !== 'activo') {
                $mensaje = 'No se puede editar un contrato terminado. Solo puede consultar cuotas y movimientos (solo lectura).';
            }
        }
        if ($mensaje === '' && $slip_id > 0) {
            $oid = ($accion === 'editar' && $id > 0) ? $id : 0;
            $stDup = $pdo->prepare('SELECT id FROM contratos WHERE estado = \'activo\' AND slip_id = ? AND id <> ? LIMIT 1');
            $stDup->execute([$slip_id, $oid]);
            if ($stDup->fetch()) {
                $mensaje = 'Ese slip ya tiene otro contrato activo. Libere el contrato anterior o elija otro slip.';
            }
        }
        if ($mensaje === '' && $inmueble_id > 0) {
            $oid = ($accion === 'editar' && $id > 0) ? $id : 0;
            $stDup = $pdo->prepare('SELECT id FROM contratos WHERE estado = \'activo\' AND inmueble_id = ? AND id <> ? LIMIT 1');
            $stDup->execute([$inmueble_id, $oid]);
            if ($stDup->fetch()) {
                $mensaje = 'Ese inmueble ya tiene otro contrato activo. Libere el contrato anterior o elija otro inmueble.';
            }
        }
        if ($mensaje === '') {
        if ($accion === 'editar' && $id > 0) {
            $pdo->prepare('UPDATE contratos SET cliente_id=?, cuenta_id=?, muelle_id=?, slip_id=?, grupo_id=?, inmueble_id=?, fecha_inicio=?, fecha_fin=?, monto_total=?, observaciones=?, numero_recibo=?, activo=1, estado=\'activo\', updated_by=? WHERE id=?')
                ->execute([
                    $cliente_id,
                    $cuenta_id,
                    $muelle_id ?: null,
                    $slip_id ?: null,
                    $grupo_id ?: null,
                    $inmueble_id ?: null,
                    $fecha_inicio,
                    $fecha_fin,
                    $monto_total,
                    $observaciones,
                    $numero_recibo === '' ? null : $numero_recibo,
                    $uid,
                    $id
                ]);
            redirigir(MARINA_URL . '/index.php?p=contratos&ok=Actualizado');
        } else {
            $pdo->prepare('INSERT INTO contratos (cliente_id, cuenta_id, muelle_id, slip_id, grupo_id, inmueble_id, fecha_inicio, fecha_fin, monto_total, observaciones, numero_recibo, activo, estado, created_by, updated_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
                ->execute([
                    $cliente_id,
                    $cuenta_id,
                    $muelle_id ?: null,
                    $slip_id ?: null,
                    $grupo_id ?: null,
                    $inmueble_id ?: null,
                    $fecha_inicio,
                    $fecha_fin,
                    $monto_total,
                    $observaciones,
                    $numero_recibo === '' ? null : $numero_recibo,
                    1,
                    'activo',
                    $uid,
                    $uid
                ]);
            $contrato_id = (int) $pdo->lastInsertId();
            redirigir(MARINA_URL . '/index.php?p=contratos&accion=cuotas&id=' . $contrato_id);
        }
        }
    }
}

$registro = null;
if (($accion === 'editar' || $accion === 'cuotas') && $id > 0) {
    $st = $pdo->prepare('SELECT * FROM contratos WHERE id = ?');
    $st->execute([$id]);
    $registro = $st->fetch();
    if (!$registro) redirigir(MARINA_URL . '/index.php?p=contratos');
}

if ($accion === 'editar' && $id > 0 && $registro && (string) ($registro['estado'] ?? 'activo') !== 'activo') {
    redirigir(MARINA_URL . '/index.php?p=contratos&accion=cuotas&id=' . $id . '&err=' . rawurlencode('Contrato terminado: no se puede editar; use Cuotas para consultar el historial.'));
}

$clientes = $pdo->query('SELECT id, nombre FROM clientes ORDER BY nombre')->fetchAll(PDO::FETCH_KEY_PAIR);
$cuentas = $pdo->query('SELECT c.id, CONCAT(b.nombre, " - ", c.nombre) AS nom FROM cuentas c JOIN bancos b ON c.banco_id = b.id ORDER BY b.nombre, c.nombre')->fetchAll(PDO::FETCH_KEY_PAIR);
$muelles = $pdo->query('SELECT id, nombre FROM muelles ORDER BY nombre')->fetchAll(PDO::FETCH_KEY_PAIR);
$slips = $pdo->query('SELECT s.id, CONCAT(m.nombre, " - ", s.nombre) AS nom FROM slips s JOIN muelles m ON s.muelle_id = m.id ORDER BY m.nombre, s.nombre')->fetchAll(PDO::FETCH_KEY_PAIR);
$grupos = $pdo->query('SELECT id, nombre FROM grupos ORDER BY nombre')->fetchAll(PDO::FETCH_KEY_PAIR);
$inmuebles = $pdo->query('SELECT i.id, CONCAT(g.nombre, " - ", i.nombre) AS nom FROM inmuebles i JOIN grupos g ON i.grupo_id = g.id ORDER BY g.nombre, i.nombre')->fetchAll(PDO::FETCH_KEY_PAIR);
$formas_pago = $pdo->query("SELECT id, nombre FROM formas_pago WHERE tipo_movimiento = 'ingreso' ORDER BY nombre")->fetchAll(PDO::FETCH_KEY_PAIR);

// --- Vista Cuotas (página aparte, con modales: agregar, pagar, abonar, ver)
if ($accion === 'cuotas' && $registro) {
    $contratoTerminado = (string) ($registro['estado'] ?? 'activo') !== 'activo';

    $mostrarModalAgregarCuota = false;
    $mostrarModalPagarCuota = false;
    $mostrarModalAbonarCuota = false;
    $cuotaAgregarDatos = [];
    $cuotaPagarDatos = [];
    $cuotaAbonarDatos = [];

    // Cargar cuotas del contrato
    $stCuotas = $pdo->prepare('SELECT id, numero_cuota, monto, fecha_vencimiento, fecha_pago, forma_pago_id, referencia FROM cuotas WHERE contrato_id = ? ORDER BY numero_cuota');
    $stCuotas->execute([$id]);
    $cuotas = $stCuotas->fetchAll(PDO::FETCH_ASSOC);

    // Cargar movimientos de cuotas (pago/abono)
    $movsByCuota = [];
    if (!empty($cuotas)) {
        $cuotaIds = array_map(function($c) { return (int)$c['id']; }, $cuotas);
        $placeholders = implode(',', array_fill(0, count($cuotaIds), '?'));
        $stMovs = $pdo->prepare("
            SELECT mo.*, fp.nombre AS forma_pago_nombre
            FROM cuotas_movimientos mo
            LEFT JOIN formas_pago fp ON mo.forma_pago_id = fp.id
            WHERE mo.cuota_id IN ($placeholders)
            ORDER BY mo.fecha_pago DESC, mo.id DESC
        ");
        $stMovs->execute($cuotaIds);
        while ($m = $stMovs->fetch(PDO::FETCH_ASSOC)) {
            $cid = (int)$m['cuota_id'];
            if (!isset($movsByCuota[$cid])) $movsByCuota[$cid] = [];
            $movsByCuota[$cid][] = $m;
        }
    }

    // Calcular saldo por cuota (y fallback con datos viejos en cuotas.* si no hay movimientos)
    $pagadoTotalPorCuota = [];
    $saldoPorCuota = [];
    $movsParaJS = [];
    $cuotasById = [];
    foreach ($cuotas as $c) {
        $cuotaId = (int)$c['id'];
        $cuotasById[$cuotaId] = $c;
        $pagado = 0.0;
        if (!empty($movsByCuota[$cuotaId])) {
            foreach ($movsByCuota[$cuotaId] as $m) $pagado += (float)$m['monto'];
        } elseif (!empty($c['fecha_pago'])) {
            // Compatibilidad: el sistema anterior marcaba un solo pago completo
            $pagado = (float)$c['monto'];
            $movsByCuota[$cuotaId] = [[
                'id' => 0,
                'cuota_id' => $cuotaId,
                'tipo' => 'pago',
                'monto' => $c['monto'],
                'fecha_pago' => $c['fecha_pago'],
                'forma_pago_id' => $c['forma_pago_id'],
                'forma_pago_nombre' => $formas_pago[(int)$c['forma_pago_id']] ?? null,
                'referencia' => $c['referencia']
            ]];
        }
        $pagadoTotalPorCuota[$cuotaId] = $pagado;
        $saldo = (float)$c['monto'] - $pagado;
        if ($saldo < 0) $saldo = 0.0;
        $saldoPorCuota[$cuotaId] = $saldo;

        // Preparar estructura para JS (vista de movimientos)
        $movsParaJS[$cuotaId] = [
            'saldo' => $saldo,
            'movimientos' => array_map(function($m) {
                return [
                    'tipo' => $m['tipo'],
                    'monto' => (string)$m['monto'],
                    'fecha_pago' => $m['fecha_pago'],
                    'forma_pago_nombre' => $m['forma_pago_nombre'] ?? null,
                    'referencia' => $m['referencia'] ?? null,
                ];
            }, $movsByCuota[$cuotaId] ?? [])
        ];
    }

    // Default para agregar cuota
    $cuotaAgregarDatos = ['numero_cuota' => count($cuotas) + 1, 'monto_cuota' => '', 'fecha_vencimiento' => ''];

    // --- Manejo POST: agregar cuota
    if (enviado() && isset($_POST['agregar_cuota'])) {
        if ($contratoTerminado) {
            redirigir(MARINA_URL . '/index.php?p=contratos&accion=cuotas&id=' . $id . '&err=' . rawurlencode('Contrato terminado: no se pueden agregar cuotas.'));
        }
        $num = (int)($_POST['numero_cuota'] ?? 0);
        $monto = (float)str_replace(',', '.', ($_POST['monto_cuota'] ?? 0));
        $venc = trim($_POST['fecha_vencimiento'] ?? '');
        if ($num < 1 || $monto <= 0 || $venc === '') {
            $mensaje = 'Número, monto y fecha de vencimiento obligatorios.';
            $mostrarModalAgregarCuota = true;
            $cuotaAgregarDatos = [
                'numero_cuota' => $num ?: (count($cuotas) + 1),
                'monto_cuota' => $_POST['monto_cuota'] ?? '',
                'fecha_vencimiento' => $venc
            ];
        } else {
            $pdo->prepare('INSERT INTO cuotas (contrato_id, numero_cuota, monto, fecha_vencimiento, created_by, updated_by) VALUES (?,?,?,?,?,?)')
                ->execute([$id, $num, $monto, $venc, usuarioId(), usuarioId()]);
            redirigir(MARINA_URL . '/index.php?p=contratos&accion=cuotas&id=' . $id . '&ok=Cuota+agregada');
        }
    }

    // --- Manejo POST: pagar/abonar (movimiento)
    if (enviado() && isset($_POST['registrar_movimiento'])) {
        if ($contratoTerminado) {
            redirigir(MARINA_URL . '/index.php?p=contratos&accion=cuotas&id=' . $id . '&err=' . rawurlencode('Contrato terminado: no se pueden registrar pagos ni abonos.'));
        }
        $tipo = trim($_POST['tipo_movimiento'] ?? '');
        $cuota_id = (int)($_POST['cuota_mov_id'] ?? 0);
        $monto_mov = (float)str_replace(',', '.', ($_POST['monto_movimiento'] ?? 0));
        $fecha_pago = trim($_POST['fecha_pago'] ?? '');
        $forma_pago_id = (int)($_POST['forma_pago_id'] ?? 0);
        $ref = trim($_POST['referencia_pago'] ?? '');
        $concepto_mov = trim($_POST['concepto_movimiento'] ?? '');

        if ($cuota_id < 1 || ($tipo !== 'pago' && $tipo !== 'abono') || $fecha_pago === '' || $monto_mov <= 0) {
            $mensaje = 'Complete tipo, fecha y monto válidos.';
            if ($tipo === 'pago') {
                $mostrarModalPagarCuota = true;
                        $cuotaPagarDatos = [
                            'cuota_mov_id' => $cuota_id,
                            'numero_cuota' => $cuotasById[$cuota_id]['numero_cuota'] ?? '',
                            'saldo_disponible' => $saldoPorCuota[$cuota_id] ?? 0.0,
                            'monto_movimiento' => $monto_mov,
                            'fecha_pago' => $fecha_pago,
                            'forma_pago_id' => $forma_pago_id,
                            'referencia_pago' => $ref,
                            'concepto_movimiento' => $concepto_mov
                        ];
            } else {
                $mostrarModalAbonarCuota = true;
                        $cuotaAbonarDatos = [
                            'cuota_mov_id' => $cuota_id,
                            'numero_cuota' => $cuotasById[$cuota_id]['numero_cuota'] ?? '',
                            'saldo_disponible' => $saldoPorCuota[$cuota_id] ?? 0.0,
                            'monto_movimiento' => $monto_mov,
                            'fecha_pago' => $fecha_pago,
                            'forma_pago_id' => $forma_pago_id,
                            'referencia_pago' => $ref,
                            'concepto_movimiento' => $concepto_mov
                        ];
            }
        } else {
            $saldoActual = $saldoPorCuota[$cuota_id] ?? 0.0;
            if ($monto_mov <= 0 || $monto_mov > ($saldoActual + 0.00001)) {
                $mensaje = 'El monto no puede ser mayor al saldo disponible (' . dinero($saldoActual) . ').';
                if ($tipo === 'pago') {
                    $mostrarModalPagarCuota = true;
                            $cuotaPagarDatos = [
                                'cuota_mov_id' => $cuota_id,
                                'numero_cuota' => $cuotasById[$cuota_id]['numero_cuota'] ?? '',
                                'saldo_disponible' => $saldoActual,
                                'monto_movimiento' => $monto_mov,
                                'fecha_pago' => $fecha_pago,
                                'forma_pago_id' => $forma_pago_id,
                                'referencia_pago' => $ref,
                                'concepto_movimiento' => $concepto_mov
                            ];
                } else {
                    $mostrarModalAbonarCuota = true;
                            $cuotaAbonarDatos = [
                                'cuota_mov_id' => $cuota_id,
                                'numero_cuota' => $cuotasById[$cuota_id]['numero_cuota'] ?? '',
                                'saldo_disponible' => $saldoActual,
                                'monto_movimiento' => $monto_mov,
                                'fecha_pago' => $fecha_pago,
                                'forma_pago_id' => $forma_pago_id,
                                'referencia_pago' => $ref,
                                'concepto_movimiento' => $concepto_mov
                            ];
                }
            } else {
                // Registrar movimiento
                $pdo->prepare('
                    INSERT INTO cuotas_movimientos (cuota_id, tipo, monto, fecha_pago, forma_pago_id, referencia, concepto, created_by, updated_by)
                    VALUES (?,?,?,?,?,?,?,?,?)
                ')->execute([
                    $cuota_id,
                    $tipo,
                    $monto_mov,
                    $fecha_pago,
                    $forma_pago_id ?: null,
                    $ref,
                    $concepto_mov === '' ? null : $concepto_mov,
                    usuarioId(),
                    usuarioId()
                ]);

                // Actualizar campos de compatibilidad cuando quede totalmente pagada
                $stSum = $pdo->prepare('SELECT COALESCE(SUM(monto),0) AS pagado FROM cuotas_movimientos WHERE cuota_id = ?');
                $stSum->execute([$cuota_id]);
                $pagado = (float)$stSum->fetch(PDO::FETCH_ASSOC)['pagado'];
                $montoCuota = (float)$cuotasById[$cuota_id]['monto'];

                if ($pagado >= ($montoCuota - 0.00001)) {
                    $pdo->prepare('UPDATE cuotas SET fecha_pago=?, forma_pago_id=?, referencia=?, updated_by=? WHERE id=?')
                        ->execute([$fecha_pago, $forma_pago_id ?: null, $ref, usuarioId(), $cuota_id]);
                } else {
                    $pdo->prepare('UPDATE cuotas SET fecha_pago=NULL, forma_pago_id=NULL, referencia=NULL, updated_by=? WHERE id=?')
                        ->execute([usuarioId(), $cuota_id]);
                }

                redirigir(MARINA_URL . '/index.php?p=contratos&accion=cuotas&id=' . $id . '&ok=' . ($tipo === 'pago' ? 'Cuota+pagada' : 'Abono+registrado'));
            }
        }
    }

    // Mostrar vista
    require_once __DIR__ . '/../includes/layout.php';
    if (obtener('ok')) {
        echo '<p class="success">' . e(obtener('ok')) . '</p>';
    }
    if (obtener('err')) {
        echo '<p class="error">' . e(obtener('err')) . '</p>';
    }
    if ($mensaje && !$mostrarModalAgregarCuota && !$mostrarModalPagarCuota && !$mostrarModalAbonarCuota) {
        echo '<p class="error">' . e($mensaje) . '</p>';
    }
    ?>
    <h1>Contrato #<?= $id ?> – Cuotas</h1>
    <p><strong>Cliente:</strong> <?= e($clientes[$registro['cliente_id']] ?? $registro['cliente_id']) ?> |
       <strong>Cuenta acreditación:</strong> <?= e($cuentas[$registro['cuenta_id']] ?? $registro['cuenta_id']) ?> |
       <strong>Monto total:</strong> <?= dinero($registro['monto_total']) ?> |
       <?php if ($contratoTerminado): ?><span class="badge bg-secondary">Terminado</span> <?php endif; ?>
       <a href="?p=contratos">Volver a contratos</a></p>
    <?php if ($contratoTerminado): ?>
    <div class="alert alert-secondary mb-3">Este contrato está <strong>terminado</strong>: solo puede ver cuotas y movimientos. No se pueden agregar cuotas, registrar pagos ni eliminar el contrato.</div>
    <?php endif; ?>

    <?php if (!$contratoTerminado): ?>
    <div class="toolbar d-flex gap-2 mb-3" data-proxima-cuota="<?= count($cuotas) + 1 ?>">
        <button type="button" class="btn btn-primary" id="btnAgregarCuota">Registrar cuota</button>
    </div>
    <?php endif; ?>

    <table class="no-datatable">
        <thead>
            <tr>
                <th>Nº</th>
                <th>Monto</th>
                <th>Vencimiento</th>
                <th>Saldo</th>
                <th>Acciones</th>
                <th>Ver</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($cuotas as $c): ?>
            <?php
                $cid = (int)$c['id'];
                $saldo = (float)($saldoPorCuota[$cid] ?? 0.0);
                $pagada = $saldo <= 0.00001;
            ?>
            <tr>
                <td><?= (int)$c['numero_cuota'] ?></td>
                <td><?= dinero($c['monto']) ?></td>
                <td><?= fechaFormato($c['fecha_vencimiento']) ?></td>
                <td><?= dinero($saldo) ?></td>
                <td>
                    <?php if ($contratoTerminado): ?>
                        <span class="text-muted"><?= $pagada ? 'Pagada' : '—' ?></span>
                    <?php elseif (!$pagada): ?>
                        <div class="d-flex gap-2 flex-wrap">
                            <button type="button" class="btn btn-primary btn-sm btn-pagar-cuota"
                                data-cuota-id="<?= $cid ?>"
                                data-numero="<?= (int)$c['numero_cuota'] ?>"
                                data-saldo="<?= e((string)$saldo) ?>"
                                data-monto="<?= e((string)$c['monto']) ?>">Pagar</button>
                            <button type="button" class="btn btn-secondary btn-sm btn-abonar-cuota"
                                data-cuota-id="<?= $cid ?>"
                                data-numero="<?= (int)$c['numero_cuota'] ?>"
                                data-saldo="<?= e((string)$saldo) ?>"
                                data-monto="<?= e((string)$c['monto']) ?>">Abonar</button>
                        </div>
                    <?php else: ?>
                        <span class="text-muted">Pagada</span>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="d-flex gap-2 flex-wrap">
                        <button type="button" class="btn btn-outline-primary btn-sm btn-ver-cuota-movs" data-cuota-id="<?= $cid ?>" data-tipo="todos">Pagos y abonos</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm btn-ver-cuota-movs" data-cuota-id="<?= $cid ?>" data-tipo="pago">Pagos</button>
                        <button type="button" class="btn btn-outline-dark btn-sm btn-ver-cuota-movs" data-cuota-id="<?= $cid ?>" data-tipo="abono">Abonos</button>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($cuotas)): ?>
            <tr>
                <td class="text-muted">No hay cuotas para este contrato.</td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>

    <p class="mt-3"><a href="<?= MARINA_URL ?>/index.php?p=contratos" class="btn btn-secondary">Volver a contratos</a></p>

    <?php if (!$contratoTerminado): ?>
    <!-- Modal agregar cuota -->
    <div class="modal fade" id="modalAgregarCuota" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="post" action="?p=contratos&accion=cuotas&id=<?= $id ?>">
                    <div class="modal-header">
                        <h5 class="modal-title">Registrar cuota</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div id="modalAgregarCuotaMensaje" class="alert alert-danger d-none"></div>
                        <label class="form-label">Nº cuota</label>
                        <input type="number" class="form-control" id="agregarNumeroCuota" name="numero_cuota" min="1" value="<?= e($cuotaAgregarDatos['numero_cuota'] ?? (count($cuotas) + 1)) ?>">
                        <label class="form-label mt-2">Monto</label>
                        <input type="text" class="form-control" id="agregarMontoCuota" name="monto_cuota" placeholder="0.00" value="<?= e($cuotaAgregarDatos['monto_cuota'] ?? '') ?>">
                        <label class="form-label mt-2">Fecha vencimiento</label>
                        <input type="date" class="form-control" id="agregarFechaVencimiento" name="fecha_vencimiento" value="<?= e($cuotaAgregarDatos['fecha_vencimiento'] ?? '') ?>">
                    </div>
                    <div class="modal-footer">
                        <button type="submit" name="agregar_cuota" class="btn btn-primary">Guardar</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal pagar cuota (saldo completo) -->
    <div class="modal fade" id="modalPagarCuota" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="post" action="?p=contratos&accion=cuotas&id=<?= $id ?>">
                    <input type="hidden" name="registrar_movimiento" value="1">
                    <input type="hidden" name="tipo_movimiento" value="pago">
                    <input type="hidden" name="cuota_mov_id" id="pagarCuotaId" value="">
                    <input type="hidden" name="monto_movimiento" id="pagarMontoMovimiento" value="">
                    <div class="modal-header">
                        <h5 class="modal-title">Registrar pago de cuota</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-2" id="pagarCuotaInfo"></p>
                        <div id="modalPagarCuotaMensaje" class="alert alert-danger d-none"></div>
                        <label class="form-label">Monto</label>
                        <input type="text" class="form-control" id="pagarMontoPreview" value="" disabled>
                        <label class="form-label mt-2">Fecha de pago *</label>
                        <input type="date" class="form-control" id="pagarFechaPago" name="fecha_pago" value="<?= e($cuotaPagarDatos['fecha_pago'] ?? date('Y-m-d')) ?>" required>
                        <label class="form-label mt-2">Concepto (término de la cuota)</label>
                        <input type="text" class="form-control" id="pagarConceptoMovimiento" name="concepto_movimiento" placeholder="Ej.: Cuota febrero, mensualidad 3…" value="<?= e($cuotaPagarDatos['concepto_movimiento'] ?? '') ?>">
                        <label class="form-label mt-2">Forma de pago</label>
                        <select class="form-select" id="pagarFormaPagoId" name="forma_pago_id">
                            <option value="">—</option>
                            <?php foreach ($formas_pago as $fid => $fnom): ?>
                                <option value="<?= (int)$fid ?>" <?= (isset($cuotaPagarDatos['forma_pago_id']) && (int)$cuotaPagarDatos['forma_pago_id'] === (int)$fid) ? 'selected' : '' ?>><?= e($fnom) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label class="form-label mt-2">Referencia banco / comprobante (opcional)</label>
                        <input type="text" class="form-control" id="pagarReferencia" name="referencia_pago" placeholder="Transferencia, voucher…" value="<?= e($cuotaPagarDatos['referencia_pago'] ?? '') ?>">
                        <small class="text-muted">En el estado de cuenta, la columna referencia prioriza el <strong>nº recibo del contrato</strong>.</small>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary">Registrar pago</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal abonar cuota (monto parcial) -->
    <div class="modal fade" id="modalAbonarCuota" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="post" action="?p=contratos&accion=cuotas&id=<?= $id ?>">
                    <input type="hidden" name="registrar_movimiento" value="1">
                    <input type="hidden" name="tipo_movimiento" value="abono">
                    <input type="hidden" name="cuota_mov_id" id="abonarCuotaId" value="">
                    <div class="modal-header">
                        <h5 class="modal-title">Registrar abono de cuota</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-2" id="abonarCuotaInfo"></p>
                        <div id="modalAbonarCuotaMensaje" class="alert alert-danger d-none"></div>
                        <label class="form-label">Monto abono *</label>
                        <input type="text" class="form-control" id="abonarMonto" name="monto_movimiento" required>
                        <label class="form-label mt-2">Fecha de pago *</label>
                        <input type="date" class="form-control" id="abonarFechaPago" name="fecha_pago" value="<?= e($cuotaAbonarDatos['fecha_pago'] ?? date('Y-m-d')) ?>" required>
                        <label class="form-label mt-2">Concepto (término de la cuota)</label>
                        <input type="text" class="form-control" id="abonarConceptoMovimiento" name="concepto_movimiento" placeholder="Ej.: Abono cuota marzo…" value="<?= e($cuotaAbonarDatos['concepto_movimiento'] ?? '') ?>">
                        <label class="form-label mt-2">Forma de pago</label>
                        <select class="form-select" id="abonarFormaPagoId" name="forma_pago_id">
                            <option value="">—</option>
                            <?php foreach ($formas_pago as $fid => $fnom): ?>
                                <option value="<?= (int)$fid ?>" <?= (isset($cuotaAbonarDatos['forma_pago_id']) && (int)$cuotaAbonarDatos['forma_pago_id'] === (int)$fid) ? 'selected' : '' ?>><?= e($fnom) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label class="form-label mt-2">Referencia banco / comprobante (opcional)</label>
                        <input type="text" class="form-control" id="abonarReferencia" name="referencia_pago" placeholder="Transferencia, voucher…" value="<?= e($cuotaAbonarDatos['referencia_pago'] ?? '') ?>">
                        <small class="text-muted">En el estado de cuenta, la referencia mostrada prioriza el <strong>nº recibo del contrato</strong>.</small>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary">Registrar abono</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Modal ver movimientos de cuota -->
    <div class="modal fade" id="modalVerCuotaMovs" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="verCuotaMovsTitle">Movimientos de cuota</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-6">
                            <label class="form-label">Tipo</label>
                            <select class="form-select" id="movsTipoFiltro">
                                <option value="todos">Pagos y abonos</option>
                                <option value="pago">Pagos</option>
                                <option value="abono">Abonos</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-0" id="movsSaldoInfo"></p>
                        </div>
                    </div>
                    <table class="mt-3 no-datatable">
                        <thead>
                            <tr>
                                <th>Fecha pago</th>
                                <th>Monto</th>
                                <th>Forma pago</th>
                                <th>Referencia</th>
                            </tr>
                        </thead>
                        <tbody id="movsTablaBody">
                            <tr>
                                <td class="text-muted">Cargando…</td>
                                <td></td>
                                <td></td>
                                <td></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        window.__contratoNumeroRecibo = <?= json_encode(trim((string) ($registro['numero_recibo'] ?? '')), JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
        window.__cuotaAgregarModal = {
            mostrar: <?= $mostrarModalAgregarCuota ? 'true' : 'false' ?>,
            datos: <?= json_encode($cuotaAgregarDatos, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>,
            error: <?= json_encode($mostrarModalAgregarCuota ? $mensaje : '', JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>
        };
        window.__cuotaPagarModal = {
            mostrar: <?= $mostrarModalPagarCuota ? 'true' : 'false' ?>,
            datos: <?= json_encode($cuotaPagarDatos, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>,
            error: <?= json_encode($mostrarModalPagarCuota ? $mensaje : '', JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>
        };
        window.__cuotaAbonarModal = {
            mostrar: <?= $mostrarModalAbonarCuota ? 'true' : 'false' ?>,
            datos: <?= json_encode($cuotaAbonarDatos, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>,
            error: <?= json_encode($mostrarModalAbonarCuota ? $mensaje : '', JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>
        };
        window.__cuotasMovimientos = <?= json_encode($movsParaJS, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
    </script>
    <?php
    require_once __DIR__ . '/../includes/footer.php';
    return;
}

// --- Lista de contratos + modales
$ok = obtener('ok');
$err = obtener('err');
$mostrarModal = enviado() && ($accion === 'crear' || $accion === 'editar') && $mensaje !== '';
$modalDatos = [
    'id' => $id,
    'cliente_id' => $registro['cliente_id'] ?? ($_POST['cliente_id'] ?? ''),
    'cuenta_id' => $registro['cuenta_id'] ?? ($_POST['cuenta_id'] ?? ''),
    'muelle_id' => $registro['muelle_id'] ?? ($_POST['muelle_id'] ?? ''),
    'slip_id' => $registro['slip_id'] ?? ($_POST['slip_id'] ?? ''),
    'grupo_id' => $registro['grupo_id'] ?? ($_POST['grupo_id'] ?? ''),
    'inmueble_id' => $registro['inmueble_id'] ?? ($_POST['inmueble_id'] ?? ''),
    'fecha_inicio' => $registro['fecha_inicio'] ?? ($_POST['fecha_inicio'] ?? ''),
    'fecha_fin' => $registro['fecha_fin'] ?? ($_POST['fecha_fin'] ?? ''),
    'monto_total' => $registro['monto_total'] ?? ($_POST['monto_total'] ?? ''),
    'observaciones' => $registro['observaciones'] ?? ($_POST['observaciones'] ?? ''),
    'numero_recibo' => $registro['numero_recibo'] ?? ($_POST['numero_recibo'] ?? ''),
    'estado' => (string) ($registro['estado'] ?? ($_POST['estado'] ?? 'activo')),
];
?>
<?php require_once __DIR__ . '/../includes/layout.php'; ?>

<h1>Contratos</h1>
<?php if ($ok): ?><p class="success"><?= e($ok) ?></p><?php endif; ?>
<?php if ($err): ?><p class="error"><?= e($err) ?></p><?php endif; ?>
<?php if ($mensaje && !$mostrarModal): ?><p class="error"><?= e($mensaje) ?></p><?php endif; ?>

<div class="toolbar d-flex gap-2"><button type="button" class="btn btn-primary" id="btnNuevoContrato">Nuevo contrato</button></div>

<table>
    <thead>
        <tr><th>Id</th><th>Cliente</th><th>Cuenta</th><th>Unidad</th><th>Inicio</th><th>Fin</th><th>Monto</th><th>Estado</th><th>Creado por</th><th></th></tr>
    </thead>
    <tbody>
    <?php
    $st = $pdo->query("
        SELECT co.id, co.cliente_id, co.cuenta_id, co.muelle_id, co.slip_id, co.grupo_id, co.inmueble_id, co.fecha_inicio, co.fecha_fin, co.monto_total, co.observaciones, co.numero_recibo, co.activo, COALESCE(co.estado, 'activo') AS estado,
               cl.nombre AS cliente_nombre, b.nombre AS banco_nombre, cu.nombre AS cuenta_nombre,
               m.nombre AS muelle_nombre, s.nombre AS slip_nombre, g.nombre AS grupo_nombre, i.nombre AS inmueble_nombre, u.nombre AS creado_por
        FROM contratos co
        JOIN clientes cl ON co.cliente_id = cl.id
        JOIN cuentas cu ON co.cuenta_id = cu.id
        JOIN bancos b ON cu.banco_id = b.id
        LEFT JOIN muelles m ON co.muelle_id = m.id
        LEFT JOIN slips s ON co.slip_id = s.id
        LEFT JOIN grupos g ON co.grupo_id = g.id
        LEFT JOIN inmuebles i ON co.inmueble_id = i.id
        LEFT JOIN usuarios u ON co.created_by = u.id
        ORDER BY co.id DESC
    ");
    while ($r = $st->fetch()): ?>
        <tr>
            <td><?= $r['id'] ?></td>
            <td><?= e($r['cliente_nombre']) ?></td>
            <td><?= e($r['banco_nombre'] . ' - ' . $r['cuenta_nombre']) ?></td>
            <td><?= e(($r['grupo_nombre'] ? ($r['grupo_nombre'] . ' / ' . $r['inmueble_nombre']) : (($r['muelle_nombre'] ?? '—') . ' / ' . ($r['slip_nombre'] ?? '—')))) ?></td>
            <td><?= fechaFormato($r['fecha_inicio']) ?></td>
            <td><?= fechaFormato($r['fecha_fin']) ?></td>
            <td><?= dinero($r['monto_total']) ?></td>
            <td><?php $est = (string) ($r['estado'] ?? 'activo'); ?>
                <span class="badge <?= $est === 'activo' ? 'bg-success' : 'bg-secondary' ?>"><?= $est === 'activo' ? 'Activo' : 'Terminado' ?></span>
            </td>
            <td><?= e($r['creado_por'] ?? '—') ?></td>
            <td class="acciones">
                <a href="?p=contratos&accion=cuotas&id=<?= $r['id'] ?>" class="btn btn-primary btn-sm">Cuotas</a>
                <a href="<?= MARINA_URL ?>/index.php?p=contratos-electricidad&amp;id=<?= (int) $r['id'] ?>" class="btn btn-info btn-sm text-white">Electricidad</a>
                <?php if ($est === 'activo'): ?>
                <button type="button" class="btn btn-warning btn-sm btn-liberar-contrato" data-id="<?= (int)$r['id'] ?>" data-nombre="<?= e($r['cliente_nombre'] . ' #' . $r['id']) ?>">Liberar</button>
                <button type="button" class="btn btn-secondary btn-sm btn-editar-contrato"
                    data-id="<?= (int)$r['id'] ?>"
                    data-cliente-id="<?= (int)$r['cliente_id'] ?>"
                    data-cuenta-id="<?= (int)$r['cuenta_id'] ?>"
                    data-muelle-id="<?= (int)$r['muelle_id'] ?>"
                    data-slip-id="<?= (int)$r['slip_id'] ?>"
                    data-grupo-id="<?= (int)($r['grupo_id'] ?? 0) ?>"
                    data-inmueble-id="<?= (int)($r['inmueble_id'] ?? 0) ?>"
                    data-fecha-inicio="<?= e($r['fecha_inicio']) ?>"
                    data-fecha-fin="<?= e($r['fecha_fin']) ?>"
                    data-monto-total="<?= e($r['monto_total']) ?>"
                    data-observaciones="<?= e($r['observaciones'] ?? '') ?>"
                    data-numero-recibo="<?= e($r['numero_recibo'] ?? '') ?>">Editar</button>
                <?php endif; ?>
                <?php if ($est === 'activo'): ?>
                <button type="button" class="btn btn-danger btn-sm btn-eliminar-contrato" data-id="<?= (int)$r['id'] ?>" data-nombre="<?= e($r['cliente_nombre'] . ' #' . $r['id']) ?>">Eliminar</button>
                <?php endif; ?>
            </td>
        </tr>
    <?php endwhile; ?>
    </tbody>
</table>

<!-- Modal crear/editar contrato -->
<div class="modal fade" id="contratoModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form method="post" action="?p=contratos">
                <input type="hidden" name="accion" id="contratoFormAccion" value="crear">
                <input type="hidden" name="id" id="contratoFormId" value="">
                <div class="modal-header">
                    <h5 class="modal-title" id="contratoModalTitle">Nuevo contrato</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="contratoModalMensaje" class="alert alert-danger d-none"></div>
                    <div class="row">
                        <div class="col-md-6">
                            <label>Cliente *</label>
                            <select class="form-select" id="contratoClienteId" name="cliente_id" required>
                                <option value="">Seleccione</option>
                                <?php foreach ($clientes as $cid => $cnom): ?>
                                    <option value="<?= (int)$cid ?>"><?= e($cnom) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <label class="mt-2">Cuenta acreditación *</label>
                            <select class="form-select" id="contratoCuentaId" name="cuenta_id" required>
                                <option value="">Seleccione</option>
                                <?php foreach ($cuentas as $cid => $cnom): ?>
                                    <option value="<?= (int)$cid ?>"><?= e($cnom) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <label class="mt-2">Muelle</label>
                            <select class="form-select" id="contratoMuelleId" name="muelle_id">
                                <option value="">Seleccione (opcional)</option>
                                <?php foreach ($muelles as $mid => $mnom): ?>
                                    <option value="<?= (int)$mid ?>"><?= e($mnom) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <label class="mt-2">Slip</label>
                            <select class="form-select" id="contratoSlipId" name="slip_id">
                                <option value="">Seleccione (opcional)</option>
                                <?php foreach ($slips as $sid => $snom): ?>
                                    <option value="<?= (int)$sid ?>"><?= e($snom) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <label class="mt-2">Grupo</label>
                            <select class="form-select" id="contratoGrupoId" name="grupo_id">
                                <option value="">Seleccione (opcional)</option>
                                <?php foreach ($grupos as $gid => $gnom): ?>
                                    <option value="<?= (int)$gid ?>"><?= e($gnom) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <label class="mt-2">Inmueble</label>
                            <select class="form-select" id="contratoInmuebleId" name="inmueble_id">
                                <option value="">Seleccione (opcional)</option>
                                <?php foreach ($inmuebles as $iid => $inom): ?>
                                    <option value="<?= (int)$iid ?>"><?= e($inom) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted d-block mt-1">Debe indicar al menos una unidad: muelle/slip o grupo/inmueble.</small>
                        </div>
                        <div class="col-md-6">
                            <label>Fecha inicio *</label>
                            <input type="date" class="form-control" id="contratoFechaInicio" name="fecha_inicio" required>
                            <label class="mt-2">Fecha fin *</label>
                            <input type="date" class="form-control" id="contratoFechaFin" name="fecha_fin" required>
                            <label class="mt-2">Monto total *</label>
                            <input type="text" class="form-control" id="contratoMontoTotal" name="monto_total" required>
                            <label class="mt-2">Observaciones</label>
                            <textarea class="form-control" id="contratoObservaciones" name="observaciones" rows="2"></textarea>
                            <label class="mt-2">Nº recibo al cliente</label>
                            <input type="text" class="form-control" id="contratoNumeroRecibo" name="numero_recibo" maxlength="100" placeholder="Número de recibo emitido al firmar el contrato">
                            <small class="text-muted">Aparece en estado de cuenta bancaria como referencia en los cobros de cuotas.</small>
                            <p class="text-muted small mt-2 mb-0">El contrato queda <strong>Activo</strong> al guardar. Use <strong>Liberar</strong> en la lista o en el mapa para pasarlo a <strong>Terminado</strong> y dejar la unidad libre.</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Guardar</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="confirmEliminarContratoModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <form method="post" action="?p=contratos&accion=eliminar">
                <input type="hidden" name="id" id="contratoDeleteId" value="">
                <div class="modal-header"><h5 class="modal-title">Confirmar eliminación</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">¿Eliminar este contrato y sus cuotas?</div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Eliminar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="confirmLiberarContratoModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <form method="post" action="?p=contratos&accion=liberar">
                <input type="hidden" name="id" id="contratoLiberarId" value="">
                <div class="modal-header"><h5 class="modal-title">Liberar contrato</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">¿Liberar <span id="contratoLiberarNombre"></span>? La unidad quedará libre y el contrato pasará a <strong>Terminado</strong>.</div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning">Liberar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>window.__contratoModal = { mostrar: <?= $mostrarModal ? 'true' : 'false' ?>, datos: <?= json_encode($modalDatos, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>, error: <?= json_encode($mensaje, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?> };</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
