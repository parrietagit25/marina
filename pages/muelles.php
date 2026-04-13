<?php
/**
 * Muelles - listar, crear/editar con modal, eliminar con modal
 */
$titulo = 'Muelles';

$pdo = getDb();
$accion = obtener('accion');
$id = (int) obtener('id');
$mensaje = '';

if ($accion === 'eliminar' && $id > 0 && enviado()) {
    $stC = $pdo->prepare('
        SELECT COUNT(*) FROM contratos
        WHERE COALESCE(estado, \'activo\') = \'activo\'
          AND (muelle_id = ?
           OR slip_id IN (SELECT id FROM slips WHERE muelle_id = ?))
    ');
    $stC->execute([$id, $id]);
    $numContratos = (int) $stC->fetchColumn();
    if ($numContratos > 0) {
        redirigir(MARINA_URL . '/index.php?p=muelles&err=' . rawurlencode(
            'No se puede eliminar: hay contratos vinculados a este muelle o a sus slips. Revise o reasigne los contratos primero.'
        ));
    }
    try {
        $pdo->beginTransaction();
        $pdo->prepare('DELETE FROM slips WHERE muelle_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM muelles WHERE id = ?')->execute([$id]);
        $pdo->commit();
        redirigir(MARINA_URL . '/index.php?p=muelles&ok=' . rawurlencode('Muelle eliminado'));
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        redirigir(MARINA_URL . '/index.php?p=muelles&err=' . rawurlencode(marinaMensajeErrorIntegridad($e)));
    }
}

if (enviado() && ($accion === 'crear' || $accion === 'editar')) {
    $nombre = trim($_POST['nombre'] ?? '');
    $uid = usuarioId();
    if ($nombre === '') {
        $mensaje = 'Nombre obligatorio.';
    } else {
        if ($accion === 'editar' && $id > 0) {
            $pdo->prepare('UPDATE muelles SET nombre=?, updated_by=? WHERE id=?')->execute([$nombre, $uid, $id]);
            redirigir(MARINA_URL . '/index.php?p=muelles&ok=Actualizado');
        } else {
            $pdo->prepare('INSERT INTO muelles (nombre, created_by, updated_by) VALUES (?,?,?)')->execute([$nombre, $uid, $uid]);
            redirigir(MARINA_URL . '/index.php?p=muelles&ok=Creado');
        }
    }
}

$registro = null;
if ($accion === 'editar' && $id > 0) {
    $st = $pdo->prepare('SELECT * FROM muelles WHERE id = ?');
    $st->execute([$id]);
    $registro = $st->fetch();
    if (!$registro) redirigir(MARINA_URL . '/index.php?p=muelles');
}

$ok = obtener('ok');
$err = obtener('err');
$mostrarModal = enviado() && ($accion === 'crear' || $accion === 'editar') && $mensaje !== '';
$modalDatos = ['id' => $id, 'nombre' => $registro['nombre'] ?? ($_POST['nombre'] ?? '')];
?>
<?php require_once __DIR__ . '/../includes/layout.php'; ?>

<h1>Muelles</h1>
<?php if ($ok): ?><p class="success"><?= e($ok) ?></p><?php endif; ?>
<?php if ($err): ?><p class="error"><?= e($err) ?></p><?php endif; ?>
<?php if ($mensaje && !$mostrarModal): ?><p class="error"><?= e($mensaje) ?></p><?php endif; ?>

<div class="toolbar d-flex gap-2"><button type="button" class="btn btn-primary" id="btnNuevoMuelle">Nuevo muelle</button></div>

<table>
    <thead><tr><th>Id</th><th>Nombre</th><th>Creado</th><th>Creado por</th><th></th></tr></thead>
    <tbody>
    <?php
    $st = $pdo->query('SELECT m.*, u.nombre AS creado_por FROM muelles m LEFT JOIN usuarios u ON m.created_by = u.id ORDER BY m.nombre');
    while ($r = $st->fetch()): ?>
        <tr>
            <td><?= (int)$r['id'] ?></td>
            <td><?= e($r['nombre']) ?></td>
            <td><?= fechaHoraFormato($r['created_at']) ?></td>
            <td><?= e($r['creado_por'] ?? '—') ?></td>
            <td class="acciones">
                <button type="button" class="btn btn-danger btn-sm btn-eliminar-muelle" data-id="<?= (int)$r['id'] ?>" data-nombre="<?= e($r['nombre']) ?>">Eliminar</button>
                <button type="button" class="btn btn-secondary btn-sm btn-editar-muelle" data-id="<?= (int)$r['id'] ?>" data-nombre="<?= e($r['nombre']) ?>">Editar</button>
            </td>
        </tr>
    <?php endwhile; ?>
    </tbody>
</table>

<div class="modal fade" id="muelleModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="?p=muelles">
                <input type="hidden" name="accion" id="muelleFormAccion" value="crear">
                <input type="hidden" name="id" id="muelleFormId" value="">
                <div class="modal-header">
                    <h5 class="modal-title" id="muelleModalTitle">Nuevo muelle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="muelleModalMensaje" class="alert alert-danger d-none"></div>
                    <label>Nombre</label>
                    <input type="text" class="form-control" id="muelleNombre" name="nombre" required>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Guardar</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="confirmEliminarMuelleModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <form method="post" action="?p=muelles&accion=eliminar">
                <input type="hidden" name="id" id="muelleDeleteId" value="">
                <div class="modal-header"><h5 class="modal-title">Confirmar eliminación</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">¿Eliminar muelle <span id="muelleDeleteNombre" class="fw-semibold"></span>?</div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Eliminar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>window.__muelleModal = { mostrar: <?= $mostrarModal ? 'true' : 'false' ?>, datos: <?= json_encode($modalDatos, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>, error: <?= json_encode($mensaje, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?> };</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
