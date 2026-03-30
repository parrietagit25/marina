<?php
/**
 * Ajustes de inventario de combustible (entradas/salidas de GLS sin compra ni venta).
 */
$titulo = 'Combustible — Ajuste';
$pdo = getDb();
require_once __DIR__ . '/../includes/combustible_helpers.php';

$uid = usuarioId();
$mensaje = '';

if (enviado()) {
    $postAccion = trim((string) ($_POST['marina_comb_accion'] ?? ''));
    if ($postAccion === 'eliminar') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $pdo->prepare('DELETE FROM combustible_ajustes WHERE id = ?')->execute([$id]);
                redirigir(MARINA_URL . '/index.php?p=combustible-ajuste&ok=' . rawurlencode('Ajuste eliminado'));
            } catch (Throwable $e) {
                redirigir(MARINA_URL . '/index.php?p=combustible-ajuste&err=' . rawurlencode(marinaMensajeErrorIntegridad($e)));
            }
        }
    }
    if ($postAccion === 'guardar') {
        $id = (int) ($_POST['id'] ?? 0);
        $tipo = strtolower(trim((string) ($_POST['tipo_combustible'] ?? '')));
        $fecha = trim((string) ($_POST['fecha'] ?? ''));
        $sentido = trim((string) ($_POST['sentido'] ?? ''));
        $gls = (float) str_replace(',', '.', (string) ($_POST['gls'] ?? 0));
        $motivo = trim((string) ($_POST['motivo'] ?? ''));
        if (!isset(MARINA_COMB_TIPOS[$tipo]) || $fecha === '' || $gls <= 0 || ($sentido !== 'entrada' && $sentido !== 'salida')) {
            $mensaje = 'Indique tipo, fecha, sentido y una cantidad de GLS mayor que cero.';
        } else {
            $delta = $sentido === 'entrada' ? $gls : -$gls;
            $disp = marina_combustible_inventario_efectivo_para_ajuste($pdo, $tipo, $id > 0 ? $id : null);
            if ($delta < 0 && $disp + 0.0001 < $gls) {
                $mensaje = 'No hay inventario suficiente para esta salida. Disponible (sin contar este ajuste): ' . number_format($disp, 3, '.', ',') . ' gal.';
            } else {
                try {
                    if ($id > 0) {
                        $pdo->prepare('UPDATE combustible_ajustes SET tipo_combustible=?, fecha=?, gls_delta=?, motivo=?, updated_by=? WHERE id=?')
                            ->execute([$tipo, $fecha, $delta, $motivo !== '' ? $motivo : null, $uid, $id]);
                        redirigir(MARINA_URL . '/index.php?p=combustible-ajuste&ok=' . rawurlencode('Ajuste actualizado'));
                    } else {
                        $pdo->prepare('INSERT INTO combustible_ajustes (tipo_combustible, fecha, gls_delta, motivo, created_by, updated_by) VALUES (?,?,?,?,?,?)')
                            ->execute([$tipo, $fecha, $delta, $motivo !== '' ? $motivo : null, $uid, $uid]);
                        redirigir(MARINA_URL . '/index.php?p=combustible-ajuste&ok=' . rawurlencode('Ajuste registrado'));
                    }
                } catch (Throwable $e) {
                    $mensaje = 'No se pudo guardar el ajuste.';
                }
            }
        }
    }
}

$inv = marina_combustible_inventario_por_tipo($pdo);

$edit = null;
$ui = trim((string) obtener('ui', ''));
$editId = (int) obtener('id', 0);
if ($ui === 'editar' && $editId > 0) {
    try {
        $st = $pdo->prepare('SELECT * FROM combustible_ajustes WHERE id = ?');
        $st->execute([$editId]);
        $edit = $st->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $edit = null;
    }
    if (!$edit) {
        redirigir(MARINA_URL . '/index.php?p=combustible-ajuste');
    }
}

$ok = obtener('ok');
$err = obtener('err');
$abrirModal = ($ui === 'nuevo') || ($edit !== null) || (enviado() && ($_POST['marina_comb_accion'] ?? '') === 'guardar' && $mensaje !== '');

$lista = [];
try {
    $lista = $pdo->query('SELECT * FROM combustible_ajustes ORDER BY fecha DESC, id DESC')->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $lista = [];
}

require_once __DIR__ . '/../includes/layout.php';
?>
<h1 class="h4 mb-3">Combustible — Ajuste de inventario</h1>
<p class="text-muted small">Registre correcciones de inventario (mermas, sobrantes, mediciones, etc.). No genera ingresos ni egresos contables; solo modifica el cálculo de GLS en depósito.</p>

<?php if ($ok): ?><div class="alert alert-success py-2"><?= e($ok) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-danger py-2"><?= e($err) ?></div><?php endif; ?>
<?php if ($mensaje !== ''): ?><div class="alert alert-warning py-2"><?= e($mensaje) ?></div><?php endif; ?>

<div class="card p-3 mb-3">
    <h2 class="h6 mb-2">Inventario (GLS)</h2>
    <div class="row g-2 small">
        <?php foreach (MARINA_COMB_TIPOS as $k => $lab): ?>
            <div class="col-md-6"><strong><?= e($lab) ?>:</strong> <?= number_format($inv[$k] ?? 0, 3, '.', ',') ?> gal</div>
        <?php endforeach; ?>
    </div>
    <p class="text-muted small mb-0 mt-2">Inventario = GLS recibidos en pedidos − despachos + suma de <strong>ajustes</strong> (entradas suman, salidas restan).</p>
</div>

<div class="toolbar d-flex flex-wrap gap-2 mb-3">
    <button type="button" class="btn btn-primary" id="btnNuevoAjuste">Nuevo ajuste</button>
    <a class="btn btn-outline-secondary" href="<?= MARINA_URL ?>/index.php?p=combustible-pedidos">Pedidos</a>
    <a class="btn btn-outline-secondary" href="<?= MARINA_URL ?>/index.php?p=combustible-despacho">Despacho</a>
    <a class="btn btn-outline-secondary" href="<?= MARINA_URL ?>/index.php?p=combustible-precios">Precio por galón</a>
</div>

<div class="card p-0">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 no-datatable">
            <thead>
                <tr>
                    <th>Id</th>
                    <th>Fecha</th>
                    <th>Tipo</th>
                    <th class="text-end">GLS (Δ)</th>
                    <th>Motivo</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($lista as $r): ?>
                <?php
                $d = (float) $r['gls_delta'];
                $cls = $d >= 0 ? 'text-success' : 'text-danger';
                $sign = $d >= 0 ? '+' : '';
                ?>
                <tr>
                    <td><?= (int) $r['id'] ?></td>
                    <td><?= fechaFormato($r['fecha']) ?></td>
                    <td><?= e(MARINA_COMB_TIPOS[$r['tipo_combustible']] ?? $r['tipo_combustible']) ?></td>
                    <td class="text-end fw-semibold <?= $cls ?>"><?= $sign ?><?= e((string) $r['gls_delta']) ?></td>
                    <td class="small"><?= e($r['motivo'] ?? '') ?: '—' ?></td>
                    <td class="text-nowrap">
                        <!--<button type="button" class="btn btn-sm btn-secondary btn-editar-ajuste"
                            data-ajuste="<?= htmlspecialchars(json_encode([
                                'id' => (int) $r['id'],
                                'tipo_combustible' => $r['tipo_combustible'],
                                'fecha' => $r['fecha'],
                                'gls_delta' => (float) $r['gls_delta'],
                                'motivo' => (string) ($r['motivo'] ?? ''),
                            ], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>">Editar</button>
                        <button type="button" class="btn btn-sm btn-danger btn-eliminar-ajuste"
                            data-id="<?= (int) $r['id'] ?>"
                            data-resumen="<?= e(fechaFormato($r['fecha']) . ' — ' . ($sign . $r['gls_delta']) . ' gal') ?>">Eliminar</button>
                    -->
                          </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php if ($lista === []): ?>
            <p class="text-muted small p-3 mb-0">No hay ajustes registrados.</p>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="modalAjuste" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" method="post" action="<?= MARINA_URL ?>/index.php?p=combustible-ajuste">
      <div class="modal-header">
        <h5 class="modal-title" id="modalAjusteTitulo"><?= $edit ? 'Editar ajuste' : 'Nuevo ajuste' ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="marina_comb_accion" value="guardar">
        <?php
        $ajIdModal = $edit ? (int) $edit['id'] : (int) ($_POST['id'] ?? 0);
        $edGls = $edit ? abs((float) $edit['gls_delta']) : (float) str_replace(',', '.', (string) ($_POST['gls'] ?? 0));
        $edSent = $edit ? (((float) $edit['gls_delta'] >= 0) ? 'entrada' : 'salida') : ($_POST['sentido'] ?? 'entrada');
        ?>
        <input type="hidden" name="id" id="inputAjusteId" value="<?= $ajIdModal > 0 ? $ajIdModal : '' ?>">
        <div class="mb-2">
            <label class="form-label">Tipo de combustible</label>
            <select class="form-select" name="tipo_combustible" id="aTipo" required>
                <?php foreach (MARINA_COMB_TIPOS as $k => $lab): ?>
                    <option value="<?= e($k) ?>" <?= (($edit['tipo_combustible'] ?? $_POST['tipo_combustible'] ?? 'diesel') === $k) ? 'selected' : '' ?>><?= e($lab) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-2">
            <label class="form-label">Fecha</label>
            <input type="date" class="form-control" name="fecha" required value="<?= e($edit['fecha'] ?? $_POST['fecha'] ?? date('Y-m-d')) ?>">
        </div>
        <div class="mb-2">
            <label class="form-label d-block">Operación</label>
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="sentido" id="aEntrada" value="entrada" <?= $edSent === 'entrada' ? 'checked' : '' ?> required>
                <label class="form-check-label" for="aEntrada">Agregar al inventario</label>
            </div>
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="sentido" id="aSalida" value="salida" <?= $edSent === 'salida' ? 'checked' : '' ?>>
                <label class="form-check-label" for="aSalida">Quitar del inventario</label>
            </div>
        </div>
        <div class="mb-2">
            <label class="form-label">Galones (cantidad positiva)</label>
            <input type="text" class="form-control" name="gls" id="aGls" inputmode="decimal" required value="<?= $edGls > 0 ? e((string) $edGls) : e((string) ($_POST['gls'] ?? '')) ?>">
        </div>
        <div class="mb-2">
            <label class="form-label">Motivo / nota</label>
            <textarea class="form-control" name="motivo" rows="2" placeholder="Ej.: merma por evaporación, inventario físico, corrección…"><?= e($edit['motivo'] ?? $_POST['motivo'] ?? '') ?></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-primary">Guardar</button>
      </div>
    </form>
  </div>
</div>

<div class="modal fade" id="modalEliminarAjuste" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" method="post" action="<?= MARINA_URL ?>/index.php?p=combustible-ajuste">
      <div class="modal-header">
        <h5 class="modal-title">Eliminar ajuste</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="marina_comb_accion" value="eliminar">
        <input type="hidden" name="id" id="elimAjusteId" value="">
        <p class="mb-0" id="elimAjusteTexto">¿Eliminar este ajuste? El inventario se recalculará.</p>
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
  const abrirAlCargar = <?= $abrirModal ? 'true' : 'false' ?>;
  let inicializado = false;

  function initCombustibleAjuste() {
    if (inicializado) return;
    if (!window.bootstrap) return;
    inicializado = true;

    const modalEl = document.getElementById('modalAjuste');
    const modalElimEl = document.getElementById('modalEliminarAjuste');

    function showModalAjuste() {
      if (modalEl) bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }

    function setV(id, v) {
      const el = document.getElementById(id);
      if (el) el.value = v != null ? String(v) : '';
    }

    document.getElementById('btnNuevoAjuste')?.addEventListener('click', function() {
      document.getElementById('modalAjusteTitulo').textContent = 'Nuevo ajuste';
      setV('inputAjusteId', '');
      const t = document.getElementById('aTipo');
      if (t) t.value = 'diesel';
      const fe = document.querySelector('#modalAjuste [name=fecha]');
      if (fe) fe.value = '<?= date('Y-m-d') ?>';
      document.getElementById('aEntrada').checked = true;
      setV('aGls', '');
      const mo = document.querySelector('#modalAjuste [name=motivo]');
      if (mo) mo.value = '';
      showModalAjuste();
    });

    document.addEventListener('click', function(ev) {
      const ed = ev.target.closest('.btn-editar-ajuste');
      if (ed) {
        ev.preventDefault();
        let d = {};
        try {
          var raw = ed.getAttribute('data-ajuste');
          if (raw) d = JSON.parse(raw);
        } catch (e) {}
        document.getElementById('modalAjusteTitulo').textContent = 'Editar ajuste';
        setV('inputAjusteId', d.id || '');
        const t = document.getElementById('aTipo');
        if (t) t.value = d.tipo_combustible || 'diesel';
        const fe = document.querySelector('#modalAjuste [name=fecha]');
        if (fe) fe.value = d.fecha || '';
        var delta = parseFloat(d.gls_delta);
        if (isNaN(delta)) delta = 0;
        if (delta >= 0) {
          document.getElementById('aEntrada').checked = true;
        } else {
          document.getElementById('aSalida').checked = true;
        }
        setV('aGls', String(Math.abs(delta)));
        const mo = document.querySelector('#modalAjuste [name=motivo]');
        if (mo) mo.value = d.motivo || '';
        showModalAjuste();
        return;
      }

      const del = ev.target.closest('.btn-eliminar-ajuste');
      if (del) {
        ev.preventDefault();
        var eid = del.getAttribute('data-id') || '';
        var res = del.getAttribute('data-resumen') || '';
        setV('elimAjusteId', eid);
        document.getElementById('elimAjusteTexto').textContent = res
          ? ('¿Eliminar el ajuste «' + res + '»? El inventario se actualizará.')
          : '¿Eliminar este ajuste?';
        if (modalElimEl) bootstrap.Modal.getOrCreateInstance(modalElimEl).show();
      }
    });

    if (abrirAlCargar) showModalAjuste();
  }

  function intentarInit() {
    initCombustibleAjuste();
  }

  window.addEventListener('load', intentarInit);
  if (document.readyState === 'complete') {
    intentarInit();
  } else {
    document.addEventListener('DOMContentLoaded', function() {
      intentarInit();
    });
  }
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
