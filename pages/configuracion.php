<?php
/**
 * Configuración del sistema: tamaño de fuente global.
 */
$titulo = 'Configuración';

$pdo = getDb();
$mensaje = '';
$mensajeTipo = '';

if (enviado()) {
    $pct = (int) ($_POST['font_size_percent'] ?? 100);
    $pct = max(80, min(125, $pct));
    $pct = (int) (round($pct / 5) * 5);
    try {
        $st = $pdo->prepare('INSERT INTO marina_config (clave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)');
        $st->execute(['font_size_percent', (string) $pct]);
        redirigir(MARINA_URL . '/index.php?p=configuracion&ok=' . rawurlencode('Tamaño de texto guardado. Recargue otras pestañas si las tenía abiertas.'));
    } catch (Throwable $e) {
        $mensaje = 'No se pudo guardar. Intente de nuevo.';
        $mensajeTipo = 'danger';
    }
}

if (isset($_GET['ok']) && is_string($_GET['ok']) && $_GET['ok'] !== '') {
    $mensaje = (string) $_GET['ok'];
    $mensajeTipo = 'success';
}

$fontPct = marina_config_font_size_percent($pdo);

require_once __DIR__ . '/../includes/layout.php';
?>
<h1 class="h4 mb-3">Configuración</h1>

<?php if ($mensaje !== ''): ?>
  <div class="alert alert-<?= e($mensajeTipo ?: 'info') ?> alert-dismissible fade show" role="alert">
    <?= e($mensaje) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
  </div>
<?php endif; ?>

<div class="card shadow-sm border-0" style="max-width: 36rem;">
  <div class="card-body">
    <h2 class="h6 text-muted mb-3">Tamaño del texto en todo el sistema</h2>
    <p class="small text-muted mb-3">
      <strong>100&nbsp;%</strong> es el tamaño habitual (como hasta ahora). Puede bajarlo para ver más contenido en pantalla o subirlo para leer más cómodo.
    </p>

    <form method="post" action="<?= MARINA_URL ?>/index.php?p=configuracion" id="form-font-size">
      <div class="mb-3">
        <label for="font_size_percent" class="form-label d-flex justify-content-between align-items-center">
          <span>Escala</span>
          <span class="badge bg-secondary" id="font-pct-label"><?= (int) $fontPct ?> %</span>
        </label>
        <input type="range" class="form-range" name="font_size_percent" id="font_size_percent"
          min="80" max="125" step="5" value="<?= (int) $fontPct ?>"
          aria-describedby="font-preview-help">
        <div class="d-flex justify-content-between small text-muted" id="font-preview-help">
          <span>80&nbsp;% (más pequeño)</span>
          <span>125&nbsp;% (más grande)</span>
        </div>
      </div>

      <div class="border rounded-3 p-3 mb-3 bg-light" style="font-size: 16px;">
        <div id="font-preview-box" style="font-size: calc(1em * <?= (int) $fontPct ?> / 100); line-height: 1.5;">
          <p class="mb-1 fw-semibold">Vista previa</p>
          <p class="mb-0 small">Texto de ejemplo: listados, formularios y menús usarán esta escala respecto al tamaño base del sistema (100&nbsp;% = como hasta ahora).</p>
        </div>
      </div>

      <button type="submit" class="btn btn-primary">Guardar</button>
    </form>
  </div>
</div>

<script>
(function () {
  var r = document.getElementById('font_size_percent');
  var label = document.getElementById('font-pct-label');
  var box = document.getElementById('font-preview-box');
  if (!r || !label || !box) return;
  r.addEventListener('input', function () {
    var v = r.value;
    label.textContent = v + ' %';
    box.style.fontSize = 'calc(1em * ' + v + ' / 100)';
  });
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
