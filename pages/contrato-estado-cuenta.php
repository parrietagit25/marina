<?php
/**
 * API JSON: estado de cuenta de un contrato (cuotas + electricidad).
 */
require_once __DIR__ . '/../includes/contrato_estado_cuenta.php';

header('Content-Type: application/json; charset=UTF-8');

$contratoId = (int) ($_GET['id'] ?? 0);
$pdo = getDb();
$data = marina_contrato_estado_cuenta_datos($pdo, $contratoId);

if ($data === null) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Contrato no encontrado.'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
