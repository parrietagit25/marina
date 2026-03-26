<?php
/**
 * Usuarios - listar, registrar/editar con modal, eliminación con modal
 */
$titulo = 'Usuarios';

$pdo = getDb();
$accion = obtener('accion');
$id = (int) obtener('id');
$mensaje = '';

// Eliminar
if ($accion === 'eliminar' && $id > 0 && enviado()) {
    if ($id === usuarioId()) {
        redirigir(MARINA_URL . '/index.php?p=usuarios&err=' . rawurlencode('No puede eliminar su propio usuario.'));
    }
    $bloqueo = marinaBloqueoEliminarUsuario($pdo, $id);
    if ($bloqueo !== null) {
        redirigir(MARINA_URL . '/index.php?p=usuarios&err=' . rawurlencode($bloqueo));
    }
    try {
        $pdo->prepare('DELETE FROM usuarios WHERE id = ?')->execute([$id]);
        redirigir(MARINA_URL . '/index.php?p=usuarios&ok=' . rawurlencode('Usuario eliminado.'));
    } catch (Throwable $e) {
        redirigir(MARINA_URL . '/index.php?p=usuarios&err=' . rawurlencode(marinaMensajeErrorIntegridad($e)));
    }
}

// Guardar (crear o editar)
if (enviado() && ($accion === 'crear' || $accion === 'editar')) {
    $nombre = trim($_POST['nombre'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $activo = isset($_POST['activo']) ? 1 : 0;
    $uid = usuarioId();

    if ($nombre === '' || $email === '') {
        $mensaje = 'Nombre y email obligatorios.';
    } else {
        if ($accion === 'editar' && $id > 0) {
            $sql = 'UPDATE usuarios SET nombre=?, email=?, activo=?, updated_by=? WHERE id=?';
            $params = [$nombre, $email, $activo, $uid, $id];
            if (!empty($_POST['password'])) {
                $sql = 'UPDATE usuarios SET nombre=?, email=?, password_hash=?, activo=?, updated_by=? WHERE id=?';
                $params = [$nombre, $email, password_hash($_POST['password'], PASSWORD_DEFAULT), $activo, $uid, $id];
            }
            $pdo->prepare($sql)->execute($params);
            $mensaje = 'Usuario actualizado.';
        } else {
            $pass = $_POST['password'] ?? '';
            if (strlen($pass) < 6) {
                $mensaje = 'Contraseña mínimo 6 caracteres.';
            } else {
                $pdo->prepare('INSERT INTO usuarios (nombre, email, password_hash, rol, activo, created_by, updated_by)
                    VALUES (?,?,?,?,?,?,?)')
                    ->execute([$nombre, $email, password_hash($pass, PASSWORD_DEFAULT), 'admin', $activo, $uid, $uid]);
                $mensaje = 'Usuario creado.';
            }
        }

        if ($mensaje) {
            redirigir(MARINA_URL . '/index.php?p=usuarios&ok=' . urlencode($mensaje));
        }
    }
}

$registro = null;
if ($accion === 'editar' && $id > 0) {
    $st = $pdo->prepare('SELECT * FROM usuarios WHERE id = ?');
    $st->execute([$id]);
    $registro = $st->fetch();
    if (!$registro) redirigir(MARINA_URL . '/index.php?p=usuarios');
}

$ok = obtener('ok');
$err = obtener('err');
$mostrarModal = enviado() && ($accion === 'crear' || $accion === 'editar') && $mensaje !== '';
$modoModal = ($accion === 'editar') ? 'editar' : 'crear';
$modalDatos = [
    'id' => $id,
    'nombre' => $registro['nombre'] ?? ($_POST['nombre'] ?? ''),
    'email' => $registro['email'] ?? ($_POST['email'] ?? ''),
    'activo' => isset($_POST['activo']) ? true : (bool)($registro['activo'] ?? false),
];
?>

<?php require_once __DIR__ . '/../includes/layout.php'; ?>

<h1>Usuarios</h1>

<?php if ($ok): ?>
    <p class="success"><?= e($ok) ?></p>
<?php endif; ?>
<?php if ($err): ?>
    <p class="error"><?= e($err) ?></p>
<?php endif; ?>

<?php
if ($mensaje && !$mostrarModal) {
    echo '<p class="error">' . e($mensaje) . '</p>';
}
?>

<div class="toolbar d-flex gap-2 align-items-center">
    <button type="button" class="btn btn-primary" id="btnNuevoUsuario">Nuevo usuario</button>
</div>

<table>
    <thead>
        <tr><th>Id</th><th>Nombre</th><th>Email</th><th>Rol</th><th>Activo</th><th>Creado</th><th>Creado por</th><th></th></tr>
    </thead>
    <tbody>
    <?php
    $st = $pdo->query('
        SELECT u.id, u.nombre, u.email, u.rol, u.activo, u.created_at,
               cu.nombre AS creado_por_nombre
        FROM usuarios u
        LEFT JOIN usuarios cu ON u.created_by = cu.id
        ORDER BY u.nombre
    ');
    while ($r = $st->fetch()):
        $creado = $r['creado_por_nombre'] ? e($r['creado_por_nombre']) : '—';
        $noEliminar = ((int)$r['id'] === (int)usuarioId());
    ?>
        <tr>
            <td><?= (int)$r['id'] ?></td>
            <td><?= e($r['nombre']) ?></td>
            <td><?= e($r['email']) ?></td>
            <td><?= e($r['rol']) ?></td>
            <td><?= $r['activo'] ? 'Sí' : 'No' ?></td>
            <td><?= fechaHoraFormato($r['created_at']) ?></td>
            <td><?= $creado ?></td>
            <td class="acciones">
                <?php if (!$noEliminar): ?>
                    <button type="button"
                            class="btn btn-danger btn-sm btn-eliminar-usuario"
                            data-id="<?= (int)$r['id'] ?>"
                            data-nombre="<?= e($r['nombre']) ?>">
                        Eliminar
                    </button>
                <?php endif; ?>

                <button type="button"
                        class="btn btn-secondary btn-sm btn-editar-usuario"
                        data-id="<?= (int)$r['id'] ?>"
                        data-nombre="<?= e($r['nombre']) ?>"
                        data-email="<?= e($r['email']) ?>"
                        data-activo="<?= (int)$r['activo'] ?>">
                    Editar
                </button>
            </td>
        </tr>
    <?php endwhile; ?>
    </tbody>
</table>

<!-- Modal crear/editar usuario -->
<div class="modal fade" id="usuarioModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" id="usuarioForm" action="?p=usuarios">
                <input type="hidden" name="accion" id="usuarioFormAccion" value="crear">
                <input type="hidden" name="id" id="usuarioFormId" value="">

                <div class="modal-header">
                    <h5 class="modal-title" id="usuarioModalTitle">Nuevo usuario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <div class="modal-body">
                    <div id="usuarioModalMensaje" class="alert alert-danger d-none" role="alert"></div>

                    <label>Nombre</label>
                    <input type="text" class="form-control" id="usuarioNombre" name="nombre" required>

                    <label>Email</label>
                    <input type="email" class="form-control" id="usuarioEmail" name="email" required>

                    <label>Contraseña <small class="text-muted">(dejar vacío para no cambiar)</small></label>
                    <input type="password" class="form-control" id="usuarioPassword" name="password" minlength="6">

                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" id="usuarioActivo" name="activo" value="1" checked>
                        <label class="form-check-label" for="usuarioActivo">Activo</label>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Guardar</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal confirmar eliminación -->
<div class="modal fade" id="confirmEliminarUsuarioModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <form method="post" action="?p=usuarios&accion=eliminar" id="usuarioDeleteForm">
                <input type="hidden" name="id" id="usuarioDeleteId" value="">

                <div class="modal-header">
                    <h5 class="modal-title">Confirmar eliminación</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <div class="modal-body">
                    ¿Eliminar usuario <span id="usuarioDeleteNombre" class="fw-semibold"></span>?
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Eliminar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    window.__usuariosModal = {
        mostrarModal: <?= $mostrarModal ? 'true' : 'false' ?>,
        modo: <?= json_encode($modoModal, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
        datos: <?= json_encode($modalDatos, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
        error: <?= json_encode($mensaje, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
    };
</script>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>

