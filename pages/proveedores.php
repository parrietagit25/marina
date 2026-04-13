<?php
/**
 * Proveedores - listar, crear/editar con modal, eliminar con modal
 */
$titulo = 'Proveedores';

$pdo = getDb();
$accion = obtener('accion');
$id = (int) obtener('id');
$mensaje = '';

if ($accion === 'eliminar' && $id > 0 && enviado()) {
    $bloqueo = marinaBloqueoEliminarProveedor($pdo, $id);
    if ($bloqueo !== null) {
        redirigir(MARINA_URL . '/index.php?p=proveedores&err=' . rawurlencode($bloqueo));
    }
    try {
        $pdo->prepare('DELETE FROM proveedores WHERE id = ?')->execute([$id]);
        redirigir(MARINA_URL . '/index.php?p=proveedores&ok=' . rawurlencode('Eliminado'));
    } catch (Throwable $e) {
        redirigir(MARINA_URL . '/index.php?p=proveedores&err=' . rawurlencode(marinaMensajeErrorIntegridad($e)));
    }
}

if (enviado() && ($accion === 'crear' || $accion === 'editar')) {
    $nombre = trim($_POST['nombre'] ?? '');
    $documento = trim($_POST['documento'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    $uid = usuarioId();
    if ($nombre === '') {
        $mensaje = 'Nombre obligatorio.';
    } else {
        if ($accion === 'editar' && $id > 0) {
            $pdo->prepare('UPDATE proveedores SET nombre=?, documento=?, telefono=?, email=?, direccion=?, updated_by=? WHERE id=?')
                ->execute([$nombre, $documento, $telefono, $email, $direccion, $uid, $id]);
            redirigir(MARINA_URL . '/index.php?p=proveedores&ok=Actualizado');
        } else {
            $pdo->prepare('INSERT INTO proveedores (nombre, documento, telefono, email, direccion, created_by, updated_by) VALUES (?,?,?,?,?,?,?)')
                ->execute([$nombre, $documento, $telefono, $email, $direccion, $uid, $uid]);
            redirigir(MARINA_URL . '/index.php?p=proveedores&ok=Creado');
        }
    }
}

$registro = null;
if ($accion === 'editar' && $id > 0) {
    $st = $pdo->prepare('SELECT * FROM proveedores WHERE id = ?');
    $st->execute([$id]);
    $registro = $st->fetch();
    if (!$registro) redirigir(MARINA_URL . '/index.php?p=proveedores');
}

$ok = obtener('ok');
$err = obtener('err');
$mostrarModal = enviado() && ($accion === 'crear' || $accion === 'editar') && $mensaje !== '';
$modalDatos = [
    'id' => $id,
    'nombre' => $registro['nombre'] ?? ($_POST['nombre'] ?? ''),
    'documento' => $registro['documento'] ?? ($_POST['documento'] ?? ''),
    'telefono' => $registro['telefono'] ?? ($_POST['telefono'] ?? ''),
    'email' => $registro['email'] ?? ($_POST['email'] ?? ''),
    'direccion' => $registro['direccion'] ?? ($_POST['direccion'] ?? ''),
];
?>
<?php require_once __DIR__ . '/../includes/layout.php'; ?>

<h1>Proveedores</h1>
<?php if ($ok): ?><p class="success"><?= e($ok) ?></p><?php endif; ?>
<?php if ($err): ?><p class="error"><?= e($err) ?></p><?php endif; ?>
<?php if ($mensaje && !$mostrarModal): ?><p class="error"><?= e($mensaje) ?></p><?php endif; ?>

<div class="toolbar d-flex gap-2"><button type="button" class="btn btn-primary" id="btnNuevoProveedor">Nuevo proveedor</button></div>

<table>
    <thead><tr><th>Id</th><th>Nombre</th><th>RUC/Cédula</th><th>Teléfono</th><th>Creado</th><th>Creado por</th><th></th></tr></thead>
    <tbody>
    <?php
    $st = $pdo->query('SELECT p.*, u.nombre AS creado_por FROM proveedores p LEFT JOIN usuarios u ON p.created_by = u.id ORDER BY p.nombre');
    while ($r = $st->fetch()): ?>
        <tr>
            <td><?= (int)$r['id'] ?></td>
            <td><?= e($r['nombre']) ?></td>
            <td><?= e($r['documento'] ?? '—') ?></td>
            <td><?= e($r['telefono'] ?? '—') ?></td>
            <td><?= fechaHoraFormato($r['created_at']) ?></td>
            <td><?= e($r['creado_por'] ?? '—') ?></td>
            <td class="acciones">
                <button type="button" class="btn btn-danger btn-sm btn-eliminar-proveedor" data-id="<?= (int)$r['id'] ?>" data-nombre="<?= e($r['nombre']) ?>">Eliminar</button>
                <button type="button" class="btn btn-secondary btn-sm btn-editar-proveedor" data-id="<?= (int)$r['id'] ?>" data-nombre="<?= e($r['nombre']) ?>" data-documento="<?= e($r['documento'] ?? '') ?>" data-telefono="<?= e($r['telefono'] ?? '') ?>" data-email="<?= e($r['email'] ?? '') ?>" data-direccion="<?= e($r['direccion'] ?? '') ?>">Editar</button>
            </td>
        </tr>
    <?php endwhile; ?>
    </tbody>
</table>

<div class="modal fade" id="proveedorModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="?p=proveedores">
                <input type="hidden" name="accion" id="proveedorFormAccion" value="crear">
                <input type="hidden" name="id" id="proveedorFormId" value="">
                <div class="modal-header">
                    <h5 class="modal-title" id="proveedorModalTitle">Nuevo proveedor</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="proveedorModalMensaje" class="alert alert-danger d-none"></div>
                    <label>Nombre *</label>
                    <input type="text" class="form-control" id="proveedorNombre" name="nombre" required>
                    <label class="mt-2">RUC/Cédula</label>
                    <input type="text" class="form-control" id="proveedorDocumento" name="documento">
                    <label class="mt-2">Teléfono</label>
                    <input type="text" class="form-control" id="proveedorTelefono" name="telefono">
                    <label class="mt-2">Email</label>
                    <input type="email" class="form-control" id="proveedorEmail" name="email">
                    <label class="mt-2">Dirección</label>
                    <textarea class="form-control" id="proveedorDireccion" name="direccion" rows="2"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Guardar</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="confirmEliminarProveedorModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <form method="post" action="?p=proveedores&accion=eliminar">
                <input type="hidden" name="id" id="proveedorDeleteId" value="">
                <div class="modal-header"><h5 class="modal-title">Confirmar eliminación</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">¿Eliminar proveedor <span id="proveedorDeleteNombre" class="fw-semibold"></span>?</div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Eliminar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>window.__proveedorModal = { mostrar: <?= $mostrarModal ? 'true' : 'false' ?>, datos: <?= json_encode($modalDatos, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>, error: <?= json_encode($mensaje, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?> };</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
