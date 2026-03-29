<?php
/**
 * Pedidos de combustible: inventario (GLS recibidos), egreso al recibir (gasto costo total), abonos = pago.
 */
$titulo = 'Combustible — Pedidos';
$pdo = getDb();
require_once __DIR__ . '/../includes/combustible_helpers.php';

try {
    $col = $pdo->query("SHOW COLUMNS FROM combustible_pedidos LIKE 'gasto_id'")->fetch(PDO::FETCH_ASSOC);
    if ($col) {
        $missing = $pdo->query("
            SELECT id FROM combustible_pedidos
            WHERE (gasto_id IS NULL OR gasto_id = 0)
              AND fecha_recibido IS NOT NULL
              AND gls_recibido IS NOT NULL AND gls_recibido > 0
              AND costo_total > 0.0001
        ")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($missing as $mid) {
            marina_combustible_sync_pedido_gasto($pdo, (int) $mid);
        }
    }
} catch (Throwable $e) {
    // tabla o columna no lista
}

$uid = usuarioId();
$mensaje = '';
$pedidoAbonos = (int) obtener('abonos', 0);

if (enviado()) {
    $postAccion = trim((string) ($_POST['marina_comb_accion'] ?? ''));
    if ($postAccion === 'eliminar_pedido') {
        $pid = (int) ($_POST['pedido_id'] ?? 0);
        if ($pid > 0) {
            try {
                $pdo->beginTransaction();
                marina_combustible_eliminar_pedido($pdo, $pid);
                $pdo->commit();
                redirigir(MARINA_URL . '/index.php?p=combustible-pedidos&ok=' . rawurlencode('Pedido eliminado'));
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                redirigir(MARINA_URL . '/index.php?p=combustible-pedidos&err=' . rawurlencode(marinaMensajeErrorIntegridad($e)));
            }
        }
    }
    if ($postAccion === 'guardar_pedido') {
        $id = (int) ($_POST['id'] ?? 0);
        $tipo = strtolower(trim((string) ($_POST['tipo_combustible'] ?? '')));
        $fecha_pedido = trim((string) ($_POST['fecha_pedido'] ?? ''));
        $gls_pedido = (float) str_replace(',', '.', (string) ($_POST['gls_pedido'] ?? 0));
        $fecha_rec = trim((string) ($_POST['fecha_recibido'] ?? ''));
        $gls_rec = trim((string) ($_POST['gls_recibido'] ?? ''));
        $factura = trim((string) ($_POST['numero_factura'] ?? ''));
        $costo_total = (float) str_replace(',', '.', (string) ($_POST['costo_total'] ?? 0));
        $cuenta_id = (int) ($_POST['cuenta_id'] ?? 0);
        $obs = trim((string) ($_POST['observaciones'] ?? ''));
        $fecha_rec_sql = $fecha_rec !== '' ? $fecha_rec : null;
        $gls_rec_sql = $gls_rec !== '' ? (float) str_replace(',', '.', $gls_rec) : null;
        if (!isset(MARINA_COMB_TIPOS[$tipo]) || $fecha_pedido === '') {
            $mensaje = 'Tipo y fecha de pedido son obligatorios.';
        } elseif ($costo_total < 0) {
            $mensaje = 'Costo total no válido.';
        } else {
            try {
                if ($id > 0) {
                    $pdo->prepare('UPDATE combustible_pedidos SET tipo_combustible=?, fecha_pedido=?, gls_pedido=?, fecha_recibido=?, gls_recibido=?, numero_factura=?, costo_total=?, cuenta_id=?, observaciones=?, updated_by=? WHERE id=?')
                        ->execute([$tipo, $fecha_pedido, $gls_pedido, $fecha_rec_sql, $gls_rec_sql, $factura !== '' ? $factura : null, $costo_total, $cuenta_id > 0 ? $cuenta_id : null, $obs !== '' ? $obs : null, $uid, $id]);
                    marina_combustible_sync_pedido_gasto($pdo, $id);
                    marina_combustible_actualizar_estado_pedido($pdo, $id);
                    redirigir(MARINA_URL . '/index.php?p=combustible-pedidos&ok=' . rawurlencode('Pedido actualizado'));
                } else {
                    $pdo->prepare('INSERT INTO combustible_pedidos (tipo_combustible, fecha_pedido, gls_pedido, fecha_recibido, gls_recibido, numero_factura, estado_pago, costo_total, cuenta_id, observaciones, created_by, updated_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')
                        ->execute([$tipo, $fecha_pedido, $gls_pedido, $fecha_rec_sql, $gls_rec_sql, $factura !== '' ? $factura : null, 'por_pagar', $costo_total, $cuenta_id > 0 ? $cuenta_id : null, $obs !== '' ? $obs : null, $uid, $uid]);
                    $newId = (int) $pdo->lastInsertId();
                    marina_combustible_sync_pedido_gasto($pdo, $newId);
                    marina_combustible_actualizar_estado_pedido($pdo, $newId);
                    redirigir(MARINA_URL . '/index.php?p=combustible-pedidos&ok=' . rawurlencode('Pedido registrado'));
                }
            } catch (Throwable $e) {
                $mensaje = 'No se pudo guardar el pedido.';
            }
        }
    }
    if ($postAccion === 'eliminar_pago') {
        $pagoId = (int) ($_POST['pago_id'] ?? 0);
        $pedidoId = (int) ($_POST['pedido_id_ref'] ?? 0);
        if ($pagoId > 0) {
            $st = $pdo->prepare('SELECT gasto_id FROM combustible_pedido_pagos WHERE id = ?');
            $st->execute([$pagoId]);
            $gid = (int) ($st->fetchColumn() ?: 0);
            if ($gid > 0) {
                $pdo->prepare('DELETE FROM gastos WHERE id = ?')->execute([$gid]);
            }
            $pdo->prepare('DELETE FROM combustible_pedido_pagos WHERE id = ?')->execute([$pagoId]);
            if ($pedidoId > 0) {
                marina_combustible_actualizar_estado_pedido($pdo, $pedidoId);
            }
            redirigir(MARINA_URL . '/index.php?p=combustible-pedidos&abonos=' . $pedidoId . '&ok=' . rawurlencode('Abono eliminado'));
        }
    }
    if ($postAccion === 'guardar_pago') {
        $pagoId = (int) ($_POST['pago_id'] ?? 0);
        $pedidoId = (int) ($_POST['pedido_id'] ?? 0);
        $monto = (float) str_replace(',', '.', (string) ($_POST['monto'] ?? 0));
        $fecha_pago = trim((string) ($_POST['fecha_pago'] ?? ''));
        $cuenta_p = (int) ($_POST['cuenta_id'] ?? 0);
        $forma_p = (int) ($_POST['forma_pago_id'] ?? 0);
        $ref = trim((string) ($_POST['referencia'] ?? ''));
        if ($pedidoId < 1 || $monto <= 0 || $fecha_pago === '') {
            $mensaje = 'Pedido, monto y fecha de pago son obligatorios.';
        } else {
            try {
                if ($pagoId > 0) {
                    $pdo->prepare('UPDATE combustible_pedido_pagos SET monto=?, fecha_pago=?, cuenta_id=?, forma_pago_id=?, referencia=? WHERE id=?')
                        ->execute([$monto, $fecha_pago, $cuenta_p > 0 ? $cuenta_p : null, $forma_p > 0 ? $forma_p : null, $ref !== '' ? $ref : null, $pagoId]);
                } else {
                    $pdo->prepare('INSERT INTO combustible_pedido_pagos (pedido_id, monto, fecha_pago, cuenta_id, forma_pago_id, referencia, created_by) VALUES (?,?,?,?,?,?,?)')
                        ->execute([$pedidoId, $monto, $fecha_pago, $cuenta_p > 0 ? $cuenta_p : null, $forma_p > 0 ? $forma_p : null, $ref !== '' ? $ref : null, $uid]);
                }
                marina_combustible_actualizar_estado_pedido($pdo, $pedidoId);
                redirigir(MARINA_URL . '/index.php?p=combustible-pedidos&abonos=' . $pedidoId . '&ok=' . rawurlencode('Abono guardado'));
            } catch (Throwable $e) {
                $mensaje = 'No se pudo guardar el abono.';
            }
        }
    }
}

$preciosJson = json_encode(marina_combustible_precios_vigentes($pdo), JSON_UNESCAPED_UNICODE);
$inv = marina_combustible_inventario_por_tipo($pdo);
$cuentas = $pdo->query('SELECT c.id, CONCAT(b.nombre, " - ", c.nombre) AS nom FROM cuentas c JOIN bancos b ON c.banco_id = b.id ORDER BY b.nombre, c.nombre')->fetchAll(PDO::FETCH_KEY_PAIR);
$formas_costo = $pdo->query("SELECT id, nombre FROM formas_pago WHERE tipo_movimiento = 'costo' ORDER BY nombre")->fetchAll(PDO::FETCH_KEY_PAIR);

$pedidoEdit = null;
$uiPed = trim((string) obtener('ui', ''));
$editId = (int) obtener('id', 0);
if ($uiPed === 'editar' && $editId > 0) {
    $st = $pdo->prepare('SELECT * FROM combustible_pedidos WHERE id = ?');
    $st->execute([$editId]);
    $pedidoEdit = $st->fetch(PDO::FETCH_ASSOC);
    if (!$pedidoEdit) {
        redirigir(MARINA_URL . '/index.php?p=combustible-pedidos');
    }
}

$pedidoParaAbonos = null;
$pagosLista = [];
if ($pedidoAbonos > 0) {
    $st = $pdo->prepare('SELECT * FROM combustible_pedidos WHERE id = ?');
    $st->execute([$pedidoAbonos]);
    $pedidoParaAbonos = $st->fetch(PDO::FETCH_ASSOC);
    if ($pedidoParaAbonos) {
        $st2 = $pdo->prepare('SELECT * FROM combustible_pedido_pagos WHERE pedido_id = ? ORDER BY fecha_pago, id');
        $st2->execute([$pedidoAbonos]);
        $pagosLista = $st2->fetchAll(PDO::FETCH_ASSOC);
    }
}

$montoPagadoPedido = 0.0;
if ($pedidoParaAbonos) {
    foreach ($pagosLista as $pl) {
        $montoPagadoPedido += (float) $pl['monto'];
    }
}

$ok = obtener('ok');
$err = obtener('err');
$abrirModalPedido = ($uiPed === 'nuevo') || ($pedidoEdit !== null) || (enviado() && ($_POST['marina_comb_accion'] ?? '') === 'guardar_pedido' && $mensaje !== '');

require_once __DIR__ . '/../includes/layout.php';
?>
<h1 class="h4 mb-3">Combustible — Pedidos</h1>

<?php if ($ok): ?><div class="alert alert-success py-2"><?= e($ok) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-danger py-2"><?= e($err) ?></div><?php endif; ?>
<?php if ($mensaje !== ''): ?><div class="alert alert-warning py-2"><?= e($mensaje) ?></div><?php endif; ?>

<div class="card p-3 mb-3">
    <h2 class="h6 mb-2">Inventario (GLS)</h2>
    <div class="row g-2 small">
        <?php foreach (MARINA_COMB_TIPOS as $k => $lab): ?>
            <div class="col-md-6">
                <strong><?= e($lab) ?>:</strong>
                <?= number_format($inv[$k] ?? 0, 3, '.', ',') ?> gal
            </div>
        <?php endforeach; ?>
    </div>
    <p class="text-muted small mb-0 mt-2">Inventario = suma de GLS recibidos en pedidos − despachos registrados. Con fecha de recibido, GLS recibidos y costo, el pedido genera un <strong>gasto</strong> (egreso) visible en reportes y en Factura / Pagar.</p>
</div>

<div class="toolbar d-flex flex-wrap gap-2 mb-3">
    <button type="button" class="btn btn-primary" id="btnNuevoPedido">Nuevo pedido</button>
    <a class="btn btn-outline-secondary" href="<?= MARINA_URL ?>/index.php?p=combustible-despacho">Despacho</a>
    <a class="btn btn-outline-secondary" href="<?= MARINA_URL ?>/index.php?p=combustible-precios">Precio por galón</a>
</div>

<?php if ($pedidoParaAbonos): ?>
<div class="card p-3 mb-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
        <h2 class="h6 mb-0">Abonos — pedido #<?= (int) $pedidoParaAbonos['id'] ?> (<?= e(MARINA_COMB_TIPOS[$pedidoParaAbonos['tipo_combustible']] ?? $pedidoParaAbonos['tipo_combustible']) ?>)</h2>
        <a class="btn btn-sm btn-outline-secondary" href="<?= MARINA_URL ?>/index.php?p=combustible-pedidos">Volver al listado</a>
    </div>
    <p class="small mb-2">
        Costo total: <strong><?= dinero((float) $pedidoParaAbonos['costo_total']) ?></strong> ·
        Monto pagado: <strong><?= dinero($montoPagadoPedido) ?></strong> ·
        Estado: <strong><?= ($pedidoParaAbonos['estado_pago'] ?? '') === 'pagado' ? 'Pagado' : 'Por pagar' ?></strong>
    </p>
    <div class="table-responsive mb-3">
        <table class="table table-sm table-bordered mb-0">
            <thead><tr><th>Fecha</th><th>Monto</th><th>Cuenta</th><th>Forma pago</th><th>Ref.</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($pagosLista as $pg):
                $cn = '';
                if (!empty($pg['cuenta_id']) && isset($cuentas[(int) $pg['cuenta_id']])) {
                    $cn = $cuentas[(int) $pg['cuenta_id']];
                }
                $fpn = !empty($pg['forma_pago_id']) && isset($formas_costo[(int) $pg['forma_pago_id']]) ? $formas_costo[(int) $pg['forma_pago_id']] : '—';
            ?>
                <tr>
                    <td><?= fechaFormato($pg['fecha_pago']) ?></td>
                    <td class="text-end"><?= dinero((float) $pg['monto']) ?></td>
                    <td><?= e($cn ?: '—') ?></td>
                    <td><?= e($fpn) ?></td>
                    <td><?= e($pg['referencia'] ?? '') ?></td>
                    <td>
                        <button type="button" class="btn btn-sm btn-outline-secondary btn-editar-abono"
                            data-bs-toggle="modal" data-bs-target="#modalAbono"
                            data-pago-id="<?= (int) $pg['id'] ?>"
                            data-fecha-pago="<?= e($pg['fecha_pago']) ?>"
                            data-monto="<?= e((string) $pg['monto']) ?>"
                            data-cuenta-id="<?= (int) ($pg['cuenta_id'] ?? 0) ?>"
                            data-forma-id="<?= (int) ($pg['forma_pago_id'] ?? 0) ?>"
                            data-referencia="<?= e($pg['referencia'] ?? '') ?>">Editar</button>
                        <button type="button" class="btn btn-sm btn-danger btn-eliminar-abono"
                            data-bs-toggle="modal" data-bs-target="#modalEliminarAbono"
                            data-pago-id="<?= (int) $pg['id'] ?>"
                            data-monto="<?= e(dinero((float) $pg['monto'])) ?>">Quitar</button>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($pagosLista === []): ?>
                <tr><td colspan="6" class="text-muted">Sin abonos aún. El egreso contable del pedido se registra al marcar recepción y costo; los abonos solo controlan lo pagado.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalAbono" id="btnNuevoAbono">Registrar abono</button>
</div>
<?php endif; ?>

<div class="card p-0">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Id</th><th>Tipo</th><th>Pedido</th><th>GLS ped.</th><th>Recibido</th><th>GLS rec.</th><th>Factura</th><th>Costo</th><th>Pagado</th><th>Estado</th><th></th>
                </tr>
            </thead>
            <tbody>
            <?php
            $sql = "
                SELECT p.*,
                  (SELECT COALESCE(SUM(monto),0) FROM combustible_pedido_pagos x WHERE x.pedido_id = p.id) AS sum_pagado
                FROM combustible_pedidos p
                ORDER BY p.fecha_pedido DESC, p.id DESC
            ";
            $st = $pdo->query($sql);
            while ($r = $st->fetch(PDO::FETCH_ASSOC)):
                $sumP = (float) ($r['sum_pagado'] ?? 0);
            ?>
                <tr>
                    <td><?= (int) $r['id'] ?></td>
                    <td><?= e(MARINA_COMB_TIPOS[$r['tipo_combustible']] ?? $r['tipo_combustible']) ?></td>
                    <td><?= fechaFormato($r['fecha_pedido']) ?></td>
                    <td class="text-end"><?= e((string) $r['gls_pedido']) ?></td>
                    <td><?= $r['fecha_recibido'] ? fechaFormato($r['fecha_recibido']) : '—' ?></td>
                    <td class="text-end"><?= $r['gls_recibido'] !== null ? e((string) $r['gls_recibido']) : '—' ?></td>
                    <td><?= e($r['numero_factura'] ?? '') ?></td>
                    <td class="text-end"><?= dinero((float) $r['costo_total']) ?></td>
                    <td class="text-end"><?= dinero($sumP) ?></td>
                    <td><?= ($r['estado_pago'] ?? '') === 'pagado' ? 'Pagado' : 'Por pagar' ?></td>
                    <td class="text-nowrap">
                        <a class="btn btn-sm btn-outline-primary" href="<?= MARINA_URL ?>/index.php?p=combustible-pedidos&abonos=<?= (int) $r['id'] ?>">Abonos</a>
                        <button type="button" class="btn btn-sm btn-secondary btn-editar-pedido"
                            data-pedido="<?= htmlspecialchars(json_encode([
                                'id' => (int) $r['id'],
                                'tipo_combustible' => $r['tipo_combustible'],
                                'fecha_pedido' => $r['fecha_pedido'],
                                'gls_pedido' => (string) $r['gls_pedido'],
                                'fecha_recibido' => $r['fecha_recibido'] ?? '',
                                'gls_recibido' => $r['gls_recibido'] !== null ? (string) $r['gls_recibido'] : '',
                                'numero_factura' => (string) ($r['numero_factura'] ?? ''),
                                'costo_total' => (string) $r['costo_total'],
                                'cuenta_id' => (int) ($r['cuenta_id'] ?? 0),
                                'observaciones' => (string) ($r['observaciones'] ?? ''),
                            ], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>">Editar</button>
                        <button type="button" class="btn btn-sm btn-danger btn-eliminar-pedido"
                            data-bs-toggle="modal" data-bs-target="#modalEliminarPedido"
                            data-pedido-id="<?= (int) $r['id'] ?>"
                            data-pedido-resumen="<?= e('Pedido #' . (int) $r['id'] . ' — ' . (MARINA_COMB_TIPOS[$r['tipo_combustible']] ?? $r['tipo_combustible'])) ?>">Eliminar</button>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="modalPedido" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <form class="modal-content" method="post" action="<?= MARINA_URL ?>/index.php?p=combustible-pedidos">
      <div class="modal-header">
        <h5 class="modal-title" id="modalPedidoTitulo"><?= $pedidoEdit ? 'Editar pedido' : 'Nuevo pedido' ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="marina_comb_accion" value="guardar_pedido">
        <?php
        $pedidoIdModal = $pedidoEdit ? (int) $pedidoEdit['id'] : (int) ($_POST['id'] ?? 0);
        ?>
        <input type="hidden" name="id" id="inputPedidoId" value="<?= $pedidoIdModal > 0 ? $pedidoIdModal : '' ?>">
        <div class="row g-2">
            <div class="col-md-4">
                <label class="form-label">Tipo</label>
                <select class="form-select" name="tipo_combustible" id="pedTipo" required>
                    <?php foreach (MARINA_COMB_TIPOS as $k => $lab): ?>
                        <option value="<?= e($k) ?>" <?= (($pedidoEdit['tipo_combustible'] ?? $_POST['tipo_combustible'] ?? 'diesel') === $k) ? 'selected' : '' ?>><?= e($lab) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Fecha pedido</label>
                <input type="date" class="form-control" name="fecha_pedido" id="pedFechaPed" required value="<?= e($pedidoEdit['fecha_pedido'] ?? $_POST['fecha_pedido'] ?? date('Y-m-d')) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">GLS pedido</label>
                <input type="text" class="form-control" name="gls_pedido" inputmode="decimal" value="<?= e((string) ($pedidoEdit['gls_pedido'] ?? $_POST['gls_pedido'] ?? '0')) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Fecha recibido</label>
                <input type="date" class="form-control" name="fecha_recibido" id="pedFechaRec" value="<?= e($pedidoEdit['fecha_recibido'] ?? $_POST['fecha_recibido'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">GLS recibido</label>
                <input type="text" class="form-control" name="gls_recibido" id="pedGlsRec" inputmode="decimal" value="<?= e(isset($pedidoEdit['gls_recibido']) && $pedidoEdit['gls_recibido'] !== null ? (string) $pedidoEdit['gls_recibido'] : ($_POST['gls_recibido'] ?? '')) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">N.º factura</label>
                <input type="text" class="form-control" name="numero_factura" value="<?= e($pedidoEdit['numero_factura'] ?? $_POST['numero_factura'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Costo total</label>
                <input type="text" class="form-control" name="costo_total" id="pedCostoTotal" inputmode="decimal" value="<?= e((string) ($pedidoEdit['costo_total'] ?? $_POST['costo_total'] ?? '0')) ?>">
                <div class="form-text small">Se calcula solo al escribir <strong>GLS recibido</strong> o al cambiar el <strong>tipo</strong> (precio compra vigente × GLS).</div>
                <button type="button" class="btn btn-link btn-sm p-0 mt-1" id="btnCalcCosto">Volver a calcular</button>
            </div>
            <div class="col-md-4">
                <label class="form-label">Cuenta (sugerida abonos)</label>
                <select class="form-select" name="cuenta_id">
                    <option value="0">—</option>
                    <?php foreach ($cuentas as $cid => $nom): ?>
                        <option value="<?= (int) $cid ?>" <?= (int) ($pedidoEdit['cuenta_id'] ?? $_POST['cuenta_id'] ?? 0) === (int) $cid ? 'selected' : '' ?>><?= e($nom) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12">
                <label class="form-label">Observaciones</label>
                <textarea class="form-control" name="observaciones" rows="2"><?= e($pedidoEdit['observaciones'] ?? $_POST['observaciones'] ?? '') ?></textarea>
            </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-primary">Guardar</button>
      </div>
    </form>
  </div>
</div>

<?php if ($pedidoParaAbonos): ?>
<div class="modal fade" id="modalAbono" tabindex="-1" aria-labelledby="modalAbonoLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" method="post" action="<?= MARINA_URL ?>/index.php?p=combustible-pedidos&abonos=<?= (int) $pedidoAbonos ?>">
      <div class="modal-header">
        <h5 class="modal-title" id="modalAbonoLabel">Registrar abono</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="marina_comb_accion" value="guardar_pago">
        <input type="hidden" name="pedido_id" value="<?= (int) $pedidoAbonos ?>">
        <input type="hidden" name="pago_id" id="inputAbonoPagoId" value="">
        <div class="mb-2"><label class="form-label">Fecha pago</label><input type="date" name="fecha_pago" id="abonoFecha" class="form-control" required></div>
        <div class="mb-2"><label class="form-label">Monto</label><input type="text" name="monto" id="abonoMonto" class="form-control" inputmode="decimal" required></div>
        <div class="mb-2"><label class="form-label">Cuenta</label>
          <select name="cuenta_id" id="abonoCuenta" class="form-select"><option value="0">—</option><?php foreach ($cuentas as $cid => $nom): ?>
            <option value="<?= (int) $cid ?>"><?= e($nom) ?></option><?php endforeach; ?></select></div>
        <div class="mb-2"><label class="form-label">Forma pago</label>
          <select name="forma_pago_id" id="abonoForma" class="form-select"><option value="0">—</option><?php foreach ($formas_costo as $fid => $fnom): ?>
            <option value="<?= (int) $fid ?>"><?= e($fnom) ?></option><?php endforeach; ?></select></div>
        <div class="mb-2"><label class="form-label">Referencia</label><input type="text" name="referencia" id="abonoRef" class="form-control"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-primary">Guardar abono</button>
      </div>
    </form>
  </div>
</div>
<div class="modal fade" id="modalEliminarAbono" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" method="post" action="<?= MARINA_URL ?>/index.php?p=combustible-pedidos&abonos=<?= (int) $pedidoAbonos ?>">
      <div class="modal-header">
        <h5 class="modal-title">Quitar abono</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="marina_comb_accion" value="eliminar_pago">
        <input type="hidden" name="pago_id" id="elimAbonoPagoId" value="">
        <input type="hidden" name="pedido_id_ref" value="<?= (int) $pedidoAbonos ?>">
        <p class="mb-0" id="elimAbonoTexto">¿Eliminar este abono? No afecta el gasto del pedido (costo al recibir).</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-danger">Eliminar</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<div class="modal fade" id="modalEliminarPedido" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" method="post" action="<?= MARINA_URL ?>/index.php?p=combustible-pedidos">
      <div class="modal-header">
        <h5 class="modal-title">Eliminar pedido</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="marina_comb_accion" value="eliminar_pedido">
        <input type="hidden" name="pedido_id" id="elimPedidoId" value="">
        <p class="mb-0" id="elimPedidoTexto">¿Eliminar este pedido y todos sus abonos? Esta acción no se puede deshacer.</p>
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
  const abrirAlCargar = <?= $abrirModalPedido ? 'true' : 'false' ?>;

  function initCombustiblePedidos() {
    if (!window.bootstrap) return;
    const modalPedidoEl = document.getElementById('modalPedido');
    function showModalPedido() {
      if (modalPedidoEl) bootstrap.Modal.getOrCreateInstance(modalPedidoEl).show();
    }

    function precioCompra(tipo) {
      const p = precios[tipo] || {};
      return parseFloat(p.compra) || 0;
    }
    function aplicarCostoAutomaticoPedido() {
      const tipo = document.getElementById('pedTipo')?.value || 'diesel';
      const gls = parseFloat(String(document.getElementById('pedGlsRec')?.value || '').replace(',', '.')) || 0;
      const unit = precioCompra(tipo);
      const el = document.getElementById('pedCostoTotal');
      if (!el) return;
      if (gls > 0) {
        const tot = Math.round(gls * unit * 100) / 100;
        el.value = String(tot);
      } else {
        el.value = '0';
      }
    }
    document.getElementById('btnCalcCosto')?.addEventListener('click', aplicarCostoAutomaticoPedido);
    document.getElementById('pedTipo')?.addEventListener('change', aplicarCostoAutomaticoPedido);
    document.getElementById('pedGlsRec')?.addEventListener('input', aplicarCostoAutomaticoPedido);
    document.getElementById('pedGlsRec')?.addEventListener('change', aplicarCostoAutomaticoPedido);

    function setVal(id, v) {
      const el = document.getElementById(id);
      if (el) el.value = v != null ? String(v) : '';
    }
    document.getElementById('btnNuevoPedido')?.addEventListener('click', function() {
      document.getElementById('modalPedidoTitulo').textContent = 'Nuevo pedido';
      setVal('inputPedidoId', '');
      setVal('pedTipo', 'diesel');
      setVal('pedFechaPed', '<?= date('Y-m-d') ?>');
      setVal('pedFechaRec', '');
      setVal('pedGlsRec', '');
      const glsN = document.querySelector('#modalPedido [name=gls_pedido]');
      if (glsN) glsN.value = '0';
      const nfN = document.querySelector('#modalPedido [name=numero_factura]');
      if (nfN) nfN.value = '';
      setVal('pedCostoTotal', '0');
      const cu = document.querySelector('#modalPedido select[name=cuenta_id]');
      if (cu) cu.value = '0';
      const ob = document.querySelector('#modalPedido textarea[name=observaciones]');
      if (ob) ob.value = '';
      showModalPedido();
    });
    document.querySelectorAll('.btn-editar-pedido').forEach(function(btn) {
      btn.addEventListener('click', function() {
        let d = {};
        try { d = JSON.parse(btn.getAttribute('data-pedido') || '{}'); } catch (e) {}
        document.getElementById('modalPedidoTitulo').textContent = 'Editar pedido';
        setVal('inputPedidoId', d.id || '');
        setVal('pedTipo', d.tipo_combustible || 'diesel');
        setVal('pedFechaPed', d.fecha_pedido || '');
        const glsP = document.querySelector('#modalPedido [name=gls_pedido]');
        if (glsP) glsP.value = d.gls_pedido ?? '0';
        setVal('pedFechaRec', d.fecha_recibido || '');
        setVal('pedGlsRec', d.gls_recibido || '');
        const nf = document.querySelector('#modalPedido [name=numero_factura]');
        if (nf) nf.value = d.numero_factura || '';
        setVal('pedCostoTotal', d.costo_total ?? '0');
        const cu = document.querySelector('#modalPedido select[name=cuenta_id]');
        if (cu) cu.value = String(d.cuenta_id || '0');
        const ob = document.querySelector('#modalPedido textarea[name=observaciones]');
        if (ob) ob.value = d.observaciones || '';
        showModalPedido();
      });
    });

    document.getElementById('modalEliminarPedido')?.addEventListener('show.bs.modal', function(ev) {
      const t = ev.relatedTarget;
      const pid = t?.getAttribute?.('data-pedido-id') || '';
      const res = t?.getAttribute?.('data-pedido-resumen') || '';
      document.getElementById('elimPedidoId').value = pid;
      document.getElementById('elimPedidoTexto').textContent = res
        ? ('¿Eliminar ' + res + ' y todos sus abonos? Esta acción no se puede deshacer.')
        : '¿Eliminar este pedido y todos sus abonos?';
    });

    const modalAbono = document.getElementById('modalAbono');
    modalAbono?.addEventListener('show.bs.modal', function(ev) {
      const t = ev.relatedTarget;
      const ed = t?.classList?.contains('btn-editar-abono');
      document.getElementById('modalAbonoLabel').textContent = ed ? 'Editar abono' : 'Registrar abono';
      if (ed && t) {
        document.getElementById('inputAbonoPagoId').value = t.getAttribute('data-pago-id') || '';
        document.getElementById('abonoFecha').value = t.getAttribute('data-fecha-pago') || '';
        document.getElementById('abonoMonto').value = t.getAttribute('data-monto') || '';
        document.getElementById('abonoCuenta').value = t.getAttribute('data-cuenta-id') || '0';
        document.getElementById('abonoForma').value = t.getAttribute('data-forma-id') || '0';
        document.getElementById('abonoRef').value = t.getAttribute('data-referencia') || '';
      } else {
        document.getElementById('inputAbonoPagoId').value = '';
        document.getElementById('abonoFecha').value = '<?= date('Y-m-d') ?>';
        document.getElementById('abonoMonto').value = '';
        document.getElementById('abonoCuenta').value = '0';
        document.getElementById('abonoForma').value = '0';
        document.getElementById('abonoRef').value = '';
      }
    });
    document.getElementById('modalEliminarAbono')?.addEventListener('show.bs.modal', function(ev) {
      const t = ev.relatedTarget;
      document.getElementById('elimAbonoPagoId').value = t?.getAttribute?.('data-pago-id') || '';
      const m = t?.getAttribute?.('data-monto') || '';
      document.getElementById('elimAbonoTexto').textContent = m
        ? ('¿Eliminar el abono de ' + m + '?')
        : '¿Eliminar este abono?';
    });

    if (abrirAlCargar) showModalPedido();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initCombustiblePedidos);
  } else {
    initCombustiblePedidos();
  }
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
