<?php
/**
 * Mapa Marina: muelles y slips dentro de cada muelle.
 * - Slip en verde si tiene contrato activo (contratos.activo=1).
 */
$titulo = 'Mapa Marina';

$pdo = getDb();
$mensaje = '';

if (enviado() && (($_POST['accion'] ?? '') === 'cambiar_slip_contrato')) {
    $contratoId = (int) ($_POST['contrato_id'] ?? 0);
    $slipNuevoId = (int) ($_POST['slip_nuevo_id'] ?? 0);
    if ($contratoId <= 0 || $slipNuevoId <= 0) {
        $mensaje = 'Datos inválidos para cambiar el slip.';
    } else {
        $stContrato = $pdo->prepare('SELECT id, activo, muelle_id, slip_id FROM contratos WHERE id = ?');
        $stContrato->execute([$contratoId]);
        $contrato = $stContrato->fetch(PDO::FETCH_ASSOC);
        if (!$contrato) {
            $mensaje = 'Contrato no encontrado.';
        } else {
            $stSlip = $pdo->prepare('SELECT id, muelle_id FROM slips WHERE id = ?');
            $stSlip->execute([$slipNuevoId]);
            $slipNuevo = $stSlip->fetch(PDO::FETCH_ASSOC);
            if (!$slipNuevo) {
                $mensaje = 'Slip destino no encontrado.';
            } else {
                $stOcupado = $pdo->prepare('SELECT id FROM contratos WHERE activo = 1 AND slip_id = ? AND id <> ? LIMIT 1');
                $stOcupado->execute([$slipNuevoId, $contratoId]);
                $ocupado = (bool) $stOcupado->fetchColumn();
                if ($ocupado) {
                    $mensaje = 'No se puede mover: el slip destino ya tiene contrato activo.';
                } else {
                    $pdo->prepare('UPDATE contratos SET slip_id = ?, muelle_id = ?, updated_by = ? WHERE id = ?')
                        ->execute([$slipNuevoId, (int) $slipNuevo['muelle_id'], usuarioId(), $contratoId]);
                    redirigir(MARINA_URL . '/index.php?p=mapa-marina&ok=Contrato movido de slip');
                }
            }
        }
    }
}

$muelles = $pdo->query('SELECT id, nombre FROM muelles ORDER BY nombre')
    ->fetchAll(PDO::FETCH_ASSOC);

$slips = $pdo->query('SELECT id, nombre, muelle_id FROM slips ORDER BY muelle_id, nombre')
    ->fetchAll(PDO::FETCH_ASSOC);

$contratosPorSlip = [];
$detalleContratoPorSlip = [];
$st = $pdo->query("
    SELECT co.id, co.slip_id, co.muelle_id, co.fecha_inicio, co.fecha_fin, co.monto_total, co.observaciones, co.activo,
           cl.nombre AS cliente_nombre,
           CONCAT(b.nombre, ' - ', cu.nombre) AS cuenta_nombre
    FROM contratos co
    LEFT JOIN clientes cl ON cl.id = co.cliente_id
    LEFT JOIN cuentas cu ON cu.id = co.cuenta_id
    LEFT JOIN bancos b ON b.id = cu.banco_id
    WHERE co.activo = 1 AND co.slip_id IS NOT NULL
");
while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
    $sid = (int) $r['slip_id'];
    $contratosPorSlip[$sid] = ($contratosPorSlip[$sid] ?? 0) + 1;
    if (!isset($detalleContratoPorSlip[$sid])) {
        $detalleContratoPorSlip[$sid] = $r; // el principal para modal
    }
}

$slipsPorMuelle = [];
foreach ($slips as $s) {
    $mid = (int) $s['muelle_id'];
    if (!isset($slipsPorMuelle[$mid])) $slipsPorMuelle[$mid] = [];
    $slipsPorMuelle[$mid][] = $s;
}

$slipsIndex = [];
foreach ($slips as $s) {
    $slipsIndex[(int) $s['id']] = $s;
}

$cuotasByContrato = [];
$stCuotas = $pdo->query("
    SELECT c.id, c.contrato_id, c.numero_cuota, c.monto, c.fecha_vencimiento, c.fecha_pago,
           COALESCE(SUM(CASE WHEN m.tipo IN ('pago','abono') THEN m.monto ELSE 0 END), 0) AS total_pagado
    FROM cuotas c
    LEFT JOIN cuotas_movimientos m ON m.cuota_id = c.id
    GROUP BY c.id, c.contrato_id, c.numero_cuota, c.monto, c.fecha_vencimiento, c.fecha_pago
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
    $estado = $saldo <= 0.00001 ? 'Pagada' : 'Pendiente';
    $cuotasByContrato[$cid][] = [
        'numero_cuota' => (int) ($q['numero_cuota'] ?? 0),
        'monto' => $monto,
        'pagado' => $pagado,
        'saldo' => $saldo,
        'fecha_vencimiento' => $q['fecha_vencimiento'] ?? '',
        'estado' => $estado
    ];
}

$slipsDisponibles = [];
foreach ($slips as $s) {
    $sid = (int) $s['id'];
    $activos = (int) ($contratosPorSlip[$sid] ?? 0);
    if ($activos === 0) {
        $slipsDisponibles[] = $s;
    }
}

$ok = obtener('ok');
?>
<?php require_once __DIR__ . '/../includes/layout.php'; ?>

<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <h1 class="mb-1">Mapa Marina</h1>
        <div class="text-muted">Muelle -> Slips. Verde = slip con contrato activo.</div>
    </div>
    <div class="d-flex gap-2">
        <div><span class="badge bg-success">Con contrato</span></div>
        <div><span class="badge bg-secondary">Sin contrato</span></div>
    </div>
</div>
<?php if ($ok): ?><div class="alert alert-success"><?= e($ok) ?></div><?php endif; ?>
<?php if ($mensaje): ?><div class="alert alert-danger"><?= e($mensaje) ?></div><?php endif; ?>

<?php if (empty($muelles)): ?>
    <div class="error">No hay muelles registrados.</div>
<?php else: ?>
    <div class="mapa-grid">
        <?php foreach ($muelles as $m): ?>
            <?php
                $mid = (int) $m['id'];
                $slipsMu = $slipsPorMuelle[$mid] ?? [];
            ?>
            <div class="mapa-muelle card p-3">
                <h2 class="h5 mb-2"><?= e($m['nombre']) ?></h2>
                <?php if (empty($slipsMu)): ?>
                    <div class="text-muted small">Sin slips</div>
                <?php else: ?>
                    <div class="mapa-slips-list">
                        <?php foreach ($slipsMu as $s): ?>
                            <?php
                                $sid = (int) $s['id'];
                                $totalContratos = $contratosPorSlip[$sid] ?? 0;
                                $tieneContrato = $totalContratos > 0;
                                $detalle = $detalleContratoPorSlip[$sid] ?? null;
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
                                        'slip_actual_id' => $sid,
                                        'slip_actual_nombre' => $s['nombre'] ?? '',
                                        'muelle_actual_nombre' => $m['nombre'] ?? '',
                                        'cuotas' => $cuotasByContrato[$contratoId] ?? []
                                    ];
                                }
                            ?>
                            <div
                                class="mapa-slip-item d-flex align-items-center justify-content-between <?= $tieneContrato ? 'cursor-pointer' : '' ?>"
                                <?php if ($tieneContrato && $detallePayload): ?>
                                    data-bs-toggle="modal"
                                    data-bs-target="#detalleContratoSlipModal"
                                    data-contrato='<?= e(json_encode($detallePayload, JSON_UNESCAPED_UNICODE)) ?>'
                                <?php endif; ?>
                            >
                                <div class="fw-semibold"><?= e($s['nombre']) ?></div>
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

<div class="modal fade" id="detalleContratoSlipModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalle del contrato en slip</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3 mb-3">
                    <div class="col-12 col-md-4"><strong>Contrato:</strong> <span id="dcContratoId">—</span></div>
                    <div class="col-12 col-md-4"><strong>Cliente:</strong> <span id="dcCliente">—</span></div>
                    <div class="col-12 col-md-4"><strong>Cuenta:</strong> <span id="dcCuenta">—</span></div>
                    <div class="col-12 col-md-4"><strong>Muelle:</strong> <span id="dcMuelle">—</span></div>
                    <div class="col-12 col-md-4"><strong>Slip:</strong> <span id="dcSlip">—</span></div>
                    <div class="col-12 col-md-4"><strong>Monto total:</strong> <span id="dcMontoTotal">—</span></div>
                    <div class="col-12 col-md-4"><strong>Inicio:</strong> <span id="dcFechaInicio">—</span></div>
                    <div class="col-12 col-md-4"><strong>Fin:</strong> <span id="dcFechaFin">—</span></div>
                </div>

                <div class="mb-2"><strong>Observaciones:</strong> <span id="dcObs">—</span></div>

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
                        <tbody id="dcCuotasTbody">
                            <tr><td colspan="6" class="text-muted">Sin cuotas.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <form method="post" action="?p=mapa-marina">
                <input type="hidden" name="accion" value="cambiar_slip_contrato">
                <input type="hidden" name="contrato_id" id="cambiarSlipContratoId" value="">
                <div class="modal-footer d-flex justify-content-between flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <label for="cambiarSlipNuevoId" class="mb-0"><strong>Cambiar a slip:</strong></label>
                        <select class="form-select" id="cambiarSlipNuevoId" name="slip_nuevo_id" required>
                            <option value="">Seleccione...</option>
                            <?php foreach ($slipsDisponibles as $sd): ?>
                                <?php
                                    $mid = (int) ($sd['muelle_id'] ?? 0);
                                    $muelleNom = '';
                                    foreach ($muelles as $mrow) {
                                        if ((int)$mrow['id'] === $mid) { $muelleNom = $mrow['nombre']; break; }
                                    }
                                ?>
                                <option value="<?= (int) $sd['id'] ?>"><?= e(($muelleNom ? $muelleNom . ' - ' : '') . ($sd['nombre'] ?? '')) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
                        <button type="submit" class="btn btn-primary">Cambiar slip</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
window.addEventListener('load', function() {
    var modalEl = document.getElementById('detalleContratoSlipModal');
    if (!modalEl) return;

    var elContratoId = document.getElementById('dcContratoId');
    var elCliente = document.getElementById('dcCliente');
    var elCuenta = document.getElementById('dcCuenta');
    var elMuelle = document.getElementById('dcMuelle');
    var elSlip = document.getElementById('dcSlip');
    var elMonto = document.getElementById('dcMontoTotal');
    var elFIni = document.getElementById('dcFechaInicio');
    var elFFin = document.getElementById('dcFechaFin');
    var elObs = document.getElementById('dcObs');
    var elTbody = document.getElementById('dcCuotasTbody');
    var elContratoInput = document.getElementById('cambiarSlipContratoId');
    var elSlipNuevo = document.getElementById('cambiarSlipNuevoId');

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
            elMuelle.textContent = d.muelle_actual_nombre || '—';
            elSlip.textContent = d.slip_actual_nombre || '—';
            elMonto.textContent = money(d.monto_total || 0);
            elFIni.textContent = d.fecha_inicio || '—';
            elFFin.textContent = d.fecha_fin || '—';
            elObs.textContent = d.observaciones || '—';
            if (elContratoInput) elContratoInput.value = d.id || '';
            if (elSlipNuevo) elSlipNuevo.value = '';

            var cuotas = Array.isArray(d.cuotas) ? d.cuotas : [];
            if (!cuotas.length) {
                elTbody.innerHTML = '<tr><td colspan="6" class="text-muted">Sin cuotas registradas.</td></tr>';
                return;
            }
            var html = '';
            cuotas.forEach(function(c) {
                var estado = c.estado || 'Pendiente';
                var badge = estado === 'Pagada' ? 'bg-success' : 'bg-warning text-dark';
                html += '<tr>' +
                    '<td>#' + esc(c.numero_cuota || '') + '</td>' +
                    '<td>' + esc(money(c.monto || 0)) + '</td>' +
                    '<td>' + esc(money(c.pagado || 0)) + '</td>' +
                    '<td>' + esc(money(c.saldo || 0)) + '</td>' +
                    '<td>' + esc(c.fecha_vencimiento || '') + '</td>' +
                    '<td><span class="badge ' + badge + '">' + esc(estado) + '</span></td>' +
                '</tr>';
            });
            elTbody.innerHTML = html;
        });
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

