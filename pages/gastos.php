<?php
/**
 * Gastos como facturas (monto total) y abonos (gasto_pagos) hasta cubrir el total.
 */
$titulo = 'Gastos';

$pdo = getDb();
require_once __DIR__ . '/../includes/gasto_helpers.php';
$accion = obtener('accion');
$id = (int) obtener('id');
$mensaje = '';

$st = $pdo->query("
    SELECT p.id, p.nombre
    FROM partidas p
    WHERE NOT EXISTS (SELECT 1 FROM partidas h WHERE h.parent_id = p.id)
    ORDER BY p.nombre
");
$partidas_hoja = $st->fetchAll(PDO::FETCH_KEY_PAIR);

if ($accion === 'eliminar_abono' && enviado()) {
    $pago_id = (int) ($_POST['pago_id'] ?? 0);
    $gasto_id_ab = (int) ($_POST['gasto_id'] ?? 0);
    if ($pago_id < 1 || $gasto_id_ab < 1) {
        redirigir(MARINA_URL . '/index.php?p=gastos&err=' . rawurlencode('Solicitud no válida.'));
    }
    try {
        $stChk = $pdo->prepare('SELECT id FROM gasto_pagos WHERE id = ? AND gasto_id = ? LIMIT 1');
        $stChk->execute([$pago_id, $gasto_id_ab]);
        if (!$stChk->fetch()) {
            redirigir(MARINA_URL . '/index.php?p=gastos&err=' . rawurlencode('Abono no encontrado.'));
        }
        $pdo->beginTransaction();
        $pdo->prepare('DELETE FROM gasto_pagos WHERE id = ? AND gasto_id = ?')->execute([$pago_id, $gasto_id_ab]);
        marina_gasto_refrescar_estado($pdo, $gasto_id_ab);
        $uidAb = usuarioId();
        if ($uidAb !== null) {
            $pdo->prepare('UPDATE gastos SET updated_by = ? WHERE id = ?')->execute([$uidAb, $gasto_id_ab]);
        }
        $pdo->commit();
        redirigir(MARINA_URL . '/index.php?p=gastos&ok=' . rawurlencode('Abono eliminado; el pendiente de la factura se actualizó.'));
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        redirigir(MARINA_URL . '/index.php?p=gastos&err=' . rawurlencode('No se pudo eliminar el abono.'));
    }
}

if ($accion === 'eliminar' && $id > 0 && enviado()) {
    $pag = marina_gasto_total_pagado($pdo, $id);
    if ($pag > 0.001) {
        redirigir(MARINA_URL . '/index.php?p=gastos&err=' . rawurlencode('No se puede eliminar: la factura tiene abonos registrados.'));
    }
    try {
        $pdo->prepare('DELETE FROM gastos WHERE id = ?')->execute([$id]);
        redirigir(MARINA_URL . '/index.php?p=gastos&ok=' . rawurlencode('Factura eliminada'));
    } catch (Throwable $e) {
        redirigir(MARINA_URL . '/index.php?p=gastos&err=' . rawurlencode(marinaMensajeErrorIntegridad($e)));
    }
}

if (enviado() && $accion === 'abonar' && $id > 0) {
    $montoAb = (float) str_replace(',', '.', (string) ($_POST['monto_abono'] ?? 0));
    $fecha_pago = trim((string) ($_POST['fecha_pago'] ?? ''));
    $cuenta_id = (int) ($_POST['cuenta_id'] ?? 0);
    $forma_pago_id = (int) ($_POST['forma_pago_id'] ?? 0);
    $referencia = trim((string) ($_POST['referencia_abono'] ?? ''));
    $observaciones = trim((string) ($_POST['observaciones_abono'] ?? ''));
    $uid = usuarioId();

    $stG = $pdo->prepare('SELECT id, monto, estado FROM gastos WHERE id = ? FOR UPDATE');
    $pdo->beginTransaction();
    try {
        $stG->execute([$id]);
        $g = $stG->fetch(PDO::FETCH_ASSOC);
        if (!$g) {
            $pdo->rollBack();
            $mensaje = 'Factura no encontrada.';
        } elseif (($g['estado'] ?? '') === 'pagada') {
            $pdo->rollBack();
            $mensaje = 'La factura ya está pagada; no se permiten más abonos.';
        } elseif ($montoAb <= 0) {
            $pdo->rollBack();
            $mensaje = 'El monto del abono debe ser mayor a 0.';
        } elseif ($fecha_pago === '') {
            $pdo->rollBack();
            $mensaje = 'La fecha del pago es obligatoria.';
        } elseif ($cuenta_id < 1) {
            $pdo->rollBack();
            $mensaje = 'Debe seleccionar la cuenta desde la que se paga.';
        } elseif ($forma_pago_id < 1) {
            $pdo->rollBack();
            $mensaje = 'Debe seleccionar la forma de pago.';
        } else {
            $stFp = $pdo->prepare('SELECT tipo_movimiento FROM formas_pago WHERE id = ?');
            $stFp->execute([$forma_pago_id]);
            $tf = $stFp->fetch(PDO::FETCH_ASSOC);
            if (!$tf || ($tf['tipo_movimiento'] ?? '') !== 'costo') {
                $pdo->rollBack();
                $mensaje = 'La forma de pago debe ser de tipo costo (egreso).';
            } else {
                $montoFact = round((float) ($g['monto'] ?? 0), 2);
                $ya = marina_gasto_total_pagado($pdo, $id);
                $pend = round($montoFact - $ya, 2);
                if ($montoAb - $pend > 0.009) {
                    $pdo->rollBack();
                    $mensaje = 'El abono no puede superar el saldo pendiente (' . dinero($pend) . ').';
                } else {
                    $pdo->prepare('
                        INSERT INTO gasto_pagos (gasto_id, monto, fecha_pago, cuenta_id, forma_pago_id, referencia, observaciones, created_by, updated_by)
                        VALUES (?,?,?,?,?,?,?,?,?)
                    ')->execute([
                        $id,
                        round($montoAb, 2),
                        $fecha_pago,
                        $cuenta_id,
                        $forma_pago_id,
                        $referencia !== '' ? $referencia : null,
                        $observaciones !== '' ? $observaciones : null,
                        $uid,
                        $uid,
                    ]);
                    marina_gasto_refrescar_estado($pdo, $id);
                    $pdo->commit();
                    redirigir(MARINA_URL . '/index.php?p=gastos&ok=' . rawurlencode('Abono registrado'));
                }
            }
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $mensaje = 'No se pudo registrar el abono.';
    }
}

if (enviado() && ($accion === 'crear' || $accion === 'editar')) {
    $partida_id = (int) ($_POST['partida_id'] ?? 0);
    $proveedor_id = (int) ($_POST['proveedor_id'] ?? 0);
    $monto = (float) str_replace(',', '.', (string) ($_POST['monto'] ?? 0));
    $fecha_gasto = trim((string) ($_POST['fecha_gasto'] ?? ''));
    $referencia = trim((string) ($_POST['referencia'] ?? ''));
    $observaciones = trim((string) ($_POST['observaciones'] ?? ''));
    $uid = usuarioId();

    if ($partida_id < 1 || $proveedor_id < 1 || $monto <= 0 || $fecha_gasto === '') {
        $mensaje = 'Partida, proveedor, monto total y fecha de factura son obligatorios.';
    } elseif ($accion === 'editar' && $id > 0) {
        $stG = $pdo->prepare('SELECT id, monto, estado FROM gastos WHERE id = ?');
        $stG->execute([$id]);
        $g = $stG->fetch(PDO::FETCH_ASSOC);
        if (!$g) {
            $mensaje = 'Factura no encontrada.';
        } elseif (($g['estado'] ?? '') === 'pagada') {
            $mensaje = 'No se puede editar una factura ya pagada.';
        } else {
            $ya = marina_gasto_total_pagado($pdo, $id);
            if (round($monto, 2) + 0.001 < round($ya, 2)) {
                $mensaje = 'El monto total no puede ser menor a lo ya abonado (' . dinero($ya) . ').';
            } else {
                try {
                    $pdo->prepare('UPDATE gastos SET partida_id=?, proveedor_id=?, cuenta_id=NULL, forma_pago_id=NULL, monto=?, fecha_gasto=?, referencia=?, observaciones=?, updated_by=? WHERE id=?')
                        ->execute([$partida_id, $proveedor_id, $monto, $fecha_gasto, $referencia, $observaciones, $uid, $id]);
                    marina_gasto_refrescar_estado($pdo, $id);
                    redirigir(MARINA_URL . '/index.php?p=gastos&ok=' . rawurlencode('Factura actualizada'));
                } catch (Throwable $e) {
                    $mensaje = 'No se pudo actualizar.';
                }
            }
        }
    } else {
        try {
            $pdo->prepare('INSERT INTO gastos (partida_id, proveedor_id, cuenta_id, forma_pago_id, monto, fecha_gasto, referencia, observaciones, created_by, updated_by, estado) VALUES (?,?,NULL,NULL,?,?,?,?,?,?,\'pendiente\')')
                ->execute([$partida_id, $proveedor_id, $monto, $fecha_gasto, $referencia, $observaciones, $uid, $uid]);
            redirigir(MARINA_URL . '/index.php?p=gastos&ok=' . rawurlencode('Factura creada; registre abonos desde «Abonar».'));
        } catch (Throwable $e) {
            $mensaje = 'No se pudo crear la factura.';
        }
    }
}

$registro = null;
if ($accion === 'editar' && $id > 0 && !enviado()) {
    $st = $pdo->prepare('SELECT * FROM gastos WHERE id = ?');
    $st->execute([$id]);
    $registro = $st->fetch();
    if (!$registro) {
        redirigir(MARINA_URL . '/index.php?p=gastos');
    }
}

$proveedores = $pdo->query('SELECT id, nombre FROM proveedores ORDER BY nombre')->fetchAll(PDO::FETCH_KEY_PAIR);
$cuentas = $pdo->query('SELECT c.id, CONCAT(b.nombre, " - ", c.nombre) AS nom FROM cuentas c JOIN bancos b ON c.banco_id = b.id ORDER BY b.nombre, c.nombre')->fetchAll(PDO::FETCH_KEY_PAIR);
$formas_pago = $pdo->query("SELECT id, nombre FROM formas_pago WHERE tipo_movimiento = 'costo' ORDER BY nombre")->fetchAll(PDO::FETCH_KEY_PAIR);

$ok = obtener('ok');
$err = obtener('err');
$mostrarModal = enviado() && ($accion === 'crear' || $accion === 'editar') && $mensaje !== '';
$mostrarModalAbono = enviado() && $accion === 'abonar' && $mensaje !== '';

$modalDatos = [
    'id' => $id,
    'partida_id' => $registro['partida_id'] ?? ($_POST['partida_id'] ?? ''),
    'proveedor_id' => $registro['proveedor_id'] ?? ($_POST['proveedor_id'] ?? ''),
    'monto' => $registro['monto'] ?? ($_POST['monto'] ?? ''),
    'fecha_gasto' => $registro['fecha_gasto'] ?? ($_POST['fecha_gasto'] ?? date('Y-m-d')),
    'referencia' => $registro['referencia'] ?? ($_POST['referencia'] ?? ''),
    'observaciones' => $registro['observaciones'] ?? ($_POST['observaciones'] ?? ''),
    'estado' => $registro['estado'] ?? ($_POST['estado'] ?? 'pendiente'),
];
$modalAbonoDatos = [
    'gasto_id' => $id,
    'monto_abono' => $_POST['monto_abono'] ?? '',
    'fecha_pago' => $_POST['fecha_pago'] ?? date('Y-m-d'),
    'cuenta_id' => $_POST['cuenta_id'] ?? '',
    'forma_pago_id' => $_POST['forma_pago_id'] ?? '',
    'referencia_abono' => $_POST['referencia_abono'] ?? '',
    'observaciones_abono' => $_POST['observaciones_abono'] ?? '',
];
?>
<?php require_once __DIR__ . '/../includes/layout.php'; ?>

<h1>Gastos / Facturas</h1>
<p class="text-muted small">Primero se registra la factura (monto total). Luego se registran uno o más abonos hasta cubrir el total; al completarse, el estado pasa a <strong>pagada</strong> y no se admiten más pagos.</p>

<?php if ($ok): ?><p class="success"><?= e($ok) ?></p><?php endif; ?>
<?php if ($err): ?><p class="error"><?= e($err) ?></p><?php endif; ?>
<?php if ($mensaje && !$mostrarModal && !$mostrarModalAbono): ?><p class="error"><?= e($mensaje) ?></p><?php endif; ?>

<div class="toolbar d-flex gap-2"><button type="button" class="btn btn-primary" id="btnNuevoGasto">Nueva factura</button></div>

<?php if (empty($partidas_hoja)): ?>
<p class="error">No hay partidas hoja. Cree partidas en el módulo Partidas; los gastos se registran en las que no tienen subpartidas.</p>
<?php endif; ?>

<table>
    <thead>
        <tr>
            <th>Id</th>
            <th>Partida</th>
            <th>Proveedor</th>
            <th>Total factura</th>
            <th>Abonado</th>
            <th>Pendiente</th>
            <th>Estado</th>
            <th>Fecha factura</th>
            <th>Creado por</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
    <?php
    $st = $pdo->query("
        SELECT g.*, p.nombre AS partida_nombre, pr.nombre AS proveedor_nombre, u.nombre AS creado_por,
               (SELECT COALESCE(SUM(gp.monto), 0) FROM gasto_pagos gp WHERE gp.gasto_id = g.id) AS total_pagado
        FROM gastos g
        JOIN partidas p ON g.partida_id = p.id
        JOIN proveedores pr ON g.proveedor_id = pr.id
        LEFT JOIN usuarios u ON g.created_by = u.id
        ORDER BY g.fecha_gasto DESC, g.id DESC
    ");
    $facturas = $st->fetchAll(PDO::FETCH_ASSOC);
    $abonosPorGasto = [];
    $idsFacturas = array_map(static function ($row) {
        return (int) ($row['id'] ?? 0);
    }, $facturas);
    $idsFacturas = array_values(array_filter($idsFacturas, static function ($id) {
        return $id > 0;
    }));
    if ($idsFacturas !== []) {
        $ph = implode(',', array_fill(0, count($idsFacturas), '?'));
        $stAb = $pdo->prepare("
            SELECT gp.id, gp.gasto_id, gp.monto, gp.fecha_pago, gp.referencia, gp.observaciones,
                   CONCAT_WS(' - ', b.nombre, c.nombre) AS cuenta_nombre,
                   fp.nombre AS forma_pago_nombre
            FROM gasto_pagos gp
            LEFT JOIN cuentas c ON c.id = gp.cuenta_id
            LEFT JOIN bancos b ON b.id = c.banco_id
            LEFT JOIN formas_pago fp ON fp.id = gp.forma_pago_id
            WHERE gp.gasto_id IN ($ph)
            ORDER BY gp.fecha_pago ASC, gp.id ASC
        ");
        $stAb->execute($idsFacturas);
        while ($a = $stAb->fetch(PDO::FETCH_ASSOC)) {
            $gid = (int) ($a['gasto_id'] ?? 0);
            if ($gid < 1) {
                continue;
            }
            if (!isset($abonosPorGasto[$gid])) {
                $abonosPorGasto[$gid] = [];
            }
            $abonosPorGasto[$gid][] = [
                'id' => (int) ($a['id'] ?? 0),
                'fecha_pago' => (string) ($a['fecha_pago'] ?? ''),
                'monto' => (float) ($a['monto'] ?? 0),
                'cuenta_nombre' => trim((string) ($a['cuenta_nombre'] ?? '')) !== '' ? trim((string) $a['cuenta_nombre']) : '—',
                'forma_pago_nombre' => trim((string) ($a['forma_pago_nombre'] ?? '')) !== '' ? trim((string) $a['forma_pago_nombre']) : '—',
                'referencia' => (string) ($a['referencia'] ?? ''),
                'observaciones' => (string) ($a['observaciones'] ?? ''),
            ];
        }
    }
    foreach ($facturas as $r):
        $totalF = round((float) ($r['monto'] ?? 0), 2);
        $totalP = round((float) ($r['total_pagado'] ?? 0), 2);
        $pend = round($totalF - $totalP, 2);
        if ($pend < 0) {
            $pend = 0.0;
        }
        $est = (string) ($r['estado'] ?? 'pendiente');
        $puedeEditar = $est !== 'pagada';
        $puedeAbonar = $est !== 'pagada' && $pend > 0.001;
        $puedeEliminar = $totalP < 0.001;
        ?>
        <tr>
            <td><?= (int) $r['id'] ?></td>
            <td><?= e($r['partida_nombre']) ?></td>
            <td><?= e($r['proveedor_nombre']) ?></td>
            <td><?= dinero($totalF) ?></td>
            <td><?= dinero($totalP) ?></td>
            <td><?= dinero($pend) ?></td>
            <td><span class="badge <?= $est === 'pagada' ? 'text-bg-success' : 'text-bg-warning' ?>"><?= e($est === 'pagada' ? 'Pagada' : 'Pendiente') ?></span></td>
            <td><?= fechaFormato($r['fecha_gasto']) ?></td>
            <td><?= e($r['creado_por'] ?? '—') ?></td>
            <td class="acciones">
                <?php
                $fid = (int) $r['id'];
                $abonosJson = json_encode($abonosPorGasto[$fid] ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
                ?>
                <button type="button" class="btn btn-outline-info btn-sm btn-ver-abonos-gasto"
                    data-factura-id="<?= $fid ?>"
                    data-partida="<?= e($r['partida_nombre']) ?>"
                    data-proveedor="<?= e($r['proveedor_nombre']) ?>"
                    data-abonos="<?= htmlspecialchars($abonosJson, ENT_QUOTES, 'UTF-8') ?>">Ver abonos</button>
                <?php if ($puedeAbonar): ?>
                    <button type="button" class="btn btn-success btn-sm btn-abonar-gasto"
                        data-id="<?= $fid ?>"
                        data-pendiente="<?= e((string) $pend) ?>"
                        data-partida="<?= e($r['partida_nombre']) ?>"
                        data-proveedor="<?= e($r['proveedor_nombre']) ?>">Abonar</button>
                <?php endif; ?>
                <?php if ($puedeEditar): ?>
                    <button type="button" class="btn btn-secondary btn-sm btn-editar-gasto"
                        data-id="<?= (int) $r['id'] ?>"
                        data-partida-id="<?= (int) $r['partida_id'] ?>"
                        data-proveedor-id="<?= (int) $r['proveedor_id'] ?>"
                        data-monto="<?= e($r['monto']) ?>"
                        data-fecha-gasto="<?= e($r['fecha_gasto']) ?>"
                        data-referencia="<?= e($r['referencia'] ?? '') ?>"
                        data-observaciones="<?= e($r['observaciones'] ?? '') ?>"
                        data-estado="<?= e($est) ?>">Editar factura</button>
                <?php endif; ?>
                <?php if ($puedeEliminar): ?>
                    <button type="button" class="btn btn-danger btn-sm btn-eliminar-gasto" data-id="<?= (int) $r['id'] ?>" data-nombre="<?= e($r['partida_nombre'] . ' — ' . dinero($totalF)) ?>">Eliminar</button>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<!-- Modal factura -->
<div class="modal fade" id="gastoModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form method="post" action="?p=gastos">
                <input type="hidden" name="accion" id="gastoFormAccion" value="crear">
                <input type="hidden" name="id" id="gastoFormId" value="">
                <div class="modal-header">
                    <h5 class="modal-title" id="gastoModalTitle">Nueva factura</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="gastoModalMensaje" class="alert alert-danger d-none"></div>
                    <p class="small text-muted">La cuenta y la forma de pago se registran en cada <strong>abono</strong>, no en la factura.</p>
                    <div class="row">
                        <div class="col-md-6">
                            <label>Partida (hoja) *</label>
                            <select class="form-select" id="gastoPartidaId" name="partida_id" required>
                                <option value="">Seleccione</option>
                                <?php foreach ($partidas_hoja as $pid => $pnom): ?>
                                    <option value="<?= (int) $pid ?>"><?= e($pnom) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <label class="mt-2">Proveedor *</label>
                            <select class="form-select" id="gastoProveedorId" name="proveedor_id" required>
                                <option value="">Seleccione</option>
                                <?php foreach ($proveedores as $pid => $pnom): ?>
                                    <option value="<?= (int) $pid ?>"><?= e($pnom) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <label class="mt-2">Monto total de la factura *</label>
                            <input type="text" class="form-control" id="gastoMonto" name="monto" required>
                            <label class="mt-2">Fecha de la factura *</label>
                            <input type="date" class="form-control" id="gastoFechaGasto" name="fecha_gasto" required>
                        </div>
                        <div class="col-md-6">
                            <label>Referencia</label>
                            <input type="text" class="form-control" id="gastoReferencia" name="referencia">
                            <label class="mt-2">Observaciones</label>
                            <textarea class="form-control" id="gastoObservaciones" name="observaciones" rows="4"></textarea>
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

<!-- Modal listado abonos -->
<div class="modal fade" id="gastoVerAbonosModal" tabindex="-1" aria-labelledby="gastoVerAbonosModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="gastoVerAbonosModalLabel">Abonos de la factura</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-2" id="gastoVerAbonosResumen"></p>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Fecha pago</th>
                                <th class="text-end">Monto</th>
                                <th>Cuenta</th>
                                <th>Forma de pago</th>
                                <th>Referencia</th>
                                <th>Observaciones</th>
                                <th class="text-end" style="width: 6rem">Acción</th>
                            </tr>
                        </thead>
                        <tbody id="gastoVerAbonosTbody"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal abono -->
<div class="modal fade" id="gastoAbonoModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="?p=gastos">
                <input type="hidden" name="accion" value="abonar">
                <input type="hidden" name="id" id="gastoAbonoGastoId" value="">
                <div class="modal-header">
                    <h5 class="modal-title">Registrar abono</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="gastoAbonoModalMensaje" class="alert alert-danger d-none"></div>
                    <p class="small mb-2" id="gastoAbonoResumen"></p>
                    <p class="small fw-semibold" id="gastoAbonoPendienteTexto"></p>
                    <label>Monto del abono *</label>
                    <input type="text" class="form-control" name="monto_abono" id="gastoAbonoMonto" required>
                    <label class="mt-2">Fecha del pago *</label>
                    <input type="date" class="form-control" name="fecha_pago" id="gastoAbonoFechaPago" required>
                    <label class="mt-2">Cuenta *</label>
                    <select class="form-select" name="cuenta_id" id="gastoAbonoCuentaId" required>
                        <option value="">Seleccione</option>
                        <?php foreach ($cuentas as $cid => $cnom): ?>
                            <option value="<?= (int) $cid ?>"><?= e($cnom) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label class="mt-2">Forma de pago *</label>
                    <select class="form-select" name="forma_pago_id" id="gastoAbonoFormaPagoId" required>
                        <option value="">Seleccione</option>
                        <?php foreach ($formas_pago as $fid => $fnom): ?>
                            <option value="<?= (int) $fid ?>"><?= e($fnom) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label class="mt-2">Referencia</label>
                    <input type="text" class="form-control" name="referencia_abono" id="gastoAbonoReferencia">
                    <label class="mt-2">Observaciones</label>
                    <textarea class="form-control" name="observaciones_abono" id="gastoAbonoObservaciones" rows="2"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Registrar abono</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="confirmEliminarAbonoGastoModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <form method="post" action="?p=gastos">
                <input type="hidden" name="accion" value="eliminar_abono">
                <input type="hidden" name="pago_id" id="gastoAbonoDeletePagoId" value="">
                <input type="hidden" name="gasto_id" id="gastoAbonoDeleteGastoId" value="">
                <div class="modal-header">
                    <h5 class="modal-title">Eliminar abono</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="small mb-0" id="gastoAbonoDeleteTexto"></p>
                    <p class="small text-muted mt-2 mb-0">El monto vuelve al saldo pendiente de la factura (ya no contará como pagado).</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Eliminar abono</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="confirmEliminarGastoModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <form method="post" action="?p=gastos&accion=eliminar">
                <input type="hidden" name="id" id="gastoDeleteId" value="">
                <div class="modal-header"><h5 class="modal-title">Confirmar eliminación</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">¿Eliminar esta factura sin abonos?</div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Eliminar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
window.__gastoModal = { mostrar: <?= $mostrarModal ? 'true' : 'false' ?>, datos: <?= json_encode($modalDatos, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>, error: <?= json_encode($mensaje, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?> };
window.__gastoAbonoModal = { mostrar: <?= $mostrarModalAbono ? 'true' : 'false' ?>, datos: <?= json_encode($modalAbonoDatos, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>, error: <?= json_encode($mensaje, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?> };
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
