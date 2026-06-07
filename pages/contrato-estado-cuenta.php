<?php
/**
 * API JSON: estado de cuenta de un contrato (cuotas + electricidad).
 * POST accion=enviar_email — envía estado de cuenta por correo.
 */
require_once __DIR__ . '/../includes/contrato_estado_cuenta.php';
require_once __DIR__ . '/../includes/alertas_helpers.php';

header('Content-Type: application/json; charset=UTF-8');

$pdo = getDb();
$contratoId = (int) ($_GET['id'] ?? $_POST['contrato_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && trim((string) ($_POST['accion'] ?? '')) === 'enviar_email') {
    if ($contratoId < 1) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Contrato no válido.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $res = marina_alertas_enviar_estado_cuenta($pdo, $contratoId, true);
    if (!$res['ok']) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => $res['error'] ?? 'No se pudo enviar.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode(['ok' => true, 'mensaje' => 'Estado de cuenta enviado por correo.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$data = marina_contrato_estado_cuenta_datos($pdo, $contratoId);

if ($data === null) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Contrato no encontrado.'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
