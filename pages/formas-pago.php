<?php
/**
 * Formas de pago - listar, crear/editar con modal, eliminar con modal
 */
$titulo = 'Tipo de movimientos';

$pdo = getDb();
$accion = obtener('accion');
$id = (int) obtener('id');
$mensaje = '';

if ($accion === 'eliminar' && $id > 0 && enviado()) {
    $bloqueo = marinaBloqueoEliminarFormaPago($pdo, $id);
    if ($bloqueo !== null) {
        redirigir(MARINA_URL . '/index.php?p=formas-pago&err=' . rawurlencode($bloqueo));
    }
    try {
        $pdo->prepare('DELETE FROM formas_pago WHERE id = ?')->execute([$id]);
        redirigir(MARINA_URL . '/index.php?p=formas-pago&ok=' . rawurlencode('Eliminado'));
    } catch (Throwable $e) {
        redirigir(MARINA_URL . '/index.php?p=formas-pago&err=' . rawurlencode(marinaMensajeErrorIntegridad($e)));
    }
}

if (enviado() && ($accion === 'crear' || $accion === 'editar')) {
    $nombre = trim($_POST['nombre'] ?? '');
    $tipo_movimiento = trim($_POST['tipo_movimiento'] ?? '');
    $uid = usuarioId();
    if ($nombre === '') {
        $mensaje = 'Nombre obligatorio.';
    } elseif (!in_array($tipo_movimiento, ['ingreso', 'costo'], true)) {
        $mensaje = 'Debe seleccionar tipo de movimiento válido.';
    } else {
        if ($accion === 'editar' && $id > 0) {
            $pdo->prepare('UPDATE formas_pago SET nombre=?, tipo_movimiento=?, updated_by=? WHERE id=?')->execute([$nombre, $tipo_movimiento, $uid, $id]);
            redirigir(MARINA_URL . '/index.php?p=formas-pago&ok=Actualizado');
        } else {
            $pdo->prepare('INSERT INTO formas_pago (nombre, tipo_movimiento, created_by, updated_by) VALUES (?,?,?,?)')->execute([$nombre, $tipo_movimiento, $uid, $uid]);
            redirigir(MARINA_URL . '/index.php?p=formas-pago&ok=Creado');
        }
    }
}

$registro = null;
if ($accion === 'editar' && $id > 0) {
    $st = $pdo->prepare('SELECT * FROM formas_pago WHERE id = ?');
    $st->execute([$id]);
    $registro = $st->fetch();
    if (!$registro) redirigir(MARINA_URL . '/index.php?p=formas-pago');
}

$ok = obtener('ok');
$err = obtener('err');
$mostrarModal = enviado() && ($accion === 'crear' || $accion === 'editar') && $mensaje !== '';
$modalDatos = [
    'id' => $id,
    'nombre' => $registro['nombre'] ?? ($_POST['nombre'] ?? ''),
    'tipo_movimiento' => $registro['tipo_movimiento'] ?? ($_POST['tipo_movimiento'] ?? 'ingreso')
];
?>
<?php require_once __DIR__ . '/../includes/layout.php'; ?>

<h1>Tipo de movimientos</h1>
<?php if ($ok): ?><p class="success"><?= e($ok) ?></p><?php endif; ?>
<?php if ($err): ?><p class="error"><?= e($err) ?></p><?php endif; ?>
<?php if ($mensaje && !$mostrarModal): ?><p class="error"><?= e($mensaje) ?></p><?php endif; ?>

<div class="toolbar d-flex gap-2"><button type="button" class="btn btn-primary" id="btnNuevoFormaPago">Nuevo tipo de movimiento</button></div>

<table>
    <thead><tr><th>Id</th><th>Nombre</th><th>Tipo</th><th>Creado</th><th>Creado por</th><th></th></tr></thead>
    <tbody>
    <?php
    $st = $pdo->query('SELECT f.*, u.nombre AS creado_por FROM formas_pago f LEFT JOIN usuarios u ON f.created_by = u.id ORDER BY f.nombre');
    while ($r = $st->fetch()): ?>
        <tr>
            <td><?= (int)$r['id'] ?></td>
            <td><?= e($r['nombre']) ?></td>
            <td><?= e($r['tipo_movimiento'] === 'costo' ? 'Costo' : 'Ingreso') ?></td>
            <td><?= fechaHoraFormato($r['created_at']) ?></td>
            <td><?= e($r['creado_por'] ?? '—') ?></td>
            <td class="acciones">
                <button type="button" class="btn btn-danger btn-sm btn-eliminar-formapago" data-id="<?= (int)$r['id'] ?>" data-nombre="<?= e($r['nombre']) ?>">Eliminar</button>
                <button type="button" class="btn btn-secondary btn-sm btn-editar-formapago" data-id="<?= (int)$r['id'] ?>" data-nombre="<?= e($r['nombre']) ?>" data-tipo-movimiento="<?= e($r['tipo_movimiento']) ?>">Editar</button>
            </td>
        </tr>
    <?php endwhile; ?>
    </tbody>
</table>

<div class="modal fade" id="formaPagoModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="?p=formas-pago">
                <input type="hidden" name="accion" id="formaPagoFormAccion" value="crear">
                <input type="hidden" name="id" id="formaPagoFormId" value="">
                <div class="modal-header">
                    <h5 class="modal-title" id="formaPagoModalTitle">Nuevo tipo de movimiento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="formaPagoModalMensaje" class="alert alert-danger d-none"></div>
                    <label>Nombre</label>
                    <input type="text" class="form-control" id="formaPagoNombre" name="nombre" required placeholder="Ej: Efectivo, Transferencia">
                    <label class="mt-2">Tipo de movimiento</label>
                    <select class="form-select" id="formaPagoTipoMovimiento" name="tipo_movimiento" required>
                        <option value="ingreso">Ingreso</option>
                        <option value="costo">Costo</option>
                    </select>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Guardar</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="confirmEliminarFormaPagoModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <form method="post" action="?p=formas-pago&accion=eliminar">
                <input type="hidden" name="id" id="formaPagoDeleteId" value="">
                <div class="modal-header"><h5 class="modal-title">Confirmar eliminación</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">¿Eliminar tipo de movimiento <span id="formaPagoDeleteNombre" class="fw-semibold"></span>?</div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Eliminar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>window.__formaPagoModal = { mostrar: <?= $mostrarModal ? 'true' : 'false' ?>, datos: <?= json_encode($modalDatos, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>, error: <?= json_encode($mensaje, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?> };</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
