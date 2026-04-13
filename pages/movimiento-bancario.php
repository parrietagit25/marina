<?php
/**
 * Movimiento bancario: línea de tiempo de ingresos/costos
 * (cuotas, electricidad por contrato, gastos y movimientos manuales).
 */
$titulo = 'Movimiento bancario';
$pdo = getDb();

$desde = obtener('desde', date('Y-m-01'));
$hasta = obtener('hasta', date('Y-m-d'));
$cuenta_id = (int) obtener('cuenta_id', 0);
$mensaje = '';
$mostrarModal = false;

$cuentasRows = $pdo->query("
    SELECT c.id, c.nombre AS cuenta_nombre, b.nombre AS banco_nombre
    FROM cuentas c
    JOIN bancos b ON c.banco_id = b.id
    ORDER BY b.nombre, c.nombre
")->fetchAll(PDO::FETCH_ASSOC);

$cuentas = [];
$cuentasById = [];
foreach ($cuentasRows as $row) {
    $cid = (int) $row['id'];
    $cuentas[$cid] = ($row['banco_nombre'] ?? '') . ' - ' . ($row['cuenta_nombre'] ?? '');
    $cuentasById[$cid] = $row;
}

$formasRows = $pdo->query("SELECT id, nombre, tipo_movimiento FROM formas_pago ORDER BY tipo_movimiento, nombre")->fetchAll(PDO::FETCH_ASSOC);
$formasById = [];
foreach ($formasRows as $fr) {
    $formasById[(int) $fr['id']] = $fr;
}

$formData = [
    'movimiento_id' => 0,
    'fecha_movimiento' => date('Y-m-d'),
    'cuenta_id' => 0,
    'tipo_movimiento' => 'ingreso',
    'forma_pago_id' => 0,
    'monto' => '',
    'referencia' => '',
    'descripcion' => ''
];

$accionPost = enviado() ? trim((string) ($_POST['accion'] ?? '')) : '';
if (enviado() && ($accionPost === 'crear_movimiento_bancario' || $accionPost === 'actualizar_movimiento_bancario')) {
    $formData = [
        'movimiento_id' => $accionPost === 'actualizar_movimiento_bancario' ? (int) ($_POST['movimiento_id'] ?? 0) : 0,
        'fecha_movimiento' => trim($_POST['fecha_movimiento'] ?? date('Y-m-d')),
        'cuenta_id' => (int) ($_POST['cuenta_id'] ?? 0),
        'tipo_movimiento' => trim($_POST['tipo_movimiento'] ?? ''),
        'forma_pago_id' => (int) ($_POST['forma_pago_id'] ?? 0),
        'monto' => trim($_POST['monto'] ?? ''),
        'referencia' => trim($_POST['referencia'] ?? ''),
        'descripcion' => trim($_POST['descripcion'] ?? '')
    ];
    $mostrarModal = true;

    $tipoValido = in_array($formData['tipo_movimiento'], ['ingreso', 'costo'], true);
    $formaSeleccionada = $formasById[$formData['forma_pago_id']] ?? null;
    $montoNum = (float) str_replace(',', '', $formData['monto']);
    $esEdicion = $accionPost === 'actualizar_movimiento_bancario' && $formData['movimiento_id'] > 0;

    if ($accionPost === 'actualizar_movimiento_bancario' && $formData['movimiento_id'] <= 0) {
        $mensaje = 'Movimiento no válido.';
    }

    if ($mensaje === '' && $esEdicion) {
        $stEx = $pdo->prepare('SELECT id FROM movimientos_bancarios WHERE id = ? LIMIT 1');
        $stEx->execute([$formData['movimiento_id']]);
        if (!$stEx->fetch()) {
            $mensaje = 'El movimiento ya no existe o no se puede editar.';
        }
    }

    if ($mensaje === '' && ($formData['cuenta_id'] <= 0 || !isset($cuentasById[$formData['cuenta_id']]))) {
        $mensaje = 'Debe seleccionar una cuenta válida.';
    } elseif ($mensaje === '' && !$tipoValido) {
        $mensaje = 'Debe seleccionar el tipo de movimiento.';
    } elseif ($mensaje === '' && ($formData['forma_pago_id'] <= 0 || !$formaSeleccionada)) {
        $mensaje = 'Debe seleccionar un tipo de movimiento bancario.';
    } elseif ($mensaje === '' && (($formaSeleccionada['tipo_movimiento'] ?? '') !== $formData['tipo_movimiento'])) {
        $mensaje = 'El tipo seleccionado no coincide con la clasificación del movimiento.';
    } elseif ($mensaje === '' && $montoNum <= 0) {
        $mensaje = 'El monto debe ser mayor a 0.';
    } elseif ($mensaje === '' && $formData['fecha_movimiento'] === '') {
        $mensaje = 'La fecha es obligatoria.';
    } elseif ($mensaje === '') {
        try {
            if ($esEdicion) {
                $sqlUp = "
                    UPDATE movimientos_bancarios SET
                        cuenta_id = ?,
                        forma_pago_id = ?,
                        tipo_movimiento = ?,
                        monto = ?,
                        fecha_movimiento = ?,
                        referencia = ?,
                        descripcion = ?,
                        updated_by = ?
                    WHERE id = ?
                ";
                $pdo->prepare($sqlUp)->execute([
                    $formData['cuenta_id'],
                    $formData['forma_pago_id'],
                    $formData['tipo_movimiento'],
                    $montoNum,
                    $formData['fecha_movimiento'],
                    $formData['referencia'] !== '' ? $formData['referencia'] : null,
                    $formData['descripcion'] !== '' ? $formData['descripcion'] : null,
                    usuarioId(),
                    $formData['movimiento_id'],
                ]);
                redirigir(MARINA_URL . '/index.php?p=movimiento-bancario&ok=' . rawurlencode('Movimiento actualizado'));
            } else {
                $sqlInsert = "
                    INSERT INTO movimientos_bancarios
                        (cuenta_id, forma_pago_id, tipo_movimiento, monto, fecha_movimiento, referencia, descripcion, created_by, updated_by)
                    VALUES
                        (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ";
                $pdo->prepare($sqlInsert)->execute([
                    $formData['cuenta_id'],
                    $formData['forma_pago_id'],
                    $formData['tipo_movimiento'],
                    $montoNum,
                    $formData['fecha_movimiento'],
                    $formData['referencia'] !== '' ? $formData['referencia'] : null,
                    $formData['descripcion'] !== '' ? $formData['descripcion'] : null,
                    usuarioId(),
                    usuarioId()
                ]);
                redirigir(MARINA_URL . '/index.php?p=movimiento-bancario&ok=Movimiento registrado');
            }
        } catch (Throwable $e) {
            $mensaje = $esEdicion
                ? 'No se pudo actualizar el movimiento.'
                : 'No se pudo registrar. Verifique que exista la tabla movimientos_bancarios.';
        }
    }
}

$params = [$desde, $hasta];
$cuentaFiltro = '';
if ($cuenta_id > 0) {
    $cuentaFiltro = ' AND co.cuenta_id = ? ';
    $params[] = $cuenta_id;
}

// Ingresos por pagos/abonos de cuotas
$ing = $pdo->prepare("
    SELECT mo.fecha_pago AS fecha,
           'Ingreso' AS tipo,
           mo.tipo AS origen,
           mo.monto AS monto,
           CONCAT('Cuota #', cu.numero_cuota) AS concepto,
           CONCAT(b.nombre, ' - ', c.nombre) AS cuenta_nombre,
           COALESCE(mo.referencia, '') AS referencia,
           '' AS descripcion,
           NULL AS movimiento_manual_id,
           NULL AS manual_cuenta_id,
           NULL AS manual_forma_pago_id
    FROM cuotas_movimientos mo
    JOIN cuotas cu ON mo.cuota_id = cu.id
    JOIN contratos co ON cu.contrato_id = co.id
    JOIN cuentas c ON co.cuenta_id = c.id
    JOIN bancos b ON c.banco_id = b.id
    WHERE mo.fecha_pago BETWEEN ? AND ?
      AND mo.tipo IN ('pago','abono')
      $cuentaFiltro
");
$ing->execute($params);
$ingresos = $ing->fetchAll(PDO::FETCH_ASSOC);

$ingEleParams = [$desde, $hasta];
$ingEleFiltro = '';
if ($cuenta_id > 0) {
    $ingEleFiltro = ' AND ep.cuenta_id = ? ';
    $ingEleParams[] = $cuenta_id;
}
$ingresosEle = [];
try {
    $ingEle = $pdo->prepare("
        SELECT ep.fecha_pago AS fecha,
               'Ingreso' AS tipo,
               'electricidad' AS origen,
               ep.monto AS monto,
               CONCAT('Electricidad — Contrato #', co.id) AS concepto,
               CONCAT(b.nombre, ' - ', c.nombre) AS cuenta_nombre,
               COALESCE(ep.referencia, '') AS referencia,
               COALESCE(ep.observaciones, '') AS descripcion,
               NULL AS movimiento_manual_id,
               NULL AS manual_cuenta_id,
               NULL AS manual_forma_pago_id
        FROM contrato_electricidad_pagos ep
        JOIN contrato_electricidad_facturas f ON f.id = ep.factura_id
        JOIN contratos co ON co.id = f.contrato_id
        JOIN cuentas c ON c.id = ep.cuenta_id
        JOIN bancos b ON c.banco_id = b.id
        WHERE ep.fecha_pago BETWEEN ? AND ?
          $ingEleFiltro
    ");
    $ingEle->execute($ingEleParams);
    $ingresosEle = $ingEle->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $ingresosEle = [];
}

// Costos por gastos
$gastosParams = [$desde, $hasta];
$gastoCuentaFiltro = '';
if ($cuenta_id > 0) {
    $gastoCuentaFiltro = ' AND gp.cuenta_id = ? ';
    $gastosParams[] = $cuenta_id;
}

$cos = $pdo->prepare("
    SELECT gp.fecha_pago AS fecha,
           'Costo' AS tipo,
           'gasto' AS origen,
           gp.monto AS monto,
           CONCAT('Gasto - ', p.nombre) AS concepto,
           CONCAT(b.nombre, ' - ', c.nombre) AS cuenta_nombre,
           COALESCE(gp.referencia, '') AS referencia,
           COALESCE(gp.observaciones, '') AS descripcion,
           NULL AS movimiento_manual_id,
           NULL AS manual_cuenta_id,
           NULL AS manual_forma_pago_id
    FROM gasto_pagos gp
    JOIN gastos g ON gp.gasto_id = g.id
    JOIN partidas p ON g.partida_id = p.id
    LEFT JOIN cuentas c ON gp.cuenta_id = c.id
    LEFT JOIN bancos b ON c.banco_id = b.id
    WHERE gp.fecha_pago BETWEEN ? AND ?
      $gastoCuentaFiltro
");
$cos->execute($gastosParams);
$gastos = $cos->fetchAll(PDO::FETCH_ASSOC);

// Movimientos manuales
$manuales = [];
try {
    $manualParams = [$desde, $hasta];
    $manualCuentaFiltro = '';
    if ($cuenta_id > 0) {
        $manualCuentaFiltro = ' AND mb.cuenta_id = ? ';
        $manualParams[] = $cuenta_id;
    }
    $mov = $pdo->prepare("
        SELECT mb.fecha_movimiento AS fecha,
               CASE WHEN mb.tipo_movimiento = 'costo' THEN 'Costo' ELSE 'Ingreso' END AS tipo,
               'manual' AS origen,
               mb.monto AS monto,
               CONCAT('Movimiento - ', fp.nombre) AS concepto,
               CONCAT(b.nombre, ' - ', c.nombre) AS cuenta_nombre,
               COALESCE(mb.referencia, '') AS referencia,
               COALESCE(mb.descripcion, '') AS descripcion,
               mb.id AS movimiento_manual_id,
               mb.cuenta_id AS manual_cuenta_id,
               mb.forma_pago_id AS manual_forma_pago_id
        FROM movimientos_bancarios mb
        JOIN cuentas c ON mb.cuenta_id = c.id
        JOIN bancos b ON c.banco_id = b.id
        JOIN formas_pago fp ON mb.forma_pago_id = fp.id
        WHERE mb.fecha_movimiento BETWEEN ? AND ?
          $manualCuentaFiltro
    ");
    $mov->execute($manualParams);
    $manuales = $mov->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $manuales = [];
}

$movs = array_merge($ingresos, $ingresosEle, $gastos, $manuales);
usort($movs, function($a, $b) {
    $ta = strtotime($a['fecha'] ?? '');
    $tb = strtotime($b['fecha'] ?? '');
    if ($ta === $tb) {
        return 0;
    }
    return ($ta < $tb) ? 1 : -1;
});

$totalIngresos = 0.0;
$totalCostos = 0.0;
foreach ($movs as $m) {
    $tipo = $m['tipo'] ?? '';
    $val = (float) ($m['monto'] ?? 0);
    if ($tipo === 'Ingreso') {
        $totalIngresos += $val;
    } elseif ($tipo === 'Costo') {
        $totalCostos += $val;
    }
}
$diferencia = $totalIngresos - $totalCostos;

$modalDataJson = json_encode([
    'mostrar' => $mostrarModal,
    'error' => $mensaje,
    'datos' => $formData
], JSON_UNESCAPED_UNICODE);
?>
<?php require_once __DIR__ . '/../includes/layout.php'; ?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h1 class="h4 mb-0">Movimiento bancario</h1>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#movBancarioModal" id="btnNuevoMovBancario">
        Registrar movimiento bancario
    </button>
</div>

<?php if ($ok = obtener('ok')): ?>
    <div class="alert alert-success"><?= e($ok) ?></div>
<?php endif; ?>

<form method="get" class="toolbar mb-3">
    <input type="hidden" name="p" value="movimiento-bancario">
    <div class="row g-2 align-items-end">
        <div class="col-12 col-md-3">
            <label class="form-label mb-1">Desde</label>
            <input type="date" class="form-control" name="desde" value="<?= e($desde) ?>">
        </div>
        <div class="col-12 col-md-3">
            <label class="form-label mb-1">Hasta</label>
            <input type="date" class="form-control" name="hasta" value="<?= e($hasta) ?>">
        </div>
        <div class="col-12 col-md-3">
            <label class="form-label mb-1">Cuenta</label>
            <select class="form-select" name="cuenta_id">
                <option value="0">Todas</option>
                <?php foreach ($cuentas as $cid => $cnom): ?>
                    <option value="<?= (int)$cid ?>" <?= $cuenta_id === (int)$cid ? 'selected' : '' ?>><?= e($cnom) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-auto">
            <button type="submit" class="btn btn-outline-primary">Filtrar</button>
        </div>
    </div>
</form>

<div class="card p-3 mb-3">
    <div class="d-flex flex-wrap gap-2">
        <div class="me-3"><strong>Total ingresos:</strong> <?= dinero($totalIngresos) ?></div>
        <div class="me-3"><strong>Total costos:</strong> <?= dinero($totalCostos) ?></div>
        <div><strong>Diferencia:</strong> <span style="color:<?= $diferencia >= 0 ? 'green' : '#b42318' ?>"><?= dinero($diferencia) ?></span></div>
    </div>
</div>

<div class="card p-3">
    <h2 class="h5 mb-3">Movimientos</h2>
    <div class="table-responsive">
        <table class="table align-middle no-datatable">
            <thead>
            <tr>
                <th>Fecha</th>
                <th>Tipo</th>
                <th>Concepto</th>
                <th>Cuenta</th>
                <th>Referencia</th>
                <th>Comentario</th>
                <th>Monto</th>
                <th class="text-end">Acciones</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($movs)): ?>
                <tr><td colspan="8">No hay movimientos en el período.</td></tr>
            <?php else: ?>
                <?php foreach ($movs as $m): ?>
                    <?php
                    $tipo = $m['tipo'] ?? '';
                    $monto = (float) ($m['monto'] ?? 0);
                    $color = $tipo === 'Ingreso' ? '#137333' : '#b42318';
                    $midManual = isset($m['movimiento_manual_id']) ? (int) $m['movimiento_manual_id'] : 0;
                    $editPayload = null;
                    if ($midManual > 0) {
                        $editPayload = [
                            'movimiento_id' => $midManual,
                            'cuenta_id' => (int) ($m['manual_cuenta_id'] ?? 0),
                            'forma_pago_id' => (int) ($m['manual_forma_pago_id'] ?? 0),
                            'tipo_movimiento' => $tipo === 'Costo' ? 'costo' : 'ingreso',
                            'fecha_movimiento' => (string) ($m['fecha'] ?? ''),
                            'monto' => number_format($monto, 2, '.', ''),
                            'referencia' => (string) ($m['referencia'] ?? ''),
                            'descripcion' => (string) ($m['descripcion'] ?? ''),
                        ];
                    }
                    ?>
                    <tr>
                        <td><?= fechaFormato($m['fecha']) ?></td>
                        <td><span class="fw-semibold" style="color:<?= $color ?>"><?= e($tipo) ?></span></td>
                        <td><?= e($m['concepto'] ?? '') ?></td>
                        <td><?= e($m['cuenta_nombre'] ?? '—') ?></td>
                        <td><?= e($m['referencia'] ?? '') ?></td>
                        <td><?= e($m['descripcion'] ?? '') ?></td>
                        <td><?= dinero($monto) ?></td>
                        <td class="text-end">
                            <?php if ($editPayload !== null): ?>
                                <button type="button" class="btn btn-sm btn-outline-primary btn-editar-mov-bancario"
                                    data-mov="<?= htmlspecialchars(json_encode($editPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8') ?>">
                                    Editar
                                </button>
                            <?php else: ?>
                                <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="movBancarioModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" id="formMovBancario">
                <input type="hidden" name="accion" id="movBancarioAccion" value="crear_movimiento_bancario">
                <input type="hidden" name="movimiento_id" id="movBancarioMovimientoId" value="">
                <div class="modal-header">
                    <h5 class="modal-title" id="movBancarioModalTitle">Registrar movimiento bancario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger d-none" id="movBancarioModalMensaje"></div>

                    <label class="form-label">Cuenta</label>
                    <select class="form-select" name="cuenta_id" id="movBancarioCuentaId" required>
                        <option value="">Seleccione...</option>
                        <?php foreach ($cuentasRows as $cRow): ?>
                            <option value="<?= (int)$cRow['id'] ?>">
                                <?= e(($cRow['banco_nombre'] ?? '') . ' - ' . ($cRow['cuenta_nombre'] ?? '')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <div class="row g-2 mt-1">
                        <div class="col-12 col-md-6">
                            <label class="form-label">Tipo</label>
                            <select class="form-select" name="tipo_movimiento" id="movBancarioTipo" required>
                                <option value="ingreso">Ingreso</option>
                                <option value="costo">Costo</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">Fecha</label>
                            <input type="date" class="form-control" name="fecha_movimiento" id="movBancarioFecha" required>
                        </div>
                    </div>

                    <label class="form-label mt-2">Tipo de movimiento</label>
                    <select class="form-select" name="forma_pago_id" id="movBancarioFormaPagoId" required>
                        <option value="">Seleccione...</option>
                        <?php foreach ($formasRows as $fRow): ?>
                            <option
                                value="<?= (int)$fRow['id'] ?>"
                                data-tipo-mov="<?= e($fRow['tipo_movimiento']) ?>"
                                data-nombre="<?= e($fRow['nombre']) ?>"
                            >
                                <?= e($fRow['nombre']) ?> (<?= e($fRow['tipo_movimiento'] === 'costo' ? 'Costo' : 'Ingreso') ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <label class="form-label mt-2">Monto</label>
                    <input type="number" step="0.01" min="0.01" class="form-control" name="monto" id="movBancarioMonto" required>

                    <label class="form-label mt-2">Referencia del movimiento</label>
                    <input type="text" class="form-control" name="referencia" id="movBancarioReferencia" maxlength="100">

                    <label class="form-label mt-2">Comentario / Descripción</label>
                    <textarea class="form-control" name="descripcion" id="movBancarioDescripcion" rows="3"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="movBancarioSubmitBtn">Guardar movimiento</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
window.__movBancarioModal = <?= $modalDataJson ?: '{"mostrar":false,"error":"","datos":{}}' ?>;
window.addEventListener('load', function() {
    var el = document.getElementById('movBancarioModal');
    if (!el) return;

    var modal = (typeof bootstrap !== 'undefined') ? new bootstrap.Modal(el) : null;
    var cuenta = document.getElementById('movBancarioCuentaId');
    var tipo = document.getElementById('movBancarioTipo');
    var forma = document.getElementById('movBancarioFormaPagoId');
    var fecha = document.getElementById('movBancarioFecha');
    var monto = document.getElementById('movBancarioMonto');
    var referencia = document.getElementById('movBancarioReferencia');
    var descripcion = document.getElementById('movBancarioDescripcion');
    var msg = document.getElementById('movBancarioModalMensaje');
    var btnNuevo = document.getElementById('btnNuevoMovBancario');
    var accionEl = document.getElementById('movBancarioAccion');
    var movIdEl = document.getElementById('movBancarioMovimientoId');
    var titleEl = document.getElementById('movBancarioModalTitle');
    var submitBtn = document.getElementById('movBancarioSubmitBtn');

    function setError(texto) {
        if (!msg) return;
        msg.textContent = texto || '';
        msg.classList.toggle('d-none', !texto);
    }

    var formasCatalogo = [];
    if (forma) {
        Array.prototype.forEach.call(forma.options, function(opt, idx) {
            if (idx === 0) return;
            formasCatalogo.push({
                id: opt.value,
                nombre: opt.getAttribute('data-nombre') || opt.textContent || '',
                tipo: opt.getAttribute('data-tipo-mov') || ''
            });
        });
    }

    function reconstruirOpcionesForma(elegido, valorSeleccionado) {
        if (!forma) return;
        while (forma.firstChild) {
            forma.removeChild(forma.firstChild);
        }
        var optDefault = document.createElement('option');
        optDefault.value = '';
        optDefault.textContent = 'Seleccione...';
        forma.appendChild(optDefault);

        var selectedFound = false;
        formasCatalogo.forEach(function(item) {
            if (item.tipo !== elegido) return;
            var op = document.createElement('option');
            op.value = item.id;
            op.textContent = item.nombre;
            if (String(valorSeleccionado || '') === String(item.id)) {
                op.selected = true;
                selectedFound = true;
            }
            forma.appendChild(op);
        });
        if (!selectedFound) {
            forma.value = '';
        }
    }

    function filtrarTipos() {
        if (!tipo || !forma) return;
        var elegido = tipo.value || 'ingreso';
        reconstruirOpcionesForma(elegido, forma.value || '');
    }

    function modoRegistrar() {
        if (accionEl) accionEl.value = 'crear_movimiento_bancario';
        if (movIdEl) movIdEl.value = '';
        if (titleEl) titleEl.textContent = 'Registrar movimiento bancario';
        if (submitBtn) submitBtn.textContent = 'Guardar movimiento';
    }

    function limpiarForm() {
        modoRegistrar();
        if (cuenta) cuenta.value = '';
        if (tipo) tipo.value = 'ingreso';
        if (forma) forma.value = '';
        if (fecha) fecha.value = '<?= e(date('Y-m-d')) ?>';
        if (monto) monto.value = '';
        if (referencia) referencia.value = '';
        if (descripcion) descripcion.value = '';
        filtrarTipos();
        setError('');
    }

    function abrirEdicion(data) {
        if (!data || !data.movimiento_id) return;
        setError('');
        if (accionEl) accionEl.value = 'actualizar_movimiento_bancario';
        if (movIdEl) movIdEl.value = String(data.movimiento_id);
        if (titleEl) titleEl.textContent = 'Editar movimiento bancario';
        if (submitBtn) submitBtn.textContent = 'Guardar cambios';
        if (cuenta) cuenta.value = String(data.cuenta_id || '');
        if (tipo) tipo.value = data.tipo_movimiento || 'ingreso';
        reconstruirOpcionesForma(tipo ? (tipo.value || 'ingreso') : 'ingreso', String(data.forma_pago_id || ''));
        if (fecha) fecha.value = data.fecha_movimiento || '';
        if (monto) monto.value = data.monto != null ? String(data.monto) : '';
        if (referencia) referencia.value = data.referencia || '';
        if (descripcion) descripcion.value = data.descripcion || '';
        if (modal) modal.show();
    }

    document.addEventListener('click', function(ev) {
        var btn = ev.target && ev.target.closest ? ev.target.closest('.btn-editar-mov-bancario') : null;
        if (!btn) return;
        var raw = btn.getAttribute('data-mov');
        if (!raw) return;
        try {
            abrirEdicion(JSON.parse(raw));
        } catch (e) {}
    });

    if (btnNuevo) {
        btnNuevo.addEventListener('click', function() {
            limpiarForm();
        });
    }
    if (tipo) tipo.addEventListener('change', filtrarTipos);
    el.addEventListener('shown.bs.modal', filtrarTipos);

    if (window.__movBancarioModal && window.__movBancarioModal.mostrar) {
        var datos = window.__movBancarioModal.datos || {};
        var mid = parseInt(String(datos.movimiento_id || '0'), 10);
        if (mid > 0) {
            if (accionEl) accionEl.value = 'actualizar_movimiento_bancario';
            if (movIdEl) movIdEl.value = String(mid);
            if (titleEl) titleEl.textContent = 'Editar movimiento bancario';
            if (submitBtn) submitBtn.textContent = 'Guardar cambios';
        } else {
            modoRegistrar();
        }
        if (cuenta) cuenta.value = String(datos.cuenta_id || '');
        if (tipo) tipo.value = datos.tipo_movimiento || 'ingreso';
        reconstruirOpcionesForma(tipo ? (tipo.value || 'ingreso') : 'ingreso', String(datos.forma_pago_id || ''));
        if (fecha) fecha.value = datos.fecha_movimiento || '<?= e(date('Y-m-d')) ?>';
        if (monto) monto.value = datos.monto || '';
        if (referencia) referencia.value = datos.referencia || '';
        if (descripcion) descripcion.value = datos.descripcion || '';
        setError(window.__movBancarioModal.error || '');
        if (modal) modal.show();
    } else {
        filtrarTipos();
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

