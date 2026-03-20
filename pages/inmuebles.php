<?php
/**
 * Inmuebles - listar, crear/editar con modal, eliminar con modal
 */
$titulo = 'Inmuebles';

$pdo = getDb();
$accion = obtener('accion');
$id = (int) obtener('id');
$mensaje = '';

if ($accion === 'eliminar' && $id > 0 && enviado()) {
    $pdo->prepare('DELETE FROM inmuebles WHERE id = ?')->execute([$id]);
    redirigir(MARINA_URL . '/index.php?p=inmuebles&ok=Inmueble+eliminado');
}

if (enviado() && ($accion === 'crear' || $accion === 'editar')) {
    $grupo_id = (int) ($_POST['grupo_id'] ?? 0);
    $nombre = trim($_POST['nombre'] ?? '');
    $uid = usuarioId();
    if ($grupo_id < 1 || $nombre === '') {
        $mensaje = 'Grupo y nombre obligatorios.';
    } else {
        if ($accion === 'editar' && $id > 0) {
            $pdo->prepare('UPDATE inmuebles SET grupo_id=?, nombre=?, updated_by=? WHERE id=?')->execute([$grupo_id, $nombre, $uid, $id]);
            redirigir(MARINA_URL . '/index.php?p=inmuebles&ok=Actualizado');
        } else {
            $pdo->prepare('INSERT INTO inmuebles (grupo_id, nombre, created_by, updated_by) VALUES (?,?,?,?)')->execute([$grupo_id, $nombre, $uid, $uid]);
            redirigir(MARINA_URL . '/index.php?p=inmuebles&ok=Creado');
        }
    }
}

$registro = null;
if ($accion === 'editar' && $id > 0) {
    $st = $pdo->prepare('SELECT * FROM inmuebles WHERE id = ?');
    $st->execute([$id]);
    $registro = $st->fetch();
    if (!$registro) redirigir(MARINA_URL . '/index.php?p=inmuebles');
}

$grupos = $pdo->query('SELECT id, nombre FROM grupos ORDER BY nombre')->fetchAll(PDO::FETCH_KEY_PAIR);
$ok = obtener('ok');
$mostrarModal = enviado() && ($accion === 'crear' || $accion === 'editar') && $mensaje !== '';
$modalDatos = [
    'id' => $id,
    'grupo_id' => $registro['grupo_id'] ?? ($_POST['grupo_id'] ?? ''),
    'nombre' => $registro['nombre'] ?? ($_POST['nombre'] ?? ''),
];
?>
<?php require_once __DIR__ . '/../includes/layout.php'; ?>

<h1>Inmuebles</h1>
<?php if ($ok): ?><p class="success"><?= e($ok) ?></p><?php endif; ?>
<?php if ($mensaje && !$mostrarModal): ?><p class="error"><?= e($mensaje) ?></p><?php endif; ?>

<div class="toolbar d-flex gap-2">
    <button type="button" class="btn btn-primary" id="btnNuevoInmueble" data-bs-toggle="modal" data-bs-target="#inmuebleModal">Nuevo inmueble</button>
</div>

<table>
    <thead><tr><th>Id</th><th>Grupo</th><th>Nombre inmueble</th><th>Creado</th><th>Creado por</th><th></th></tr></thead>
    <tbody>
    <?php
    $st = $pdo->query('SELECT i.*, g.nombre AS grupo_nombre, u.nombre AS creado_por FROM inmuebles i JOIN grupos g ON i.grupo_id = g.id LEFT JOIN usuarios u ON i.created_by = u.id ORDER BY g.nombre, i.nombre');
    while ($r = $st->fetch()): ?>
        <tr>
            <td><?= (int)$r['id'] ?></td>
            <td><?= e($r['grupo_nombre']) ?></td>
            <td><?= e($r['nombre']) ?></td>
            <td><?= fechaHoraFormato($r['created_at']) ?></td>
            <td><?= e($r['creado_por'] ?? '—') ?></td>
            <td class="acciones">
                <button type="button" class="btn btn-danger btn-sm btn-eliminar-inmueble" data-id="<?= (int)$r['id'] ?>" data-nombre="<?= e($r['grupo_nombre'] . ' - ' . $r['nombre']) ?>">Eliminar</button>
                <button type="button" class="btn btn-secondary btn-sm btn-editar-inmueble" data-id="<?= (int)$r['id'] ?>" data-grupo-id="<?= (int)$r['grupo_id'] ?>" data-nombre="<?= e($r['nombre']) ?>">Editar</button>
            </td>
        </tr>
    <?php endwhile; ?>
    </tbody>
</table>

<div class="modal fade" id="inmuebleModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="?p=inmuebles">
                <input type="hidden" name="accion" id="inmuebleFormAccion" value="crear">
                <input type="hidden" name="id" id="inmuebleFormId" value="">
                <div class="modal-header">
                    <h5 class="modal-title" id="inmuebleModalTitle">Nuevo inmueble</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="inmuebleModalMensaje" class="alert alert-danger d-none"></div>
                    <label>Grupo</label>
                    <select class="form-select" id="inmuebleGrupoId" name="grupo_id" required>
                        <option value="">Seleccione</option>
                        <?php foreach ($grupos as $gid => $gnom): ?>
                            <option value="<?= (int)$gid ?>"><?= e($gnom) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label class="mt-2">Nombre del inmueble</label>
                    <input type="text" class="form-control" id="inmuebleNombre" name="nombre" required placeholder="Ej: Local 1, Casa A-12">
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Guardar</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="confirmEliminarInmuebleModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <form method="post" action="?p=inmuebles&accion=eliminar">
                <input type="hidden" name="id" id="inmuebleDeleteId" value="">
                <div class="modal-header"><h5 class="modal-title">Confirmar eliminación</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">¿Eliminar inmueble <span id="inmuebleDeleteNombre" class="fw-semibold"></span>?</div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Eliminar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
window.__inmuebleModal = { mostrar: <?= $mostrarModal ? 'true' : 'false' ?>, datos: <?= json_encode($modalDatos, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>, error: <?= json_encode($mensaje, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?> };
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

