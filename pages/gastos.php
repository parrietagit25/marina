<?php
/**
 * Gastos - crear/editar/eliminar con modales
 */
$titulo = 'Gastos';

$pdo = getDb();
$accion = obtener('accion');
$id = (int) obtener('id');
$mensaje = '';

$st = $pdo->query("
    SELECT p.id, p.nombre
    FROM partidas p
    WHERE NOT EXISTS (SELECT 1 FROM partidas h WHERE h.parent_id = p.id)
    ORDER BY p.nombre
");
$partidas_hoja = $st->fetchAll(PDO::FETCH_KEY_PAIR);

if ($accion === 'eliminar' && $id > 0 && enviado()) {
    $pdo->prepare('DELETE FROM gastos WHERE id = ?')->execute([$id]);
    redirigir(MARINA_URL . '/index.php?p=gastos&ok=Eliminado');
}

if (enviado() && ($accion === 'crear' || $accion === 'editar')) {
    $partida_id = (int) ($_POST['partida_id'] ?? 0);
    $proveedor_id = (int) ($_POST['proveedor_id'] ?? 0);
    $cuenta_id = (int) ($_POST['cuenta_id'] ?? 0);
    $forma_pago_id = (int) ($_POST['forma_pago_id'] ?? 0);
    $monto = (float) str_replace(',', '.', $_POST['monto'] ?? 0);
    $fecha_gasto = trim($_POST['fecha_gasto'] ?? '');
    $referencia = trim($_POST['referencia'] ?? '');
    $observaciones = trim($_POST['observaciones'] ?? '');
    $uid = usuarioId();
    if ($partida_id < 1 || $proveedor_id < 1 || $monto <= 0 || $fecha_gasto === '') {
        $mensaje = 'Partida, proveedor, monto y fecha obligatorios.';
    } else {
        if ($accion === 'editar' && $id > 0) {
            $pdo->prepare('UPDATE gastos SET partida_id=?, proveedor_id=?, cuenta_id=?, forma_pago_id=?, monto=?, fecha_gasto=?, referencia=?, observaciones=?, updated_by=? WHERE id=?')
                ->execute([$partida_id, $proveedor_id, $cuenta_id ?: null, $forma_pago_id ?: null, $monto, $fecha_gasto, $referencia, $observaciones, $uid, $id]);
            redirigir(MARINA_URL . '/index.php?p=gastos&ok=Actualizado');
        } else {
            $pdo->prepare('INSERT INTO gastos (partida_id, proveedor_id, cuenta_id, forma_pago_id, monto, fecha_gasto, referencia, observaciones, created_by, updated_by) VALUES (?,?,?,?,?,?,?,?,?,?)')
                ->execute([$partida_id, $proveedor_id, $cuenta_id ?: null, $forma_pago_id ?: null, $monto, $fecha_gasto, $referencia, $observaciones, $uid, $uid]);
            redirigir(MARINA_URL . '/index.php?p=gastos&ok=Creado');
        }
    }
}

$registro = null;
if ($accion === 'editar' && $id > 0) {
    $st = $pdo->prepare('SELECT * FROM gastos WHERE id = ?');
    $st->execute([$id]);
    $registro = $st->fetch();
    if (!$registro) redirigir(MARINA_URL . '/index.php?p=gastos');
}

$proveedores = $pdo->query('SELECT id, nombre FROM proveedores ORDER BY nombre')->fetchAll(PDO::FETCH_KEY_PAIR);
$cuentas = $pdo->query('SELECT c.id, CONCAT(b.nombre, " - ", c.nombre) AS nom FROM cuentas c JOIN bancos b ON c.banco_id = b.id ORDER BY b.nombre, c.nombre')->fetchAll(PDO::FETCH_KEY_PAIR);
$formas_pago = $pdo->query("SELECT id, nombre FROM formas_pago WHERE tipo_movimiento = 'costo' ORDER BY nombre")->fetchAll(PDO::FETCH_KEY_PAIR);

$ok = obtener('ok');
$mostrarModal = enviado() && ($accion === 'crear' || $accion === 'editar') && $mensaje !== '';
$modalDatos = [
    'id' => $id,
    'partida_id' => $registro['partida_id'] ?? ($_POST['partida_id'] ?? ''),
    'proveedor_id' => $registro['proveedor_id'] ?? ($_POST['proveedor_id'] ?? ''),
    'cuenta_id' => $registro['cuenta_id'] ?? ($_POST['cuenta_id'] ?? ''),
    'forma_pago_id' => $registro['forma_pago_id'] ?? ($_POST['forma_pago_id'] ?? ''),
    'monto' => $registro['monto'] ?? ($_POST['monto'] ?? ''),
    'fecha_gasto' => $registro['fecha_gasto'] ?? ($_POST['fecha_gasto'] ?? date('Y-m-d')),
    'referencia' => $registro['referencia'] ?? ($_POST['referencia'] ?? ''),
    'observaciones' => $registro['observaciones'] ?? ($_POST['observaciones'] ?? ''),
];
?>
<?php require_once __DIR__ . '/../includes/layout.php'; ?>

<h1>Gastos</h1>
<?php if ($ok): ?><p class="success"><?= e($ok) ?></p><?php endif; ?>
<?php if ($mensaje && !$mostrarModal): ?><p class="error"><?= e($mensaje) ?></p><?php endif; ?>

<div class="toolbar d-flex gap-2"><button type="button" class="btn btn-primary" id="btnNuevoGasto">Nuevo gasto</button></div>

<?php if (empty($partidas_hoja)): ?>
<p class="error">No hay partidas hoja. Cree partidas en el módulo Partidas; los gastos se registran en las que no tienen subpartidas.</p>
<?php endif; ?>

<table>
    <thead><tr><th>Id</th><th>Partida</th><th>Proveedor</th><th>Monto</th><th>Fecha</th><th>Cuenta</th><th>Creado por</th><th></th></tr></thead>
    <tbody>
    <?php
    $st = $pdo->query("
        SELECT g.*, p.nombre AS partida_nombre, pr.nombre AS proveedor_nombre,
               CONCAT(b.nombre, ' - ', c.nombre) AS cuenta_nombre, u.nombre AS creado_por
        FROM gastos g
        JOIN partidas p ON g.partida_id = p.id
        JOIN proveedores pr ON g.proveedor_id = pr.id
        LEFT JOIN cuentas c ON g.cuenta_id = c.id
        LEFT JOIN bancos b ON c.banco_id = b.id
        LEFT JOIN usuarios u ON g.created_by = u.id
        ORDER BY g.fecha_gasto DESC, g.id DESC
    ");
    while ($r = $st->fetch()): ?>
        <tr>
            <td><?= (int)$r['id'] ?></td>
            <td><?= e($r['partida_nombre']) ?></td>
            <td><?= e($r['proveedor_nombre']) ?></td>
            <td><?= dinero($r['monto']) ?></td>
            <td><?= fechaFormato($r['fecha_gasto']) ?></td>
            <td><?= e($r['cuenta_nombre'] ?? '—') ?></td>
            <td><?= e($r['creado_por'] ?? '—') ?></td>
            <td class="acciones">
                <button type="button" class="btn btn-danger btn-sm btn-eliminar-gasto" data-id="<?= (int)$r['id'] ?>" data-nombre="<?= e($r['partida_nombre'] . ' - ' . dinero($r['monto'])) ?>">Eliminar</button>
                <button type="button" class="btn btn-secondary btn-sm btn-editar-gasto"
                    data-id="<?= (int)$r['id'] ?>"
                    data-partida-id="<?= (int)$r['partida_id'] ?>"
                    data-proveedor-id="<?= (int)$r['proveedor_id'] ?>"
                    data-cuenta-id="<?= (int)($r['cuenta_id'] ?? 0) ?>"
                    data-forma-pago-id="<?= (int)($r['forma_pago_id'] ?? 0) ?>"
                    data-monto="<?= e($r['monto']) ?>"
                    data-fecha-gasto="<?= e($r['fecha_gasto']) ?>"
                    data-referencia="<?= e($r['referencia'] ?? '') ?>"
                    data-observaciones="<?= e($r['observaciones'] ?? '') ?>">Editar</button>
            </td>
        </tr>
    <?php endwhile; ?>
    </tbody>
</table>

<!-- Modal crear/editar gasto -->
<div class="modal fade" id="gastoModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form method="post" action="?p=gastos">
                <input type="hidden" name="accion" id="gastoFormAccion" value="crear">
                <input type="hidden" name="id" id="gastoFormId" value="">
                <div class="modal-header">
                    <h5 class="modal-title" id="gastoModalTitle">Nuevo gasto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="gastoModalMensaje" class="alert alert-danger d-none"></div>
                    <div class="row">
                        <div class="col-md-6">
                            <label>Partida (hoja) *</label>
                            <select class="form-select" id="gastoPartidaId" name="partida_id" required>
                                <option value="">Seleccione</option>
                                <?php foreach ($partidas_hoja as $pid => $pnom): ?>
                                    <option value="<?= (int)$pid ?>"><?= e($pnom) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <label class="mt-2">Proveedor *</label>
                            <select class="form-select" id="gastoProveedorId" name="proveedor_id" required>
                                <option value="">Seleccione</option>
                                <?php foreach ($proveedores as $pid => $pnom): ?>
                                    <option value="<?= (int)$pid ?>"><?= e($pnom) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <label class="mt-2">Cuenta</label>
                            <select class="form-select" id="gastoCuentaId" name="cuenta_id">
                                <option value="">—</option>
                                <?php foreach ($cuentas as $cid => $cnom): ?>
                                    <option value="<?= (int)$cid ?>"><?= e($cnom) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <label class="mt-2">Forma de pago</label>
                            <select class="form-select" id="gastoFormaPagoId" name="forma_pago_id">
                                <option value="">—</option>
                                <?php foreach ($formas_pago as $fid => $fnom): ?>
                                    <option value="<?= (int)$fid ?>"><?= e($fnom) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label>Monto *</label>
                            <input type="text" class="form-control" id="gastoMonto" name="monto" required>
                            <label class="mt-2">Fecha del gasto *</label>
                            <input type="date" class="form-control" id="gastoFechaGasto" name="fecha_gasto" required>
                            <label class="mt-2">Referencia</label>
                            <input type="text" class="form-control" id="gastoReferencia" name="referencia">
                            <label class="mt-2">Observaciones</label>
                            <textarea class="form-control" id="gastoObservaciones" name="observaciones" rows="2"></textarea>
                        </div>
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

<div class="modal fade" id="confirmEliminarGastoModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <form method="post" action="?p=gastos&accion=eliminar">
                <input type="hidden" name="id" id="gastoDeleteId" value="">
                <div class="modal-header"><h5 class="modal-title">Confirmar eliminación</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">¿Eliminar este gasto?</div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Eliminar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>window.__gastoModal = { mostrar: <?= $mostrarModal ? 'true' : 'false' ?>, datos: <?= json_encode($modalDatos, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>, error: <?= json_encode($mensaje, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?> };</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
