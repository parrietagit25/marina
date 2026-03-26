<?php
/**
 * Partidas jerárquicas - crear/editar/eliminar con modales
 */
$titulo = 'Partidas';

$pdo = getDb();
$accion = obtener('accion');
$id = (int) obtener('id');
$parent_id = (int) obtener('parent_id');
$mensaje = '';
$mostrarModalPago = false;
$pagoError = '';
$pagoDatos = [
    'partida_id' => 0,
    'proveedor_id' => 0,
    'cuenta_id' => 0,
    'forma_pago_id' => 0,
    'monto' => '',
    'fecha_gasto' => date('Y-m-d'),
    'referencia' => '',
    'observaciones' => ''
];

if ($accion === 'eliminar' && $id > 0 && enviado()) {
    $bloqueo = marinaBloqueoEliminarPartida($pdo, $id);
    if ($bloqueo !== null) {
        redirigir(MARINA_URL . '/index.php?p=partidas&err=' . rawurlencode($bloqueo));
    }
    try {
        $pdo->prepare('DELETE FROM partidas WHERE id = ?')->execute([$id]);
        redirigir(MARINA_URL . '/index.php?p=partidas&ok=' . rawurlencode('Partida eliminada'));
    } catch (Throwable $e) {
        redirigir(MARINA_URL . '/index.php?p=partidas&err=' . rawurlencode(marinaMensajeErrorIntegridad($e)));
    }
}

if (enviado() && (($_POST['accion'] ?? '') === 'pagar_partida')) {
    $pagoDatos = [
        'partida_id' => (int) ($_POST['partida_id'] ?? 0),
        'proveedor_id' => (int) ($_POST['proveedor_id'] ?? 0),
        'cuenta_id' => (int) ($_POST['cuenta_id'] ?? 0),
        'forma_pago_id' => (int) ($_POST['forma_pago_id'] ?? 0),
        'monto' => trim($_POST['monto'] ?? ''),
        'fecha_gasto' => trim($_POST['fecha_gasto'] ?? date('Y-m-d')),
        'referencia' => trim($_POST['referencia'] ?? ''),
        'observaciones' => trim($_POST['observaciones'] ?? '')
    ];
    $mostrarModalPago = true;

    $partidaPagoId = (int) $pagoDatos['partida_id'];
    $montoPago = (float) str_replace(',', '', (string) $pagoDatos['monto']);

    if ($partidaPagoId <= 0) {
        $pagoError = 'Debe seleccionar una partida válida.';
    } else {
        $stLeaf = $pdo->prepare('SELECT id FROM partidas WHERE parent_id = ? LIMIT 1');
        $stLeaf->execute([$partidaPagoId]);
        $tieneHijos = (bool) $stLeaf->fetchColumn();
        if ($tieneHijos) {
            $pagoError = 'Solo se puede pagar en partidas hoja (sin subpartidas).';
        }
    }

    if ($pagoError === '' && (int) $pagoDatos['proveedor_id'] <= 0) {
        $pagoError = 'Debe seleccionar un proveedor.';
    }
    if ($pagoError === '' && $montoPago <= 0) {
        $pagoError = 'El monto debe ser mayor a 0.';
    }
    if ($pagoError === '' && $pagoDatos['fecha_gasto'] === '') {
        $pagoError = 'La fecha del pago es obligatoria.';
    }

    if ($pagoError === '') {
        $sqlPago = "
            INSERT INTO gastos
                (partida_id, proveedor_id, cuenta_id, forma_pago_id, monto, fecha_gasto, referencia, observaciones, created_by, updated_by)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";
        $pdo->prepare($sqlPago)->execute([
            (int) $pagoDatos['partida_id'],
            (int) $pagoDatos['proveedor_id'],
            $pagoDatos['cuenta_id'] > 0 ? (int) $pagoDatos['cuenta_id'] : null,
            $pagoDatos['forma_pago_id'] > 0 ? (int) $pagoDatos['forma_pago_id'] : null,
            $montoPago,
            $pagoDatos['fecha_gasto'],
            $pagoDatos['referencia'] !== '' ? $pagoDatos['referencia'] : null,
            $pagoDatos['observaciones'] !== '' ? $pagoDatos['observaciones'] : null,
            usuarioId(),
            usuarioId()
        ]);
        redirigir(MARINA_URL . '/index.php?p=partidas&ok=Pago registrado');
    }
}

if (enviado() && ($accion === 'crear' || $accion === 'editar')) {
    $nombre = trim($_POST['nombre'] ?? '');
    $pid = ($accion === 'editar') ? (int) ($_POST['parent_id'] ?? 0) : (int) ($_POST['parent_id'] ?? 0);
    $uid = usuarioId();
    $parentTienePagos = false;
    if ($pid > 0) {
        $stParentPago = $pdo->prepare('SELECT id FROM gastos WHERE partida_id = ? LIMIT 1');
        $stParentPago->execute([$pid]);
        $parentTienePagos = (bool) $stParentPago->fetchColumn();
    }
    if ($nombre === '') {
        $mensaje = 'Nombre obligatorio.';
    } elseif ($accion === 'editar' && $id > 0 && $pid == $id) {
        $mensaje = 'Una partida no puede ser padre de sí misma.';
    } elseif ($parentTienePagos) {
        $mensaje = 'No se puede asignar como padre: la partida seleccionada ya tiene pagos asociados.';
    } else {
        if ($accion === 'editar' && $id > 0) {
            $pdo->prepare('UPDATE partidas SET parent_id=?, nombre=?, updated_by=? WHERE id=?')
                ->execute([$pid ?: null, $nombre, $uid, $id]);
            redirigir(MARINA_URL . '/index.php?p=partidas&ok=Actualizado');
        } else {
            $pdo->prepare('INSERT INTO partidas (parent_id, nombre, created_by, updated_by) VALUES (?,?,?,?)')
                ->execute([$pid ?: null, $nombre, $uid, $uid]);
            redirigir(MARINA_URL . '/index.php?p=partidas&ok=Creado');
        }
    }
}

$registro = null;
if ($accion === 'editar' && $id > 0) {
    $st = $pdo->prepare('SELECT * FROM partidas WHERE id = ?');
    $st->execute([$id]);
    $registro = $st->fetch();
    if (!$registro) redirigir(MARINA_URL . '/index.php?p=partidas');
}

$todas = $pdo->query('SELECT id, parent_id, nombre FROM partidas ORDER BY nombre')->fetchAll(PDO::FETCH_ASSOC);

$gastosRows = $pdo->query("
    SELECT g.id, g.partida_id, g.fecha_gasto, g.monto, g.referencia, g.observaciones,
           pr.nombre AS proveedor_nombre,
           fp.nombre AS forma_pago_nombre,
           CONCAT(b.nombre, ' - ', c.nombre) AS cuenta_nombre
    FROM gastos g
    LEFT JOIN proveedores pr ON pr.id = g.proveedor_id
    LEFT JOIN formas_pago fp ON fp.id = g.forma_pago_id
    LEFT JOIN cuentas c ON c.id = g.cuenta_id
    LEFT JOIN bancos b ON b.id = c.banco_id
    ORDER BY g.fecha_gasto DESC, g.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

$pagosByPartida = [];
$pagosCountByPartida = [];
foreach ($gastosRows as $gr) {
    $pid = (int) ($gr['partida_id'] ?? 0);
    if ($pid <= 0) {
        continue;
    }
    if (!isset($pagosByPartida[$pid])) {
        $pagosByPartida[$pid] = [];
    }
    $pagosByPartida[$pid][] = [
        'fecha' => $gr['fecha_gasto'] ?? '',
        'monto' => (float) ($gr['monto'] ?? 0),
        'referencia' => $gr['referencia'] ?? '',
        'proveedor' => $gr['proveedor_nombre'] ?? '',
        'cuenta' => $gr['cuenta_nombre'] ?? '',
        'forma_pago' => $gr['forma_pago_nombre'] ?? '',
        'observaciones' => $gr['observaciones'] ?? '',
    ];
    $pagosCountByPartida[$pid] = ($pagosCountByPartida[$pid] ?? 0) + 1;
}

$proveedores = $pdo->query('SELECT id, nombre FROM proveedores ORDER BY nombre')->fetchAll(PDO::FETCH_KEY_PAIR);
$cuentas = $pdo->query("SELECT c.id, CONCAT(b.nombre, ' - ', c.nombre) AS nom FROM cuentas c JOIN bancos b ON b.id = c.banco_id ORDER BY b.nombre, c.nombre")->fetchAll(PDO::FETCH_KEY_PAIR);
$formasPagoCosto = $pdo->query("SELECT id, nombre FROM formas_pago WHERE tipo_movimiento = 'costo' ORDER BY nombre")->fetchAll(PDO::FETCH_KEY_PAIR);

// Agrupa partidas por parent_id para pintar el árbol completo.
$partidasPorPadre = [];
foreach ($todas as $row) {
    $key = ($row['parent_id'] === null) ? 'root' : (string) ((int) $row['parent_id']);
    if (!isset($partidasPorPadre[$key])) {
        $partidasPorPadre[$key] = [];
    }
    $partidasPorPadre[$key][] = $row;
}

function arbolPartidas(array $map, array $pagosCount, array $pagosByPartida, string $parentKey = 'root', int $nivel = 0) {
    $items = $map[$parentKey] ?? [];
    foreach ($items as $p) {
        $id = (int) $p['id'];
        $childKey = (string) $id;
        $hasChildren = !empty($map[$childKey]);
        $pagoCount = (int) ($pagosCount[$id] ?? 0);
        $hasPayments = $pagoCount > 0;
        $pagosJson = e(json_encode($pagosByPartida[$id] ?? [], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE));

        echo '<li class="partida-item nivel-' . $nivel . '">';
        echo '<div class="partida-node">';
        echo '<div class="partida-node-main">';
        echo '<span class="partida-name">' . e($p['nombre']) . '</span>';
        echo '<span class="badge text-bg-light border">ID ' . $id . '</span>';
        if ($hasChildren) {
            echo '<span class="badge text-bg-primary">' . count($map[$childKey]) . ' subpartida(s)</span>';
        }
        if ($hasPayments) {
            echo '<span class="badge text-bg-success">' . $pagoCount . ' pago(s)</span>';
        }
        echo '</div>';
        echo '<div class="partida-node-actions">';
        echo '<button type="button" class="btn btn-secondary btn-sm btn-editar-partida" data-id="' . $id . '" data-parent-id="' . (int)($p['parent_id'] ?? 0) . '" data-nombre="' . e($p['nombre']) . '">Editar</button> ';
        if (!$hasPayments) {
            echo '<button type="button" class="btn btn-primary btn-sm btn-subpartida-partida" data-parent-id="' . $id . '">+ Subpartida</button> ';
        } else {
            echo '<button type="button" class="btn btn-outline-secondary btn-sm" disabled title="No se puede agregar subpartida porque esta partida tiene pagos">+ Subpartida</button> ';
        }
        if (!$hasChildren) {
            echo '<button type="button" class="btn btn-success btn-sm btn-pagar-partida" data-partida-id="' . $id . '" data-partida-nombre="' . e($p['nombre']) . '" data-bs-toggle="modal" data-bs-target="#pagarPartidaModal">Pagar</button> ';
        }
        if ($hasPayments) {
            echo '<button type="button" class="btn btn-info btn-sm btn-ver-pagos-partida" data-partida-nombre="' . e($p['nombre']) . '" data-pagos="' . $pagosJson . '" data-bs-toggle="modal" data-bs-target="#pagosPartidaModal">Ver pagos</button> ';
        }
        if (!$hasChildren && !$hasPayments) {
            echo '<button type="button" class="btn btn-danger btn-sm btn-eliminar-partida" data-id="' . $id . '" data-nombre="' . e($p['nombre']) . '">Eliminar</button>';
        } else {
            echo '<button type="button" class="btn btn-outline-secondary btn-sm" disabled title="No se puede eliminar porque tiene subpartidas o pagos asociados">No eliminable</button>';
        }
        echo '</div>';
        echo '</div>';

        if ($hasChildren) {
            echo '<ul class="partida-tree partida-children">';
            arbolPartidas($map, $pagosCount, $pagosByPartida, $childKey, $nivel + 1);
            echo '</ul>';
        }
        echo '</li>';
    }
}

$ok = obtener('ok');
$err = obtener('err');
$mostrarModal = enviado() && ($accion === 'crear' || $accion === 'editar') && $mensaje !== '';
$modalDatos = [
    'id' => $id,
    'parent_id' => $registro['parent_id'] ?? ($parent_id ?: ($_POST['parent_id'] ?? 0)),
    'nombre' => $registro['nombre'] ?? ($_POST['nombre'] ?? ''),
];
?>
<?php require_once __DIR__ . '/../includes/layout.php'; ?>

<h1>Partidas</h1>
<?php if ($ok): ?><p class="success"><?= e($ok) ?></p><?php endif; ?>
<?php if ($err): ?><p class="error"><?= e($err) ?></p><?php endif; ?>
<?php if ($mensaje && !$mostrarModal): ?><p class="error"><?= e($mensaje) ?></p><?php endif; ?>

<p class="text-muted">Estructura jerárquica. Los gastos se registran en partidas hoja (sin subpartidas).</p>
<div class="toolbar d-flex gap-2 mb-3">
    <button type="button" class="btn btn-primary" id="btnNuevaPartidaRaiz">Nueva partida (raíz)</button>
</div>
<div class="card p-3">
    <?php if (empty($todas)): ?>
        <p class="mb-0 text-muted">Aún no hay partidas registradas.</p>
    <?php else: ?>
        <ul class="partida-tree list-unstyled mb-0">
            <?php arbolPartidas($partidasPorPadre, $pagosCountByPartida, $pagosByPartida, 'root', 0); ?>
        </ul>
    <?php endif; ?>
</div>

<!-- Modal crear/editar partida -->
<div class="modal fade" id="partidaModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="?p=partidas">
                <input type="hidden" name="accion" id="partidaFormAccion" value="crear">
                <input type="hidden" name="id" id="partidaFormId" value="">
                <div class="modal-header">
                    <h5 class="modal-title" id="partidaModalTitle">Nueva partida</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="partidaModalMensaje" class="alert alert-danger d-none"></div>
                    <label>Partida padre</label>
                    <select class="form-select" id="partidaParentId" name="parent_id">
                        <option value="0">(Raíz / sin padre)</option>
                        <?php foreach ($todas as $p): ?>
                            <option value="<?= (int)$p['id'] ?>"><?= e($p['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label class="mt-2">Nombre</label>
                    <input type="text" class="form-control" id="partidaNombre" name="nombre" required>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Guardar</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal confirmar eliminar -->
<div class="modal fade" id="confirmEliminarPartidaModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <form method="post" action="?p=partidas&accion=eliminar">
                <input type="hidden" name="id" id="partidaDeleteId" value="">
                <div class="modal-header"><h5 class="modal-title">Confirmar eliminación</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">¿Eliminar partida <span id="partidaDeleteNombre" class="fw-semibold"></span>?</div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Eliminar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal pagar partida -->
<div class="modal fade" id="pagarPartidaModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="?p=partidas" id="pagarPartidaForm">
                <input type="hidden" name="accion" value="pagar_partida">
                <input type="hidden" name="partida_id" id="pagarPartidaId" value="">
                <div class="modal-header">
                    <h5 class="modal-title">Registrar pago en partida</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="pagarPartidaError" class="alert alert-danger d-none"></div>
                    <div class="mb-2 text-muted">Partida: <strong id="pagarPartidaNombre">—</strong></div>

                    <label class="form-label">Proveedor</label>
                    <select class="form-select" name="proveedor_id" id="pagarPartidaProveedorId" required>
                        <option value="0">Seleccione...</option>
                        <?php foreach ($proveedores as $prId => $prNom): ?>
                            <option value="<?= (int) $prId ?>"><?= e($prNom) ?></option>
                        <?php endforeach; ?>
                    </select>

                    <div class="row g-2 mt-1">
                        <div class="col-12 col-md-6">
                            <label class="form-label">Cuenta</label>
                            <select class="form-select" name="cuenta_id" id="pagarPartidaCuentaId">
                                <option value="0">Seleccione...</option>
                                <?php foreach ($cuentas as $cuId => $cuNom): ?>
                                    <option value="<?= (int) $cuId ?>"><?= e($cuNom) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">Forma de pago</label>
                            <select class="form-select" name="forma_pago_id" id="pagarPartidaFormaPagoId">
                                <option value="0">Seleccione...</option>
                                <?php foreach ($formasPagoCosto as $fpId => $fpNom): ?>
                                    <option value="<?= (int) $fpId ?>"><?= e($fpNom) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row g-2 mt-1">
                        <div class="col-12 col-md-6">
                            <label class="form-label">Monto</label>
                            <input type="number" step="0.01" min="0.01" class="form-control" name="monto" id="pagarPartidaMonto" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">Fecha</label>
                            <input type="date" class="form-control" name="fecha_gasto" id="pagarPartidaFecha" required>
                        </div>
                    </div>

                    <label class="form-label mt-2">Referencia</label>
                    <input type="text" class="form-control" name="referencia" id="pagarPartidaReferencia" maxlength="100">

                    <label class="form-label mt-2">Comentario / Descripción</label>
                    <textarea class="form-control" name="observaciones" id="pagarPartidaObservaciones" rows="3"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">Guardar pago</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal ver pagos asociados -->
<div class="modal fade" id="pagosPartidaModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Pagos asociados - <span id="pagosPartidaNombre"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-sm align-middle no-datatable">
                        <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Monto</th>
                            <th>Proveedor</th>
                            <th>Cuenta</th>
                            <th>Forma pago</th>
                            <th>Referencia</th>
                            <th>Comentario</th>
                        </tr>
                        </thead>
                        <tbody id="pagosPartidaTbody">
                        <tr><td colspan="7" class="text-muted">Sin registros.</td></tr>
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

<script>window.__partidaModal = { mostrar: <?= $mostrarModal ? 'true' : 'false' ?>, datos: <?= json_encode($modalDatos, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>, error: <?= json_encode($mensaje, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?> };</script>
<script>
window.__pagarPartidaModal = {
    mostrar: <?= $mostrarModalPago ? 'true' : 'false' ?>,
    error: <?= json_encode($pagoError, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>,
    datos: <?= json_encode($pagoDatos, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>
};
</script>
<script>
window.addEventListener('load', function() {
    var pagarModalEl = document.getElementById('pagarPartidaModal');
    var pagarPartidaNombre = document.getElementById('pagarPartidaNombre');
    var pagarPartidaId = document.getElementById('pagarPartidaId');
    var pagarPartidaError = document.getElementById('pagarPartidaError');
    var pagarProveedor = document.getElementById('pagarPartidaProveedorId');
    var pagarCuenta = document.getElementById('pagarPartidaCuentaId');
    var pagarForma = document.getElementById('pagarPartidaFormaPagoId');
    var pagarMonto = document.getElementById('pagarPartidaMonto');
    var pagarFecha = document.getElementById('pagarPartidaFecha');
    var pagarReferencia = document.getElementById('pagarPartidaReferencia');
    var pagarObs = document.getElementById('pagarPartidaObservaciones');

    function setPagoError(txt) {
        if (!pagarPartidaError) return;
        pagarPartidaError.textContent = txt || '';
        pagarPartidaError.classList.toggle('d-none', !txt);
    }

    function fillPagar(data) {
        if (pagarPartidaId) pagarPartidaId.value = data.partida_id || '';
        if (pagarPartidaNombre) pagarPartidaNombre.textContent = data.partida_nombre || '—';
        if (pagarProveedor) pagarProveedor.value = data.proveedor_id || 0;
        if (pagarCuenta) pagarCuenta.value = data.cuenta_id || 0;
        if (pagarForma) pagarForma.value = data.forma_pago_id || 0;
        if (pagarMonto) pagarMonto.value = data.monto || '';
        if (pagarFecha) pagarFecha.value = data.fecha_gasto || '<?= e(date('Y-m-d')) ?>';
        if (pagarReferencia) pagarReferencia.value = data.referencia || '';
        if (pagarObs) pagarObs.value = data.observaciones || '';
    }

    if (pagarModalEl && typeof bootstrap !== 'undefined') {
        var pagarModal = new bootstrap.Modal(pagarModalEl);
        document.querySelectorAll('.btn-pagar-partida').forEach(function(btn) {
            btn.addEventListener('click', function() {
                fillPagar({
                    partida_id: btn.getAttribute('data-partida-id') || '',
                    partida_nombre: btn.getAttribute('data-partida-nombre') || '',
                    proveedor_id: 0,
                    cuenta_id: 0,
                    forma_pago_id: 0,
                    monto: '',
                    fecha_gasto: '<?= e(date('Y-m-d')) ?>',
                    referencia: '',
                    observaciones: ''
                });
                setPagoError('');
            });
        });

        if (window.__pagarPartidaModal && window.__pagarPartidaModal.mostrar) {
            var pd = window.__pagarPartidaModal.datos || {};
            var partidaNombre = '';
            var partidaBtn = document.querySelector('.btn-pagar-partida[data-partida-id="' + String(pd.partida_id || '') + '"]');
            if (partidaBtn) {
                partidaNombre = partidaBtn.getAttribute('data-partida-nombre') || '';
            }
            fillPagar({
                partida_id: pd.partida_id || '',
                partida_nombre: partidaNombre,
                proveedor_id: pd.proveedor_id || 0,
                cuenta_id: pd.cuenta_id || 0,
                forma_pago_id: pd.forma_pago_id || 0,
                monto: pd.monto || '',
                fecha_gasto: pd.fecha_gasto || '<?= e(date('Y-m-d')) ?>',
                referencia: pd.referencia || '',
                observaciones: pd.observaciones || ''
            });
            setPagoError(window.__pagarPartidaModal.error || '');
            pagarModal.show();
        }
    }

    var nombreEl = document.getElementById('pagosPartidaNombre');
    var tbody = document.getElementById('pagosPartidaTbody');
    if (!nombreEl || !tbody) return;

    function escapeHtml(v) {
        return String(v || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function fmtMoney(n) {
        var num = Number(n || 0);
        return new Intl.NumberFormat('es-PA', { style: 'currency', currency: 'USD' }).format(num);
    }

    document.querySelectorAll('.btn-ver-pagos-partida').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var nombre = btn.getAttribute('data-partida-nombre') || '';
            var pagosRaw = btn.getAttribute('data-pagos') || '[]';
            var pagos = [];
            try {
                pagos = JSON.parse(pagosRaw);
            } catch (e) {
                pagos = [];
            }

            nombreEl.textContent = nombre;
            if (!pagos.length) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-muted">Sin pagos asociados.</td></tr>';
                return;
            }

            var html = '';
            pagos.forEach(function(p) {
                html += '<tr>'
                    + '<td>' + escapeHtml(p.fecha || '') + '</td>'
                    + '<td>' + escapeHtml(fmtMoney(p.monto || 0)) + '</td>'
                    + '<td>' + escapeHtml(p.proveedor || '') + '</td>'
                    + '<td>' + escapeHtml(p.cuenta || '') + '</td>'
                    + '<td>' + escapeHtml(p.forma_pago || '') + '</td>'
                    + '<td>' + escapeHtml(p.referencia || '') + '</td>'
                    + '<td>' + escapeHtml(p.observaciones || '') + '</td>'
                    + '</tr>';
            });
            tbody.innerHTML = html;
        });
    });
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
