<?php
/**
 * Tarifas: por día o por pie (para cálculo de contratos).
 */
$titulo = 'Tarifas';

$pdo = getDb();
$accion = obtener('accion');
$id = (int) obtener('id');
$mensaje = '';

if ($accion === 'eliminar' && $id > 0 && enviado()) {
    try {
        $pdo->prepare('DELETE FROM tarifas WHERE id = ?')->execute([$id]);
        redirigir(MARINA_URL . '/index.php?p=tarifas&ok=' . rawurlencode('Tarifa eliminada'));
    } catch (Throwable $e) {
        redirigir(MARINA_URL . '/index.php?p=tarifas&err=' . rawurlencode(marinaMensajeErrorIntegridad($e)));
    }
}

if (enviado() && ($accion === 'crear' || $accion === 'editar')) {
    $nombre = trim($_POST['nombre'] ?? '');
    $tipo = trim($_POST['tipo'] ?? 'dia');
    $tipo = $tipo === 'pie' ? 'pie' : 'dia';
    $precio_dia = (float) str_replace(',', '.', $_POST['precio_dia'] ?? 0);
    $uid = usuarioId();
    if ($nombre === '') {
        $mensaje = 'Nombre obligatorio.';
    } elseif ($precio_dia <= 0) {
        $mensaje = $tipo === 'pie' ? 'Indique un precio por pie mayor a cero.' : 'Indique un precio por día mayor a cero.';
    } else {
        if ($accion === 'editar' && $id > 0) {
            $pdo->prepare('UPDATE tarifas SET nombre=?, tipo=?, precio_dia=?, updated_by=? WHERE id=?')
                ->execute([$nombre, $tipo, $precio_dia, $uid, $id]);
            redirigir(MARINA_URL . '/index.php?p=tarifas&ok=Actualizado');
        } else {
            $pdo->prepare('INSERT INTO tarifas (nombre, tipo, precio_dia, created_by, updated_by) VALUES (?,?,?,?,?)')
                ->execute([$nombre, $tipo, $precio_dia, $uid, $uid]);
            redirigir(MARINA_URL . '/index.php?p=tarifas&ok=Creado');
        }
    }
}

$registro = null;
if ($accion === 'editar' && $id > 0) {
    $st = $pdo->prepare('SELECT * FROM tarifas WHERE id = ?');
    $st->execute([$id]);
    $registro = $st->fetch();
    if (!$registro) {
        redirigir(MARINA_URL . '/index.php?p=tarifas');
    }
}

$ok = obtener('ok');
$err = obtener('err');
$mostrarModal = enviado() && ($accion === 'crear' || $accion === 'editar') && $mensaje !== '';
$modalDatos = [
    'id' => $id,
    'nombre' => $registro['nombre'] ?? ($_POST['nombre'] ?? ''),
    'tipo' => $registro['tipo'] ?? ($_POST['tipo'] ?? 'dia'),
    'precio_dia' => $registro['precio_dia'] ?? ($_POST['precio_dia'] ?? ''),
];
?>
<?php require_once __DIR__ . '/../includes/layout.php'; ?>

<h1>Tarifas</h1>
<p class="text-muted small mb-3">Registre tarifas <strong>por día</strong> (estadía) o <strong>por pie</strong> (tamaño del barco). Al crear un contrato puede combinar ambas y aplicar ITBMS.</p>
<?php if ($ok): ?><p class="success"><?= e($ok) ?></p><?php endif; ?>
<?php if ($err): ?><p class="error"><?= e($err) ?></p><?php endif; ?>
<?php if ($mensaje && !$mostrarModal): ?><p class="error"><?= e($mensaje) ?></p><?php endif; ?>

<div class="toolbar d-flex gap-2">
    <button type="button" class="btn btn-primary" id="btnNuevoTarifa">Nueva tarifa</button>
</div>

<table>
    <thead><tr><th>Id</th><th>Nombre</th><th>Tipo</th><th>Precio</th><th>Creado</th><th></th></tr></thead>
    <tbody>
    <?php
    try {
        $st = $pdo->query("SELECT t.*, u.nombre AS creado_por FROM tarifas t LEFT JOIN usuarios u ON t.created_by = u.id ORDER BY COALESCE(t.tipo, 'dia'), t.nombre");
        while ($r = $st->fetch()):
            $tipo = (string) ($r['tipo'] ?? 'dia');
            $tipoLabel = $tipo === 'pie' ? 'Por pie' : 'Por día';
            $precioLabel = $tipo === 'pie' ? '/pie' : '/día';
            ?>
        <tr>
            <td><?= (int) $r['id'] ?></td>
            <td><?= e($r['nombre']) ?></td>
            <td><?= e($tipoLabel) ?></td>
            <td><?= dinero((float) $r['precio_dia']) ?><?= e($precioLabel) ?></td>
            <td><?= fechaHoraFormato($r['created_at']) ?></td>
            <td class="acciones">
                <button type="button" class="btn btn-danger btn-sm btn-eliminar-tarifa" data-id="<?= (int) $r['id'] ?>" data-nombre="<?= e($r['nombre']) ?>">Eliminar</button>
                <button type="button" class="btn btn-secondary btn-sm btn-editar-tarifa"
                    data-id="<?= (int) $r['id'] ?>"
                    data-nombre="<?= e($r['nombre']) ?>"
                    data-tipo="<?= e($tipo) ?>"
                    data-precio-dia="<?= e($r['precio_dia']) ?>">Editar</button>
            </td>
        </tr>
        <?php endwhile;
    } catch (Throwable $e) {
        echo '<tr><td colspan="6" class="text-danger">Ejecute el script <code>sql/tarifas.sql</code> y <code>sql/alter_tarifas_contratos_pie.sql</code> en la base de datos.</td></tr>';
    }
    ?>
    </tbody>
</table>

<div class="modal fade" id="tarifaModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="?p=tarifas">
                <input type="hidden" name="accion" id="tarifaFormAccion" value="crear">
                <input type="hidden" name="id" id="tarifaFormId" value="">
                <div class="modal-header">
                    <h5 class="modal-title" id="tarifaModalTitle">Nueva tarifa</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="tarifaModalMensaje" class="alert alert-danger d-none"></div>
                    <label class="form-label">Nombre de la tarifa *</label>
                    <input type="text" class="form-control" id="tarifaNombre" name="nombre" required maxlength="150">
                    <label class="form-label mt-2">Tipo *</label>
                    <select class="form-select" id="tarifaTipo" name="tipo" required>
                        <option value="dia">Por día (estadía)</option>
                        <option value="pie">Por pie (tamaño del barco)</option>
                    </select>
                    <label class="form-label mt-2" id="tarifaPrecioLabel">Precio por día *</label>
                    <input type="text" class="form-control" id="tarifaPrecioDia" name="precio_dia" required inputmode="decimal" placeholder="0.00">
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Guardar</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="confirmEliminarTarifaModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <form method="post" action="?p=tarifas&accion=eliminar">
                <input type="hidden" name="id" id="tarifaDeleteId" value="">
                <div class="modal-header"><h5 class="modal-title">Confirmar eliminación</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">¿Eliminar tarifa <span id="tarifaDeleteNombre" class="fw-semibold"></span>?</div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Eliminar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>window.__tarifaModal = { mostrar: <?= $mostrarModal ? 'true' : 'false' ?>, datos: <?= json_encode($modalDatos, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>, error: <?= json_encode($mensaje, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?> };</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
