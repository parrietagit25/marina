<?php
/**
 * Configuración del sistema: tamaño de fuente y Resend (marketing).
 */
$titulo = 'Configuración';

$pdo = getDb();
require_once __DIR__ . '/../includes/marketing_helpers.php';
require_once __DIR__ . '/../includes/cron_helpers.php';
$mensaje = '';
$mensajeTipo = '';

if (enviado()) {
    $accion = trim((string) ($_POST['accion'] ?? 'font'));
    try {
        if ($accion === 'resend') {
            $apiKey = trim((string) ($_POST['resend_api_key'] ?? ''));
            $from = trim((string) ($_POST['resend_from_email'] ?? ''));
            marina_config_guardar($pdo, 'resend_api_key', $apiKey);
            marina_config_guardar($pdo, 'resend_from_email', $from);
            redirigir(MARINA_URL . '/index.php?p=configuracion&ok=' . rawurlencode('Configuración de correo (Resend) guardada.'));
        } elseif ($accion === 'cron') {
            $token = trim((string) ($_POST['cron_token'] ?? ''));
            if ($token === '') {
                $token = marina_cron_generar_token();
            }
            marina_config_guardar($pdo, 'cron_token', $token);
            redirigir(MARINA_URL . '/index.php?p=configuracion&ok=' . rawurlencode('Token de cron guardado.'));
        } elseif ($accion === 'generar_cron_token') {
            marina_config_guardar($pdo, 'cron_token', marina_cron_generar_token());
            redirigir(MARINA_URL . '/index.php?p=configuracion&ok=' . rawurlencode('Nuevo token de cron generado.'));
        } else {
            $pct = (int) ($_POST['font_size_percent'] ?? 100);
            $pct = max(80, min(125, $pct));
            $pct = (int) (round($pct / 5) * 5);
            marina_config_guardar($pdo, 'font_size_percent', (string) $pct);
            redirigir(MARINA_URL . '/index.php?p=configuracion&ok=' . rawurlencode('Tamaño de texto guardado. Recargue otras pestañas si las tenía abiertas.'));
        }
    } catch (Throwable $e) {
        $mensaje = 'No se pudo guardar. Intente de nuevo.';
        $mensajeTipo = 'danger';
    }
}

if (isset($_GET['ok']) && is_string($_GET['ok']) && $_GET['ok'] !== '') {
    $mensaje = (string) $_GET['ok'];
    $mensajeTipo = 'success';
}
if (isset($_GET['err']) && is_string($_GET['err']) && $_GET['err'] !== '') {
    $mensaje = (string) $_GET['err'];
    $mensajeTipo = 'danger';
}

$fontPct = marina_config_font_size_percent($pdo);
$resendCfg = marina_marketing_resend_config($pdo);
$cronToken = marina_cron_token($pdo);
$cronRuta = marina_cron_ruta_proyecto();
$cronUrlBase = (isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] !== '')
    ? (((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . (MARINA_URL !== '' ? MARINA_URL : ''))
    : 'https://tudominio.com' . (MARINA_URL !== '' ? MARINA_URL : '');

require_once __DIR__ . '/../includes/layout.php';
?>
<h1 class="h4 mb-3">Configuración</h1>

<?php if ($mensaje !== ''): ?>
  <div class="alert alert-<?= e($mensajeTipo ?: 'info') ?> alert-dismissible fade show" role="alert">
    <?= e($mensaje) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
  </div>
<?php endif; ?>

<div class="row g-3">
<div class="col-12 col-lg-6">
<div class="card shadow-sm border-0 h-100">
  <div class="card-body">
    <h2 class="h6 text-muted mb-3">Tamaño del texto en todo el sistema</h2>
    <p class="small text-muted mb-3">
      <strong>100&nbsp;%</strong> es el tamaño habitual. Puede bajarlo o subirlo según preferencia.
    </p>
    <form method="post" action="<?= MARINA_URL ?>/index.php?p=configuracion" id="form-font-size">
      <input type="hidden" name="accion" value="font">
      <div class="mb-3">
        <label for="font_size_percent" class="form-label d-flex justify-content-between align-items-center">
          <span>Escala</span>
          <span class="badge bg-secondary" id="font-pct-label"><?= (int) $fontPct ?> %</span>
        </label>
        <input type="range" class="form-range" name="font_size_percent" id="font_size_percent"
          min="80" max="125" step="5" value="<?= (int) $fontPct ?>">
      </div>
      <button type="submit" class="btn btn-primary">Guardar</button>
    </form>
  </div>
</div>
</div>

<div class="col-12 col-lg-6">
<div class="card shadow-sm border-0 h-100">
  <div class="card-body">
    <h2 class="h6 text-muted mb-3">Correo — Resend (marketing)</h2>
    <p class="small text-muted mb-3">
      Necesario para enviar campañas desde <a href="<?= MARINA_URL ?>/index.php?p=marketing-plantillas">Plantillas de marketing</a>.
      Obtenga su API key en <a href="https://resend.com" target="_blank" rel="noopener">resend.com</a> y verifique el dominio del remitente.
    </p>
    <form method="post" action="<?= MARINA_URL ?>/index.php?p=configuracion">
      <input type="hidden" name="accion" value="resend">
      <div class="mb-3">
        <label class="form-label">API key Resend</label>
        <input type="password" class="form-control" name="resend_api_key" value="<?= e($resendCfg['api_key']) ?>" autocomplete="off" placeholder="re_...">
      </div>
      <div class="mb-3">
        <label class="form-label">Remitente (from)</label>
        <input type="text" class="form-control" name="resend_from_email" value="<?= e($resendCfg['from_email']) ?>" placeholder="Marina &lt;correo@tudominio.com&gt;">
        <p class="small text-muted mb-0 mt-1">Formato: <code>Nombre &lt;email@dominio.com&gt;</code></p>
      </div>
      <?php if (marina_marketing_resend_configurado($pdo)): ?>
        <p class="small text-success mb-2">Resend configurado correctamente.</p>
      <?php else: ?>
        <p class="small text-warning mb-2">Falta API key o remitente para poder enviar campañas.</p>
      <?php endif; ?>
      <button type="submit" class="btn btn-primary">Guardar Resend</button>
    </form>
  </div>
</div>
</div>

<div class="col-12">
<div class="card shadow-sm border-0">
  <div class="card-body">
    <h2 class="h6 text-muted mb-3">Cron jobs — Namecheap / cPanel</h2>
    <p class="small text-muted mb-3">
      Token para ejecutar tareas por URL. Las <strong>alertas diarias</strong> se configuran en
      <a href="<?= MARINA_URL ?>/index.php?p=alertas">Alertas</a>.
      Las campañas de marketing se envían <strong>manualmente</strong> desde Plantillas.
    </p>
    <form method="post" action="<?= MARINA_URL ?>/index.php?p=configuracion" class="mb-2">
      <input type="hidden" name="accion" value="cron">
      <div class="row g-3 align-items-end">
        <div class="col-md-8">
          <label class="form-label">Token de seguridad (cron por URL)</label>
          <input type="text" class="form-control font-monospace" name="cron_token" value="<?= e($cronToken) ?>" autocomplete="off" placeholder="Se genera al guardar si está vacío">
          <p class="small text-muted mb-0 mt-1">Requerido si usa <code>curl</code> o <code>wget</code> en lugar de PHP por CLI.</p>
        </div>
        <div class="col-md-4">
          <button type="submit" class="btn btn-primary">Guardar token</button>
        </div>
      </div>
    </form>
    <form method="post" action="<?= MARINA_URL ?>/index.php?p=configuracion" class="mb-3">
      <input type="hidden" name="accion" value="generar_cron_token">
      <button type="submit" class="btn btn-sm btn-outline-secondary">Generar token nuevo</button>
    </form>

    <p class="small fw-semibold mb-2">Cron — Alertas diarias (una vez al día, ej. 8:00 a.m.)</p>
    <p class="small text-muted mb-1">Opción A — PHP por CLI:</p>
    <pre class="small bg-light border rounded p-2 mb-2 user-select-all">0 8 * * * /usr/local/bin/php <?= e($cronRuta) ?>/cron/alertas_diarias.php</pre>
    <p class="small text-muted mb-1">Opción B — URL con token:</p>
    <pre class="small bg-light border rounded p-2 mb-3 user-select-all">0 8 * * * /usr/bin/curl -sS "<?= e($cronUrlBase) ?>/cron/alertas_diarias.php?token=<?= e($cronToken !== '' ? $cronToken : 'SU_TOKEN') ?>"</pre>

    <ul class="small text-muted mb-0">
      <li>Detalle de alertas y prueba manual en <a href="<?= MARINA_URL ?>/index.php?p=alertas">Alertas</a></li>
      <li>Si la app está en subcarpeta, ajuste la ruta (<code>public_html/marina/cron/...</code>)</li>
    </ul>
  </div>
</div>
</div>
</div>

<script>
(function () {
  var r = document.getElementById('font_size_percent');
  var label = document.getElementById('font-pct-label');
  if (!r || !label) return;
  r.addEventListener('input', function () {
    label.textContent = r.value + ' %';
  });
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
