<?php
/**
 * Grupos - listar, crear/editar con modal, eliminar con modal
 */
$titulo = 'Grupos';

$pdo = getDb();
$accion = obtener('accion');
$id = (int) obtener('id');
$mensaje = '';

if ($accion === 'eliminar' && $id > 0 && enviado()) {
    $pdo->prepare('DELETE FROM grupos WHERE id = ?')->execute([$id]);
    redirigir(MARINA_URL . '/index.php?p=grupos&ok=Grupo+eliminado');
}

if (enviado() && ($accion === 'crear' || $accion === 'editar')) {
    $nombre = trim($_POST['nombre'] ?? '');
    $uid = usuarioId();
    if ($nombre === '') {
        $mensaje = 'Nombre obligatorio.';
    } else {
        if ($accion === 'editar' && $id > 0) {
            $pdo->prepare('UPDATE grupos SET nombre=?, updated_by=? WHERE id=?')->execute([$nombre, $uid, $id]);
            redirigir(MARINA_URL . '/index.php?p=grupos&ok=Actualizado');
        } else {
            $pdo->prepare('INSERT INTO grupos (nombre, created_by, updated_by) VALUES (?,?,?)')->execute([$nombre, $uid, $uid]);
            redirigir(MARINA_URL . '/index.php?p=grupos&ok=Creado');
        }
    }
}

$registro = null;
if ($accion === 'editar' && $id > 0) {
    $st = $pdo->prepare('SELECT * FROM grupos WHERE id = ?');
    $st->execute([$id]);
    $registro = $st->fetch();
    if (!$registro) redirigir(MARINA_URL . '/index.php?p=grupos');
}

$ok = obtener('ok');
$mostrarModal = enviado() && ($accion === 'crear' || $accion === 'editar') && $mensaje !== '';
$modalDatos = ['id' => $id, 'nombre' => $registro['nombre'] ?? ($_POST['nombre'] ?? '')];
?>
<?php require_once __DIR__ . '/../includes/layout.php'; ?>

<h1>Grupos</h1>
<?php if ($ok): ?><p class="success"><?= e($ok) ?></p><?php endif; ?>
<?php if ($mensaje && !$mostrarModal): ?><p class="error"><?= e($mensaje) ?></p><?php endif; ?>

<div class="toolbar d-flex gap-2">
    <button type="button" class="btn btn-primary" id="btnNuevoGrupo" data-bs-toggle="modal" data-bs-target="#grupoModal">Nuevo grupo</button>
</div>

<table>
    <thead><tr><th>Id</th><th>Nombre</th><th>Creado</th><th>Creado por</th><th></th></tr></thead>
    <tbody>
    <?php
    $st = $pdo->query('SELECT g.*, u.nombre AS creado_por FROM grupos g LEFT JOIN usuarios u ON g.created_by = u.id ORDER BY g.nombre');
    while ($r = $st->fetch()): ?>
        <tr>
            <td><?= (int)$r['id'] ?></td>
            <td><?= e($r['nombre']) ?></td>
            <td><?= fechaHoraFormato($r['created_at']) ?></td>
            <td><?= e($r['creado_por'] ?? '—') ?></td>
            <td class="acciones">
                <button type="button" class="btn btn-danger btn-sm btn-eliminar-grupo" data-id="<?= (int)$r['id'] ?>" data-nombre="<?= e($r['nombre']) ?>">Eliminar</button>
                <button type="button" class="btn btn-secondary btn-sm btn-editar-grupo" data-id="<?= (int)$r['id'] ?>" data-nombre="<?= e($r['nombre']) ?>">Editar</button>
            </td>
        </tr>
    <?php endwhile; ?>
    </tbody>
</table>

<div class="modal fade" id="grupoModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="?p=grupos">
                <input type="hidden" name="accion" id="grupoFormAccion" value="crear">
                <input type="hidden" name="id" id="grupoFormId" value="">
                <div class="modal-header">
                    <h5 class="modal-title" id="grupoModalTitle">Nuevo grupo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="grupoModalMensaje" class="alert alert-danger d-none"></div>
                    <label>Nombre</label>
                    <input type="text" class="form-control" id="grupoNombre" name="nombre" required>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Guardar</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="confirmEliminarGrupoModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <form method="post" action="?p=grupos&accion=eliminar">
                <input type="hidden" name="id" id="grupoDeleteId" value="">
                <div class="modal-header"><h5 class="modal-title">Confirmar eliminación</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">¿Eliminar grupo <span id="grupoDeleteNombre" class="fw-semibold"></span>?</div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Eliminar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>window.__grupoModal = { mostrar: <?= $mostrarModal ? 'true' : 'false' ?>, datos: <?= json_encode($modalDatos, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>, error: <?= json_encode($mensaje, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?> };</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

