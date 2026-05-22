<?php
/**
 * Usuarios - listar, registrar/editar con modal, eliminación con modal
 */
$titulo = 'Usuarios';

require_once __DIR__ . '/../includes/permisos.php';

$pdo = getDb();
$accion = obtener('accion');
$id = (int) obtener('id');
$mensaje = '';

// Guardar permisos (rol / menú)
if ($accion === 'guardar_permisos' && enviado()) {
    if (!marina_permiso_puede_gestionar_roles()) {
        redirigir(MARINA_URL . '/index.php?p=usuarios&err=' . rawurlencode('No tiene permiso para configurar roles.'));
    }
    $uidPerm = (int) ($_POST['usuario_id'] ?? 0);
    if ($uidPerm <= 0) {
        redirigir(MARINA_URL . '/index.php?p=usuarios&err=' . rawurlencode('Usuario no válido.'));
    }
    $accesoTotal = isset($_POST['acceso_total']);
    if ($accesoTotal) {
        $pdo->prepare('UPDATE usuarios SET permisos_json = NULL, updated_by = ? WHERE id = ?')
            ->execute([usuarioId(), $uidPerm]);
    } else {
        $paginas = isset($_POST['perm_pagina']) && is_array($_POST['perm_pagina']) ? $_POST['perm_pagina'] : [];
        $editar = isset($_POST['perm_editar']);
        $eliminar = isset($_POST['perm_eliminar']);
        marina_permisos_guardar_usuario($pdo, $uidPerm, $paginas, $editar, $eliminar);
    }
    if ($uidPerm === usuarioId()) {
        marina_permisos_hidratar_sesion($pdo, $uidPerm);
    }
    redirigir(MARINA_URL . '/index.php?p=usuarios&ok=' . rawurlencode('Permisos del usuario actualizados.'));
}

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

$puedeGestionarRoles = marina_permiso_puede_gestionar_roles();
$puedeEditarUsuarios = marina_permiso_puede_editar();
$puedeEliminarUsuarios = marina_permiso_puede_eliminar();
$permisosPorUsuario = [];
if ($puedeGestionarRoles) {
    $stPerm = $pdo->query('SELECT id, nombre, permisos_json FROM usuarios ORDER BY nombre');
    while ($row = $stPerm->fetch(PDO::FETCH_ASSOC)) {
        $parsed = marina_permisos_parse_json($row['permisos_json'] !== null ? (string) $row['permisos_json'] : null);
        $permisosPorUsuario[(int) $row['id']] = [
            'nombre' => $row['nombre'],
            'acceso_total' => $parsed['acceso_total'],
            'paginas' => $parsed['paginas'],
            'editar' => $parsed['editar'],
            'eliminar' => $parsed['eliminar'],
        ];
    }
}
$menuPermisosDef = marina_menu_permisos_definicion();
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

<div class="toolbar d-flex gap-2 align-items-center flex-wrap">
    <?php if ($puedeEditarUsuarios): ?>
    <button type="button" class="btn btn-primary" id="btnNuevoUsuario">Nuevo usuario</button>
    <?php endif; ?>
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
                <?php if ($puedeGestionarRoles): ?>
                <button type="button"
                        class="btn btn-outline-primary btn-sm btn-rol-usuario"
                        data-id="<?= (int)$r['id'] ?>"
                        data-nombre="<?= e($r['nombre']) ?>">
                    Rol
                </button>
                <?php endif; ?>
                <?php if (!$noEliminar && $puedeEliminarUsuarios): ?>
                    <button type="button"
                            class="btn btn-danger btn-sm btn-eliminar-usuario"
                            data-id="<?= (int)$r['id'] ?>"
                            data-nombre="<?= e($r['nombre']) ?>">
                        Eliminar
                    </button>
                <?php endif; ?>
                <?php if ($puedeEditarUsuarios): ?>
                <button type="button"
                        class="btn btn-secondary btn-sm btn-editar-usuario"
                        data-id="<?= (int)$r['id'] ?>"
                        data-nombre="<?= e($r['nombre']) ?>"
                        data-email="<?= e($r['email']) ?>"
                        data-activo="<?= (int)$r['activo'] ?>">
                    Editar
                </button>
                <?php endif; ?>
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

<?php if ($puedeGestionarRoles): ?>
<!-- Modal permisos / rol -->
<div class="modal fade" id="usuarioRolModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form method="post" action="?p=usuarios" id="usuarioRolForm">
                <input type="hidden" name="accion" value="guardar_permisos">
                <input type="hidden" name="usuario_id" id="usuarioRolId" value="">

                <div class="modal-header">
                    <h5 class="modal-title">Rol — <span id="usuarioRolNombre" class="fw-semibold"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <div class="modal-body">
                    <p class="text-muted small">Marque las secciones del menú a las que tendrá acceso. Los permisos de <strong>Editar</strong> y <strong>Eliminar</strong> aplican en todo el sistema.</p>

                    <div class="permisos-globales card bg-light border-0 p-3 mb-3">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="acceso_total" id="permAccesoTotal" value="1">
                            <label class="form-check-label fw-semibold" for="permAccesoTotal">Acceso completo (sin restricciones)</label>
                        </div>
                        <div class="row g-2">
                            <div class="col-sm-6">
                                <div class="form-check">
                                    <input class="form-check-input perm-global-flag" type="checkbox" name="perm_editar" id="permEditar" value="1">
                                    <label class="form-check-label" for="permEditar">Permitir <strong>editar</strong> (crear y modificar registros)</label>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="form-check">
                                    <input class="form-check-input perm-global-flag" type="checkbox" name="perm_eliminar" id="permEliminar" value="1">
                                    <label class="form-check-label" for="permEliminar">Permitir <strong>eliminar</strong> registros</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="permisosMenuLista" class="permisos-menu-lista">
                        <?php foreach ($menuPermisosDef as $bloque):
                            if (($bloque['seccion'] ?? '') === 'General') {
                                continue;
                            }
                            $secId = preg_replace('/[^a-zA-Z0-9]/', '', $bloque['seccion']);
                        ?>
                        <div class="permisos-menu-seccion mb-3" data-seccion="<?= e($secId) ?>">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="fw-semibold text-primary"><?= e($bloque['seccion']) ?></span>
                                <button type="button" class="btn btn-link btn-sm p-0 perm-seccion-toggle" data-seccion="<?= e($secId) ?>">Marcar sección</button>
                            </div>
                            <div class="row g-1">
                                <?php foreach ($bloque['items'] as $it): ?>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input perm-pagina-check" type="checkbox"
                                               name="perm_pagina[]"
                                               id="perm-<?= e($it['pagina']) ?>"
                                               value="<?= e($it['pagina']) ?>"
                                               data-seccion="<?= e($secId) ?>">
                                        <label class="form-check-label" for="perm-<?= e($it['pagina']) ?>"><?= e($it['etiqueta']) ?></label>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <div class="permisos-menu-seccion mb-2">
                            <span class="fw-semibold text-primary d-block mb-2">General</span>
                            <div class="row g-1">
                                <?php foreach ($menuPermisosDef[0]['items'] ?? [] as $it): ?>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input perm-pagina-check" type="checkbox"
                                               name="perm_pagina[]"
                                               id="perm-<?= e($it['pagina']) ?>"
                                               value="<?= e($it['pagina']) ?>">
                                        <label class="form-check-label" for="perm-<?= e($it['pagina']) ?>"><?= e($it['etiqueta']) ?></label>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Guardar permisos</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

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
    window.__usuariosPermisos = <?= json_encode($permisosPorUsuario, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    window.__permTodasPaginas = <?= json_encode(marina_permisos_todas_paginas(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>

