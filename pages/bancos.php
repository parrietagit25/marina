<?php
/**
 * Bancos - listar, crear/editar con modal, eliminar con modal
 */
$titulo = 'Bancos';

$pdo = getDb();
$accion = obtener('accion');
$id = (int) obtener('id');
$mensaje = '';

if ($accion === 'eliminar' && $id > 0 && enviado()) {
    $pdo->prepare('DELETE FROM bancos WHERE id = ?')->execute([$id]);
    redirigir(MARINA_URL . '/index.php?p=bancos&ok=Banco+eliminado');
}

if (enviado() && ($accion === 'crear' || $accion === 'editar')) {
    $nombre = trim($_POST['nombre'] ?? '');
    $uid = usuarioId();
    if ($nombre === '') {
        $mensaje = 'Nombre obligatorio.';
    } else {
        if ($accion === 'editar' && $id > 0) {
            $pdo->prepare('UPDATE bancos SET nombre=?, updated_by=? WHERE id=?')->execute([$nombre, $uid, $id]);
            redirigir(MARINA_URL . '/index.php?p=bancos&ok=Actualizado');
        } else {
            $pdo->prepare('INSERT INTO bancos (nombre, created_by, updated_by) VALUES (?,?,?)')->execute([$nombre, $uid, $uid]);
            redirigir(MARINA_URL . '/index.php?p=bancos&ok=Creado');
        }
    }
}

$registro = null;
if ($accion === 'editar' && $id > 0) {
    $st = $pdo->prepare('SELECT * FROM bancos WHERE id = ?');
    $st->execute([$id]);
    $registro = $st->fetch();
    if (!$registro) redirigir(MARINA_URL . '/index.php?p=bancos');
}

$ok = obtener('ok');
$mostrarModal = enviado() && ($accion === 'crear' || $accion === 'editar') && $mensaje !== '';
$modalDatos = ['id' => $id, 'nombre' => $registro['nombre'] ?? ($_POST['nombre'] ?? '')];
?>
<?php require_once __DIR__ . '/../includes/layout.php'; ?>

<h1>Bancos</h1>
<?php if ($ok): ?><p class="success"><?= e($ok) ?></p><?php endif; ?>
<?php if ($mensaje && !$mostrarModal): ?><p class="error"><?= e($mensaje) ?></p><?php endif; ?>

<div class="toolbar d-flex gap-2"><button type="button" class="btn btn-primary" id="btnNuevoBanco">Nuevo banco</button></div>

<table>
    <thead><tr><th>Id</th><th>Nombre</th><th>Creado</th><th>Creado por</th><th></th></tr></thead>
    <tbody>
    <?php
    $st = $pdo->query('SELECT b.*, u.nombre AS creado_por FROM bancos b LEFT JOIN usuarios u ON b.created_by = u.id ORDER BY b.nombre');
    while ($r = $st->fetch()): ?>
        <tr>
            <td><?= (int)$r['id'] ?></td>
            <td><?= e($r['nombre']) ?></td>
            <td><?= fechaHoraFormato($r['created_at']) ?></td>
            <td><?= e($r['creado_por'] ?? '—') ?></td>
            <td class="acciones">
                <button type="button" class="btn btn-danger btn-sm btn-eliminar-banco" data-id="<?= (int)$r['id'] ?>" data-nombre="<?= e($r['nombre']) ?>">Eliminar</button>
                <button type="button" class="btn btn-secondary btn-sm btn-editar-banco" data-id="<?= (int)$r['id'] ?>" data-nombre="<?= e($r['nombre']) ?>">Editar</button>
            </td>
        </tr>
    <?php endwhile; ?>
    </tbody>
</table>

<!-- Modal crear/editar -->
<div class="modal fade" id="bancoModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="?p=bancos">
                <input type="hidden" name="accion" id="bancoFormAccion" value="crear">
                <input type="hidden" name="id" id="bancoFormId" value="">
                <div class="modal-header">
                    <h5 class="modal-title" id="bancoModalTitle">Nuevo banco</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="bancoModalMensaje" class="alert alert-danger d-none"></div>
                    <label>Nombre</label>
                    <input type="text" class="form-control" id="bancoNombre" name="nombre" required>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Guardar</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal confirmar eliminar -->
<div class="modal fade" id="confirmEliminarBancoModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <form method="post" action="?p=bancos&accion=eliminar">
                <input type="hidden" name="id" id="bancoDeleteId" value="">
                <div class="modal-header"><h5 class="modal-title">Confirmar eliminación</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">¿Eliminar banco <span id="bancoDeleteNombre" class="fw-semibold"></span>?</div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Eliminar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>window.__bancoModal = { mostrar: <?= $mostrarModal ? 'true' : 'false' ?>, datos: <?= json_encode($modalDatos, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>, error: <?= json_encode($mensaje, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?> };</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
