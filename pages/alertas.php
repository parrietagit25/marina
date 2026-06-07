<?php
/**
 * Alertas — configuración de correos automáticos y excepciones.
 */
$titulo = 'Alertas';
$pdo = getDb();
require_once __DIR__ . '/../includes/marketing_helpers.php';
require_once __DIR__ . '/../includes/cron_helpers.php';
require_once __DIR__ . '/../includes/alertas_helpers.php';

$previewCodigo = trim((string) obtener('preview', ''));
if ($previewCodigo !== '') {
    header('Content-Type: application/json; charset=utf-8');
    $prev = marina_alertas_preview($previewCodigo);
    if ($prev === null) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Alerta no encontrada.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode($prev, JSON_UNESCAPED_UNICODE);
    exit;
}

$ok = obtener('ok');
$err = obtener('err');
$mensaje = '';
$mensajeTipo = '';

if (enviado()) {
    $accion = trim((string) ($_POST['accion'] ?? ''));
    try {
        if ($accion === 'guardar_alertas') {
            $activas = $_POST['alerta_activa'] ?? [];
            if (!is_array($activas)) {
                $activas = [];
            }
            foreach (marina_alertas_definiciones() as $codigo => $def) {
                marina_alertas_guardar_activa($pdo, $codigo, isset($activas[$codigo]));
            }
            redirigir(MARINA_URL . '/index.php?p=alertas&ok=' . rawurlencode('Configuración de alertas guardada.'));
        }
        if ($accion === 'agregar_excepcion') {
            $emailExc = trim((string) ($_POST['email_excepcion'] ?? ''));
            $codExc = trim((string) ($_POST['codigo_excepcion'] ?? ''));
            $msg = marina_alertas_excepcion_agregar($pdo, $emailExc, $codExc);
            if ($msg !== null) {
                redirigir(MARINA_URL . '/index.php?p=alertas&err=' . rawurlencode($msg));
            }
            redirigir(MARINA_URL . '/index.php?p=alertas&ok=' . rawurlencode('Excepción agregada.'));
        }
        if ($accion === 'eliminar_excepcion') {
            $excId = (int) ($_POST['excepcion_id'] ?? 0);
            if ($excId > 0) {
                marina_alertas_excepcion_eliminar($pdo, $excId);
            }
            redirigir(MARINA_URL . '/index.php?p=alertas&ok=' . rawurlencode('Excepción eliminada.'));
        }
        if ($accion === 'probar_diarias') {
            $res = marina_alertas_ejecutar_diarias($pdo);
            $total = (int) ($res['cuotas_vencidas']['enviados'] ?? 0)
                + (int) ($res['contrato_por_vencer']['enviados'] ?? 0)
                + (int) ($res['contratos_finalizados']['enviados'] ?? 0);
            redirigir(MARINA_URL . '/index.php?p=alertas&ok=' . rawurlencode('Prueba ejecutada. Correos enviados: ' . $total . '.'));
        }
    } catch (Throwable $e) {
        redirigir(MARINA_URL . '/index.php?p=alertas&err=' . rawurlencode('No se pudo completar la acción.'));
    }
}

marina_alertas_seed_config($pdo);
$defs = marina_alertas_definiciones();
$excepciones = marina_alertas_excepciones_listar($pdo);
$resendOk = marina_marketing_resend_configurado($pdo);
$cronToken = marina_cron_token($pdo);
$cronRuta = marina_cron_ruta_proyecto();
$cronUrlBase = (isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] !== '')
    ? (((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . (MARINA_URL !== '' ? MARINA_URL : ''))
    : 'https://tudominio.com' . (MARINA_URL !== '' ? MARINA_URL : '');

$stLog = $pdo->query('
    SELECT codigo_alerta, email, estado, enviado_at, error_mensaje, referencia_id
    FROM alertas_envios_log
    ORDER BY id DESC
    LIMIT 30
');
$ultimosEnvios = $stLog ? $stLog->fetchAll(PDO::FETCH_ASSOC) : [];

require_once __DIR__ . '/../includes/layout.php';
?>

<h1 class="h4 mb-2">Alertas por correo</h1>
<p class="text-muted small mb-3">
    Configure avisos automáticos y excepciones. Los correos se envían con <strong>Resend</strong>
    <?php if (!$resendOk): ?>
        — <span class="text-warning">configure la API en <a href="<?= MARINA_URL ?>/index.php?p=configuracion">Configuración</a></span>
    <?php endif; ?>.
</p>

<?php if ($ok): ?><div class="alert alert-success py-2"><?= e($ok) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-danger py-2"><?= e($err) ?></div><?php endif; ?>

<div class="row g-3">
<div class="col-12 col-xl-7">
<div class="card shadow-sm border-0">
    <div class="card-body">
        <h2 class="h6 text-muted mb-3">Tipos de alerta</h2>
        <form method="post" action="<?= MARINA_URL ?>/index.php?p=alertas">
            <input type="hidden" name="accion" value="guardar_alertas">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-3 no-datatable">
                    <thead>
                        <tr>
                            <th>Alerta</th>
                            <th>Tipo</th>
                            <th class="text-center">Activa</th>
                            <th class="text-end">Plantilla</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($defs as $codigo => $def): ?>
                        <tr>
                            <td>
                                <strong><?= e($def['etiqueta']) ?></strong>
                                <p class="small text-muted mb-0"><?= e($def['descripcion']) ?></p>
                            </td>
                            <td class="small text-nowrap">
                                <?= $def['programada'] ? '<span class="badge bg-primary">Diaria (cron)</span>' : '<span class="badge bg-secondary">Manual / evento</span>' ?>
                            </td>
                            <td class="text-center">
                                <input type="checkbox" class="form-check-input" name="alerta_activa[<?= e($codigo) ?>]" value="1"
                                    <?= marina_alertas_activa($pdo, $codigo) ? 'checked' : '' ?>>
                            </td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-outline-info btn-preview-alerta"
                                    data-codigo="<?= e($codigo) ?>"
                                    data-etiqueta="<?= e($def['etiqueta']) ?>">Vista previa</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <button type="submit" class="btn btn-primary">Guardar alertas</button>
        </form>
    </div>
</div>
</div>

<div class="col-12 col-xl-5">
<div class="card shadow-sm border-0 mb-3">
    <div class="card-body">
        <h2 class="h6 text-muted mb-3">Correos de excepción</h2>
        <p class="small text-muted">Estos correos <strong>no recibirán</strong> la alerta indicada (útil para pruebas o contactos alternativos).</p>
        <form method="post" class="row g-2 mb-3">
            <input type="hidden" name="accion" value="agregar_excepcion">
            <div class="col-md-5">
                <input type="email" class="form-control form-control-sm" name="email_excepcion" required placeholder="correo@ejemplo.com">
            </div>
            <div class="col-md-5">
                <select class="form-select form-select-sm" name="codigo_excepcion" required>
                    <?php foreach ($defs as $codigo => $def): ?>
                        <option value="<?= e($codigo) ?>"><?= e($def['etiqueta']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-sm btn-outline-primary w-100">Agregar</button>
            </div>
        </form>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>Correo</th><th>No recibe</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($excepciones as $exc): ?>
                    <tr>
                        <td class="small"><?= e($exc['email']) ?></td>
                        <td class="small"><?= e($exc['etiqueta']) ?></td>
                        <td class="text-end">
                            <form method="post" class="d-inline">
                                <input type="hidden" name="accion" value="eliminar_excepcion">
                                <input type="hidden" name="excepcion_id" value="<?= (int) $exc['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger">Quitar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($excepciones === []): ?>
                    <tr><td colspan="3" class="text-muted small">Sin excepciones registradas.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <h2 class="h6 text-muted mb-2">Cron diario (Namecheap / cPanel)</h2>
        <p class="small text-muted mb-2">Programe <strong>una vez al día</strong> (recomendado 8:00 a.m.). Ejecuta: cuotas vencidas, contrato por vencer (7 días) y contratos finalizados hoy.</p>
        <pre class="small bg-light border rounded p-2 mb-2 user-select-all">0 8 * * * /usr/local/bin/php <?= e($cronRuta) ?>/cron/alertas_diarias.php</pre>
        <pre class="small bg-light border rounded p-2 mb-3 user-select-all">0 8 * * * /usr/bin/curl -sS "<?= e($cronUrlBase) ?>/cron/alertas_diarias.php?token=<?= e($cronToken !== '' ? $cronToken : 'SU_TOKEN') ?>"</pre>
        <form method="post">
            <input type="hidden" name="accion" value="probar_diarias">
            <button type="submit" class="btn btn-sm btn-outline-secondary" <?= $resendOk ? '' : 'disabled' ?>>Ejecutar prueba ahora</button>
        </form>
        <p class="small text-muted mb-0 mt-2">Token cron en <a href="<?= MARINA_URL ?>/index.php?p=configuracion">Configuración</a>.</p>
    </div>
</div>
</div>
</div>

<div class="card shadow-sm border-0 mt-3">
    <div class="card-body">
        <h2 class="h6 text-muted mb-3">Últimos envíos</h2>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Alerta</th>
                        <th>Correo</th>
                        <th>Ref.</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($ultimosEnvios as $log): ?>
                    <?php
                    $cod = (string) ($log['codigo_alerta'] ?? '');
                    $est = (string) ($log['estado'] ?? '');
                    ?>
                    <tr>
                        <td class="small text-nowrap"><?= !empty($log['enviado_at']) ? fechaHoraFormato($log['enviado_at']) : '—' ?></td>
                        <td class="small"><?= e($defs[$cod]['etiqueta'] ?? $cod) ?></td>
                        <td class="small"><?= e($log['email'] ?? '') ?></td>
                        <td class="small"><?= !empty($log['referencia_id']) ? '#' . (int) $log['referencia_id'] : '—' ?></td>
                        <td class="small <?= $est === 'enviado' ? 'text-success' : 'text-danger' ?>">
                            <?= e($est === 'enviado' ? 'Enviado' : ($log['error_mensaje'] ?? 'Fallido')) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($ultimosEnvios === []): ?>
                    <tr><td colspan="5" class="text-muted small">Aún no hay registros de envío.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="previewAlertaModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-0" id="previewAlertaTitulo">Vista previa del correo</h5>
                    <p class="small text-muted mb-0 mt-1" id="previewAlertaAsunto"></p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <p class="small text-muted px-3 pt-3 mb-0" id="previewAlertaNota"></p>
                <iframe id="previewAlertaFrame" class="marketing-preview-frame" title="Vista previa alerta"></iframe>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
  var baseUrl = <?= json_encode(MARINA_URL . '/index.php?p=alertas', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  document.querySelectorAll('.btn-preview-alerta').forEach(function(btn) {
    btn.addEventListener('click', function() {
      var codigo = btn.getAttribute('data-codigo') || '';
      var etiqueta = btn.getAttribute('data-etiqueta') || 'Alerta';
      if (!codigo) return;
      var modalEl = document.getElementById('previewAlertaModal');
      var frame = document.getElementById('previewAlertaFrame');
      var titulo = document.getElementById('previewAlertaTitulo');
      var asunto = document.getElementById('previewAlertaAsunto');
      var nota = document.getElementById('previewAlertaNota');
      if (titulo) titulo.textContent = 'Vista previa — ' + etiqueta;
      if (asunto) asunto.textContent = 'Cargando…';
      if (nota) nota.textContent = '';
      if (frame) frame.srcdoc = '<p style="font-family:sans-serif;padding:24px;color:#64748b;">Cargando vista previa…</p>';
      if (modalEl && typeof bootstrap !== 'undefined') {
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
      }
      fetch(baseUrl + '&preview=' + encodeURIComponent(codigo), { credentials: 'same-origin' })
        .then(function(r) { return r.json(); })
        .then(function(data) {
          if (!data || !data.ok) {
            if (frame) frame.srcdoc = '<p style="font-family:sans-serif;padding:24px;color:#b91c1c;">No se pudo cargar la vista previa.</p>';
            return;
          }
          if (asunto) asunto.textContent = 'Asunto: ' + (data.asunto || '—');
          if (nota) nota.textContent = data.nota || '';
          if (frame) frame.srcdoc = data.html || '';
        })
        .catch(function() {
          if (frame) frame.srcdoc = '<p style="font-family:sans-serif;padding:24px;color:#b91c1c;">Error de conexión.</p>';
        });
    });
  });
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
