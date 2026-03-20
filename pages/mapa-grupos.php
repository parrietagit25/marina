<?php
/**
 * Mapa Grupos: grupos y sus inmuebles.
 * - Inmueble en verde si tiene contrato activo (contratos.activo=1).
 */
$titulo = 'Mapa Grupos';

$pdo = getDb();
$mensaje = '';

if (enviado() && (($_POST['accion'] ?? '') === 'cambiar_inmueble_contrato')) {
    $contratoId = (int) ($_POST['contrato_id'] ?? 0);
    $inmuebleNuevoId = (int) ($_POST['inmueble_nuevo_id'] ?? 0);
    if ($contratoId <= 0 || $inmuebleNuevoId <= 0) {
        $mensaje = 'Datos inválidos para cambiar el inmueble.';
    } else {
        $stContrato = $pdo->prepare('SELECT id, activo, grupo_id, inmueble_id FROM contratos WHERE id = ?');
        $stContrato->execute([$contratoId]);
        $contrato = $stContrato->fetch(PDO::FETCH_ASSOC);
        if (!$contrato) {
            $mensaje = 'Contrato no encontrado.';
        } else {
            $stInmueble = $pdo->prepare('SELECT id, grupo_id FROM inmuebles WHERE id = ?');
            $stInmueble->execute([$inmuebleNuevoId]);
            $inmuebleNuevo = $stInmueble->fetch(PDO::FETCH_ASSOC);
            if (!$inmuebleNuevo) {
                $mensaje = 'Inmueble destino no encontrado.';
            } else {
                $stOcupado = $pdo->prepare('SELECT id FROM contratos WHERE activo = 1 AND inmueble_id = ? AND id <> ? LIMIT 1');
                $stOcupado->execute([$inmuebleNuevoId, $contratoId]);
                $ocupado = (bool) $stOcupado->fetchColumn();
                if ($ocupado) {
                    $mensaje = 'No se puede mover: el inmueble destino ya tiene contrato activo.';
                } else {
                    $pdo->prepare('UPDATE contratos SET inmueble_id = ?, grupo_id = ?, updated_by = ? WHERE id = ?')
                        ->execute([$inmuebleNuevoId, (int) $inmuebleNuevo['grupo_id'], usuarioId(), $contratoId]);
                    redirigir(MARINA_URL . '/index.php?p=mapa-grupos&ok=Contrato movido de inmueble');
                }
            }
        }
    }
}

$grupos = $pdo->query('SELECT id, nombre FROM grupos ORDER BY nombre')
    ->fetchAll(PDO::FETCH_ASSOC);

$inmuebles = $pdo->query('SELECT id, nombre, grupo_id FROM inmuebles ORDER BY grupo_id, nombre')
    ->fetchAll(PDO::FETCH_ASSOC);

$contratosPorInmueble = [];
$detalleContratoPorInmueble = [];
$st = $pdo->query("
    SELECT co.id, co.inmueble_id, co.grupo_id, co.fecha_inicio, co.fecha_fin, co.monto_total, co.observaciones, co.activo,
           cl.nombre AS cliente_nombre,
           CONCAT(b.nombre, ' - ', cu.nombre) AS cuenta_nombre
    FROM contratos co
    LEFT JOIN clientes cl ON cl.id = co.cliente_id
    LEFT JOIN cuentas cu ON cu.id = co.cuenta_id
    LEFT JOIN bancos b ON b.id = cu.banco_id
    WHERE co.activo = 1 AND co.inmueble_id IS NOT NULL
");
while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
    $iid = (int) $r['inmueble_id'];
    $contratosPorInmueble[$iid] = ($contratosPorInmueble[$iid] ?? 0) + 1;
    if (!isset($detalleContratoPorInmueble[$iid])) {
        $detalleContratoPorInmueble[$iid] = $r;
    }
}

$inmueblesPorGrupo = [];
foreach ($inmuebles as $i) {
    $gid = (int) $i['grupo_id'];
    if (!isset($inmueblesPorGrupo[$gid])) $inmueblesPorGrupo[$gid] = [];
    $inmueblesPorGrupo[$gid][] = $i;
}

$cuotasByContrato = [];
$stCuotas = $pdo->query("
    SELECT c.id, c.contrato_id, c.numero_cuota, c.monto, c.fecha_vencimiento,
           COALESCE(SUM(CASE WHEN m.tipo IN ('pago','abono') THEN m.monto ELSE 0 END), 0) AS total_pagado
    FROM cuotas c
    LEFT JOIN cuotas_movimientos m ON m.cuota_id = c.id
    GROUP BY c.id, c.contrato_id, c.numero_cuota, c.monto, c.fecha_vencimiento
    ORDER BY c.numero_cuota
");
while ($q = $stCuotas->fetch(PDO::FETCH_ASSOC)) {
    $cid = (int) $q['contrato_id'];
    if (!isset($cuotasByContrato[$cid])) {
        $cuotasByContrato[$cid] = [];
    }
    $monto = (float) ($q['monto'] ?? 0);
    $pagado = (float) ($q['total_pagado'] ?? 0);
    $saldo = max(0, $monto - $pagado);
    $cuotasByContrato[$cid][] = [
        'numero_cuota' => (int) ($q['numero_cuota'] ?? 0),
        'monto' => $monto,
        'pagado' => $pagado,
        'saldo' => $saldo,
        'fecha_vencimiento' => $q['fecha_vencimiento'] ?? '',
        'estado' => $saldo <= 0.00001 ? 'Pagada' : 'Pendiente'
    ];
}

$inmueblesDisponibles = [];
foreach ($inmuebles as $i) {
    $iid = (int) $i['id'];
    if ((int) ($contratosPorInmueble[$iid] ?? 0) === 0) {
        $inmueblesDisponibles[] = $i;
    }
}
$ok = obtener('ok');
?>
<?php require_once __DIR__ . '/../includes/layout.php'; ?>

<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <h1 class="mb-1">Mapa Grupos</h1>
        <div class="text-muted">Grupo -> Inmuebles. Verde = inmueble con contrato activo.</div>
    </div>
    <div class="d-flex gap-2">
        <div><span class="badge bg-success">Con contrato</span></div>
        <div><span class="badge bg-secondary">Sin contrato</span></div>
    </div>
</div>
<?php if ($ok): ?><div class="alert alert-success"><?= e($ok) ?></div><?php endif; ?>
<?php if ($mensaje): ?><div class="alert alert-danger"><?= e($mensaje) ?></div><?php endif; ?>

<?php if (empty($grupos)): ?>
    <div class="error">No hay grupos registrados.</div>
<?php else: ?>
    <div class="mapa-grid">
        <?php foreach ($grupos as $g): ?>
            <?php
                $gid = (int) $g['id'];
                $inmueblesGrupo = $inmueblesPorGrupo[$gid] ?? [];
            ?>
            <div class="mapa-muelle card p-3">
                <h2 class="h5 mb-2"><?= e($g['nombre']) ?></h2>
                <?php if (empty($inmueblesGrupo)): ?>
                    <div class="text-muted small">Sin inmuebles</div>
                <?php else: ?>
                    <div class="mapa-slips-list">
                        <?php foreach ($inmueblesGrupo as $i): ?>
                            <?php
                                $iid = (int) $i['id'];
                                $tieneContrato = ((int)($contratosPorInmueble[$iid] ?? 0)) > 0;
                                $detalle = $detalleContratoPorInmueble[$iid] ?? null;
                                $detallePayload = null;
                                if ($detalle) {
                                    $contratoId = (int) $detalle['id'];
                                    $detallePayload = [
                                        'id' => $contratoId,
                                        'cliente' => $detalle['cliente_nombre'] ?? '',
                                        'cuenta' => $detalle['cuenta_nombre'] ?? '',
                                        'fecha_inicio' => $detalle['fecha_inicio'] ?? '',
                                        'fecha_fin' => $detalle['fecha_fin'] ?? '',
                                        'monto_total' => (float) ($detalle['monto_total'] ?? 0),
                                        'observaciones' => $detalle['observaciones'] ?? '',
                                        'inmueble_actual_id' => $iid,
                                        'inmueble_actual_nombre' => $i['nombre'] ?? '',
                                        'grupo_actual_nombre' => $g['nombre'] ?? '',
                                        'cuotas' => $cuotasByContrato[$contratoId] ?? []
                                    ];
                                }
                            ?>
                            <div
                                class="mapa-slip-item d-flex align-items-center justify-content-between <?= $tieneContrato ? 'cursor-pointer' : '' ?>"
                                <?php if ($tieneContrato && $detallePayload): ?>
                                    data-bs-toggle="modal"
                                    data-bs-target="#detalleContratoInmuebleModal"
                                    data-contrato='<?= e(json_encode($detallePayload, JSON_UNESCAPED_UNICODE)) ?>'
                                <?php endif; ?>
                            >
                                <div class="fw-semibold"><?= e($i['nombre']) ?></div>
                                <?php if ($tieneContrato): ?>
                                    <span class="badge bg-success">Con contrato</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Sin contrato</span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="modal fade" id="detalleContratoInmuebleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalle del contrato en inmueble</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3 mb-3">
                    <div class="col-12 col-md-4"><strong>Contrato:</strong> <span id="dciContratoId">—</span></div>
                    <div class="col-12 col-md-4"><strong>Cliente:</strong> <span id="dciCliente">—</span></div>
                    <div class="col-12 col-md-4"><strong>Cuenta:</strong> <span id="dciCuenta">—</span></div>
                    <div class="col-12 col-md-4"><strong>Grupo:</strong> <span id="dciGrupo">—</span></div>
                    <div class="col-12 col-md-4"><strong>Inmueble:</strong> <span id="dciInmueble">—</span></div>
                    <div class="col-12 col-md-4"><strong>Monto total:</strong> <span id="dciMontoTotal">—</span></div>
                    <div class="col-12 col-md-4"><strong>Inicio:</strong> <span id="dciFechaInicio">—</span></div>
                    <div class="col-12 col-md-4"><strong>Fin:</strong> <span id="dciFechaFin">—</span></div>
                </div>

                <div class="mb-2"><strong>Observaciones:</strong> <span id="dciObs">—</span></div>

                <h6 class="mt-3">Cuotas pagadas y no pagadas</h6>
                <div class="table-responsive">
                    <table class="table table-sm align-middle no-datatable">
                        <thead>
                        <tr>
                            <th>Cuota</th>
                            <th>Monto</th>
                            <th>Pagado</th>
                            <th>Saldo</th>
                            <th>Vence</th>
                            <th>Estado</th>
                        </tr>
                        </thead>
                        <tbody id="dciCuotasTbody">
                            <tr><td colspan="6" class="text-muted">Sin cuotas.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <form method="post" action="?p=mapa-grupos">
                <input type="hidden" name="accion" value="cambiar_inmueble_contrato">
                <input type="hidden" name="contrato_id" id="cambiarInmuebleContratoId" value="">
                <div class="modal-footer d-flex justify-content-between flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <label for="cambiarInmuebleNuevoId" class="mb-0"><strong>Cambiar a inmueble:</strong></label>
                        <select class="form-select" id="cambiarInmuebleNuevoId" name="inmueble_nuevo_id" required>
                            <option value="">Seleccione...</option>
                            <?php foreach ($inmueblesDisponibles as $idisp): ?>
                                <?php
                                    $grupoNom = '';
                                    foreach ($grupos as $grow) {
                                        if ((int)$grow['id'] === (int)$idisp['grupo_id']) { $grupoNom = $grow['nombre']; break; }
                                    }
                                ?>
                                <option value="<?= (int) $idisp['id'] ?>"><?= e(($grupoNom ? $grupoNom . ' - ' : '') . ($idisp['nombre'] ?? '')) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
                        <button type="submit" class="btn btn-primary">Cambiar inmueble</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
window.addEventListener('load', function() {
    var elModal = document.getElementById('detalleContratoInmuebleModal');
    if (!elModal) return;

    var elContratoId = document.getElementById('dciContratoId');
    var elCliente = document.getElementById('dciCliente');
    var elCuenta = document.getElementById('dciCuenta');
    var elGrupo = document.getElementById('dciGrupo');
    var elInmueble = document.getElementById('dciInmueble');
    var elMonto = document.getElementById('dciMontoTotal');
    var elFIni = document.getElementById('dciFechaInicio');
    var elFFin = document.getElementById('dciFechaFin');
    var elObs = document.getElementById('dciObs');
    var elTbody = document.getElementById('dciCuotasTbody');
    var elContratoInput = document.getElementById('cambiarInmuebleContratoId');
    var elInmuebleNuevo = document.getElementById('cambiarInmuebleNuevoId');

    function money(n) {
        var num = Number(n || 0);
        return new Intl.NumberFormat('es-PA', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(num);
    }
    function esc(v) {
        return String(v || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    document.querySelectorAll('.mapa-slip-item[data-contrato]').forEach(function(item) {
        item.addEventListener('click', function() {
            var raw = item.getAttribute('data-contrato') || '{}';
            var d = {};
            try { d = JSON.parse(raw); } catch (e) { d = {}; }

            elContratoId.textContent = d.id || '—';
            elCliente.textContent = d.cliente || '—';
            elCuenta.textContent = d.cuenta || '—';
            elGrupo.textContent = d.grupo_actual_nombre || '—';
            elInmueble.textContent = d.inmueble_actual_nombre || '—';
            elMonto.textContent = money(d.monto_total || 0);
            elFIni.textContent = d.fecha_inicio || '—';
            elFFin.textContent = d.fecha_fin || '—';
            elObs.textContent = d.observaciones || '—';
            if (elContratoInput) elContratoInput.value = d.id || '';
            if (elInmuebleNuevo) elInmuebleNuevo.value = '';

            var cuotas = Array.isArray(d.cuotas) ? d.cuotas : [];
            if (!cuotas.length) {
                elTbody.innerHTML = '<tr><td colspan="6" class="text-muted">Sin cuotas registradas.</td></tr>';
                return;
            }

            var html = '';
            cuotas.forEach(function(c) {
                var estado = c.estado || 'Pendiente';
                var badge = estado === 'Pagada' ? 'bg-success' : 'bg-warning text-dark';
                html += '<tr>'
                    + '<td>#' + esc(c.numero_cuota || '') + '</td>'
                    + '<td>' + esc(money(c.monto || 0)) + '</td>'
                    + '<td>' + esc(money(c.pagado || 0)) + '</td>'
                    + '<td>' + esc(money(c.saldo || 0)) + '</td>'
                    + '<td>' + esc(c.fecha_vencimiento || '') + '</td>'
                    + '<td><span class="badge ' + badge + '">' + esc(estado) + '</span></td>'
                    + '</tr>';
            });
            elTbody.innerHTML = html;
        });
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

