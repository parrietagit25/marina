<?php
/**
 * Clientes - listar, crear/editar con modal, eliminar con modal
 */
$titulo = 'Clientes';

$pdo = getDb();
$accion = obtener('accion');
$id = (int) obtener('id');
$mensaje = '';
$ok = obtener('ok');
$err = obtener('err');

$cuentasRowsMov = $pdo->query("
    SELECT c.id, c.nombre AS cuenta_nombre, b.nombre AS banco_nombre
    FROM cuentas c
    JOIN bancos b ON c.banco_id = b.id
    ORDER BY b.nombre, c.nombre
")->fetchAll(PDO::FETCH_ASSOC);
$formasRowsMov = $pdo->query("SELECT id, nombre, tipo_movimiento FROM formas_pago ORDER BY tipo_movimiento, nombre")->fetchAll(PDO::FETCH_ASSOC);

if ($accion === 'registrar_movimiento_cliente' && enviado()) {
    $cliente_id_mov = (int) ($_POST['cliente_id'] ?? 0);
    $cuenta_id_mov = (int) ($_POST['cuenta_id'] ?? 0);
    $tipo_mov = trim((string) ($_POST['tipo_movimiento'] ?? ''));
    $forma_pago_id_mov = (int) ($_POST['forma_pago_id'] ?? 0);
    $monto_mov = (float) str_replace(',', '.', (string) ($_POST['monto'] ?? 0));
    $fecha_mov = trim((string) ($_POST['fecha_movimiento'] ?? ''));
    $referencia_mov = trim((string) ($_POST['referencia'] ?? ''));
    $descripcion_mov = trim((string) ($_POST['descripcion'] ?? ''));

    $stCli = $pdo->prepare('SELECT id FROM clientes WHERE id = ? LIMIT 1');
    $stCli->execute([$cliente_id_mov]);
    if (!$stCli->fetch()) {
        redirigir(MARINA_URL . '/index.php?p=clientes&err=' . rawurlencode('Cliente no válido para registrar movimiento.'));
    }

    $stCuenta = $pdo->prepare('SELECT id FROM cuentas WHERE id = ? LIMIT 1');
    $stCuenta->execute([$cuenta_id_mov]);
    if (!$stCuenta->fetch()) {
        redirigir(MARINA_URL . '/index.php?p=clientes&err=' . rawurlencode('Debe seleccionar una cuenta válida.'));
    }

    if (!in_array($tipo_mov, ['ingreso', 'costo'], true)) {
        redirigir(MARINA_URL . '/index.php?p=clientes&err=' . rawurlencode('Tipo de movimiento no válido.'));
    }
    if ($monto_mov <= 0) {
        redirigir(MARINA_URL . '/index.php?p=clientes&err=' . rawurlencode('El monto debe ser mayor a 0.'));
    }
    if ($fecha_mov === '') {
        redirigir(MARINA_URL . '/index.php?p=clientes&err=' . rawurlencode('La fecha del movimiento es obligatoria.'));
    }

    $stForma = $pdo->prepare('SELECT id, tipo_movimiento FROM formas_pago WHERE id = ? LIMIT 1');
    $stForma->execute([$forma_pago_id_mov]);
    $formaRow = $stForma->fetch(PDO::FETCH_ASSOC);
    if (!$formaRow) {
        redirigir(MARINA_URL . '/index.php?p=clientes&err=' . rawurlencode('Debe seleccionar un tipo de movimiento.'));
    }
    if ((string) ($formaRow['tipo_movimiento'] ?? '') !== $tipo_mov) {
        redirigir(MARINA_URL . '/index.php?p=clientes&err=' . rawurlencode('El tipo seleccionado no coincide con la clasificación del movimiento.'));
    }

    try {
        $pdo->prepare('
            INSERT INTO movimientos_bancarios
            (cliente_id, cuenta_id, forma_pago_id, tipo_movimiento, monto, fecha_movimiento, referencia, descripcion, created_by, updated_by)
            VALUES (?,?,?,?,?,?,?,?,?,?)
        ')->execute([
            $cliente_id_mov,
            $cuenta_id_mov,
            $forma_pago_id_mov,
            $tipo_mov,
            $monto_mov,
            $fecha_mov,
            $referencia_mov !== '' ? $referencia_mov : null,
            $descripcion_mov !== '' ? $descripcion_mov : null,
            usuarioId(),
            usuarioId(),
        ]);
        redirigir(MARINA_URL . '/index.php?p=clientes&ok=' . rawurlencode('Movimiento bancario registrado para el cliente.'));
    } catch (Throwable $e) {
        redirigir(MARINA_URL . '/index.php?p=clientes&err=' . rawurlencode('No se pudo registrar el movimiento bancario.'));
    }
}

if ($accion === 'eliminar_movimiento_cliente' && enviado()) {
    $cliente_id_mov = (int) ($_POST['cliente_id'] ?? 0);
    $movimiento_id = (int) ($_POST['movimiento_id'] ?? 0);
    if ($cliente_id_mov < 1 || $movimiento_id < 1) {
        redirigir(MARINA_URL . '/index.php?p=clientes&err=' . rawurlencode('Movimiento no válido.'));
    }

    $stMov = $pdo->prepare('SELECT id FROM movimientos_bancarios WHERE id = ? AND cliente_id = ? LIMIT 1');
    $stMov->execute([$movimiento_id, $cliente_id_mov]);
    if (!$stMov->fetch()) {
        redirigir(MARINA_URL . '/index.php?p=clientes&err=' . rawurlencode('El movimiento no pertenece al cliente seleccionado.'));
    }

    try {
        $pdo->prepare('DELETE FROM movimientos_bancarios WHERE id = ? AND cliente_id = ?')->execute([$movimiento_id, $cliente_id_mov]);
        redirigir(MARINA_URL . '/index.php?p=clientes&ok=' . rawurlencode('Movimiento bancario eliminado.'));
    } catch (Throwable $e) {
        redirigir(MARINA_URL . '/index.php?p=clientes&err=' . rawurlencode('No se pudo eliminar el movimiento.'));
    }
}

if ($accion === 'eliminar' && $id > 0 && enviado()) {
    $bloqueo = marinaBloqueoEliminarCliente($pdo, $id);
    if ($bloqueo !== null) {
        redirigir(MARINA_URL . '/index.php?p=clientes&err=' . rawurlencode($bloqueo));
    }
    try {
        $pdo->prepare('DELETE FROM clientes WHERE id = ?')->execute([$id]);
        redirigir(MARINA_URL . '/index.php?p=clientes&ok=' . rawurlencode('Cliente eliminado'));
    } catch (Throwable $e) {
        redirigir(MARINA_URL . '/index.php?p=clientes&err=' . rawurlencode(marinaMensajeErrorIntegridad($e)));
    }
}

if (enviado() && ($accion === 'crear' || $accion === 'editar')) {
    $nombre = trim($_POST['nombre'] ?? '');
    $documento = trim($_POST['documento'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    $dueno_capitan = trim($_POST['dueno_capitan'] ?? '');
    $uid = usuarioId();
    if ($nombre === '') {
        $mensaje = 'Nombre obligatorio.';
    } else {
        $excluirDup = ($accion === 'editar' && $id > 0) ? $id : 0;
        $dupMsg = marina_cliente_mensaje_si_duplicado($pdo, $nombre, $documento, $telefono, $email, $excluirDup);
        if ($dupMsg !== null) {
            $mensaje = $dupMsg;
        }
        if ($mensaje === '') {
            if ($accion === 'editar' && $id > 0) {
                $pdo->prepare('UPDATE clientes SET nombre=?, documento=?, telefono=?, email=?, direccion=?, dueno_capitan=?, updated_by=? WHERE id=?')
                    ->execute([$nombre, $documento, $telefono, $email, $direccion, $dueno_capitan !== '' ? $dueno_capitan : null, $uid, $id]);
                redirigir(MARINA_URL . '/index.php?p=clientes&ok=Actualizado');
            } else {
                $pdo->prepare('INSERT INTO clientes (nombre, documento, telefono, email, direccion, dueno_capitan, created_by, updated_by) VALUES (?,?,?,?,?,?,?,?)')
                    ->execute([$nombre, $documento, $telefono, $email, $direccion, $dueno_capitan !== '' ? $dueno_capitan : null, $uid, $uid]);
                redirigir(MARINA_URL . '/index.php?p=clientes&ok=Creado');
            }
        }
    }
}

$registro = null;
if ($accion === 'editar' && $id > 0) {
    $st = $pdo->prepare('SELECT * FROM clientes WHERE id = ?');
    $st->execute([$id]);
    $registro = $st->fetch();
    if (!$registro) redirigir(MARINA_URL . '/index.php?p=clientes');
}

$mostrarModal = enviado() && ($accion === 'crear' || $accion === 'editar') && $mensaje !== '';
$modalDatos = [
    'formAccion' => $accion,
    'id' => $id,
    'nombre' => $registro['nombre'] ?? ($_POST['nombre'] ?? ''),
    'documento' => $registro['documento'] ?? ($_POST['documento'] ?? ''),
    'telefono' => $registro['telefono'] ?? ($_POST['telefono'] ?? ''),
    'email' => $registro['email'] ?? ($_POST['email'] ?? ''),
    'direccion' => $registro['direccion'] ?? ($_POST['direccion'] ?? ''),
    'duenoCapitan' => $registro['dueno_capitan'] ?? ($_POST['dueno_capitan'] ?? ''),
];

$movimientosClienteById = [];
try {
    $stMovCli = $pdo->query("
        SELECT mb.id, mb.cliente_id, mb.fecha_movimiento, mb.tipo_movimiento, mb.monto, mb.referencia, mb.descripcion,
               CONCAT(b.nombre, ' - ', c.nombre) AS cuenta_nombre,
               fp.nombre AS forma_pago_nombre
        FROM movimientos_bancarios mb
        LEFT JOIN cuentas c ON c.id = mb.cuenta_id
        LEFT JOIN bancos b ON b.id = c.banco_id
        LEFT JOIN formas_pago fp ON fp.id = mb.forma_pago_id
        WHERE mb.cliente_id IS NOT NULL
        ORDER BY mb.fecha_movimiento DESC, mb.id DESC
    ");
    while ($mv = $stMovCli->fetch(PDO::FETCH_ASSOC)) {
        $cid = (int) ($mv['cliente_id'] ?? 0);
        if ($cid < 1) {
            continue;
        }
        if (!isset($movimientosClienteById[$cid])) {
            $movimientosClienteById[$cid] = [];
        }
        $movimientosClienteById[$cid][] = [
            'id' => (int) ($mv['id'] ?? 0),
            'fecha_movimiento' => (string) ($mv['fecha_movimiento'] ?? ''),
            'tipo_movimiento' => (string) ($mv['tipo_movimiento'] ?? ''),
            'monto' => (float) ($mv['monto'] ?? 0),
            'referencia' => (string) ($mv['referencia'] ?? ''),
            'descripcion' => (string) ($mv['descripcion'] ?? ''),
            'cuenta_nombre' => (string) ($mv['cuenta_nombre'] ?? '—'),
            'forma_pago_nombre' => (string) ($mv['forma_pago_nombre'] ?? '—'),
        ];
    }
} catch (Throwable $e) {
    $movimientosClienteById = [];
}
?>
<?php require_once __DIR__ . '/../includes/layout.php'; ?>

<h1>Clientes</h1>
<?php if ($ok): ?><p class="success"><?= e($ok) ?></p><?php endif; ?>
<?php if ($err): ?><p class="error"><?= e($err) ?></p><?php endif; ?>
<?php if ($mensaje && !$mostrarModal): ?><p class="error"><?= e($mensaje) ?></p><?php endif; ?>

<div class="toolbar d-flex gap-2"><button type="button" class="btn btn-primary" id="btnNuevoCliente">Nuevo cliente</button></div>

<table>
    <thead><tr><th>Id</th><th>Nombre</th><th>Dueño / Capitán</th><th>Teléfono</th><th>Creado</th><th>Creado por</th><th></th></tr></thead>
    <tbody>
    <?php
    $st = $pdo->query('SELECT c.*, u.nombre AS creado_por FROM clientes c LEFT JOIN usuarios u ON c.created_by = u.id ORDER BY c.nombre');
    while ($r = $st->fetch()): ?>
        <tr>
            <td><?= (int)$r['id'] ?></td>
            <td><?= e($r['nombre']) ?></td>
            <td><?= e($r['dueno_capitan'] ?? '—') ?></td>
            <td><?= e($r['telefono'] ?? '—') ?></td>
            <td><?= fechaHoraFormato($r['created_at']) ?></td>
            <td><?= e($r['creado_por'] ?? '—') ?></td>
            <td class="acciones">
                <button type="button" class="btn btn-primary btn-sm btn-registrar-mov-cliente" data-id="<?= (int)$r['id'] ?>" data-nombre="<?= e($r['nombre']) ?>">Registrar movimiento</button>
                <button type="button" class="btn btn-outline-primary btn-sm btn-ver-mov-cliente" data-id="<?= (int)$r['id'] ?>" data-nombre="<?= e($r['nombre']) ?>">Ver movimientos</button>
                <button type="button" class="btn btn-danger btn-sm btn-eliminar-cliente" data-id="<?= (int)$r['id'] ?>" data-nombre="<?= e($r['nombre']) ?>">Eliminar</button>
                <button type="button" class="btn btn-secondary btn-sm btn-editar-cliente" data-id="<?= (int)$r['id'] ?>" data-nombre="<?= e($r['nombre']) ?>" data-documento="<?= e($r['documento'] ?? '') ?>" data-telefono="<?= e($r['telefono'] ?? '') ?>" data-email="<?= e($r['email'] ?? '') ?>" data-direccion="<?= e($r['direccion'] ?? '') ?>" data-dueno-capitan="<?= e($r['dueno_capitan'] ?? '') ?>">Editar</button>
            </td>
        </tr>
    <?php endwhile; ?>
    </tbody>
</table>

<div class="modal fade" id="clienteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="?p=clientes">
                <input type="hidden" name="accion" id="clienteFormAccion" value="crear">
                <input type="hidden" name="id" id="clienteFormId" value="">
                <div class="modal-header">
                    <h5 class="modal-title" id="clienteModalTitle">Nuevo cliente</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="clienteModalMensaje" class="alert alert-danger d-none"></div>
                    <label>Nombre *</label>
                    <input type="text" class="form-control" id="clienteNombre" name="nombre" required>
                    <label class="mt-2">Documento</label>
                    <input type="text" class="form-control" id="clienteDocumento" name="documento">
                    <label class="mt-2">Teléfono</label>
                    <input type="text" class="form-control" id="clienteTelefono" name="telefono">
                    <label class="mt-2">Email</label>
                    <input type="email" class="form-control" id="clienteEmail" name="email">
                    <label class="mt-2">Dirección</label>
                    <textarea class="form-control" id="clienteDireccion" name="direccion" rows="2"></textarea>
                    <label class="mt-2">Dueño / Capitán</label>
                    <input type="text" class="form-control" id="clienteDuenoCapitan" name="dueno_capitan" maxlength="150" placeholder="Nombre del dueño o capitán">
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Guardar</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="confirmEliminarClienteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <form method="post" action="?p=clientes&accion=eliminar">
                <input type="hidden" name="id" id="clienteDeleteId" value="">
                <div class="modal-header"><h5 class="modal-title">Confirmar eliminación</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">¿Eliminar cliente <span id="clienteDeleteNombre" class="fw-semibold"></span>?</div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Eliminar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="clienteMovModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="?p=clientes&accion=registrar_movimiento_cliente">
                <input type="hidden" name="cliente_id" id="clienteMovClienteId" value="">
                <div class="modal-header">
                    <h5 class="modal-title">Registrar movimiento bancario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-2" id="clienteMovClienteNombre"></p>
                    <label>Cuenta *</label>
                    <select class="form-select" name="cuenta_id" required>
                        <option value="">Seleccione</option>
                        <?php foreach ($cuentasRowsMov as $cRow): ?>
                            <option value="<?= (int) $cRow['id'] ?>"><?= e(($cRow['banco_nombre'] ?? '') . ' - ' . ($cRow['cuenta_nombre'] ?? '')) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="row g-2 mt-1">
                        <div class="col-md-6">
                            <label>Tipo *</label>
                            <select class="form-select" name="tipo_movimiento" id="clienteMovTipo" required>
                                <option value="ingreso"><?= e(marina_ui_credito()) ?></option>
                                <option value="costo"><?= e(marina_ui_debito()) ?></option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label>Fecha *</label>
                            <input type="date" class="form-control" name="fecha_movimiento" value="<?= e(date('Y-m-d')) ?>" required>
                        </div>
                    </div>
                    <label class="mt-2">Tipo de movimiento *</label>
                    <select class="form-select" name="forma_pago_id" id="clienteMovFormaPago" required>
                        <option value="">Seleccione</option>
                        <?php foreach ($formasRowsMov as $fRow): ?>
                            <option value="<?= (int) $fRow['id'] ?>" data-tipo="<?= e($fRow['tipo_movimiento']) ?>">
                                <?= e($fRow['nombre']) ?> (<?= e($fRow['tipo_movimiento'] === 'costo' ? marina_ui_debito() : marina_ui_credito()) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <label class="mt-2">Monto *</label>
                    <input type="number" class="form-control" name="monto" min="0.01" step="0.01" required>
                    <label class="mt-2">Referencia</label>
                    <input type="text" class="form-control" name="referencia" maxlength="100">
                    <label class="mt-2">Descripción</label>
                    <textarea class="form-control" name="descripcion" rows="2"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar movimiento</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="clienteMovListModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Movimientos bancarios del cliente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-2" id="clienteMovListClienteNombre"></p>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Fecha</th>
                                <th>Tipo</th>
                                <th class="text-end">Monto</th>
                                <th>Cuenta</th>
                                <th>Tipo movimiento</th>
                                <th>Referencia</th>
                                <th>Descripción</th>
                                <th class="text-end">Acción</th>
                            </tr>
                        </thead>
                        <tbody id="clienteMovListBody">
                            <tr><td colspan="8" class="text-muted">Sin movimientos.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="confirmEliminarMovClienteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <form method="post" action="?p=clientes&accion=eliminar_movimiento_cliente">
                <input type="hidden" name="cliente_id" id="clienteMovDeleteClienteId" value="">
                <input type="hidden" name="movimiento_id" id="clienteMovDeleteMovId" value="">
                <div class="modal-header"><h5 class="modal-title">Eliminar movimiento</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    ¿Eliminar este movimiento bancario?
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
window.__clienteModal = { mostrar: <?= $mostrarModal ? 'true' : 'false' ?>, datos: <?= json_encode($modalDatos, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>, error: <?= json_encode($mensaje, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?> };
window.__movimientosClienteById = <?= json_encode($movimientosClienteById, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;

window.addEventListener('load', function() {
    if (typeof bootstrap === 'undefined') return;
    var movModalEl = document.getElementById('clienteMovModal');
    var movListModalEl = document.getElementById('clienteMovListModal');
    var delMovModalEl = document.getElementById('confirmEliminarMovClienteModal');
    if (!movModalEl || !movListModalEl || !delMovModalEl) return;

    var movModal = new bootstrap.Modal(movModalEl);
    var movListModal = new bootstrap.Modal(movListModalEl);
    var delMovModal = new bootstrap.Modal(delMovModalEl);

    var movClienteId = document.getElementById('clienteMovClienteId');
    var movClienteNombre = document.getElementById('clienteMovClienteNombre');
    var movTipo = document.getElementById('clienteMovTipo');
    var movForma = document.getElementById('clienteMovFormaPago');
    var movListNombre = document.getElementById('clienteMovListClienteNombre');
    var movListBody = document.getElementById('clienteMovListBody');
    var movDelClienteId = document.getElementById('clienteMovDeleteClienteId');
    var movDelMovId = document.getElementById('clienteMovDeleteMovId');

    function money(n) {
        var num = Number(n || 0);
        return num.toLocaleString('es-PA', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }
    function filtrarFormaPago() {
        if (!movTipo || !movForma) return;
        var tipo = movTipo.value || 'ingreso';
        Array.prototype.forEach.call(movForma.options, function(opt, idx) {
            if (idx === 0) { opt.hidden = false; return; }
            var t = opt.getAttribute('data-tipo') || '';
            opt.hidden = (t !== tipo);
            if (opt.hidden && opt.selected) movForma.value = '';
        });
    }
    if (movTipo) movTipo.addEventListener('change', filtrarFormaPago);
    filtrarFormaPago();

    document.addEventListener('click', function(ev) {
        var btnReg = ev.target.closest('.btn-registrar-mov-cliente');
        if (btnReg) {
            var cid = btnReg.getAttribute('data-id') || '';
            var nom = btnReg.getAttribute('data-nombre') || '';
            if (movClienteId) movClienteId.value = cid;
            if (movClienteNombre) movClienteNombre.textContent = 'Cliente: ' + nom;
            movModal.show();
            return;
        }

        var btnVer = ev.target.closest('.btn-ver-mov-cliente');
        if (btnVer) {
            var cidVer = btnVer.getAttribute('data-id') || '';
            var nomVer = btnVer.getAttribute('data-nombre') || '';
            var arr = (window.__movimientosClienteById && window.__movimientosClienteById[cidVer]) ? window.__movimientosClienteById[cidVer] : [];
            if (movListNombre) movListNombre.textContent = 'Cliente: ' + nomVer;
            if (movListBody) {
                if (!arr.length) {
                    movListBody.innerHTML = '<tr><td colspan="8" class="text-muted">No hay movimientos registrados para este cliente.</td></tr>';
                } else {
                    movListBody.innerHTML = arr.map(function(m) {
                        var tipoTxt = (m.tipo_movimiento === 'costo') ? '<?= e(marina_ui_debito()) ?>' : '<?= e(marina_ui_credito()) ?>';
                        return '<tr>'
                            + '<td>' + esc(m.fecha_movimiento || '') + '</td>'
                            + '<td>' + esc(tipoTxt) + '</td>'
                            + '<td class="text-end">' + money(m.monto) + '</td>'
                            + '<td>' + esc(m.cuenta_nombre || '—') + '</td>'
                            + '<td>' + esc(m.forma_pago_nombre || '—') + '</td>'
                            + '<td>' + esc(m.referencia || '') + '</td>'
                            + '<td>' + esc(m.descripcion || '') + '</td>'
                            + '<td class="text-end"><button type="button" class="btn btn-outline-danger btn-sm btn-eliminar-mov-cliente" data-cliente-id="' + esc(cidVer) + '" data-mov-id="' + esc(String(m.id || '')) + '">Eliminar</button></td>'
                            + '</tr>';
                    }).join('');
                }
            }
            movListModal.show();
            return;
        }

        var btnDel = ev.target.closest('.btn-eliminar-mov-cliente');
        if (btnDel) {
            if (movDelClienteId) movDelClienteId.value = btnDel.getAttribute('data-cliente-id') || '';
            if (movDelMovId) movDelMovId.value = btnDel.getAttribute('data-mov-id') || '';
            delMovModal.show();
        }
    });
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
