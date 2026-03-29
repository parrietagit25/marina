<?php
/**
 * Despacho de combustible — ingreso para la marina (por cuenta).
 */
$titulo = 'Combustible — Despacho';
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
                $pdo->prepare('DELETE FROM combustible_despachos WHERE id = ?')->execute([$id]);
                redirigir(MARINA_URL . '/index.php?p=combustible-despacho&ok=' . rawurlencode('Despacho eliminado'));
            } catch (Throwable $e) {
                redirigir(MARINA_URL . '/index.php?p=combustible-despacho&err=' . rawurlencode(marinaMensajeErrorIntegridad($e)));
            }
        }
    }
    if ($postAccion === 'guardar') {
        $id = (int) ($_POST['id'] ?? 0);
        $tipo = strtolower(trim((string) ($_POST['tipo_combustible'] ?? '')));
        $fecha = trim((string) ($_POST['fecha'] ?? ''));
        $emb = trim((string) ($_POST['embarcacion'] ?? ''));
        $gls = (float) str_replace(',', '.', (string) ($_POST['gls'] ?? 0));
        $monto = (float) str_replace(',', '.', (string) ($_POST['monto_total'] ?? 0));
        $cuenta_id = (int) ($_POST['cuenta_id'] ?? 0);
        if (!isset(MARINA_COMB_TIPOS[$tipo]) || $fecha === '' || $emb === '' || $gls <= 0 || $monto < 0 || $cuenta_id < 1) {
            $mensaje = 'Complete tipo, fecha, embarcación, GLS, cuenta y monto válidos.';
        } else {
            try {
                if ($id > 0) {
                    $pdo->prepare('UPDATE combustible_despachos SET tipo_combustible=?, fecha=?, embarcacion=?, gls=?, monto_total=?, cuenta_id=?, updated_by=? WHERE id=?')
                        ->execute([$tipo, $fecha, $emb, $gls, $monto, $cuenta_id, $uid, $id]);
                    redirigir(MARINA_URL . '/index.php?p=combustible-despacho&ok=' . rawurlencode('Despacho actualizado'));
                } else {
                    $pdo->prepare('INSERT INTO combustible_despachos (tipo_combustible, fecha, embarcacion, gls, monto_total, cuenta_id, created_by, updated_by) VALUES (?,?,?,?,?,?,?,?)')
                        ->execute([$tipo, $fecha, $emb, $gls, $monto, $cuenta_id, $uid, $uid]);
                    redirigir(MARINA_URL . '/index.php?p=combustible-despacho&ok=' . rawurlencode('Despacho registrado'));
                }
            } catch (Throwable $e) {
                $mensaje = 'No se pudo guardar.';
            }
        }
    }
}

$preciosJson = json_encode(marina_combustible_precios_vigentes($pdo), JSON_UNESCAPED_UNICODE);
$inv = marina_combustible_inventario_por_tipo($pdo);
$cuentas = $pdo->query('SELECT c.id, CONCAT(b.nombre, " - ", c.nombre) AS nom FROM cuentas c JOIN bancos b ON c.banco_id = b.id ORDER BY b.nombre, c.nombre')->fetchAll(PDO::FETCH_KEY_PAIR);

$edit = null;
$ui = trim((string) obtener('ui', ''));
$editId = (int) obtener('id', 0);
if ($ui === 'editar' && $editId > 0) {
    $st = $pdo->prepare('SELECT * FROM combustible_despachos WHERE id = ?');
    $st->execute([$editId]);
    $edit = $st->fetch(PDO::FETCH_ASSOC);
    if (!$edit) {
        redirigir(MARINA_URL . '/index.php?p=combustible-despacho');
    }
}

$ok = obtener('ok');
$err = obtener('err');
$abrirModal = ($ui === 'nuevo') || ($edit !== null) || (enviado() && ($_POST['marina_comb_accion'] ?? '') === 'guardar' && $mensaje !== '');

require_once __DIR__ . '/../includes/layout.php';
?>
<h1 class="h4 mb-3">Combustible — Despacho</h1>
<p class="text-muted small">El ingreso se refleja en reportes de ingresos y en la cuenta seleccionada. La cuenta es obligatoria.</p>

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
</div>

<div class="toolbar d-flex flex-wrap gap-2 mb-3">
    <button type="button" class="btn btn-primary" id="btnNuevoDespacho">Nuevo despacho</button>
    <a class="btn btn-outline-secondary" href="<?= MARINA_URL ?>/index.php?p=combustible-pedidos">Pedidos</a>
    <a class="btn btn-outline-secondary" href="<?= MARINA_URL ?>/index.php?p=combustible-precios">Precio por galón</a>
</div>

<div class="card p-0">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr><th>Id</th><th>Fecha</th><th>Tipo</th><th>Embarcación</th><th class="text-end">GLS</th><th class="text-end">Monto</th><th>Cuenta</th><th></th></tr>
            </thead>
            <tbody>
            <?php
            $st = $pdo->query("
                SELECT d.*, CONCAT(b.nombre, ' - ', c.nombre) AS cuenta_nom
                FROM combustible_despachos d
                JOIN cuentas c ON c.id = d.cuenta_id
                JOIN bancos b ON b.id = c.banco_id
                ORDER BY d.fecha DESC, d.id DESC
            ");
            while ($r = $st->fetch(PDO::FETCH_ASSOC)): ?>
                <tr>
                    <td><?= (int) $r['id'] ?></td>
                    <td><?= fechaFormato($r['fecha']) ?></td>
                    <td><?= e(MARINA_COMB_TIPOS[$r['tipo_combustible']] ?? $r['tipo_combustible']) ?></td>
                    <td><?= e($r['embarcacion']) ?></td>
                    <td class="text-end"><?= e((string) $r['gls']) ?></td>
                    <td class="text-end"><?= dinero((float) $r['monto_total']) ?></td>
                    <td><?= e($r['cuenta_nom']) ?></td>
                    <td class="text-nowrap">
                        <button type="button" class="btn btn-sm btn-secondary btn-editar-despacho"
                            data-despacho="<?= htmlspecialchars(json_encode([
                                'id' => (int) $r['id'],
                                'tipo_combustible' => $r['tipo_combustible'],
                                'fecha' => $r['fecha'],
                                'embarcacion' => $r['embarcacion'],
                                'gls' => (string) $r['gls'],
                                'monto_total' => (string) $r['monto_total'],
                                'cuenta_id' => (int) $r['cuenta_id'],
                            ], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>">Editar</button>
                        <button type="button" class="btn btn-sm btn-danger btn-eliminar-despacho"
                            data-bs-toggle="modal" data-bs-target="#modalEliminarDespacho"
                            data-id="<?= (int) $r['id'] ?>"
                            data-resumen="<?= e($r['embarcacion'] . ' — ' . fechaFormato($r['fecha'])) ?>">Eliminar</button>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="modalDespacho" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" method="post" action="<?= MARINA_URL ?>/index.php?p=combustible-despacho">
      <div class="modal-header">
        <h5 class="modal-title" id="modalDespachoTitulo"><?= $edit ? 'Editar despacho' : 'Nuevo despacho' ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="marina_comb_accion" value="guardar">
        <?php
        $despIdModal = $edit ? (int) $edit['id'] : (int) ($_POST['id'] ?? 0);
        ?>
        <input type="hidden" name="id" id="inputDespachoId" value="<?= $despIdModal > 0 ? $despIdModal : '' ?>">
        <div class="mb-2">
            <label class="form-label">Tipo</label>
            <select class="form-select" name="tipo_combustible" id="dTipo" required>
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
            <label class="form-label">Embarcación</label>
            <input type="text" class="form-control" name="embarcacion" required value="<?= e($edit['embarcacion'] ?? $_POST['embarcacion'] ?? '') ?>">
        </div>
        <div class="mb-2">
            <label class="form-label">GLS</label>
            <input type="text" class="form-control" name="gls" id="dGls" inputmode="decimal" required value="<?= e((string) ($edit['gls'] ?? $_POST['gls'] ?? '')) ?>">
        </div>
        <div class="mb-2">
            <label class="form-label">Monto total</label>
            <input type="text" class="form-control" name="monto_total" id="dMonto" inputmode="decimal" required value="<?= e((string) ($edit['monto_total'] ?? $_POST['monto_total'] ?? '')) ?>">
            <div class="form-text small">Se calcula solo al escribir <strong>GLS</strong> o al cambiar el <strong>tipo</strong> (precio venta vigente × GLS).</div>
            <button type="button" class="btn btn-link btn-sm p-0 mt-1" id="btnCalcVenta">Volver a calcular</button>
        </div>
        <div class="mb-2">
            <label class="form-label">Cuenta (acreditación)</label>
            <select class="form-select" name="cuenta_id" required>
                <option value="0">— Seleccione —</option>
                <?php foreach ($cuentas as $cid => $nom): ?>
                    <option value="<?= (int) $cid ?>" <?= (int) ($edit['cuenta_id'] ?? $_POST['cuenta_id'] ?? 0) === (int) $cid ? 'selected' : '' ?>><?= e($nom) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-primary">Guardar</button>
      </div>
    </form>
  </div>
</div>

<div class="modal fade" id="modalEliminarDespacho" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" method="post" action="<?= MARINA_URL ?>/index.php?p=combustible-despacho">
      <div class="modal-header">
        <h5 class="modal-title">Eliminar despacho</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="marina_comb_accion" value="eliminar">
        <input type="hidden" name="id" id="elimDespachoId" value="">
        <p class="mb-0" id="elimDespachoTexto">¿Eliminar este despacho? Dejará de contar como ingreso en reportes.</p>
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
  const precios = <?= $preciosJson ?>;
  const abrirAlCargar = <?= $abrirModal ? 'true' : 'false' ?>;

  function initCombustibleDespacho() {
    if (!window.bootstrap) return;
    const modalDespachoEl = document.getElementById('modalDespacho');
    function showModalDespacho() {
      if (modalDespachoEl) bootstrap.Modal.getOrCreateInstance(modalDespachoEl).show();
    }

    function precioVenta(tipo) {
      const p = precios[tipo] || {};
      return parseFloat(p.venta) || 0;
    }
    function aplicarMontoAutomaticoDespacho() {
      const tipo = document.getElementById('dTipo')?.value || 'diesel';
      const gls = parseFloat(String(document.getElementById('dGls')?.value || '').replace(',', '.')) || 0;
      const unit = precioVenta(tipo);
      const el = document.getElementById('dMonto');
      if (!el) return;
      if (gls > 0) {
        const tot = Math.round(gls * unit * 100) / 100;
        el.value = String(tot);
      } else {
        el.value = '0';
      }
    }
    document.getElementById('btnCalcVenta')?.addEventListener('click', aplicarMontoAutomaticoDespacho);
    document.getElementById('dTipo')?.addEventListener('change', aplicarMontoAutomaticoDespacho);
    document.getElementById('dGls')?.addEventListener('input', aplicarMontoAutomaticoDespacho);
    document.getElementById('dGls')?.addEventListener('change', aplicarMontoAutomaticoDespacho);

    function setV(id, v) {
      const el = document.getElementById(id);
      if (el) el.value = v != null ? String(v) : '';
    }
    document.getElementById('btnNuevoDespacho')?.addEventListener('click', function() {
      document.getElementById('modalDespachoTitulo').textContent = 'Nuevo despacho';
      setV('inputDespachoId', '');
      setV('dTipo', 'diesel');
      const fe = document.querySelector('#modalDespacho [name=fecha]');
      if (fe) fe.value = '<?= date('Y-m-d') ?>';
      const em = document.querySelector('#modalDespacho [name=embarcacion]');
      if (em) em.value = '';
      setV('dGls', '');
      setV('dMonto', '0');
      const cu = document.querySelector('#modalDespacho select[name=cuenta_id]');
      if (cu) cu.value = '0';
      showModalDespacho();
    });
    document.querySelectorAll('.btn-editar-despacho').forEach(function(btn) {
      btn.addEventListener('click', function() {
        let d = {};
        try { d = JSON.parse(btn.getAttribute('data-despacho') || '{}'); } catch (e) {}
        document.getElementById('modalDespachoTitulo').textContent = 'Editar despacho';
        setV('inputDespachoId', d.id || '');
        setV('dTipo', d.tipo_combustible || 'diesel');
        const fe2 = document.querySelector('#modalDespacho [name=fecha]');
        if (fe2) fe2.value = d.fecha || '';
        const em2 = document.querySelector('#modalDespacho [name=embarcacion]');
        if (em2) em2.value = d.embarcacion || '';
        setV('dGls', d.gls || '');
        setV('dMonto', d.monto_total || '');
        const cu2 = document.querySelector('#modalDespacho select[name=cuenta_id]');
        if (cu2) cu2.value = String(d.cuenta_id || '0');
        showModalDespacho();
      });
    });
    document.getElementById('modalEliminarDespacho')?.addEventListener('show.bs.modal', function(ev) {
      const t = ev.relatedTarget;
      document.getElementById('elimDespachoId').value = t?.getAttribute?.('data-id') || '';
      const r = t?.getAttribute?.('data-resumen') || '';
      document.getElementById('elimDespachoTexto').textContent = r
        ? ('¿Eliminar el despacho «' + r + '»? Dejará de contar como ingreso en reportes.')
        : '¿Eliminar este despacho?';
    });

    if (abrirAlCargar) showModalDespacho();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initCombustibleDespacho);
  } else {
    initCombustibleDespacho();
  }
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
