<?php
/**
 * Precio por galón (compra y venta) por tipo; historial por vigente_desde.
 */
$titulo = 'Combustible — Precio por galón';
$pdo = getDb();
require_once __DIR__ . '/../includes/combustible_helpers.php';

$accion = obtener('accion');
$id = (int) obtener('id');
$ui = trim((string) obtener('ui', ''));
$mensaje = '';
$uid = usuarioId();
$abrirModal = false;

if ($accion === 'eliminar' && $id > 0 && enviado()) {
    try {
        $pdo->prepare('DELETE FROM combustible_precios WHERE id = ?')->execute([$id]);
        redirigir(MARINA_URL . '/index.php?p=combustible-precios&ok=' . rawurlencode('Registro eliminado'));
    } catch (Throwable $e) {
        redirigir(MARINA_URL . '/index.php?p=combustible-precios&err=' . rawurlencode(marinaMensajeErrorIntegridad($e)));
    }
}

if (enviado() && ($accion === 'crear' || $accion === 'editar')) {
    $tipo = strtolower(trim((string) ($_POST['tipo_combustible'] ?? '')));
    $compra = (float) str_replace(',', '.', (string) ($_POST['precio_compra_galon'] ?? 0));
    $venta = (float) str_replace(',', '.', (string) ($_POST['precio_venta_galon'] ?? 0));
    $desde = trim((string) ($_POST['vigente_desde'] ?? ''));
    if (!isset(MARINA_COMB_TIPOS[$tipo])) {
        $mensaje = 'Tipo de combustible no válido.';
    } elseif ($desde === '') {
        $mensaje = 'La fecha vigente es obligatoria.';
    } else {
        if ($accion === 'editar' && $id > 0) {
            $pdo->prepare('UPDATE combustible_precios SET tipo_combustible=?, precio_compra_galon=?, precio_venta_galon=?, vigente_desde=?, updated_by=? WHERE id=?')
                ->execute([$tipo, $compra, $venta, $desde, $uid, $id]);
            redirigir(MARINA_URL . '/index.php?p=combustible-precios&ok=' . rawurlencode('Precio actualizado'));
        } else {
            // Misma fecha+tipo que el registro semilla (0,00) u otro duplicado: actualizar en lugar de fallar.
            $stEx = $pdo->prepare('SELECT id FROM combustible_precios WHERE tipo_combustible = ? AND vigente_desde = ? LIMIT 1');
            $stEx->execute([$tipo, $desde]);
            $idEx = (int) ($stEx->fetchColumn() ?: 0);
            try {
                if ($idEx > 0) {
                    $pdo->prepare('UPDATE combustible_precios SET precio_compra_galon=?, precio_venta_galon=?, updated_by=? WHERE id=?')
                        ->execute([$compra, $venta, $uid, $idEx]);
                    redirigir(MARINA_URL . '/index.php?p=combustible-precios&ok=' . rawurlencode('Precio guardado'));
                } else {
                    $pdo->prepare('INSERT INTO combustible_precios (tipo_combustible, precio_compra_galon, precio_venta_galon, vigente_desde, created_by, updated_by) VALUES (?,?,?,?,?,?)')
                        ->execute([$tipo, $compra, $venta, $desde, $uid, $uid]);
                    redirigir(MARINA_URL . '/index.php?p=combustible-precios&ok=' . rawurlencode('Precio registrado'));
                }
            } catch (Throwable $e) {
                $mensaje = 'No se pudo guardar. Si cambió la fecha, puede haber otro registro con el mismo tipo y fecha.';
            }
        }
    }
}

$registro = null;
if ($ui === 'nuevo') {
    $abrirModal = true;
    $accion = 'crear';
    $id = 0;
}
if (($ui === 'editar' || $accion === 'editar') && $id > 0) {
    $st = $pdo->prepare('SELECT * FROM combustible_precios WHERE id = ?');
    $st->execute([$id]);
    $registro = $st->fetch();
    if (!$registro) {
        redirigir(MARINA_URL . '/index.php?p=combustible-precios');
    }
    $abrirModal = true;
    $accion = 'editar';
}

$vigentes = marina_combustible_precios_vigentes($pdo);
$ok = obtener('ok');
$err = obtener('err');
$mostrarModal = $abrirModal || (enviado() && ($accion === 'crear' || $accion === 'editar') && $mensaje !== '');
$modalDatos = [
    'id' => $id,
    'tipo_combustible' => $registro['tipo_combustible'] ?? ($_POST['tipo_combustible'] ?? 'diesel'),
    'precio_compra_galon' => $registro['precio_compra_galon'] ?? ($_POST['precio_compra_galon'] ?? ''),
    'precio_venta_galon' => $registro['precio_venta_galon'] ?? ($_POST['precio_venta_galon'] ?? ''),
    'vigente_desde' => $registro['vigente_desde'] ?? ($_POST['vigente_desde'] ?? date('Y-m-d')),
];

require_once __DIR__ . '/../includes/layout.php';
?>
<h1 class="h4 mb-3">Combustible — Precio por galón</h1>
<p class="text-muted small mb-3">Los montos de pedidos y despachos usan el precio vigente más reciente (fecha ≤ hoy) por tipo. Si ya existe una fila para el mismo tipo y la misma fecha (p. ej. la creada al iniciar el módulo en 0,00), al guardar se <strong>actualiza</strong> ese registro.</p>

<?php if ($ok): ?><div class="alert alert-success py-2"><?= e($ok) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-danger py-2"><?= e($err) ?></div><?php endif; ?>
<?php if ($mensaje !== ''): ?><div class="alert alert-warning py-2"><?= e($mensaje) ?></div><?php endif; ?>

<div class="card p-3 mb-3">
    <div class="row g-2 small">
        <?php foreach (MARINA_COMB_TIPOS as $k => $label): ?>
            <div class="col-md-6">
                <strong><?= e($label) ?> (vigente)</strong><br>
                Compra / gl: <?= dinero($vigentes[$k]['compra']) ?> · Venta / gl: <?= dinero($vigentes[$k]['venta']) ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="toolbar d-flex gap-2 mb-3">
    <button type="button" class="btn btn-primary" id="btnNuevoPrecio">Nuevo precio</button>
    <a class="btn btn-outline-secondary" href="<?= MARINA_URL ?>/index.php?p=combustible-pedidos">Pedidos</a>
    <a class="btn btn-outline-secondary" href="<?= MARINA_URL ?>/index.php?p=combustible-despacho">Despacho</a>
    <a class="btn btn-outline-secondary" href="<?= MARINA_URL ?>/index.php?p=combustible-ajuste">Ajuste</a>
</div>

<div class="card p-0">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Tipo</th><th>Vigente desde</th><th class="text-end">Compra / gl</th><th class="text-end">Venta / gl</th><th></th></tr></thead>
            <tbody>
            <?php
            $st = $pdo->query('SELECT * FROM combustible_precios ORDER BY tipo_combustible, vigente_desde DESC, id DESC');
            while ($r = $st->fetch(PDO::FETCH_ASSOC)): ?>
                <tr>
                    <td><?= e(MARINA_COMB_TIPOS[$r['tipo_combustible']] ?? $r['tipo_combustible']) ?></td>
                    <td><?= fechaFormato($r['vigente_desde']) ?></td>
                    <td class="text-end"><?= dinero((float) $r['precio_compra_galon']) ?></td>
                    <td class="text-end"><?= dinero((float) $r['precio_venta_galon']) ?></td>
                    <td class="text-nowrap">
                        <button type="button" class="btn btn-sm btn-secondary btn-editar-precio"
                            data-precio="<?= htmlspecialchars(json_encode([
                                'id' => (int) $r['id'],
                                'tipo_combustible' => $r['tipo_combustible'],
                                'vigente_desde' => $r['vigente_desde'],
                                'precio_compra_galon' => (string) $r['precio_compra_galon'],
                                'precio_venta_galon' => (string) $r['precio_venta_galon'],
                            ], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>">Editar</button>
                        <button type="button" class="btn btn-sm btn-danger btn-eliminar-precio"
                            data-bs-toggle="modal" data-bs-target="#modalEliminarPrecio"
                            data-id="<?= (int) $r['id'] ?>"
                            data-label="<?= e((MARINA_COMB_TIPOS[$r['tipo_combustible']] ?? $r['tipo_combustible']) . ' — ' . fechaFormato($r['vigente_desde'])) ?>">Eliminar</button>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal crear/editar -->
<div class="modal fade" id="modalPrecio" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" method="post" action="<?= MARINA_URL ?>/index.php?p=combustible-precios">
      <div class="modal-header">
        <h5 class="modal-title" id="modalPrecioTitulo"><?= $accion === 'editar' ? 'Editar precio' : 'Nuevo precio' ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="accion" id="inputPrecioAccion" value="<?= e($accion === 'editar' ? 'editar' : 'crear') ?>">
        <input type="hidden" name="id" id="inputPrecioId" value="<?= ($accion === 'editar' && $id > 0) ? (int) $id : '' ?>">
        <div class="mb-2">
            <label class="form-label">Tipo</label>
            <select class="form-select" name="tipo_combustible" id="precioTipo" required>
                <?php foreach (MARINA_COMB_TIPOS as $k => $lab): ?>
                    <option value="<?= e($k) ?>" <?= ($modalDatos['tipo_combustible'] ?? '') === $k ? 'selected' : '' ?>><?= e($lab) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-2">
            <label class="form-label">Vigente desde</label>
            <input type="date" class="form-control" name="vigente_desde" id="precioDesde" value="<?= e($modalDatos['vigente_desde'] ?? '') ?>" required>
        </div>
        <div class="mb-2">
            <label class="form-label">Precio compra / galón</label>
            <input type="text" class="form-control" name="precio_compra_galon" id="precioCompra" value="<?= e((string) ($modalDatos['precio_compra_galon'] ?? '')) ?>" inputmode="decimal">
        </div>
        <div class="mb-2">
            <label class="form-label">Precio venta / galón</label>
            <input type="text" class="form-control" name="precio_venta_galon" id="precioVenta" value="<?= e((string) ($modalDatos['precio_venta_galon'] ?? '')) ?>" inputmode="decimal">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-primary">Guardar</button>
      </div>
    </form>
  </div>
</div>

<div class="modal fade" id="modalEliminarPrecio" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" method="post" action="<?= MARINA_URL ?>/index.php?p=combustible-precios">
      <div class="modal-header">
        <h5 class="modal-title">Eliminar precio</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="accion" value="eliminar">
        <input type="hidden" name="id" id="elimPrecioId" value="">
        <p class="mb-0" id="elimPrecioTexto">¿Eliminar este registro de precio?</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-danger">Eliminar</button>
      </div>
    </form>
  </div>
</div>

<script>
(function(){
  const mostrarAlCargar = <?= $mostrarModal ? 'true' : 'false' ?>;

  function initCombustiblePrecios() {
    if (!window.bootstrap) return;
    const modalPrecioEl = document.getElementById('modalPrecio');
    function showModalPrecio() {
      if (modalPrecioEl) bootstrap.Modal.getOrCreateInstance(modalPrecioEl).show();
    }

    document.getElementById('btnNuevoPrecio')?.addEventListener('click', function() {
      document.getElementById('modalPrecioTitulo').textContent = 'Nuevo precio';
      document.getElementById('inputPrecioAccion').value = 'crear';
      document.getElementById('inputPrecioId').value = '';
      document.getElementById('precioTipo').value = 'diesel';
      document.getElementById('precioDesde').value = '<?= date('Y-m-d') ?>';
      document.getElementById('precioCompra').value = '';
      document.getElementById('precioVenta').value = '';
      showModalPrecio();
    });
    document.querySelectorAll('.btn-editar-precio').forEach(function(btn) {
      btn.addEventListener('click', function() {
        let d = {};
        try { d = JSON.parse(btn.getAttribute('data-precio') || '{}'); } catch (e) {}
        document.getElementById('modalPrecioTitulo').textContent = 'Editar precio';
        document.getElementById('inputPrecioAccion').value = 'editar';
        document.getElementById('inputPrecioId').value = d.id || '';
        document.getElementById('precioTipo').value = d.tipo_combustible || 'diesel';
        document.getElementById('precioDesde').value = d.vigente_desde || '';
        document.getElementById('precioCompra').value = d.precio_compra_galon || '';
        document.getElementById('precioVenta').value = d.precio_venta_galon || '';
        showModalPrecio();
      });
    });
    document.getElementById('modalEliminarPrecio')?.addEventListener('show.bs.modal', function(ev) {
      const t = ev.relatedTarget;
      document.getElementById('elimPrecioId').value = t?.getAttribute?.('data-id') || '';
      const lb = t?.getAttribute?.('data-label') || '';
      document.getElementById('elimPrecioTexto').textContent = lb
        ? ('¿Eliminar el precio «' + lb + '»?')
        : '¿Eliminar este registro de precio?';
    });

    if (mostrarAlCargar) showModalPrecio();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initCombustiblePrecios);
  } else {
    initCombustiblePrecios();
  }
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
