<?php
/**
 * Marketing — plantillas de correo (Summernote) y lanzamiento de campañas.
 */
$titulo = 'Plantillas de marketing';
$pdo = getDb();
require_once __DIR__ . '/../includes/marketing_helpers.php';

$accion = trim((string) obtener('accion', ''));
$id = (int) obtener('id', 0);
$vista = obtener('vista', 'lista');
$ok = obtener('ok');
$err = obtener('err');
$mensaje = '';
$uid = usuarioId();

if (enviado() && $accion === 'upload_imagen') {
    header('Content-Type: application/json; charset=utf-8');
    $file = $_FILES['file'] ?? null;
    if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        echo json_encode(['ok' => false, 'error' => 'No se recibió la imagen.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $tmp = (string) ($file['tmp_name'] ?? '');
    $size = (int) ($file['size'] ?? 0);
    if ($size < 1 || $size > 3 * 1024 * 1024) {
        echo json_encode(['ok' => false, 'error' => 'La imagen debe pesar menos de 3 MB.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmp) ?: '';
    $map = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
    if (!isset($map[$mime])) {
        echo json_encode(['ok' => false, 'error' => 'Formato no permitido (JPG, PNG, GIF, WEBP).'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $dir = MARINA_ROOT . '/uploads/marketing';
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        echo json_encode(['ok' => false, 'error' => 'No se pudo crear la carpeta de imágenes.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $nombre = 'mkt_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $map[$mime];
    $dest = $dir . '/' . $nombre;
    if (!move_uploaded_file($tmp, $dest)) {
        echo json_encode(['ok' => false, 'error' => 'No se pudo guardar la imagen.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $url = MARINA_URL . '/uploads/marketing/' . rawurlencode($nombre);
    echo json_encode(['ok' => true, 'url' => $url], JSON_UNESCAPED_UNICODE);
    exit;
}

if (enviado() && $accion === 'eliminar' && $id > 0) {
    try {
        $pdo->prepare('DELETE FROM marketing_plantillas WHERE id = ?')->execute([$id]);
        redirigir(MARINA_URL . '/index.php?p=marketing-plantillas&ok=' . rawurlencode('Plantilla eliminada'));
    } catch (Throwable $e) {
        redirigir(MARINA_URL . '/index.php?p=marketing-plantillas&err=' . rawurlencode('No se pudo eliminar la plantilla.'));
    }
}

if (enviado() && $accion === 'guardar') {
    $nombre = trim((string) ($_POST['nombre'] ?? ''));
    $asunto = trim((string) ($_POST['asunto'] ?? ''));
    $cuerpo = (string) ($_POST['cuerpo_html'] ?? '');
    $editId = (int) ($_POST['id'] ?? 0);

    if ($nombre === '' || $asunto === '') {
        $mensaje = 'Nombre y asunto son obligatorios.';
        $vista = 'form';
        $id = $editId;
    } elseif (trim(strip_tags($cuerpo)) === '') {
        $mensaje = 'El cuerpo del correo no puede estar vacío.';
        $vista = 'form';
        $id = $editId;
    } else {
        if ($editId > 0) {
            $pdo->prepare('UPDATE marketing_plantillas SET nombre=?, asunto=?, cuerpo_html=?, updated_by=? WHERE id=?')
                ->execute([$nombre, $asunto, $cuerpo, $uid, $editId]);
            redirigir(MARINA_URL . '/index.php?p=marketing-plantillas&ok=' . rawurlencode('Plantilla actualizada'));
        } else {
            $pdo->prepare('INSERT INTO marketing_plantillas (nombre, asunto, cuerpo_html, created_by, updated_by) VALUES (?,?,?,?,?)')
                ->execute([$nombre, $asunto, $cuerpo, $uid, $uid]);
            redirigir(MARINA_URL . '/index.php?p=marketing-plantillas&ok=' . rawurlencode('Plantilla creada'));
        }
    }
}

if (enviado() && $accion === 'crear_campana') {
    $plantillaId = (int) ($_POST['plantilla_id'] ?? 0);
    $nombreCamp = trim((string) ($_POST['nombre_campana'] ?? ''));
    $modo = trim((string) ($_POST['modo_destino'] ?? 'clientes'));
    $tiposPost = $_POST['tipos_embarcacion'] ?? [];
    $tipos = [];
    if (is_array($tiposPost)) {
        foreach ($tiposPost as $t) {
            $v = marina_cliente_tipo_embarcacion_valido((string) $t);
            if ($v !== null) {
                $tipos[] = $v;
            }
        }
    }
    $tipos = array_values(array_unique($tipos));
    $emailsManual = marina_marketing_parsear_emails_manual((string) ($_POST['emails_manual'] ?? ''));

    if ($plantillaId < 1) {
        redirigir(MARINA_URL . '/index.php?p=marketing-plantillas&err=' . rawurlencode('Plantilla no válida.'));
    }
    if ($nombreCamp === '') {
        redirigir(MARINA_URL . '/index.php?p=marketing-plantillas&err=' . rawurlencode('Indique un nombre para la campaña.'));
    }
    if (!marina_marketing_resend_configurado($pdo)) {
        redirigir(MARINA_URL . '/index.php?p=configuracion&err=' . rawurlencode('Configure Resend (API key y remitente) antes de enviar campañas.'));
    }

    $dest = marina_marketing_armar_destinatarios($pdo, $modo, $tipos, $emailsManual);
    if ($dest === []) {
        redirigir(MARINA_URL . '/index.php?p=marketing-plantillas&err=' . rawurlencode('No hay destinatarios con los filtros indicados.'));
    }

    $pdo->prepare('
        INSERT INTO marketing_campanas (plantilla_id, nombre, modo_destino, tipos_embarcacion, emails_manual, estado, total_destinatarios, created_by)
        VALUES (?,?,?,?,?,?,?,?)
    ')->execute([
        $plantillaId,
        $nombreCamp,
        $modo,
        $tipos !== [] ? implode(',', $tipos) : null,
        $emailsManual !== [] ? implode("\n", $emailsManual) : null,
        'pendiente',
        count($dest),
        $uid,
    ]);
    $campanaId = (int) $pdo->lastInsertId();

    $ins = $pdo->prepare('INSERT INTO marketing_envios (campana_id, cliente_id, email, nombre_dest, estado) VALUES (?,?,?,?,?)');
    foreach ($dest as $d) {
        $ins->execute([
            $campanaId,
            $d['cliente_id'] ?? null,
            $d['email'],
            $d['nombre'] !== '' ? $d['nombre'] : null,
            'pendiente',
        ]);
    }

    redirigir(MARINA_URL . '/index.php?p=marketing-campanas&campana_id=' . $campanaId . '&accion=procesar&ok=' . rawurlencode('Campaña creada. Iniciando envío…'));
}

$registro = null;
if ($vista === 'form') {
    if ($id > 0) {
        $st = $pdo->prepare('SELECT * FROM marketing_plantillas WHERE id = ?');
        $st->execute([$id]);
        $registro = $st->fetch(PDO::FETCH_ASSOC);
        if (!$registro && $mensaje === '') {
            redirigir(MARINA_URL . '/index.php?p=marketing-plantillas');
        }
    }
    if ($mensaje !== '' && enviado()) {
        $registro = [
            'id' => $id,
            'nombre' => trim((string) ($_POST['nombre'] ?? '')),
            'asunto' => trim((string) ($_POST['asunto'] ?? '')),
            'cuerpo_html' => (string) ($_POST['cuerpo_html'] ?? ''),
        ];
    }
}

$plantillas = $pdo->query('
    SELECT p.*,
           (SELECT COUNT(*) FROM marketing_campanas c WHERE c.plantilla_id = p.id) AS num_campanas,
           (SELECT MAX(c.created_at) FROM marketing_campanas c WHERE c.plantilla_id = p.id) AS ultima_campana
    FROM marketing_plantillas p
    ORDER BY p.updated_at DESC, p.id DESC
')->fetchAll(PDO::FETCH_ASSOC);

$tiposEmb = marina_cliente_tipos_embarcacion();
$resendOk = marina_marketing_resend_configurado($pdo);

if ($vista === 'form') {
    $marinaHeadExtra = '
  <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-bs5.min.css" rel="stylesheet">
';
    $uploadUrl = MARINA_URL . '/index.php?p=marketing-plantillas&accion=upload_imagen';
    $plantillasBaseMeta = marina_marketing_plantillas_base();
    $plantillasBase = [];
    foreach ($plantillasBaseMeta as $clave => $meta) {
        $plantillasBase[$clave] = $meta['html'];
    }
    $jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;
    $marinaFooterExtra = '
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-bs5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/lang/summernote-es-ES.min.js"></script>
<script>
(function() {
  var uploadUrl = ' . json_encode($uploadUrl, $jsonFlags) . ';
  var plantillasBase = ' . json_encode($plantillasBase, $jsonFlags) . ';

  function initEditor() {
    if (!window.jQuery || !jQuery.fn.summernote) return;
    var $ed = jQuery("#cuerpoHtmlEditor");
    if (!$ed.length || $ed.next(".note-editor").length) return;

    $ed.summernote({
      lang: "es-ES",
      height: 420,
      minHeight: 320,
      maxHeight: 700,
      placeholder: "Diseñe su correo promocional…",
      tabsize: 2,
      fontNames: ["Arial", "Arial Black", "Georgia", "Tahoma", "Times New Roman", "Trebuchet MS", "Verdana", "Inter"],
      fontNamesIgnoreCheck: ["Inter"],
      toolbar: [
        ["style", ["style"]],
        ["font", ["bold", "italic", "underline", "strikethrough", "superscript", "subscript", "clear"]],
        ["fontname", ["fontname"]],
        ["fontsize", ["fontsize"]],
        ["color", ["color"]],
        ["para", ["ul", "ol", "paragraph", "height"]],
        ["insert", ["link", "picture", "video", "table", "hr"]],
        ["view", ["fullscreen", "codeview", "help"]]
      ],
      styleTags: ["p", "blockquote", "pre", "h1", "h2", "h3", "h4"],
      callbacks: {
        onImageUpload: function(files) {
          var editor = jQuery(this);
          Array.prototype.forEach.call(files, function(file) {
            var fd = new FormData();
            fd.append("accion", "upload_imagen");
            fd.append("file", file);
            fetch(uploadUrl, { method: "POST", body: fd, credentials: "same-origin" })
              .then(function(r) { return r.json(); })
              .then(function(data) {
                if (data && data.ok && data.url) {
                  editor.summernote("insertImage", data.url, function($img) {
                    $img.css("max-width", "100%");
                  });
                } else {
                  alert((data && data.error) || "No se pudo subir la imagen.");
                }
              })
              .catch(function() { alert("Error al subir la imagen."); });
          });
        }
      }
    });

    var form = document.getElementById("formPlantillaMarketing");
    if (form) {
      form.addEventListener("submit", function() {
        if ($ed.summernote) {
          $ed.val($ed.summernote("code"));
        }
      });
    }

    document.querySelectorAll("[data-plantilla-base]").forEach(function(btn) {
      btn.addEventListener("click", function() {
        var key = btn.getAttribute("data-plantilla-base");
        if (!plantillasBase[key]) return;
        if ($ed.summernote("isEmpty") || confirm("¿Reemplazar el contenido actual con esta plantilla base?")) {
          $ed.summernote("code", plantillasBase[key]);
        }
      });
    });

    var btnPrev = document.getElementById("btnPreviewEmail");
    if (btnPrev) {
      btnPrev.addEventListener("click", function() {
        var html = $ed.summernote("code");
        var frame = document.getElementById("previewEmailFrame");
        if (frame) {
          frame.srcdoc = html;
          new bootstrap.Modal(document.getElementById("previewEmailModal")).show();
        }
      });
    }
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initEditor);
  } else {
    initEditor();
  }
})();
</script>';
}

require_once __DIR__ . '/../includes/layout.php';
?>

<h1 class="h4 mb-2">Plantillas de marketing</h1>
<p class="text-muted small mb-3">
    Cree plantillas de correo con editor visual. Use <code>{{nombre}}</code> en asunto o cuerpo para personalizar con el nombre del cliente.
    <?php if (!$resendOk): ?>
        <span class="text-warning">Configure <a href="<?= MARINA_URL ?>/index.php?p=configuracion">Resend</a> para poder enviar campañas.</span>
    <?php endif; ?>
</p>

<?php if ($ok): ?><div class="alert alert-success py-2"><?= e($ok) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-danger py-2"><?= e($err) ?></div><?php endif; ?>

<?php if ($vista === 'form'): ?>
<div class="card p-3 mb-3 marketing-editor-card">
    <h2 class="h6 mb-2"><?= $id > 0 ? 'Editar plantilla' : 'Nueva plantilla' ?></h2>
    <p class="text-muted small mb-3">Use el editor visual para diseñar correos promocionales. Inserte imágenes, tablas, botones y estilos. Vista previa antes de guardar.</p>
    <?php if ($mensaje): ?><div class="alert alert-danger py-2"><?= e($mensaje) ?></div><?php endif; ?>
    <form method="post" action="<?= MARINA_URL ?>/index.php?p=marketing-plantillas" id="formPlantillaMarketing">
        <input type="hidden" name="accion" value="guardar">
        <input type="hidden" name="id" value="<?= (int) ($registro['id'] ?? 0) ?>">
        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <label class="form-label">Nombre de la plantilla *</label>
                <input type="text" class="form-control" name="nombre" required maxlength="200" value="<?= e($registro['nombre'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Asunto del correo *</label>
                <input type="text" class="form-control" name="asunto" required maxlength="500" value="<?= e($registro['asunto'] ?? '') ?>" placeholder="Ej: Hola {{nombre}}, promoción exclusiva">
            </div>
        </div>
        <div class="mb-2 d-flex flex-wrap align-items-center justify-content-between gap-2">
            <label class="form-label mb-0">Cuerpo del correo *</label>
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <div class="dropdown">
                    <button type="button" class="btn btn-sm btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                        Plantillas base
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end marketing-plantillas-dropdown">
                        <?php
                        $grupoActual = '';
                        foreach ($plantillasBaseMeta as $clave => $meta):
                            if ($meta['grupo'] !== $grupoActual):
                                if ($grupoActual !== '') {
                                    echo '<li><hr class="dropdown-divider"></li>';
                                }
                                $grupoActual = $meta['grupo'];
                                ?>
                        <li><h6 class="dropdown-header"><?= e($grupoActual) ?></h6></li>
                            <?php endif; ?>
                        <li>
                            <button type="button" class="dropdown-item" data-plantilla-base="<?= e($clave) ?>"><?= e($meta['label']) ?></button>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <button type="button" class="btn btn-sm btn-outline-info" id="btnPreviewEmail">Vista previa</button>
            </div>
        </div>
        <div class="marketing-summernote-wrap mb-3">
            <textarea id="cuerpoHtmlEditor" name="cuerpo_html"><?= $registro['cuerpo_html'] ?? '' ?></textarea>
        </div>
        <p class="small text-muted mb-3">Consejo: ancho recomendado 600px para correos. Use <code>{{nombre}}</code> para personalizar.</p>
        <div class="d-flex flex-wrap gap-2">
            <button type="submit" class="btn btn-primary">Guardar plantilla</button>
            <a class="btn btn-outline-secondary" href="<?= MARINA_URL ?>/index.php?p=marketing-plantillas">Cancelar</a>
        </div>
    </form>
</div>

<div class="modal fade" id="previewEmailModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Vista previa del correo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0 bg-light">
                <iframe id="previewEmailFrame" title="Vista previa" class="marketing-preview-frame"></iframe>
            </div>
        </div>
    </div>
</div>
<?php else: ?>

<div class="toolbar mb-3">
    <a class="btn btn-primary" href="<?= MARINA_URL ?>/index.php?p=marketing-plantillas&vista=form">Nueva plantilla</a>
    <a class="btn btn-outline-secondary" href="<?= MARINA_URL ?>/index.php?p=marketing-campanas">Ver campañas y envíos</a>
</div>

<div class="card p-3">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Asunto</th>
                    <th>Campañas</th>
                    <th>Actualizada</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($plantillas as $p): ?>
                <tr>
                    <td><?= e($p['nombre']) ?></td>
                    <td class="small"><?= e($p['asunto']) ?></td>
                    <td><?= (int) ($p['num_campanas'] ?? 0) ?></td>
                    <td class="text-nowrap small"><?= fechaHoraFormato($p['updated_at'] ?? '') ?></td>
                    <td class="text-end text-nowrap">
                        <a class="btn btn-sm btn-secondary" href="<?= MARINA_URL ?>/index.php?p=marketing-plantillas&vista=form&id=<?= (int) $p['id'] ?>">Editar</a>
                        <button type="button" class="btn btn-sm btn-primary btn-campana-plantilla"
                            data-id="<?= (int) $p['id'] ?>"
                            data-nombre="<?= e($p['nombre']) ?>"
                            <?= $resendOk ? '' : 'disabled title="Configure Resend primero"' ?>>Enviar campaña</button>
                        <a class="btn btn-sm btn-outline-primary" href="<?= MARINA_URL ?>/index.php?p=marketing-campanas&plantilla_id=<?= (int) $p['id'] ?>">Correos enviados</a>
                        <form method="post" class="d-inline" action="<?= MARINA_URL ?>/index.php?p=marketing-plantillas&accion=eliminar&id=<?= (int) $p['id'] ?>" onsubmit="return confirm('¿Eliminar esta plantilla y sus campañas asociadas?');">
                            <button type="submit" class="btn btn-sm btn-danger">Eliminar</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($plantillas)): ?>
                <tr><td colspan="5" class="text-muted">No hay plantillas. Cree la primera con «Nueva plantilla».</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="campanaModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="<?= MARINA_URL ?>/index.php?p=marketing-plantillas">
                <input type="hidden" name="accion" value="crear_campana">
                <input type="hidden" name="plantilla_id" id="campanaPlantillaId" value="">
                <div class="modal-header">
                    <h5 class="modal-title">Enviar campaña</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-3">Plantilla: <strong id="campanaPlantillaNombre"></strong></p>
                    <div class="mb-3">
                        <label class="form-label">Nombre de la campaña *</label>
                        <input type="text" class="form-control" name="nombre_campana" required maxlength="200" placeholder="Ej: Promo verano — Catamaranes">
                    </div>
                    <div class="mb-3">
                        <label class="form-label d-block">Destinatarios</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="modo_destino" id="modoClientes" value="clientes" checked>
                            <label class="form-check-label" for="modoClientes">Clientes (por tipo de embarcación)</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="modo_destino" id="modoManual" value="manual">
                            <label class="form-check-label" for="modoManual">Lista manual de correos</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="modo_destino" id="modoMixto" value="mixto">
                            <label class="form-check-label" for="modoMixto">Clientes filtrados + correos adicionales</label>
                        </div>
                    </div>
                    <div class="mb-3" id="bloqueTiposEmb">
                        <label class="form-label">Tipo de embarcación (clientes con email)</label>
                        <div class="d-flex flex-wrap gap-3 marina-tipo-embarcacion-group">
                            <?php foreach ($tiposEmb as $cod => $lab): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="tipos_embarcacion[]" id="campTipo<?= e($cod) ?>" value="<?= e($cod) ?>">
                                <label class="form-check-label" for="campTipo<?= e($cod) ?>"><?= e($lab) ?></label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <p class="small text-muted mb-0 mt-1">Si no marca ninguno, se incluyen todos los clientes con correo.</p>
                    </div>
                    <div class="mb-0 d-none" id="bloqueEmailsManual">
                        <label class="form-label">Correos adicionales (uno por línea o separados por coma)</label>
                        <textarea class="form-control" name="emails_manual" rows="4" placeholder="cliente@ejemplo.com&#10;otro@ejemplo.com"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Crear campaña y enviar</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var modalEl = document.getElementById('campanaModal');
    if (!modalEl) return;
    var modal = new bootstrap.Modal(modalEl);
    document.querySelectorAll('.btn-campana-plantilla').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.getElementById('campanaPlantillaId').value = btn.getAttribute('data-id') || '';
            document.getElementById('campanaPlantillaNombre').textContent = btn.getAttribute('data-nombre') || '';
            modal.show();
        });
    });
    function syncModo() {
        var modo = document.querySelector('input[name="modo_destino"]:checked');
        var m = modo ? modo.value : 'clientes';
        var bloqueTipos = document.getElementById('bloqueTiposEmb');
        var bloqueManual = document.getElementById('bloqueEmailsManual');
        if (bloqueTipos) bloqueTipos.classList.toggle('d-none', m === 'manual');
        if (bloqueManual) bloqueManual.classList.toggle('d-none', m === 'clientes');
    }
    document.querySelectorAll('input[name="modo_destino"]').forEach(function(r) {
        r.addEventListener('change', syncModo);
    });
    syncModo();
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
