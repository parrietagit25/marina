<?php
/**
 * Cuentas - listar, crear/editar con modal, eliminar con modal
 */
$titulo = 'Cuentas';

$pdo = getDb();
$accion = obtener('accion');
$id = (int) obtener('id');
$mensaje = '';

if ($accion === 'eliminar' && $id > 0 && enviado()) {
    $pdo->prepare('DELETE FROM cuentas WHERE id = ?')->execute([$id]);
    redirigir(MARINA_URL . '/index.php?p=cuentas&ok=Cuenta+eliminada');
}

if (enviado() && ($accion === 'crear' || $accion === 'editar')) {
    $banco_id = (int) ($_POST['banco_id'] ?? 0);
    $nombre = trim($_POST['nombre'] ?? '');
    $uid = usuarioId();
    if ($banco_id < 1 || $nombre === '') {
        $mensaje = 'Banco y nombre obligatorios.';
    } else {
        if ($accion === 'editar' && $id > 0) {
            $pdo->prepare('UPDATE cuentas SET banco_id=?, nombre=?, updated_by=? WHERE id=?')->execute([$banco_id, $nombre, $uid, $id]);
            redirigir(MARINA_URL . '/index.php?p=cuentas&ok=Actualizado');
        } else {
            $pdo->prepare('INSERT INTO cuentas (banco_id, nombre, created_by, updated_by) VALUES (?,?,?,?)')->execute([$banco_id, $nombre, $uid, $uid]);
            redirigir(MARINA_URL . '/index.php?p=cuentas&ok=Creado');
        }
    }
}

$registro = null;
if ($accion === 'editar' && $id > 0) {
    $st = $pdo->prepare('SELECT * FROM cuentas WHERE id = ?');
    $st->execute([$id]);
    $registro = $st->fetch();
    if (!$registro) redirigir(MARINA_URL . '/index.php?p=cuentas');
}

$bancos = $pdo->query('SELECT id, nombre FROM bancos ORDER BY nombre')->fetchAll(PDO::FETCH_KEY_PAIR);
$ok = obtener('ok');
$mostrarModal = enviado() && ($accion === 'crear' || $accion === 'editar') && $mensaje !== '';
$modalDatos = [
    'id' => $id,
    'banco_id' => $registro['banco_id'] ?? ($_POST['banco_id'] ?? ''),
    'nombre' => $registro['nombre'] ?? ($_POST['nombre'] ?? ''),
];
?>
<?php require_once __DIR__ . '/../includes/layout.php'; ?>

<h1>Cuentas</h1>
<?php if ($ok): ?><p class="success"><?= e($ok) ?></p><?php endif; ?>
<?php if ($mensaje && !$mostrarModal): ?><p class="error"><?= e($mensaje) ?></p><?php endif; ?>

<div class="toolbar d-flex gap-2"><button type="button" class="btn btn-primary" id="btnNuevoCuenta">Nueva cuenta</button></div>

<table>
    <thead><tr><th>Id</th><th>Banco</th><th>Nombre / Nº cuenta</th><th>Creado</th><th>Creado por</th><th></th></tr></thead>
    <tbody>
    <?php
    $st = $pdo->query('SELECT c.*, b.nombre AS banco_nombre, u.nombre AS creado_por FROM cuentas c JOIN bancos b ON c.banco_id = b.id LEFT JOIN usuarios u ON c.created_by = u.id ORDER BY b.nombre, c.nombre');
    while ($r = $st->fetch()): ?>
        <tr>
            <td><?= (int)$r['id'] ?></td>
            <td><?= e($r['banco_nombre']) ?></td>
            <td><?= e($r['nombre']) ?></td>
            <td><?= fechaHoraFormato($r['created_at']) ?></td>
            <td><?= e($r['creado_por'] ?? '—') ?></td>
            <td class="acciones">
                <button type="button" class="btn btn-danger btn-sm btn-eliminar-cuenta" data-id="<?= (int)$r['id'] ?>" data-nombre="<?= e($r['banco_nombre'] . ' - ' . $r['nombre']) ?>">Eliminar</button>
                <button type="button" class="btn btn-secondary btn-sm btn-editar-cuenta" data-id="<?= (int)$r['id'] ?>" data-banco-id="<?= (int)$r['banco_id'] ?>" data-nombre="<?= e($r['nombre']) ?>">Editar</button>
            </td>
        </tr>
    <?php endwhile; ?>
    </tbody>
</table>

<div class="modal fade" id="cuentaModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="?p=cuentas">
                <input type="hidden" name="accion" id="cuentaFormAccion" value="crear">
                <input type="hidden" name="id" id="cuentaFormId" value="">
                <div class="modal-header">
                    <h5 class="modal-title" id="cuentaModalTitle">Nueva cuenta</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="cuentaModalMensaje" class="alert alert-danger d-none"></div>
                    <label>Banco</label>
                    <select class="form-select" id="cuentaBancoId" name="banco_id" required>
                        <option value="">Seleccione</option>
                        <?php foreach ($bancos as $bid => $bnom): ?>
                            <option value="<?= (int)$bid ?>"><?= e($bnom) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label class="mt-2">Nombre / Número de cuenta</label>
                    <input type="text" class="form-control" id="cuentaNombre" name="nombre" required>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Guardar</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="confirmEliminarCuentaModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <form method="post" action="?p=cuentas&accion=eliminar">
                <input type="hidden" name="id" id="cuentaDeleteId" value="">
                <div class="modal-header"><h5 class="modal-title">Confirmar eliminación</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">¿Eliminar cuenta <span id="cuentaDeleteNombre" class="fw-semibold"></span>?</div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Eliminar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
window.__cuentaModal = { mostrar: <?= $mostrarModal ? 'true' : 'false' ?>, datos: <?= json_encode($modalDatos, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>, error: <?= json_encode($mensaje, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?> };
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
