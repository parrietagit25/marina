<?php
/**
 * Marketing — campañas y detalle de correos enviados (Resend).
 */
$titulo = 'Campañas de marketing';
$pdo = getDb();
require_once __DIR__ . '/../includes/marketing_helpers.php';

$plantillaId = (int) obtener('plantilla_id', 0);
$campanaId = (int) obtener('campana_id', 0);
$accion = trim((string) obtener('accion', ''));
$ok = obtener('ok');
$err = obtener('err');

if ($accion === 'procesar' && $campanaId > 0) {
    try {
        $lote = marina_marketing_procesar_lote($pdo, $campanaId, 8);
        if ($lote['pendientes'] > 0) {
            redirigir(MARINA_URL . '/index.php?p=marketing-campanas&campana_id=' . $campanaId . '&accion=procesar');
        }
        redirigir(MARINA_URL . '/index.php?p=marketing-campanas&campana_id=' . $campanaId . '&ok=' . rawurlencode('Envío completado: ' . $lote['enviados'] . ' enviados, ' . $lote['fallidos'] . ' fallidos.'));
    } catch (Throwable $e) {
        redirigir(MARINA_URL . '/index.php?p=marketing-campanas&campana_id=' . $campanaId . '&err=' . rawurlencode($e->getMessage()));
    }
}

$campana = null;
$envios = [];
if ($campanaId > 0) {
    $st = $pdo->prepare('
        SELECT c.*, p.nombre AS plantilla_nombre, p.asunto AS plantilla_asunto
        FROM marketing_campanas c
        JOIN marketing_plantillas p ON p.id = c.plantilla_id
        WHERE c.id = ?
    ');
    $st->execute([$campanaId]);
    $campana = $st->fetch(PDO::FETCH_ASSOC);
    if ($campana) {
        $stE = $pdo->prepare('
            SELECT e.*, cl.nombre AS cliente_nombre
            FROM marketing_envios e
            LEFT JOIN clientes cl ON cl.id = e.cliente_id
            WHERE e.campana_id = ?
            ORDER BY e.estado, e.email
        ');
        $stE->execute([$campanaId]);
        $envios = $stE->fetchAll(PDO::FETCH_ASSOC);
    }
}

$sqlCampanas = '
    SELECT c.*, p.nombre AS plantilla_nombre
    FROM marketing_campanas c
    JOIN marketing_plantillas p ON p.id = c.plantilla_id
';
$params = [];
if ($plantillaId > 0) {
    $sqlCampanas .= ' WHERE c.plantilla_id = ? ';
    $params[] = $plantillaId;
}
$sqlCampanas .= ' ORDER BY c.created_at DESC, c.id DESC LIMIT 200';
$stList = $pdo->prepare($sqlCampanas);
$stList->execute($params);
$campanas = $stList->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../includes/layout.php';
?>

<h1 class="h4 mb-2">Campañas y correos enviados</h1>
<p class="text-muted small mb-3">
    Historial de envíos por campaña (vía Resend). Los no enviados muestran el error devuelto por el servicio.
    <a href="<?= MARINA_URL ?>/index.php?p=marketing-plantillas">Volver a plantillas</a>
</p>

<?php if ($ok): ?><div class="alert alert-success py-2"><?= e($ok) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-danger py-2"><?= e($err) ?></div><?php endif; ?>

<?php if ($campana): ?>

<div class="card p-3 mb-3">
    <h2 class="h6 mb-2">Campaña: <?= e($campana['nombre']) ?></h2>
    <div class="row g-2 small">
        <div class="col-md-4"><strong>Plantilla:</strong> <?= e($campana['plantilla_nombre']) ?></div>
        <div class="col-md-4"><strong>Estado:</strong> <?= e(marina_marketing_estado_etiqueta($campana['estado'] ?? '')) ?></div>
        <div class="col-md-4"><strong>Fecha:</strong> <?= fechaHoraFormato($campana['created_at'] ?? '') ?></div>
        <div class="col-md-4"><strong>Modo:</strong> <?= e($campana['modo_destino'] ?? '') ?></div>
        <div class="col-md-4"><strong>Enviados:</strong> <span class="text-success"><?= (int) ($campana['total_enviados'] ?? 0) ?></span></div>
        <div class="col-md-4"><strong>No enviados:</strong> <span class="text-danger"><?= (int) ($campana['total_fallidos'] ?? 0) ?></span></div>
    </div>
    <?php if (($campana['estado'] ?? '') === 'pendiente' || ($campana['estado'] ?? '') === 'enviando'): ?>
        <a class="btn btn-sm btn-primary mt-2" href="<?= MARINA_URL ?>/index.php?p=marketing-campanas&campana_id=<?= $campanaId ?>&accion=procesar">Reanudar envío</a>
    <?php endif; ?>
    <a class="btn btn-sm btn-outline-secondary mt-2" href="<?= MARINA_URL ?>/index.php?p=marketing-campanas<?= $plantillaId > 0 ? '&plantilla_id=' . $plantillaId : '' ?>">Volver al listado</a>
</div>

<div class="card p-3">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 no-datatable">
            <thead>
                <tr>
                    <th>Email</th>
                    <th>Cliente</th>
                    <th>Estado</th>
                    <th>Resend ID</th>
                    <th>Error</th>
                    <th>Enviado</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($envios as $e): ?>
                <?php
                $est = (string) ($e['estado'] ?? '');
                $cls = $est === 'enviado' ? 'text-success' : ($est === 'fallido' ? 'text-danger' : 'text-muted');
                ?>
                <tr>
                    <td><?= e($e['email']) ?></td>
                    <td><?= e($e['cliente_nombre'] ?? $e['nombre_dest'] ?? '—') ?></td>
                    <td class="<?= $cls ?> fw-semibold"><?= e(marina_marketing_estado_etiqueta($est)) ?></td>
                    <td class="small"><?= e($e['resend_id'] ?? '—') ?></td>
                    <td class="small text-danger"><?= e($e['error_mensaje'] ?? '') ?></td>
                    <td class="text-nowrap small"><?= !empty($e['enviado_at']) ? fechaHoraFormato($e['enviado_at']) : '—' ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($envios)): ?>
                <tr><td colspan="6" class="text-muted">Sin registros de envío.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php else: ?>

<?php if ($plantillaId > 0): ?>
    <p class="small mb-2">Filtrado por plantilla #<?= $plantillaId ?>. <a href="<?= MARINA_URL ?>/index.php?p=marketing-campanas">Ver todas</a></p>
<?php endif; ?>

<div class="card p-3">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Campaña</th>
                    <th>Plantilla</th>
                    <th>Estado</th>
                    <th class="text-end">Enviados</th>
                    <th class="text-end">Fallidos</th>
                    <th>Fecha</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($campanas as $c): ?>
                <tr>
                    <td><?= e($c['nombre']) ?></td>
                    <td><?= e($c['plantilla_nombre']) ?></td>
                    <td><?= e(marina_marketing_estado_etiqueta($c['estado'] ?? '')) ?></td>
                    <td class="text-end text-success"><?= (int) ($c['total_enviados'] ?? 0) ?></td>
                    <td class="text-end text-danger"><?= (int) ($c['total_fallidos'] ?? 0) ?></td>
                    <td class="text-nowrap small"><?= fechaHoraFormato($c['created_at'] ?? '') ?></td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-primary" href="<?= MARINA_URL ?>/index.php?p=marketing-campanas&campana_id=<?= (int) $c['id'] ?>">Ver correos</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($campanas)): ?>
                <tr><td colspan="7" class="text-muted">No hay campañas aún.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
