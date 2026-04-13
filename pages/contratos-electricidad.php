<?php
/**
 * Facturas de electricidad por contrato: total + abonos o pago total (ingreso a la cuenta del contrato).
 */
$titulo = 'Electricidad — contrato';
$pdo = getDb();
require_once __DIR__ . '/../includes/electricidad_helpers.php';

$id = (int) obtener('id');
$mensaje = '';
$ok = obtener('ok');
$err = obtener('err');

if ($id < 1) {
    redirigir(MARINA_URL . '/index.php?p=contratos');
}

$stCo = $pdo->prepare('SELECT co.*, cl.nombre AS cliente_nombre FROM contratos co JOIN clientes cl ON cl.id = co.cliente_id WHERE co.id = ?');
$stCo->execute([$id]);
$contrato = $stCo->fetch(PDO::FETCH_ASSOC);
if (!$contrato) {
    redirigir(MARINA_URL . '/index.php?p=contratos');
}

$cuentaContratoId = (int) ($contrato['cuenta_id'] ?? 0);
$cuentaContratoNom = '—';
if ($cuentaContratoId > 0) {
    $stCn = $pdo->prepare('SELECT CONCAT(b.nombre, " - ", c.nombre) FROM cuentas c JOIN bancos b ON b.id = c.banco_id WHERE c.id = ?');
    $stCn->execute([$cuentaContratoId]);
    $cuentaContratoNom = (string) ($stCn->fetchColumn() ?: '—');
}
$uid = usuarioId();

if (enviado() && isset($_POST['eliminar_factura_electricidad'])) {
    $fid = (int) ($_POST['factura_id'] ?? 0);
    if ($fid < 1) {
        redirigir(MARINA_URL . '/index.php?p=contratos-electricidad&id=' . $id . '&err=' . rawurlencode('Factura no válida.'));
    }
    $stF = $pdo->prepare('SELECT id FROM contrato_electricidad_facturas WHERE id = ? AND contrato_id = ?');
    $stF->execute([$fid, $id]);
    if (!$stF->fetch()) {
        redirigir(MARINA_URL . '/index.php?p=contratos-electricidad&id=' . $id . '&err=' . rawurlencode('Factura no encontrada.'));
    }
    if (marina_electricidad_total_pagado($pdo, $fid) > 0.001) {
        redirigir(MARINA_URL . '/index.php?p=contratos-electricidad&id=' . $id . '&err=' . rawurlencode('No se puede eliminar: ya hay pagos registrados.'));
    }
    try {
        $pdo->prepare('DELETE FROM contrato_electricidad_facturas WHERE id = ? AND contrato_id = ?')->execute([$fid, $id]);
        redirigir(MARINA_URL . '/index.php?p=contratos-electricidad&id=' . $id . '&ok=' . rawurlencode('Factura eliminada.'));
    } catch (Throwable $e) {
        redirigir(MARINA_URL . '/index.php?p=contratos-electricidad&id=' . $id . '&err=' . rawurlencode('No se pudo eliminar.'));
    }
}

if (enviado() && isset($_POST['crear_factura_electricidad'])) {
    $monto = (float) str_replace(',', '.', (string) ($_POST['monto_total'] ?? 0));
    $fecha_factura = trim((string) ($_POST['fecha_factura'] ?? ''));
    $numero_factura = trim((string) ($_POST['numero_factura'] ?? ''));
    $periodo_desde = trim((string) ($_POST['periodo_desde'] ?? ''));
    $periodo_hasta = trim((string) ($_POST['periodo_hasta'] ?? ''));
    $observaciones = trim((string) ($_POST['observaciones'] ?? ''));
    if ($monto <= 0 || $fecha_factura === '') {
        $mensaje = 'Monto total y fecha de factura son obligatorios.';
    } else {
        try {
            $pdo->prepare('
                INSERT INTO contrato_electricidad_facturas
                (contrato_id, monto_total, fecha_factura, numero_factura, periodo_desde, periodo_hasta, observaciones, estado, created_by, updated_by)
                VALUES (?,?,?,?,?,?,?,\'pendiente\',?,?)
            ')->execute([
                $id,
                round($monto, 2),
                $fecha_factura,
                $numero_factura !== '' ? $numero_factura : null,
                $periodo_desde !== '' ? $periodo_desde : null,
                $periodo_hasta !== '' ? $periodo_hasta : null,
                $observaciones !== '' ? $observaciones : null,
                $uid,
                $uid,
            ]);
            redirigir(MARINA_URL . '/index.php?p=contratos-electricidad&id=' . $id . '&ok=' . rawurlencode('Factura de electricidad registrada; registre pagos o abonos.'));
        } catch (Throwable $e) {
            $mensaje = 'No se pudo crear la factura. Verifique que existan las tablas de electricidad (recargue la app para migrar).';
        }
    }
}

if (enviado() && isset($_POST['abonar_factura_electricidad'])) {
    $fid = (int) ($_POST['factura_id'] ?? 0);
    $montoAb = (float) str_replace(',', '.', (string) ($_POST['monto_pago'] ?? 0));
    $fecha_pago = trim((string) ($_POST['fecha_pago'] ?? ''));
    $forma_pago_id = (int) ($_POST['forma_pago_id'] ?? 0);
    $referencia = trim((string) ($_POST['referencia_pago'] ?? ''));
    $observaciones = trim((string) ($_POST['observaciones_pago'] ?? ''));

    $pdo->beginTransaction();
    try {
        $stG = $pdo->prepare('SELECT f.id, f.monto_total, f.estado, f.contrato_id, co.cuenta_id
            FROM contrato_electricidad_facturas f
            JOIN contratos co ON co.id = f.contrato_id
            WHERE f.id = ? FOR UPDATE');
        $stG->execute([$fid]);
        $g = $stG->fetch(PDO::FETCH_ASSOC);
        if (!$g || (int) $g['contrato_id'] !== $id) {
            $pdo->rollBack();
            $mensaje = 'Factura no encontrada.';
        } elseif (($g['estado'] ?? '') === 'pagada') {
            $pdo->rollBack();
            $mensaje = 'La factura ya está pagada.';
        } elseif ($montoAb <= 0) {
            $pdo->rollBack();
            $mensaje = 'El monto debe ser mayor a 0.';
        } elseif ($fecha_pago === '') {
            $pdo->rollBack();
            $mensaje = 'La fecha de pago es obligatoria.';
        } elseif ($forma_pago_id < 1) {
            $pdo->rollBack();
            $mensaje = 'Debe seleccionar la forma de pago.';
        } else {
            $stFp = $pdo->prepare('SELECT tipo_movimiento FROM formas_pago WHERE id = ?');
            $stFp->execute([$forma_pago_id]);
            $tf = $stFp->fetch(PDO::FETCH_ASSOC);
            if (!$tf || ($tf['tipo_movimiento'] ?? '') !== 'ingreso') {
                $pdo->rollBack();
                $mensaje = 'La forma de pago debe ser de tipo ingreso.';
            } else {
                $cuentaPago = (int) ($g['cuenta_id'] ?? 0);
                if ($cuentaPago !== $cuentaContratoId) {
                    $pdo->rollBack();
                    $mensaje = 'Cuenta del contrato inconsistente.';
                } else {
                    $montoFact = round((float) ($g['monto_total'] ?? 0), 2);
                    $ya = marina_electricidad_total_pagado($pdo, $fid);
                    $pend = round($montoFact - $ya, 2);
                    if ($montoAb - $pend > 0.009) {
                        $pdo->rollBack();
                        $mensaje = 'El pago no puede superar el saldo pendiente (' . dinero($pend) . ').';
                    } else {
                        $pdo->prepare('
                            INSERT INTO contrato_electricidad_pagos
                            (factura_id, monto, fecha_pago, cuenta_id, forma_pago_id, referencia, observaciones, created_by, updated_by)
                            VALUES (?,?,?,?,?,?,?,?,?)
                        ')->execute([
                            $fid,
                            round($montoAb, 2),
                            $fecha_pago,
                            $cuentaPago,
                            $forma_pago_id,
                            $referencia !== '' ? $referencia : null,
                            $observaciones !== '' ? $observaciones : null,
                            $uid,
                            $uid,
                        ]);
                        marina_electricidad_refrescar_estado($pdo, $fid);
                        $pdo->commit();
                        redirigir(MARINA_URL . '/index.php?p=contratos-electricidad&id=' . $id . '&ok=' . rawurlencode('Pago / abono registrado.'));
                    }
                }
            }
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $mensaje = 'No se pudo registrar el pago.';
    }
}

$formasIngreso = $pdo->query("SELECT id, nombre FROM formas_pago WHERE tipo_movimiento = 'ingreso' ORDER BY nombre")->fetchAll(PDO::FETCH_KEY_PAIR);

$facturas = [];
try {
    $stF = $pdo->prepare('
        SELECT f.*,
               (SELECT COALESCE(SUM(ep.monto), 0) FROM contrato_electricidad_pagos ep WHERE ep.factura_id = f.id) AS total_pagado
        FROM contrato_electricidad_facturas f
        WHERE f.contrato_id = ?
        ORDER BY f.fecha_factura DESC, f.id DESC
    ');
    $stF->execute([$id]);
    $facturas = $stF->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $facturas = [];
}

$abonosPorFactura = [];
$idsFacturas = array_values(array_filter(array_map(static function ($row) {
    return (int) ($row['id'] ?? 0);
}, $facturas), static function ($x) {
    return $x > 0;
}));
if ($idsFacturas !== []) {
    $ph = implode(',', array_fill(0, count($idsFacturas), '?'));
    try {
        $stAb = $pdo->prepare("
            SELECT ep.id, ep.factura_id, ep.monto, ep.fecha_pago, ep.referencia, ep.observaciones,
                   CONCAT_WS(' - ', b.nombre, c.nombre) AS cuenta_nombre,
                   fp.nombre AS forma_pago_nombre
            FROM contrato_electricidad_pagos ep
            LEFT JOIN cuentas c ON c.id = ep.cuenta_id
            LEFT JOIN bancos b ON b.id = c.banco_id
            LEFT JOIN formas_pago fp ON fp.id = ep.forma_pago_id
            WHERE ep.factura_id IN ($ph)
            ORDER BY ep.fecha_pago ASC, ep.id ASC
        ");
        $stAb->execute($idsFacturas);
        while ($a = $stAb->fetch(PDO::FETCH_ASSOC)) {
            $gid = (int) ($a['factura_id'] ?? 0);
            if ($gid < 1) {
                continue;
            }
            if (!isset($abonosPorFactura[$gid])) {
                $abonosPorFactura[$gid] = [];
            }
            $abonosPorFactura[$gid][] = [
                'fecha_pago' => (string) ($a['fecha_pago'] ?? ''),
                'monto' => (float) ($a['monto'] ?? 0),
                'cuenta_nombre' => trim((string) ($a['cuenta_nombre'] ?? '')) !== '' ? trim((string) $a['cuenta_nombre']) : '—',
                'forma_pago_nombre' => trim((string) ($a['forma_pago_nombre'] ?? '')) !== '' ? trim((string) $a['forma_pago_nombre']) : '—',
                'referencia' => (string) ($a['referencia'] ?? ''),
                'observaciones' => (string) ($a['observaciones'] ?? ''),
            ];
        }
    } catch (Throwable $e) {
        $abonosPorFactura = [];
    }
}

require_once __DIR__ . '/../includes/layout.php';
?>
<h1 class="h4 mb-2">Electricidad — contrato #<?= (int) $id ?></h1>
<p class="text-muted small mb-3">
    <strong>Cliente:</strong> <?= e($contrato['cliente_nombre'] ?? '') ?> |
    <a href="<?= MARINA_URL ?>/index.php?p=contratos">Volver a contratos</a> |
    <a href="<?= MARINA_URL ?>/index.php?p=contratos&accion=cuotas&id=<?= (int) $id ?>">Cuotas</a>
</p>
<p class="small text-muted mb-3">Registre el monto total de la factura de luz; luego registre uno o más pagos o abonos hasta cubrir el total. Los cobros se acreditan a la <strong>misma cuenta</strong> configurada en el contrato.</p>

<?php if ($ok): ?><div class="alert alert-success"><?= e($ok) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endif; ?>
<?php if ($mensaje): ?><div class="alert alert-danger"><?= e($mensaje) ?></div><?php endif; ?>

<div class="mb-3">
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNuevaFacturaEle">Nueva factura de electricidad</button>
</div>

<div class="table-responsive card p-3">
    <table class="table table-hover align-middle mb-0 no-datatable">
        <thead>
            <tr>
                <th>Id</th>
                <th>Fecha factura</th>
                <th>Nº factura</th>
                <th>Período</th>
                <th class="text-end">Total</th>
                <th class="text-end">Pagado</th>
                <th class="text-end">Pendiente</th>
                <th>Estado</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($facturas as $r):
            $fid = (int) $r['id'];
            $totalF = round((float) ($r['monto_total'] ?? 0), 2);
            $totalP = round((float) ($r['total_pagado'] ?? 0), 2);
            $pend = round($totalF - $totalP, 2);
            if ($pend < 0) {
                $pend = 0.0;
            }
            $est = (string) ($r['estado'] ?? 'pendiente');
            $puedeAbonar = $est !== 'pagada' && $pend > 0.001;
            $puedeEliminar = $totalP < 0.001;
            $per = '';
            if (!empty($r['periodo_desde']) || !empty($r['periodo_hasta'])) {
                $per = fechaFormato($r['periodo_desde'] ?? '') . ' — ' . fechaFormato($r['periodo_hasta'] ?? '');
            }
            $abonosJson = json_encode($abonosPorFactura[$fid] ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
            ?>
            <tr>
                <td><?= $fid ?></td>
                <td><?= fechaFormato($r['fecha_factura'] ?? '') ?></td>
                <td><?= e($r['numero_factura'] ?? '—') ?></td>
                <td><?= $per !== '' ? e($per) : '—' ?></td>
                <td class="text-end"><?= dinero($totalF) ?></td>
                <td class="text-end"><?= dinero($totalP) ?></td>
                <td class="text-end"><?= dinero($pend) ?></td>
                <td><span class="badge <?= $est === 'pagada' ? 'text-bg-success' : 'text-bg-warning' ?>"><?= $est === 'pagada' ? 'Pagada' : 'Pendiente' ?></span></td>
                <td class="text-nowrap">
                    <button type="button" class="btn btn-outline-info btn-sm btn-ver-abonos-ele"
                        data-abonos="<?= htmlspecialchars($abonosJson, ENT_QUOTES, 'UTF-8') ?>"
                        data-resumen="Factura #<?= $fid ?> — <?= e(dinero($totalF)) ?>">Ver pagos</button>
                    <?php if ($puedeAbonar): ?>
                    <button type="button" class="btn btn-success btn-sm btn-abonar-ele"
                        data-factura-id="<?= $fid ?>"
                        data-pendiente="<?= e((string) $pend) ?>"
                        data-label="Factura #<?= $fid ?>">Abonar</button>
                    <button type="button" class="btn btn-primary btn-sm btn-pago-total-ele"
                        data-factura-id="<?= $fid ?>"
                        data-pendiente="<?= e((string) $pend) ?>"
                        data-label="Factura #<?= $fid ?>">Pago total</button>
                    <?php endif; ?>
                    <?php if ($puedeEliminar): ?>
                    <form method="post" class="d-inline" onsubmit="return confirm('¿Eliminar esta factura sin pagos?');">
                        <input type="hidden" name="eliminar_factura_electricidad" value="1">
                        <input type="hidden" name="factura_id" value="<?= $fid ?>">
                        <button type="submit" class="btn btn-danger btn-sm">Eliminar</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($facturas)): ?>
            <tr><td colspan="9" class="text-muted">No hay facturas de electricidad para este contrato.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Nueva factura -->
<div class="modal fade" id="modalNuevaFacturaEle" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="crear_factura_electricidad" value="1">
                <div class="modal-header">
                    <h5 class="modal-title">Nueva factura de electricidad</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label">Monto total *</label>
                            <input type="text" class="form-control" name="monto_total" required>
                            <label class="form-label mt-2">Fecha de la factura *</label>
                            <input type="date" class="form-control" name="fecha_factura" value="<?= e(date('Y-m-d')) ?>" required>
                            <label class="form-label mt-2">Nº factura (proveedor)</label>
                            <input type="text" class="form-control" name="numero_factura" maxlength="100">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Período desde</label>
                            <input type="date" class="form-control" name="periodo_desde">
                            <label class="form-label mt-2">Período hasta</label>
                            <input type="date" class="form-control" name="periodo_hasta">
                            <label class="form-label mt-2">Observaciones</label>
                            <textarea class="form-control" name="observaciones" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Guardar factura</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Abonar / pago total -->
<div class="modal fade" id="modalAbonoEle" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="abonar_factura_electricidad" value="1">
                <input type="hidden" name="factura_id" id="eleFacturaId" value="">
                <div class="modal-header">
                    <h5 class="modal-title" id="eleAbonoTitle">Registrar pago</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="small mb-2" id="eleAbonoResumen"></p>
                    <p class="small fw-semibold" id="eleAbonoPendiente"></p>
                    <label class="form-label">Monto *</label>
                    <input type="text" class="form-control" name="monto_pago" id="eleMontoPago" required>
                    <label class="form-label mt-2">Fecha de pago *</label>
                    <input type="date" class="form-control" name="fecha_pago" id="eleFechaPago" value="<?= e(date('Y-m-d')) ?>" required>
                    <label class="form-label mt-2">Cuenta de acreditación (del contrato)</label>
                    <input type="text" class="form-control" value="<?= e($cuentaContratoNom) ?>" disabled>
                    <label class="form-label mt-2">Forma de pago *</label>
                    <select class="form-select" name="forma_pago_id" required>
                        <option value="">Seleccione</option>
                        <?php foreach ($formasIngreso as $fid => $fnom): ?>
                            <option value="<?= (int) $fid ?>"><?= e($fnom) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label class="form-label mt-2">Referencia</label>
                    <input type="text" class="form-control" name="referencia_pago" placeholder="Transferencia, depósito…">
                    <label class="form-label mt-2">Observaciones</label>
                    <textarea class="form-control" name="observaciones_pago" rows="2"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Registrar</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Ver pagos -->
<div class="modal fade" id="modalVerAbonosEle" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Pagos de electricidad</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-2" id="eleVerResumen"></p>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0 no-datatable">
                        <thead class="table-light">
                            <tr><th>Fecha</th><th class="text-end">Monto</th><th>Cuenta</th><th>Forma pago</th><th>Referencia</th><th>Obs.</th></tr>
                        </thead>
                        <tbody id="eleVerAbonosTbody"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
