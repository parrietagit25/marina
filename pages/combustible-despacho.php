<?php
/**
 * Despacho de combustible: factura/pagaré (descuenta inventario al registrar);
 * los cobros (pago/abono) registran ingreso por cuenta, como las cuotas.
 */
$titulo = 'Combustible — Despacho';
$pdo = getDb();
require_once __DIR__ . '/../includes/combustible_helpers.php';

$uid = usuarioId();
$mensaje = '';
$cobrarId = (int) obtener('cobrar', 0);

$formas_pago_opts = $pdo->query("SELECT id, nombre FROM formas_pago WHERE tipo_movimiento = 'ingreso' ORDER BY nombre")->fetchAll(PDO::FETCH_KEY_PAIR);

// --- Vista cobros de un despacho
if ($cobrarId > 0) {
    $stD = $pdo->prepare('SELECT * FROM combustible_despachos WHERE id = ?');
    $stD->execute([$cobrarId]);
    $desp = $stD->fetch(PDO::FETCH_ASSOC);
    if (!$desp) {
        redirigir(MARINA_URL . '/index.php?p=combustible-despacho');
    }

    $stMov = $pdo->prepare("
        SELECT mo.*, fp.nombre AS forma_pago_nombre,
               CONCAT(b.nombre, ' - ', c.nombre) AS cuenta_nom
        FROM combustible_despacho_pagos mo
        JOIN cuentas c ON c.id = mo.cuenta_id
        JOIN bancos b ON b.id = c.banco_id
        LEFT JOIN formas_pago fp ON fp.id = mo.forma_pago_id
        WHERE mo.despacho_id = ?
        ORDER BY mo.fecha_pago DESC, mo.id DESC
    ");
    $stMov->execute([$cobrarId]);
    $movimientos = $stMov->fetchAll(PDO::FETCH_ASSOC);

    $pagado = 0.0;
    foreach ($movimientos as $m) {
        $pagado += (float) ($m['monto'] ?? 0);
    }
    $montoCuota = (float) $desp['monto_total'];
    $saldo = max(0.0, $montoCuota - $pagado);

    $mostrarModalPagar = false;
    $mostrarModalAbonar = false;
    $modalPagarDatos = [];
    $modalAbonarDatos = [];

    if (enviado() && isset($_POST['eliminar_pago_despacho'])) {
        $pagoElimId = (int) ($_POST['pago_eliminar_id'] ?? 0);
        if ($pagoElimId < 1) {
            redirigir(MARINA_URL . '/index.php?p=combustible-despacho&cobrar=' . $cobrarId . '&err=' . rawurlencode('Cobro no válido.'));
        }
        $stPe = $pdo->prepare('SELECT id FROM combustible_despacho_pagos WHERE id = ? AND despacho_id = ? LIMIT 1');
        $stPe->execute([$pagoElimId, $cobrarId]);
        if (!$stPe->fetch()) {
            redirigir(MARINA_URL . '/index.php?p=combustible-despacho&cobrar=' . $cobrarId . '&err=' . rawurlencode('El cobro no pertenece a este despacho.'));
        }
        try {
            $pdo->prepare('DELETE FROM combustible_despacho_pagos WHERE id = ? AND despacho_id = ?')->execute([$pagoElimId, $cobrarId]);
            redirigir(MARINA_URL . '/index.php?p=combustible-despacho&cobrar=' . $cobrarId . '&ok=' . rawurlencode('Cobro eliminado.'));
        } catch (Throwable $e) {
            redirigir(MARINA_URL . '/index.php?p=combustible-despacho&cobrar=' . $cobrarId . '&err=' . rawurlencode('No se pudo eliminar el cobro.'));
        }
    }

    if (enviado() && isset($_POST['registrar_movimiento_despacho'])) {
        $tipo = trim((string) ($_POST['tipo_movimiento'] ?? ''));
        $monto_mov = (float) str_replace(',', '.', (string) ($_POST['monto_movimiento'] ?? 0));
        $fecha_pago = trim((string) ($_POST['fecha_pago'] ?? ''));
        $cuenta_mov = (int) ($_POST['cuenta_id_mov'] ?? 0);
        $forma_pago_id = (int) ($_POST['forma_pago_id'] ?? 0);
        $ref = trim((string) ($_POST['referencia_pago'] ?? ''));
        $concepto_mov = trim((string) ($_POST['concepto_movimiento'] ?? ''));

        if (($tipo !== 'pago' && $tipo !== 'abono') || $fecha_pago === '' || $monto_mov <= 0 || $cuenta_mov < 1) {
            $mensaje = 'Indique tipo, fecha, monto y cuenta válidos.';
            $repModal = [
                'monto_movimiento' => $monto_mov,
                'fecha_pago' => $fecha_pago,
                'forma_pago_id' => $forma_pago_id,
                'ref' => $ref,
                'concepto_mov' => $concepto_mov,
                'cuenta_id_mov' => $cuenta_mov,
            ];
            if ($tipo === 'pago') {
                $mostrarModalPagar = true;
                $modalPagarDatos = $repModal;
            } else {
                $mostrarModalAbonar = true;
                $modalAbonarDatos = $repModal;
            }
        } elseif ($monto_mov > $saldo + 0.00001) {
            $mensaje = 'El monto no puede ser mayor al saldo pendiente (' . dinero($saldo) . ').';
            $repModal = [
                'monto_movimiento' => $monto_mov,
                'fecha_pago' => $fecha_pago,
                'forma_pago_id' => $forma_pago_id,
                'ref' => $ref,
                'concepto_mov' => $concepto_mov,
                'cuenta_id_mov' => $cuenta_mov,
            ];
            if ($tipo === 'pago') {
                $mostrarModalPagar = true;
                $modalPagarDatos = $repModal;
            } else {
                $mostrarModalAbonar = true;
                $modalAbonarDatos = $repModal;
            }
        } else {
            $pdo->prepare('
                INSERT INTO combustible_despacho_pagos (despacho_id, tipo, monto, fecha_pago, cuenta_id, forma_pago_id, referencia, concepto, created_by, updated_by)
                VALUES (?,?,?,?,?,?,?,?,?,?)
            ')->execute([
                $cobrarId,
                $tipo,
                $monto_mov,
                $fecha_pago,
                $cuenta_mov,
                $forma_pago_id > 0 ? $forma_pago_id : null,
                $ref !== '' ? $ref : null,
                $concepto_mov !== '' ? $concepto_mov : null,
                $uid,
                $uid,
            ]);
            redirigir(MARINA_URL . '/index.php?p=combustible-despacho&cobrar=' . $cobrarId . '&ok=' . rawurlencode($tipo === 'pago' ? 'Cobro registrado' : 'Abono registrado'));
        }
    }

    $cuentas = $pdo->query('SELECT c.id, CONCAT(b.nombre, " - ", c.nombre) AS nom FROM cuentas c JOIN bancos b ON c.banco_id = b.id ORDER BY b.nombre, c.nombre')->fetchAll(PDO::FETCH_KEY_PAIR);
    $cuentaDefault = (int) ($desp['cuenta_id'] ?? 0);
    $okCob = obtener('ok');
    $errCob = obtener('err');

    require_once __DIR__ . '/../includes/layout.php';
    ?>
    <h1 class="h4 mb-2">Cobros — Despacho #<?= (int) $desp['id'] ?></h1>
    <p class="text-muted small mb-3">
        <strong><?= e(MARINA_COMB_TIPOS[$desp['tipo_combustible']] ?? $desp['tipo_combustible']) ?></strong> —
        <?= fechaFormato($desp['fecha']) ?> —
        <strong><?= e($desp['embarcacion']) ?></strong> —
        <?= e((string) $desp['gls']) ?> GLS —
        Total <?= dinero($montoCuota) ?> |
        Pagado <?= dinero($pagado) ?> |
        Saldo <strong><?= dinero($saldo) ?></strong>
        <a class="ms-2" href="<?= MARINA_URL ?>/index.php?p=combustible-despacho">Volver al listado</a>
    </p>
    <?php if ($okCob): ?><div class="alert alert-success py-2"><?= e($okCob) ?></div><?php endif; ?>
    <?php if ($errCob): ?><div class="alert alert-danger py-2"><?= e($errCob) ?></div><?php endif; ?>
    <?php if ($mensaje !== ''): ?><div class="alert alert-warning py-2"><?= e($mensaje) ?></div><?php endif; ?>

    <div class="toolbar d-flex flex-wrap gap-2 mb-3">
        <?php if ($saldo > 0.00001): ?>
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalPagarDespacho">Registrar pago</button>
            <button type="button" class="btn btn-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#modalAbonarDespacho">Registrar abono</button>
        <?php else: ?>
            <span class="text-muted small align-self-center">Factura cancelada.</span>
        <?php endif; ?>
    </div>

    <div class="card p-0 mb-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Tipo</th>
                        <th class="text-end">Monto</th>
                        <th>Cuenta</th>
                        <th>Forma pago</th>
                        <th>Referencia</th>
                        <th>Concepto</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($movimientos as $m): ?>
                    <tr>
                        <td><?= fechaFormato($m['fecha_pago']) ?></td>
                        <td><?= ($m['tipo'] ?? '') === 'abono' ? 'Abono' : 'Pago' ?></td>
                        <td class="text-end"><?= dinero((float) ($m['monto'] ?? 0)) ?></td>
                        <td><?= e($m['cuenta_nom'] ?? '') ?></td>
                        <td><?= e($m['forma_pago_nombre'] ?? '') ?></td>
                        <td><?= e($m['referencia'] ?? '') ?></td>
                        <td class="small"><?= e($m['concepto'] ?? '') ?></td>
                        <td class="text-nowrap">
                            <form method="post" action="<?= MARINA_URL ?>/index.php?p=combustible-despacho&cobrar=<?= (int) $cobrarId ?>" class="d-inline"
                                onsubmit="return confirm('¿Eliminar este cobro? Dejará de figurar como ingreso en reportes y saldos.');">
                                <input type="hidden" name="eliminar_pago_despacho" value="1">
                                <input type="hidden" name="pago_eliminar_id" value="<?= (int) ($m['id'] ?? 0) ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($movimientos === []): ?>
                    <tr><td colspan="8" class="text-muted">Sin cobros aún. El despacho ya descontó inventario; el ingreso contable se registra aquí.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal pagar -->
    <div class="modal fade" id="modalPagarDespacho" tabindex="-1" <?= $mostrarModalPagar ? 'data-marina-show="1"' : '' ?>>
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" method="post" action="<?= MARINA_URL ?>/index.php?p=combustible-despacho&cobrar=<?= (int) $cobrarId ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Registrar pago</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="registrar_movimiento_despacho" value="1">
                    <input type="hidden" name="tipo_movimiento" value="pago">
                    <p class="small text-muted">Saldo disponible: <strong><?= dinero($saldo) ?></strong></p>
                    <div class="mb-2">
                        <label class="form-label">Monto</label>
                        <input type="text" class="form-control" name="monto_movimiento" inputmode="decimal" required
                            value="<?= e((string) ($modalPagarDatos['monto_movimiento'] ?? $saldo)) ?>">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Fecha de pago</label>
                        <input type="date" class="form-control" name="fecha_pago" required value="<?= e($modalPagarDatos['fecha_pago'] ?? date('Y-m-d')) ?>">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Cuenta (acreditación)</label>
                        <select class="form-select" name="cuenta_id_mov" required>
                            <option value="0">— Seleccione —</option>
                            <?php foreach ($cuentas as $cid => $nom): ?>
                                <option value="<?= (int) $cid ?>" <?= (int) ($modalPagarDatos['cuenta_id_mov'] ?? $cuentaDefault) === (int) $cid ? 'selected' : '' ?>><?= e($nom) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Forma de pago</label>
                        <select class="form-select" name="forma_pago_id">
                            <option value="0">—</option>
                            <?php foreach ($formas_pago_opts as $fpid => $fpnom): ?>
                                <option value="<?= (int) $fpid ?>" <?= (int) ($modalPagarDatos['forma_pago_id'] ?? 0) === (int) $fpid ? 'selected' : '' ?>><?= e($fpnom) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Referencia</label>
                        <input type="text" class="form-control" name="referencia_pago" value="<?= e($modalPagarDatos['ref'] ?? '') ?>">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Concepto</label>
                        <input type="text" class="form-control" name="concepto_movimiento" value="<?= e($modalPagarDatos['concepto_mov'] ?? '') ?>">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal abonar -->
    <div class="modal fade" id="modalAbonarDespacho" tabindex="-1" <?= $mostrarModalAbonar ? 'data-marina-show="1"' : '' ?>>
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" method="post" action="<?= MARINA_URL ?>/index.php?p=combustible-despacho&cobrar=<?= (int) $cobrarId ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Registrar abono</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="registrar_movimiento_despacho" value="1">
                    <input type="hidden" name="tipo_movimiento" value="abono">
                    <p class="small text-muted">Saldo disponible: <strong><?= dinero($saldo) ?></strong></p>
                    <div class="mb-2">
                        <label class="form-label">Monto</label>
                        <input type="text" class="form-control" name="monto_movimiento" inputmode="decimal" required
                            value="<?= e((string) ($modalAbonarDatos['monto_movimiento'] ?? '')) ?>">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Fecha</label>
                        <input type="date" class="form-control" name="fecha_pago" required value="<?= e($modalAbonarDatos['fecha_pago'] ?? date('Y-m-d')) ?>">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Cuenta (acreditación)</label>
                        <select class="form-select" name="cuenta_id_mov" required>
                            <option value="0">— Seleccione —</option>
                            <?php foreach ($cuentas as $cid => $nom): ?>
                                <option value="<?= (int) $cid ?>" <?= (int) ($modalAbonarDatos['cuenta_id_mov'] ?? $cuentaDefault) === (int) $cid ? 'selected' : '' ?>><?= e($nom) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Forma de pago</label>
                        <select class="form-select" name="forma_pago_id">
                            <option value="0">—</option>
                            <?php foreach ($formas_pago_opts as $fpid => $fpnom): ?>
                                <option value="<?= (int) $fpid ?>" <?= (int) ($modalAbonarDatos['forma_pago_id'] ?? 0) === (int) $fpid ? 'selected' : '' ?>><?= e($fpnom) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Referencia</label>
                        <input type="text" class="form-control" name="referencia_pago" value="<?= e($modalAbonarDatos['ref'] ?? '') ?>">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Concepto</label>
                        <input type="text" class="form-control" name="concepto_movimiento" value="<?= e($modalAbonarDatos['concepto_mov'] ?? '') ?>">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
    <script>
    (function(){
      document.querySelectorAll('.modal[data-marina-show="1"]').forEach(function(el) {
        if (window.bootstrap) bootstrap.Modal.getOrCreateInstance(el).show();
      });
    })();
    </script>
    <?php
    require_once __DIR__ . '/../includes/footer.php';
    return;
}

// --- Listado de despachos
if (enviado()) {
    $postAccion = trim((string) ($_POST['marina_comb_accion'] ?? ''));
    if ($postAccion === 'eliminar') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $pdo->prepare('DELETE FROM combustible_despachos WHERE id = ?')->execute([$id]);
                redirigir(MARINA_URL . '/index.php?p=combustible-despacho&ok=' . rawurlencode('Despacho eliminado'));
            } catch (Throwable $e) {
                redirigir(MARINA_URL . '/index.php?p=combustible-despacho&err=' . rawurlencode(marinaMensajeErrorIntegridad($e)));
            }
        }
    }
    if ($postAccion === 'guardar') {
        $id = (int) ($_POST['id'] ?? 0);
        $tipo = strtolower(trim((string) ($_POST['tipo_combustible'] ?? '')));
        $fecha = trim((string) ($_POST['fecha'] ?? ''));
        $emb = trim((string) ($_POST['embarcacion'] ?? ''));
        $gls = (float) str_replace(',', '.', (string) ($_POST['gls'] ?? 0));
        $monto = (float) str_replace(',', '.', (string) ($_POST['monto_total'] ?? 0));
        $cuenta_id = (int) ($_POST['cuenta_id'] ?? 0);
        $cuenta_id = $cuenta_id > 0 ? $cuenta_id : null;

        if (!isset(MARINA_COMB_TIPOS[$tipo]) || $fecha === '' || $emb === '' || $gls <= 0 || $monto < 0) {
            $mensaje = 'Complete tipo, fecha, embarcación, GLS y monto válidos.';
        } else {
            $tieneCobros = false;
            if ($id > 0) {
                $stC = $pdo->prepare('SELECT COALESCE(SUM(monto), 0) FROM combustible_despacho_pagos WHERE despacho_id = ?');
                $stC->execute([$id]);
                $tieneCobros = (float) $stC->fetchColumn() > 0.00001;
            }

            if ($tieneCobros) {
                $stOld = $pdo->prepare('SELECT tipo_combustible, gls, monto_total FROM combustible_despachos WHERE id = ?');
                $stOld->execute([$id]);
                $old = $stOld->fetch(PDO::FETCH_ASSOC);
                if (!$old) {
                    $mensaje = 'Despacho no encontrado.';
                } else {
                    $sameTipo = strtolower((string) $old['tipo_combustible']) === $tipo;
                    $sameGls = abs((float) $old['gls'] - $gls) < 0.0001;
                    $sameMonto = abs((float) $old['monto_total'] - $monto) < 0.01;
                    if (!$sameTipo || !$sameGls || !$sameMonto) {
                        $mensaje = 'No se puede cambiar tipo, GLS ni monto total mientras existan cobros. Elimine los cobros desde la base si necesita corregir (o cree una factura nueva).';
                    } else {
                        try {
                            $pdo->prepare('UPDATE combustible_despachos SET fecha=?, embarcacion=?, cuenta_id=?, updated_by=? WHERE id=?')
                                ->execute([$fecha, $emb, $cuenta_id, $uid, $id]);
                            redirigir(MARINA_URL . '/index.php?p=combustible-despacho&ok=' . rawurlencode('Despacho actualizado'));
                        } catch (Throwable $e) {
                            $mensaje = 'No se pudo guardar.';
                        }
                    }
                }
            } else {
                $disp = marina_combustible_disponible_despacho_tipo($pdo, $tipo, $id > 0 ? $id : null);
                if ($gls > $disp + 0.0001) {
                    $mensaje = 'No hay inventario suficiente para este despacho. Disponible (' . (MARINA_COMB_TIPOS[$tipo] ?? $tipo) . '): ' . number_format($disp, 3, '.', ',') . ' gal.';
                } else {
                    try {
                        if ($id > 0) {
                            $pdo->prepare('UPDATE combustible_despachos SET tipo_combustible=?, fecha=?, embarcacion=?, gls=?, monto_total=?, cuenta_id=?, updated_by=? WHERE id=?')
                                ->execute([$tipo, $fecha, $emb, $gls, $monto, $cuenta_id, $uid, $id]);
                            redirigir(MARINA_URL . '/index.php?p=combustible-despacho&ok=' . rawurlencode('Despacho actualizado'));
                        } else {
                            $pdo->prepare('INSERT INTO combustible_despachos (tipo_combustible, fecha, embarcacion, gls, monto_total, cuenta_id, created_by, updated_by) VALUES (?,?,?,?,?,?,?,?)')
                                ->execute([$tipo, $fecha, $emb, $gls, $monto, $cuenta_id, $uid, $uid]);
                            redirigir(MARINA_URL . '/index.php?p=combustible-despacho&ok=' . rawurlencode('Factura de despacho registrada (inventario descontado; registre cobros en «Cobrar»).'));
                        }
                    } catch (Throwable $e) {
                        $mensaje = 'No se pudo guardar.';
                    }
                }
            }
        }
    }
}

$desdeFiltro = trim((string) ($_GET['desde'] ?? date('Y-m-01')));
$hastaFiltro = trim((string) ($_GET['hasta'] ?? date('Y-m-d')));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $desdeFiltro)) {
    $desdeFiltro = date('Y-m-01');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $hastaFiltro)) {
    $hastaFiltro = date('Y-m-d');
}
if ($desdeFiltro > $hastaFiltro) {
    [$desdeFiltro, $hastaFiltro] = [$hastaFiltro, $desdeFiltro];
}
$despFiltroQs = 'desde=' . rawurlencode($desdeFiltro) . '&hasta=' . rawurlencode($hastaFiltro);

$preciosJson = json_encode(marina_combustible_precios_vigentes($pdo), JSON_UNESCAPED_UNICODE);
$inv = marina_combustible_inventario_por_tipo($pdo);
$cuentas = $pdo->query('SELECT c.id, CONCAT(b.nombre, " - ", c.nombre) AS nom FROM cuentas c JOIN bancos b ON c.banco_id = b.id ORDER BY b.nombre, c.nombre')->fetchAll(PDO::FETCH_KEY_PAIR);

$edit = null;
$ui = trim((string) obtener('ui', ''));
$editId = (int) obtener('id', 0);
if ($ui === 'editar' && $editId > 0) {
    $st = $pdo->prepare('SELECT * FROM combustible_despachos WHERE id = ?');
    $st->execute([$editId]);
    $edit = $st->fetch(PDO::FETCH_ASSOC);
    if (!$edit) {
        redirigir(MARINA_URL . '/index.php?p=combustible-despacho');
    }
}

$ok = obtener('ok');
$err = obtener('err');
$abrirModal = ($ui === 'nuevo') || ($edit !== null) || (enviado() && ($_POST['marina_comb_accion'] ?? '') === 'guardar' && $mensaje !== '');

require_once __DIR__ . '/../includes/layout.php';
?>
<h1 class="h4 mb-3">Combustible — Despacho</h1>
<p class="text-muted small"><strong>La factura no mueve el banco:</strong> solo descuenta inventario (GLS) y define el monto total del pagaré. Los ingresos a cuenta (una o varias veces: pagos y/o abonos hasta completar el total) se registran en <strong>Cobrar</strong>. La cuenta en el alta es opcional y solo sirve como valor sugerido al cobrar.</p>

<?php if ($ok): ?><div class="alert alert-success py-2"><?= e($ok) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-danger py-2"><?= e($err) ?></div><?php endif; ?>
<?php if ($mensaje !== ''): ?><div class="alert alert-warning py-2"><?= e($mensaje) ?></div><?php endif; ?>

<div class="card p-3 mb-3">
    <h2 class="h6 mb-2">Inventario (GLS)</h2>
    <div class="row g-2 small">
        <?php foreach (MARINA_COMB_TIPOS as $k => $lab): ?>
            <div class="col-md-6"><strong><?= e($lab) ?>:</strong> <?= number_format($inv[$k] ?? 0, 3, '.', ',') ?> gal</div>
        <?php endforeach; ?>
    </div>
    <p class="text-muted small mb-0 mt-2">Incluye <a href="<?= MARINA_URL ?>/index.php?p=combustible-ajuste">ajustes</a> de inventario. Cada despacho resta GLS.</p>
</div>

<div class="toolbar d-flex flex-wrap gap-2 mb-3">
    <button type="button" class="btn btn-primary" id="btnNuevoDespacho">Nueva factura de despacho</button>
    <a class="btn btn-outline-secondary" href="<?= MARINA_URL ?>/index.php?p=combustible-pedidos">Pedidos</a>
    <a class="btn btn-outline-secondary" href="<?= MARINA_URL ?>/index.php?p=combustible-ajuste">Ajuste</a>
    <a class="btn btn-outline-secondary" href="<?= MARINA_URL ?>/index.php?p=combustible-precios">Precio por galón</a>
</div>

<form method="get" class="combustible-filtro-form card p-3 mb-3">
    <input type="hidden" name="p" value="combustible-despacho">
    <div class="combustible-filtro-form__inner">
        <div class="combustible-filtro-campo">
            <label class="form-label small mb-0" for="despFiltroDesde">Desde</label>
            <input type="date" id="despFiltroDesde" name="desde" class="form-control form-control-sm" value="<?= e($desdeFiltro) ?>">
        </div>
        <div class="combustible-filtro-campo">
            <label class="form-label small mb-0" for="despFiltroHasta">Hasta</label>
            <input type="date" id="despFiltroHasta" name="hasta" class="form-control form-control-sm" value="<?= e($hastaFiltro) ?>">
        </div>
        <div class="combustible-filtro-campo combustible-filtro-campo--btn">
            <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
        </div>
        <p class="combustible-filtro-hint text-muted small mb-0">
            Listado y totales por <strong>fecha de despacho</strong> (<?= e(fechaFormato($desdeFiltro)) ?> — <?= e(fechaFormato($hastaFiltro)) ?>).
        </p>
    </div>
</form>

<?php
$stDespTot = $pdo->prepare("
    SELECT
        COALESCE(SUM(d.monto_total), 0) AS total_facturado,
        COALESCE(SUM(COALESCE(p.pagado, 0)), 0) AS total_abonado
    FROM combustible_despachos d
    LEFT JOIN (
        SELECT despacho_id, SUM(monto) AS pagado FROM combustible_despacho_pagos GROUP BY despacho_id
    ) p ON p.despacho_id = d.id
    WHERE d.fecha BETWEEN ? AND ?
");
$stDespTot->execute([$desdeFiltro, $hastaFiltro]);
$despTotRow = $stDespTot->fetch(PDO::FETCH_ASSOC);
$despTotalFacturado = (float) ($despTotRow['total_facturado'] ?? 0);
$despTotalAbonado = (float) ($despTotRow['total_abonado'] ?? 0);
$despTotalSaldo = max(0.0, $despTotalFacturado - $despTotalAbonado);
?>

<div class="card p-0">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 no-datatable" data-export-filename="combustible_despacho" data-export-sheet="Despacho">
            <thead>
                <tr>
                    <th>Id</th>
                    <th>Fecha</th>
                    <th>Tipo</th>
                    <th>Embarcación</th>
                    <th class="text-end">GLS</th>
                    <th class="text-end">Total</th>
                    <th class="text-end">Pagado</th>
                    <th class="text-end">Saldo</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php
            $st = $pdo->prepare("
                SELECT d.*,
                       COALESCE(p.pagado, 0) AS pagado_sum
                FROM combustible_despachos d
                LEFT JOIN (
                    SELECT despacho_id, SUM(monto) AS pagado FROM combustible_despacho_pagos GROUP BY despacho_id
                ) p ON p.despacho_id = d.id
                WHERE d.fecha BETWEEN ? AND ?
                ORDER BY d.fecha DESC, d.id DESC
            ");
            $st->execute([$desdeFiltro, $hastaFiltro]);
            while ($r = $st->fetch(PDO::FETCH_ASSOC)):
                $pagadoF = (float) ($r['pagado_sum'] ?? 0);
                $totalF = (float) $r['monto_total'];
                $saldoF = max(0.0, $totalF - $pagadoF);
                ?>
                <tr>
                    <td><?= (int) $r['id'] ?></td>
                    <td><?= fechaFormato($r['fecha']) ?></td>
                    <td><?= e(MARINA_COMB_TIPOS[$r['tipo_combustible']] ?? $r['tipo_combustible']) ?></td>
                    <td><?= e($r['embarcacion']) ?></td>
                    <td class="text-end"><?= e((string) $r['gls']) ?></td>
                    <td class="text-end"><?= dinero($totalF) ?></td>
                    <td class="text-end"><?= dinero($pagadoF) ?></td>
                    <td class="text-end"><?= $saldoF > 0.00001 ? dinero($saldoF) : '—' ?></td>
                    <td class="text-nowrap">
                        <a class="btn btn-sm btn-primary" href="<?= MARINA_URL ?>/index.php?p=combustible-despacho&amp;cobrar=<?= (int) $r['id'] ?>&amp;<?= e($despFiltroQs) ?>">Cobrar</a>
                        <button type="button" class="btn btn-sm btn-secondary btn-editar-despacho"
                            data-despacho="<?= htmlspecialchars(json_encode([
                                'id' => (int) $r['id'],
                                'tipo_combustible' => $r['tipo_combustible'],
                                'fecha' => $r['fecha'],
                                'embarcacion' => $r['embarcacion'],
                                'gls' => (string) $r['gls'],
                                'monto_total' => (string) $r['monto_total'],
                                'cuenta_id' => (int) ($r['cuenta_id'] ?? 0),
                            ], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>">Editar</button>
                        <button type="button" class="btn btn-sm btn-danger btn-eliminar-despacho"
                            data-bs-toggle="modal" data-bs-target="#modalEliminarDespacho"
                            data-id="<?= (int) $r['id'] ?>"
                            data-resumen="<?= e($r['embarcacion'] . ' — ' . fechaFormato($r['fecha'])) ?>">Eliminar</button>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
            <tfoot class="combustible-tfoot">
                <tr>
                    <th colspan="5" class="text-end">Totales</th>
                    <th class="text-end"><?= dinero($despTotalFacturado) ?></th>
                    <th class="text-end"><?= dinero($despTotalAbonado) ?></th>
                    <th class="text-end"><?= dinero($despTotalSaldo) ?></th>
                    <th></th>
                </tr>
                <tr class="combustible-tfoot-labels">
                    <td colspan="5" class="text-end text-muted small border-0 pt-0"></td>
                    <td class="text-end text-muted small border-0 pt-0">Total registrado facturado</td>
                    <td class="text-end text-muted small border-0 pt-0">Total abonado</td>
                    <td class="text-end text-muted small border-0 pt-0">Total saldo</td>
                    <td class="border-0"></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<div class="modal fade" id="modalDespacho" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" method="post" action="<?= MARINA_URL ?>/index.php?p=combustible-despacho">
      <div class="modal-header">
        <h5 class="modal-title" id="modalDespachoTitulo"><?= $edit ? 'Editar despacho' : 'Nueva factura de despacho' ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="marina_comb_accion" value="guardar">
        <?php
        $despIdModal = $edit ? (int) $edit['id'] : (int) ($_POST['id'] ?? 0);
        ?>
        <input type="hidden" name="id" id="inputDespachoId" value="<?= $despIdModal > 0 ? $despIdModal : '' ?>">
        <div class="mb-2">
            <label class="form-label">Tipo</label>
            <select class="form-select" name="tipo_combustible" id="dTipo" required>
                <?php foreach (MARINA_COMB_TIPOS as $k => $lab): ?>
                    <option value="<?= e($k) ?>" <?= (($edit['tipo_combustible'] ?? $_POST['tipo_combustible'] ?? 'diesel') === $k) ? 'selected' : '' ?>><?= e($lab) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-2">
            <label class="form-label">Fecha</label>
            <input type="date" class="form-control" name="fecha" required value="<?= e($edit['fecha'] ?? $_POST['fecha'] ?? date('Y-m-d')) ?>">
        </div>
        <div class="mb-2">
            <label class="form-label">Embarcación</label>
            <input type="text" class="form-control" name="embarcacion" required value="<?= e($edit['embarcacion'] ?? $_POST['embarcacion'] ?? '') ?>">
        </div>
        <div class="mb-2">
            <label class="form-label">GLS</label>
            <input type="text" class="form-control" name="gls" id="dGls" inputmode="decimal" required value="<?= e((string) ($edit['gls'] ?? $_POST['gls'] ?? '')) ?>">
        </div>
        <div class="mb-2">
            <label class="form-label">Monto total (factura / pagaré)</label>
            <input type="text" class="form-control" name="monto_total" id="dMonto" inputmode="decimal" required value="<?= e((string) ($edit['monto_total'] ?? $_POST['monto_total'] ?? '')) ?>">
            <div class="form-text small">Se calcula al escribir <strong>GLS</strong> o al cambiar el <strong>tipo</strong> (precio venta vigente × GLS).</div>
            <button type="button" class="btn btn-link btn-sm p-0 mt-1" id="btnCalcVenta">Volver a calcular</button>
        </div>
        <div class="mb-2">
            <label class="form-label">Cuenta sugerida al cobrar <span class="text-muted fw-normal">(opcional)</span></label>
            <select class="form-select" name="cuenta_id" id="dCuenta">
                <option value="0">— Definir al cobrar —</option>
                <?php foreach ($cuentas as $cid => $nom): ?>
                    <option value="<?= (int) $cid ?>" <?= (int) ($edit['cuenta_id'] ?? $_POST['cuenta_id'] ?? 0) === (int) $cid ? 'selected' : '' ?>><?= e($nom) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-primary">Guardar</button>
      </div>
    </form>
  </div>
</div>

<div class="modal fade" id="modalEliminarDespacho" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" method="post" action="<?= MARINA_URL ?>/index.php?p=combustible-despacho">
      <div class="modal-header">
        <h5 class="modal-title">Eliminar despacho</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="marina_comb_accion" value="eliminar">
        <input type="hidden" name="id" id="elimDespachoId" value="">
        <p class="mb-0" id="elimDespachoTexto">¿Eliminar este despacho? Se borrarán los cobros asociados y se revertirá el efecto en inventario.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-danger">Eliminar</button>
      </div>
    </form>
  </div>
</div>

<script>
(function(){
  const precios = <?= $preciosJson ?>;
  const abrirAlCargar = <?= $abrirModal ? 'true' : 'false' ?>;

  function initCombustibleDespacho() {
    if (!window.bootstrap) return;
    const modalDespachoEl = document.getElementById('modalDespacho');
    function showModalDespacho() {
      if (modalDespachoEl) bootstrap.Modal.getOrCreateInstance(modalDespachoEl).show();
    }

    function precioVenta(tipo) {
      const p = precios[tipo] || {};
      return parseFloat(p.venta) || 0;
    }
    function aplicarMontoAutomaticoDespacho() {
      const tipo = document.getElementById('dTipo')?.value || 'diesel';
      const gls = parseFloat(String(document.getElementById('dGls')?.value || '').replace(',', '.')) || 0;
      const unit = precioVenta(tipo);
      const el = document.getElementById('dMonto');
      if (!el) return;
      if (gls > 0) {
        const tot = Math.round(gls * unit * 100) / 100;
        el.value = String(tot);
      } else {
        el.value = '0';
      }
    }
    document.getElementById('btnCalcVenta')?.addEventListener('click', aplicarMontoAutomaticoDespacho);
    document.getElementById('dTipo')?.addEventListener('change', aplicarMontoAutomaticoDespacho);
    document.getElementById('dGls')?.addEventListener('input', aplicarMontoAutomaticoDespacho);
    document.getElementById('dGls')?.addEventListener('change', aplicarMontoAutomaticoDespacho);

    function setV(id, v) {
      const el = document.getElementById(id);
      if (el) el.value = v != null ? String(v) : '';
    }
    document.getElementById('btnNuevoDespacho')?.addEventListener('click', function() {
      document.getElementById('modalDespachoTitulo').textContent = 'Nueva factura de despacho';
      setV('inputDespachoId', '');
      setV('dTipo', 'diesel');
      const fe = document.querySelector('#modalDespacho [name=fecha]');
      if (fe) fe.value = '<?= date('Y-m-d') ?>';
      const em = document.querySelector('#modalDespacho [name=embarcacion]');
      if (em) em.value = '';
      setV('dGls', '');
      setV('dMonto', '0');
      const cu = document.querySelector('#modalDespacho select[name=cuenta_id]');
      if (cu) cu.value = '0';
      showModalDespacho();
    });
    document.querySelectorAll('.btn-editar-despacho').forEach(function(btn) {
      btn.addEventListener('click', function() {
        let d = {};
        try { d = JSON.parse(btn.getAttribute('data-despacho') || '{}'); } catch (e) {}
        document.getElementById('modalDespachoTitulo').textContent = 'Editar despacho';
        setV('inputDespachoId', d.id || '');
        setV('dTipo', d.tipo_combustible || 'diesel');
        const fe2 = document.querySelector('#modalDespacho [name=fecha]');
        if (fe2) fe2.value = d.fecha || '';
        const em2 = document.querySelector('#modalDespacho [name=embarcacion]');
        if (em2) em2.value = d.embarcacion || '';
        setV('dGls', d.gls || '');
        setV('dMonto', d.monto_total || '');
        const cu2 = document.querySelector('#modalDespacho select[name=cuenta_id]');
        if (cu2) cu2.value = String(d.cuenta_id || '0');
        showModalDespacho();
      });
    });
    document.getElementById('modalEliminarDespacho')?.addEventListener('show.bs.modal', function(ev) {
      const t = ev.relatedTarget;
      document.getElementById('elimDespachoId').value = t?.getAttribute?.('data-id') || '';
      const r = t?.getAttribute?.('data-resumen') || '';
      document.getElementById('elimDespachoTexto').textContent = r
        ? ('¿Eliminar el despacho «' + r + '» y sus cobros? El inventario se ajustará al eliminar el registro.')
        : '¿Eliminar este despacho?';
    });

    if (abrirAlCargar) showModalDespacho();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initCombustibleDespacho);
  } else {
    initCombustibleDespacho();
  }
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
