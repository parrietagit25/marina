<?php
/**
 * Slips - listar, crear/editar con modal, eliminar con modal
 */
$titulo = 'Slips';

$pdo = getDb();
$accion = obtener('accion');
$id = (int) obtener('id');
$mensaje = '';

if ($accion === 'eliminar' && $id > 0 && enviado()) {
    $bloqueo = marinaBloqueoEliminarSlip($pdo, $id);
    if ($bloqueo !== null) {
        redirigir(MARINA_URL . '/index.php?p=slips&err=' . rawurlencode($bloqueo));
    }
    try {
        $pdo->prepare('DELETE FROM slips WHERE id = ?')->execute([$id]);
        redirigir(MARINA_URL . '/index.php?p=slips&ok=' . rawurlencode('Slip eliminado'));
    } catch (Throwable $e) {
        redirigir(MARINA_URL . '/index.php?p=slips&err=' . rawurlencode(marinaMensajeErrorIntegridad($e)));
    }
}

if (enviado() && ($accion === 'crear' || $accion === 'editar')) {
    $muelle_id = (int) ($_POST['muelle_id'] ?? 0);
    $nombre = trim($_POST['nombre'] ?? '');
    $uid = usuarioId();
    if ($muelle_id < 1 || $nombre === '') {
        $mensaje = 'Muelle y nombre obligatorios.';
    } else {
        if ($accion === 'editar' && $id > 0) {
            $pdo->prepare('UPDATE slips SET muelle_id=?, nombre=?, updated_by=? WHERE id=?')->execute([$muelle_id, $nombre, $uid, $id]);
            redirigir(MARINA_URL . '/index.php?p=slips&ok=Actualizado');
        } else {
            $pdo->prepare('INSERT INTO slips (muelle_id, nombre, created_by, updated_by) VALUES (?,?,?,?)')->execute([$muelle_id, $nombre, $uid, $uid]);
            redirigir(MARINA_URL . '/index.php?p=slips&ok=Creado');
        }
    }
}

$registro = null;
if ($accion === 'editar' && $id > 0) {
    $st = $pdo->prepare('SELECT * FROM slips WHERE id = ?');
    $st->execute([$id]);
    $registro = $st->fetch();
    if (!$registro) redirigir(MARINA_URL . '/index.php?p=slips');
}

$muelles = $pdo->query('SELECT id, nombre FROM muelles ORDER BY nombre')->fetchAll(PDO::FETCH_KEY_PAIR);
$ok = obtener('ok');
$err = obtener('err');
$mostrarModal = enviado() && ($accion === 'crear' || $accion === 'editar') && $mensaje !== '';
$modalDatos = [
    'id' => $id,
    'muelle_id' => $registro['muelle_id'] ?? ($_POST['muelle_id'] ?? ''),
    'nombre' => $registro['nombre'] ?? ($_POST['nombre'] ?? ''),
];
?>
<?php require_once __DIR__ . '/../includes/layout.php'; ?>

<h1>Slips</h1>
<?php if ($ok): ?><p class="success"><?= e($ok) ?></p><?php endif; ?>
<?php if ($err): ?><p class="error"><?= e($err) ?></p><?php endif; ?>
<?php if ($mensaje && !$mostrarModal): ?><p class="error"><?= e($mensaje) ?></p><?php endif; ?>

<div class="toolbar d-flex gap-2"><button type="button" class="btn btn-primary" id="btnNuevoSlip">Nuevo slip</button></div>

<table>
    <thead><tr><th>Id</th><th>Muelle</th><th>Nombre slip</th><th>Creado</th><th>Creado por</th><th></th></tr></thead>
    <tbody>
    <?php
    $st = $pdo->query('SELECT s.*, m.nombre AS muelle_nombre, u.nombre AS creado_por FROM slips s JOIN muelles m ON s.muelle_id = m.id LEFT JOIN usuarios u ON s.created_by = u.id ORDER BY m.nombre, s.nombre');
    while ($r = $st->fetch()): ?>
        <tr>
            <td><?= (int)$r['id'] ?></td>
            <td><?= e($r['muelle_nombre']) ?></td>
            <td><?= e($r['nombre']) ?></td>
            <td><?= fechaHoraFormato($r['created_at']) ?></td>
            <td><?= e($r['creado_por'] ?? '—') ?></td>
            <td class="acciones">
                <button type="button" class="btn btn-danger btn-sm btn-eliminar-slip" data-id="<?= (int)$r['id'] ?>" data-nombre="<?= e($r['muelle_nombre'] . ' - ' . $r['nombre']) ?>">Eliminar</button>
                <button type="button" class="btn btn-secondary btn-sm btn-editar-slip" data-id="<?= (int)$r['id'] ?>" data-muelle-id="<?= (int)$r['muelle_id'] ?>" data-nombre="<?= e($r['nombre']) ?>">Editar</button>
            </td>
        </tr>
    <?php endwhile; ?>
    </tbody>
</table>

<div class="modal fade" id="slipModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="?p=slips">
                <input type="hidden" name="accion" id="slipFormAccion" value="crear">
                <input type="hidden" name="id" id="slipFormId" value="">
                <div class="modal-header">
                    <h5 class="modal-title" id="slipModalTitle">Nuevo slip</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="slipModalMensaje" class="alert alert-danger d-none"></div>
                    <label>Muelle</label>
                    <select class="form-select" id="slipMuelleId" name="muelle_id" required>
                        <option value="">Seleccione</option>
                        <?php foreach ($muelles as $mid => $mnom): ?>
                            <option value="<?= (int)$mid ?>"><?= e($mnom) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label class="mt-2">Nombre del slip</label>
                    <input type="text" class="form-control" id="slipNombre" name="nombre" required placeholder="Ej: 1, A-12">
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Guardar</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="confirmEliminarSlipModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <form method="post" action="?p=slips&accion=eliminar">
                <input type="hidden" name="id" id="slipDeleteId" value="">
                <div class="modal-header"><h5 class="modal-title">Confirmar eliminación</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">¿Eliminar slip <span id="slipDeleteNombre" class="fw-semibold"></span>?</div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Eliminar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
window.__slipModal = { mostrar: <?= $mostrarModal ? 'true' : 'false' ?>, datos: <?= json_encode($modalDatos, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>, error: <?= json_encode($mensaje, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?> };
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
