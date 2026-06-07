<?php
/**
 * Alertas por correo (Resend): tareas programadas y envíos manuales.
 */
declare(strict_types=1);

require_once __DIR__ . '/marketing_helpers.php';
require_once __DIR__ . '/contrato_estado_cuenta.php';

/** @return array<string, array{etiqueta: string, descripcion: string, programada: bool}> */
function marina_alertas_definiciones(): array
{
    return [
        'cuotas_vencidas' => [
            'etiqueta' => 'Cuotas vencidas',
            'descripcion' => 'Recordatorio diario amable cuando hay cuotas vencidas con saldo pendiente.',
            'programada' => true,
        ],
        'contrato_por_vencer' => [
            'etiqueta' => 'Contrato por finalizar',
            'descripcion' => 'Aviso 7 días antes de la fecha de fin del contrato (contratos activos).',
            'programada' => true,
        ],
        'contrato_finalizado' => [
            'etiqueta' => 'Contrato finalizado — despedida',
            'descripcion' => 'Mensaje cordial al liberar/finalizar un contrato sin saldos pendientes.',
            'programada' => true,
        ],
        'contrato_finalizado_deuda' => [
            'etiqueta' => 'Contrato finalizado con deuda',
            'descripcion' => 'Aviso respetuoso cuando el contrato finalizó y aún hay cuotas o electricidad pendiente.',
            'programada' => true,
        ],
        'bienvenida' => [
            'etiqueta' => 'Bienvenida al contrato',
            'descripcion' => 'Se envía al registrar un contrato nuevo (no es tarea programada).',
            'programada' => false,
        ],
        'estado_cuenta' => [
            'etiqueta' => 'Estado de cuenta',
            'descripcion' => 'Envío manual desde Contratos o el mapa de la marina.',
            'programada' => false,
        ],
    ];
}

function marina_alertas_activa(PDO $pdo, string $codigo): bool
{
    if (!isset(marina_alertas_definiciones()[$codigo])) {
        return false;
    }
    try {
        $st = $pdo->prepare('SELECT activo FROM alertas_config WHERE codigo = ? LIMIT 1');
        $st->execute([$codigo]);
        $v = $st->fetchColumn();
        if ($v === false) {
            return true;
        }

        return (int) $v === 1;
    } catch (Throwable $e) {
        return true;
    }
}

function marina_alertas_guardar_activa(PDO $pdo, string $codigo, bool $activo): void
{
    $defs = marina_alertas_definiciones();
    if (!isset($defs[$codigo])) {
        return;
    }
    $pdo->prepare('
        INSERT INTO alertas_config (codigo, etiqueta, programada, activo)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE activo = VALUES(activo), etiqueta = VALUES(etiqueta), programada = VALUES(programada)
    ')->execute([
        $codigo,
        $defs[$codigo]['etiqueta'],
        $defs[$codigo]['programada'] ? 1 : 0,
        $activo ? 1 : 0,
    ]);
}

/** @return list<array{id: int, email: string, codigo_alerta: string, etiqueta: string}> */
function marina_alertas_excepciones_listar(PDO $pdo): array
{
    $defs = marina_alertas_definiciones();
    $st = $pdo->query('SELECT id, email, codigo_alerta FROM alertas_excepciones ORDER BY email ASC, codigo_alerta ASC');
    $rows = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
    $out = [];
    foreach ($rows as $r) {
        $cod = (string) ($r['codigo_alerta'] ?? '');
        $out[] = [
            'id' => (int) ($r['id'] ?? 0),
            'email' => (string) ($r['email'] ?? ''),
            'codigo_alerta' => $cod,
            'etiqueta' => $defs[$cod]['etiqueta'] ?? $cod,
        ];
    }

    return $out;
}

function marina_alertas_excepcion_agregar(PDO $pdo, string $email, string $codigoAlerta): ?string
{
    $email = strtolower(trim($email));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'Correo no válido.';
    }
    if (!isset(marina_alertas_definiciones()[$codigoAlerta])) {
        return 'Tipo de alerta no válido.';
    }
    try {
        $pdo->prepare('INSERT INTO alertas_excepciones (email, codigo_alerta) VALUES (?, ?)')
            ->execute([$email, $codigoAlerta]);
    } catch (Throwable $e) {
        if (str_contains($e->getMessage(), 'Duplicate') || str_contains($e->getMessage(), '1062')) {
            return 'Esa excepción ya existe.';
        }

        return 'No se pudo guardar la excepción.';
    }

    return null;
}

function marina_alertas_excepcion_eliminar(PDO $pdo, int $id): void
{
    $pdo->prepare('DELETE FROM alertas_excepciones WHERE id = ?')->execute([$id]);
}

function marina_alertas_email_excluido(PDO $pdo, string $email, string $codigo): bool
{
    $email = strtolower(trim($email));
    if ($email === '') {
        return true;
    }
    try {
        $st = $pdo->prepare('SELECT 1 FROM alertas_excepciones WHERE LOWER(email) = ? AND codigo_alerta = ? LIMIT 1');
        $st->execute([$email, $codigo]);

        return (bool) $st->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

function marina_alertas_clave_dedup(string $codigo, string $referencia, bool $soloUnaVez): string
{
    $base = $codigo . ':' . $referencia;
    if ($soloUnaVez) {
        return $base;
    }

    return $base . ':' . date('Y-m-d');
}

function marina_alertas_ya_enviado(PDO $pdo, string $claveDedup): bool
{
    try {
        $st = $pdo->prepare('SELECT 1 FROM alertas_envios_log WHERE clave_dedup = ? AND estado = \'enviado\' LIMIT 1');
        $st->execute([$claveDedup]);

        return (bool) $st->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

function marina_alertas_registrar_envio(
    PDO $pdo,
    string $codigo,
    string $claveDedup,
    string $referenciaTipo,
    ?int $referenciaId,
    string $email,
    bool $ok,
    ?string $resendId,
    ?string $error
): void {
    $pdo->prepare('
        INSERT INTO alertas_envios_log (codigo_alerta, clave_dedup, referencia_tipo, referencia_id, email, estado, resend_id, error_mensaje, enviado_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ')->execute([
        $codigo,
        $claveDedup,
        $referenciaTipo,
        $referenciaId,
        $email,
        $ok ? 'enviado' : 'fallido',
        $resendId,
        $error,
    ]);
}

function marina_alertas_html_wrap(string $titulo, string $cuerpo): string
{
    return '<div style="max-width:600px;margin:0 auto;font-family:Georgia,\'Times New Roman\',serif;background:#ffffff;border:1px solid #e2e8f0;">'
        . '<div style="background:#f8fafc;padding:24px 28px;border-bottom:3px solid #0ea5e9;">'
        . '<p style="margin:0 0 6px;font-size:13px;color:#64748b;letter-spacing:0.5px;">Marina · Coronado Vista Mar</p>'
        . '<h1 style="margin:0;font-size:22px;color:#0f172a;font-weight:600;">' . htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') . '</h1></div>'
        . '<div style="padding:28px 28px;color:#334155;line-height:1.75;font-size:16px;">' . $cuerpo . '</div>'
        . '<div style="padding:18px 28px;text-align:center;font-size:12px;color:#94a3b8;background:#f8fafc;border-top:1px solid #e2e8f0;">'
        . 'Con aprecio, Equipo Marina<br><span style="font-size:11px;">Si tiene alguna consulta, responda a este correo.</span></div></div>';
}

function marina_alertas_saludo(string $nombre): string
{
    $n = trim($nombre);
    if ($n === '') {
        return '<p style="margin:0 0 18px;">Estimado/a cliente,</p>';
    }

    return '<p style="margin:0 0 18px;">Estimado/a <strong>' . htmlspecialchars($n, ENT_QUOTES, 'UTF-8') . '</strong>,</p>';
}

function marina_alertas_email_cliente_contrato(PDO $pdo, int $contratoId): ?array
{
    $st = $pdo->prepare('
        SELECT cl.email, cl.nombre, co.id AS contrato_id
        FROM contratos co
        JOIN clientes cl ON cl.id = co.cliente_id
        WHERE co.id = ?
        LIMIT 1
    ');
    $st->execute([$contratoId]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    if (!$r) {
        return null;
    }
    $email = strtolower(trim((string) ($r['email'] ?? '')));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return null;
    }

    return [
        'email' => $email,
        'nombre' => trim((string) ($r['nombre'] ?? '')),
        'contrato_id' => (int) ($r['contrato_id'] ?? 0),
    ];
}

function marina_alertas_contrato_unidad(PDO $pdo, int $contratoId): string
{
    $data = marina_contrato_estado_cuenta_datos($pdo, $contratoId);

    return $data ? (string) ($data['contrato']['unidad'] ?? '—') : '—';
}

/** @return array{saldo_cuotas: float, saldo_electricidad: float, saldo_total: float} */
function marina_alertas_contrato_saldos(PDO $pdo, int $contratoId): array
{
    $data = marina_contrato_estado_cuenta_datos($pdo, $contratoId);
    if (!$data) {
        return ['saldo_cuotas' => 0.0, 'saldo_electricidad' => 0.0, 'saldo_total' => 0.0];
    }

    $sc = (float) ($data['resumen_cuotas']['saldo'] ?? 0);
    $se = (float) ($data['resumen_electricidad']['saldo'] ?? 0);

    return [
        'saldo_cuotas' => $sc,
        'saldo_electricidad' => $se,
        'saldo_total' => round($sc + $se, 2),
    ];
}

/**
 * @return list<array{numero: int, vencimiento: string, saldo: float}>
 */
function marina_alertas_cuotas_vencidas_contrato(PDO $pdo, int $contratoId): array
{
    $data = marina_contrato_estado_cuenta_datos($pdo, $contratoId);
    if (!$data) {
        return [];
    }
    $hoy = date('Y-m-d');
    $out = [];
    foreach ($data['cuotas'] as $cu) {
        $saldo = (float) ($cu['saldo'] ?? 0);
        $venc = (string) ($cu['vencimiento'] ?? '');
        if ($saldo <= 0.00001 || $venc === '' || $venc >= $hoy) {
            continue;
        }
        $out[] = [
            'numero' => (int) ($cu['numero'] ?? 0),
            'vencimiento' => $venc,
            'saldo' => $saldo,
        ];
    }

    return $out;
}

function marina_alertas_tabla_cuotas_html(array $cuotas): string
{
    if ($cuotas === []) {
        return '';
    }
    $rows = '';
    foreach ($cuotas as $c) {
        $rows .= '<tr>'
            . '<td style="padding:8px 12px;border-bottom:1px solid #e2e8f0;">#' . (int) $c['numero'] . '</td>'
            . '<td style="padding:8px 12px;border-bottom:1px solid #e2e8f0;">' . htmlspecialchars(fechaFormato($c['vencimiento']), ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td style="padding:8px 12px;border-bottom:1px solid #e2e8f0;text-align:right;">' . htmlspecialchars(dinero((float) $c['saldo']), ENT_QUOTES, 'UTF-8') . '</td>'
            . '</tr>';
    }

    return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:20px 0;border:1px solid #e2e8f0;border-radius:6px;overflow:hidden;font-size:15px;">'
        . '<thead><tr style="background:#f1f5f9;">'
        . '<th style="padding:10px 12px;text-align:left;">Cuota</th>'
        . '<th style="padding:10px 12px;text-align:left;">Vencimiento</th>'
        . '<th style="padding:10px 12px;text-align:right;">Saldo</th>'
        . '</tr></thead><tbody>' . $rows . '</tbody></table>';
}

function marina_alertas_estado_cuenta_html(PDO $pdo, int $contratoId): ?string
{
    $data = marina_contrato_estado_cuenta_datos($pdo, $contratoId);
    if (!$data) {
        return null;
    }
    $c = $data['contrato'];
    $rc = $data['resumen_cuotas'];
    $re = $data['resumen_electricidad'];

    $html = marina_alertas_saludo((string) ($c['cliente'] ?? ''));
    $html .= '<p style="margin:0 0 16px;">Le compartimos el estado de cuenta de su contrato '
        . '<strong>#' . (int) $c['id'] . '</strong>'
        . ($c['unidad'] !== '—' ? ' (' . htmlspecialchars((string) $c['unidad'], ENT_QUOTES, 'UTF-8') . ')' : '')
        . ', correspondiente al período '
        . htmlspecialchars(fechaFormato($c['fecha_inicio']) . ' – ' . fechaFormato($c['fecha_fin']), ENT_QUOTES, 'UTF-8') . '.</p>';

    $html .= '<p style="margin:0 0 8px;font-size:15px;color:#475569;"><strong>Resumen cuotas</strong></p>';
    $html .= '<p style="margin:0 0 16px;">Total: ' . dinero((float) $rc['total_cuotas'])
        . ' · Pagado: ' . dinero((float) $rc['total_pagado'])
        . ' · <strong>Saldo: ' . dinero((float) $rc['saldo']) . '</strong></p>';

    if (($data['cuotas'] ?? []) !== []) {
        $rows = '';
        foreach ($data['cuotas'] as $cu) {
            $rows .= '<tr>'
                . '<td style="padding:8px 12px;border-bottom:1px solid #e2e8f0;">#' . (int) $cu['numero'] . '</td>'
                . '<td style="padding:8px 12px;border-bottom:1px solid #e2e8f0;">' . htmlspecialchars(fechaFormato($cu['vencimiento']), ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td style="padding:8px 12px;border-bottom:1px solid #e2e8f0;">' . htmlspecialchars((string) $cu['estado'], ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td style="padding:8px 12px;border-bottom:1px solid #e2e8f0;text-align:right;">' . dinero((float) $cu['saldo']) . '</td>'
                . '</tr>';
        }
        $html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 20px;border:1px solid #e2e8f0;font-size:14px;">'
            . '<thead><tr style="background:#f8fafc;"><th style="padding:8px 12px;text-align:left;">Cuota</th>'
            . '<th style="padding:8px 12px;text-align:left;">Vence</th><th style="padding:8px 12px;text-align:left;">Estado</th>'
            . '<th style="padding:8px 12px;text-align:right;">Saldo</th></tr></thead><tbody>' . $rows . '</tbody></table>';
    }

    if ((float) ($re['total_facturado'] ?? 0) > 0.00001) {
        $html .= '<p style="margin:0 0 8px;font-size:15px;color:#475569;"><strong>Electricidad</strong></p>';
        $html .= '<p style="margin:0 0 16px;">Facturado: ' . dinero((float) $re['total_facturado'])
            . ' · Pagado: ' . dinero((float) $re['total_pagado'])
            . ' · Saldo: ' . dinero((float) $re['saldo']) . '</p>';
    }

    $html .= '<p style="margin:0;font-size:15px;color:#64748b;">Ante cualquier duda sobre su cuenta, estaremos encantados de ayudarle.</p>';

    return marina_alertas_html_wrap('Estado de cuenta', $html);
}

/**
 * @return array{ok: bool, error: ?string, resend_id: ?string}
 */
function marina_alertas_enviar(
    PDO $pdo,
    string $codigo,
    string $email,
    string $nombre,
    string $asunto,
    string $html,
    string $referenciaTipo,
    ?int $referenciaId,
    bool $dedupUnaVez,
    bool $forzar = false
): array {
    if (!marina_alertas_activa($pdo, $codigo)) {
        return ['ok' => false, 'error' => 'Esta alerta está deshabilitada.', 'resend_id' => null];
    }
    if (!marina_marketing_resend_configurado($pdo)) {
        return ['ok' => false, 'error' => 'Configure Resend en Configuración.', 'resend_id' => null];
    }
    $email = strtolower(trim($email));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'El cliente no tiene un correo válido.', 'resend_id' => null];
    }
    if (marina_alertas_email_excluido($pdo, $email, $codigo)) {
        return ['ok' => false, 'error' => 'Correo en lista de excepciones para esta alerta.', 'resend_id' => null];
    }

    $refKey = $referenciaId !== null ? (string) $referenciaId : $email;
    $clave = marina_alertas_clave_dedup($codigo, $refKey, $dedupUnaVez);
    if (!$forzar && marina_alertas_ya_enviado($pdo, $clave)) {
        return ['ok' => false, 'error' => 'Ya se envió este aviso.', 'resend_id' => null];
    }

    $cfg = marina_marketing_resend_config($pdo);
    $res = marina_resend_enviar_email($cfg['api_key'], $cfg['from_email'], $email, $asunto, $html);
    marina_alertas_registrar_envio(
        $pdo,
        $codigo,
        $clave,
        $referenciaTipo,
        $referenciaId,
        $email,
        $res['ok'],
        $res['id'],
        $res['error']
    );

    return ['ok' => $res['ok'], 'error' => $res['error'], 'resend_id' => $res['id']];
}

/** @return array{ok: bool, error: ?string} */
function marina_alertas_enviar_bienvenida(PDO $pdo, int $contratoId): array
{
    $dest = marina_alertas_email_cliente_contrato($pdo, $contratoId);
    if (!$dest) {
        return ['ok' => false, 'error' => 'Sin correo de cliente para bienvenida.'];
    }
    $data = marina_contrato_estado_cuenta_datos($pdo, $contratoId);
    if (!$data) {
        return ['ok' => false, 'error' => 'Contrato no encontrado.'];
    }
    $c = $data['contrato'];
    $cuerpo = marina_alertas_saludo($dest['nombre']);
    $cuerpo .= '<p style="margin:0 0 16px;">Es un verdadero placer darle la bienvenida a nuestra marina. '
        . 'Su contrato <strong>#' . (int) $c['id'] . '</strong> ha quedado registrado';
    if (($c['unidad'] ?? '—') !== '—') {
        $cuerpo .= ' para <strong>' . htmlspecialchars((string) $c['unidad'], ENT_QUOTES, 'UTF-8') . '</strong>';
    }
    $cuerpo .= ', con vigencia desde el <strong>' . htmlspecialchars(fechaFormato($c['fecha_inicio']), ENT_QUOTES, 'UTF-8')
        . '</strong> hasta el <strong>' . htmlspecialchars(fechaFormato($c['fecha_fin']), ENT_QUOTES, 'UTF-8') . '</strong>.</p>';
    $cuerpo .= '<p style="margin:0 0 16px;">Queremos que se sienta acompañado/a en todo momento. '
        . 'Si necesita orientación sobre servicios, cuotas o cualquier detalle de su estadía, nuestro equipo estará encantado de atenderle.</p>';
    $cuerpo .= '<p style="margin:0;">Le deseamos una excelente experiencia junto al mar.</p>';
    $html = marina_alertas_html_wrap('¡Bienvenido/a a bordo!', $cuerpo);

    $res = marina_alertas_enviar(
        $pdo,
        'bienvenida',
        $dest['email'],
        $dest['nombre'],
        'Bienvenida a Marina — contrato #' . (int) $c['id'],
        $html,
        'contrato',
        $contratoId,
        true
    );

    return ['ok' => $res['ok'], 'error' => $res['error']];
}

/** @return array{ok: bool, error: ?string} */
function marina_alertas_enviar_estado_cuenta(PDO $pdo, int $contratoId, bool $forzar = false): array
{
    $dest = marina_alertas_email_cliente_contrato($pdo, $contratoId);
    if (!$dest) {
        return ['ok' => false, 'error' => 'El cliente no tiene correo electrónico registrado.'];
    }
    $html = marina_alertas_estado_cuenta_html($pdo, $contratoId);
    if ($html === null) {
        return ['ok' => false, 'error' => 'No se pudo generar el estado de cuenta.'];
    }

    $res = marina_alertas_enviar(
        $pdo,
        'estado_cuenta',
        $dest['email'],
        $dest['nombre'],
        'Estado de cuenta — contrato #' . $contratoId,
        $html,
        'contrato',
        $contratoId,
        false,
        $forzar
    );

    return ['ok' => $res['ok'], 'error' => $res['error']];
}

/** @return array{enviados: int, omitidos: int, errores: int, detalle: list<string>} */
function marina_alertas_cron_cuotas_vencidas(PDO $pdo): array
{
    $res = ['enviados' => 0, 'omitidos' => 0, 'errores' => 0, 'detalle' => []];
    if (!marina_alertas_activa($pdo, 'cuotas_vencidas')) {
        $res['detalle'][] = 'Alerta deshabilitada.';

        return $res;
    }

    $hoy = date('Y-m-d');
    $st = $pdo->prepare("
        SELECT DISTINCT co.id AS contrato_id
        FROM contratos co
        JOIN cuotas cu ON cu.contrato_id = co.id
        LEFT JOIN (
            SELECT cuota_id, SUM(monto) AS pagado FROM cuotas_movimientos GROUP BY cuota_id
        ) pm ON pm.cuota_id = cu.id
        WHERE cu.fecha_vencimiento < ?
          AND GREATEST(0, cu.monto - IF(cu.fecha_pago IS NOT NULL AND (pm.pagado IS NULL OR pm.pagado = 0), cu.monto, COALESCE(pm.pagado, 0))) > 0.01
    ");
    $st->execute([$hoy]);
    $ids = array_map(static fn($r) => (int) $r['contrato_id'], $st->fetchAll(PDO::FETCH_ASSOC));

    foreach ($ids as $contratoId) {
        $dest = marina_alertas_email_cliente_contrato($pdo, $contratoId);
        if (!$dest) {
            $res['omitidos']++;
            continue;
        }
        $vencidas = marina_alertas_cuotas_vencidas_contrato($pdo, $contratoId);
        if ($vencidas === []) {
            $res['omitidos']++;
            continue;
        }
        $unidad = marina_alertas_contrato_unidad($pdo, $contratoId);
        $cuerpo = marina_alertas_saludo($dest['nombre']);
        $cuerpo .= '<p style="margin:0 0 16px;">Con el mayor respeto le informamos que, al revisar su cuenta, '
            . 'notamos cuota(s) vencida(s) pendiente(s) de pago en el contrato <strong>#' . $contratoId . '</strong>'
            . ($unidad !== '—' ? ' (' . htmlspecialchars($unidad, ENT_QUOTES, 'UTF-8') . ')' : '') . '.</p>';
        $cuerpo .= '<p style="margin:0 0 16px;">Sabemos que a veces los plazos se nos escapan; '
            . 'le agradeceríamos mucho si pudiera regularizar a su conveniencia. '
            . 'Estamos a su disposición para cualquier aclaración.</p>';
        $cuerpo .= marina_alertas_tabla_cuotas_html($vencidas);
        $cuerpo .= '<p style="margin:0;">Gracias por su confianza y comprensión.</p>';
        $html = marina_alertas_html_wrap('Recordatorio amable — cuotas vencidas', $cuerpo);

        $send = marina_alertas_enviar(
            $pdo,
            'cuotas_vencidas',
            $dest['email'],
            $dest['nombre'],
            'Recordatorio amable — cuotas vencidas (contrato #' . $contratoId . ')',
            $html,
            'contrato',
            $contratoId,
            false
        );
        if ($send['ok']) {
            $res['enviados']++;
        } elseif ($send['error'] === 'Ya se envió este aviso.' || str_contains((string) $send['error'], 'excepciones')) {
            $res['omitidos']++;
        } else {
            $res['errores']++;
            $res['detalle'][] = 'Contrato #' . $contratoId . ': ' . ($send['error'] ?? 'Error');
        }
    }

    return $res;
}

/** @return array{enviados: int, omitidos: int, errores: int, detalle: list<string>} */
function marina_alertas_cron_contrato_por_vencer(PDO $pdo): array
{
    $res = ['enviados' => 0, 'omitidos' => 0, 'errores' => 0, 'detalle' => []];
    if (!marina_alertas_activa($pdo, 'contrato_por_vencer')) {
        $res['detalle'][] = 'Alerta deshabilitada.';

        return $res;
    }

    $fechaObjetivo = date('Y-m-d', strtotime('+7 days'));
    $st = $pdo->prepare("
        SELECT co.id
        FROM contratos co
        WHERE COALESCE(co.estado, 'activo') = 'activo'
          AND co.fecha_fin = ?
    ");
    $st->execute([$fechaObjetivo]);

    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $contratoId = (int) ($row['id'] ?? 0);
        $dest = marina_alertas_email_cliente_contrato($pdo, $contratoId);
        if (!$dest) {
            $res['omitidos']++;
            continue;
        }
        $data = marina_contrato_estado_cuenta_datos($pdo, $contratoId);
        $c = $data['contrato'] ?? [];
        $cuerpo = marina_alertas_saludo($dest['nombre']);
        $cuerpo .= '<p style="margin:0 0 16px;">Le escribimos con cariño para recordarle que su contrato '
            . '<strong>#' . $contratoId . '</strong> finalizará el próximo '
            . '<strong>' . htmlspecialchars(fechaFormato($fechaObjetivo), ENT_QUOTES, 'UTF-8') . '</strong>.</p>';
        $cuerpo .= '<p style="margin:0 0 16px;">Si desea renovar, ampliar su estadía o conversar opciones, '
            . 'con gusto le atenderemos con anticipación para que todo quede resuelto sin apuros.</p>';
        if (($c['unidad'] ?? '—') !== '—') {
            $cuerpo .= '<p style="margin:0 0 16px;color:#64748b;font-size:15px;">Unidad: '
                . htmlspecialchars((string) $c['unidad'], ENT_QUOTES, 'UTF-8') . '</p>';
        }
        $cuerpo .= '<p style="margin:0;">Será un placer seguir recibiéndole en nuestra marina.</p>';
        $html = marina_alertas_html_wrap('Su contrato finaliza en una semana', $cuerpo);

        $send = marina_alertas_enviar(
            $pdo,
            'contrato_por_vencer',
            $dest['email'],
            $dest['nombre'],
            'Recordatorio: su contrato finaliza en 7 días',
            $html,
            'contrato',
            $contratoId,
            true
        );
        if ($send['ok']) {
            $res['enviados']++;
        } elseif ($send['error'] === 'Ya se envió este aviso.' || str_contains((string) $send['error'], 'excepciones')) {
            $res['omitidos']++;
        } else {
            $res['errores']++;
            $res['detalle'][] = 'Contrato #' . $contratoId . ': ' . ($send['error'] ?? 'Error');
        }
    }

    return $res;
}

/** @return array{enviados: int, omitidos: int, errores: int, detalle: list<string>} */
function marina_alertas_cron_contratos_finalizados(PDO $pdo): array
{
    $res = ['enviados' => 0, 'omitidos' => 0, 'errores' => 0, 'detalle' => []];
    $st = $pdo->query("
        SELECT co.id
        FROM contratos co
        WHERE co.estado = 'terminado'
          AND DATE(co.updated_at) = CURDATE()
    ");
    $ids = array_map(static fn($r) => (int) $r['id'], $st ? $st->fetchAll(PDO::FETCH_ASSOC) : []);

    foreach ($ids as $contratoId) {
        $dest = marina_alertas_email_cliente_contrato($pdo, $contratoId);
        if (!$dest) {
            $res['omitidos']++;
            continue;
        }
        $saldos = marina_alertas_contrato_saldos($pdo, $contratoId);
        $data = marina_contrato_estado_cuenta_datos($pdo, $contratoId);
        $c = $data['contrato'] ?? [];
        $tieneDeuda = $saldos['saldo_total'] > 0.01;

        if ($tieneDeuda) {
            if (!marina_alertas_activa($pdo, 'contrato_finalizado_deuda')) {
                $res['omitidos']++;
                continue;
            }
            $codigo = 'contrato_finalizado_deuda';
            $titulo = 'Contrato finalizado — saldo pendiente';
            $asunto = 'Contrato finalizado — detalle de saldo pendiente';
            $cuerpo = marina_alertas_saludo($dest['nombre']);
            $cuerpo .= '<p style="margin:0 0 16px;">Esperamos que haya disfrutado su estadía con nosotros. '
                . 'Le confirmamos que su contrato <strong>#' . $contratoId . '</strong> ha finalizado.</p>';
            $cuerpo .= '<p style="margin:0 0 16px;">Al revisar su cuenta, observamos un saldo pendiente. '
                . 'Le agradeceríamos, cuando le sea posible, regularizar lo siguiente:</p>';
            $cuerpo .= '<ul style="margin:0 0 18px;padding-left:20px;">';
            if ($saldos['saldo_cuotas'] > 0.01) {
                $cuerpo .= '<li>Cuotas: <strong>' . dinero($saldos['saldo_cuotas']) . '</strong></li>';
            }
            if ($saldos['saldo_electricidad'] > 0.01) {
                $cuerpo .= '<li>Electricidad: <strong>' . dinero($saldos['saldo_electricidad']) . '</strong></li>';
            }
            $cuerpo .= '<li><strong>Total pendiente: ' . dinero($saldos['saldo_total']) . '</strong></li></ul>';
            $cuerpo .= '<p style="margin:0;">Quedamos atentos para ayudarle con el proceso de pago de la forma más cómoda para usted.</p>';
        } else {
            if (!marina_alertas_activa($pdo, 'contrato_finalizado')) {
                $res['omitidos']++;
                continue;
            }
            $codigo = 'contrato_finalizado';
            $titulo = 'Gracias por su visita';
            $asunto = 'Gracias por confiar en nosotros — hasta pronto';
            $cuerpo = marina_alertas_saludo($dest['nombre']);
            $cuerpo .= '<p style="margin:0 0 16px;">Su contrato <strong>#' . $contratoId . '</strong> ha concluido '
                . 'y queremos agradecerle sinceramente por haber compartido este tiempo en nuestra marina.</p>';
            if (($c['unidad'] ?? '—') !== '—') {
                $cuerpo .= '<p style="margin:0 0 16px;">Fue un honor atenderle en '
                    . htmlspecialchars((string) $c['unidad'], ENT_QUOTES, 'UTF-8') . '.</p>';
            }
            $cuerpo .= '<p style="margin:0 0 16px;">Le deseamos fair winds and following seas — '
                . 'vuelva pronto; siempre será bienvenido/a.</p>';
            $cuerpo .= '<p style="margin:0;">Con cariño, todo el equipo de Marina.</p>';
        }

        $html = marina_alertas_html_wrap($titulo, $cuerpo);
        $send = marina_alertas_enviar(
            $pdo,
            $codigo,
            $dest['email'],
            $dest['nombre'],
            $asunto,
            $html,
            'contrato',
            $contratoId,
            true
        );
        if ($send['ok']) {
            $res['enviados']++;
        } elseif ($send['error'] === 'Ya se envió este aviso.' || str_contains((string) $send['error'], 'excepciones')) {
            $res['omitidos']++;
        } else {
            $res['errores']++;
            $res['detalle'][] = 'Contrato #' . $contratoId . ': ' . ($send['error'] ?? 'Error');
        }
    }

    return $res;
}

/** @return array<string, mixed> */
function marina_alertas_ejecutar_diarias(PDO $pdo): array
{
    return [
        'fecha' => date('Y-m-d H:i:s'),
        'cuotas_vencidas' => marina_alertas_cron_cuotas_vencidas($pdo),
        'contrato_por_vencer' => marina_alertas_cron_contrato_por_vencer($pdo),
        'contratos_finalizados' => marina_alertas_cron_contratos_finalizados($pdo),
    ];
}

/**
 * Vista previa del correo con datos de ejemplo (sin enviar).
 *
 * @return array{ok: bool, codigo: string, etiqueta: string, asunto: string, html: string, nota: string}|null
 */
function marina_alertas_preview(string $codigo): ?array
{
    $defs = marina_alertas_definiciones();
    if (!isset($defs[$codigo])) {
        return null;
    }

    $nombre = 'María González';
    $contratoId = 123;
    $unidad = 'Muelle A / Slip 12';
    $fechaFin = date('Y-m-d', strtotime('+7 days'));
    $nota = 'Vista previa con datos de ejemplo. El correo real usará la información del cliente y contrato.';

    switch ($codigo) {
        case 'cuotas_vencidas':
            $vencidas = [
                ['numero' => 2, 'vencimiento' => date('Y-m-d', strtotime('-15 days')), 'saldo' => 450.00],
                ['numero' => 3, 'vencimiento' => date('Y-m-d', strtotime('-5 days')), 'saldo' => 450.00],
            ];
            $cuerpo = marina_alertas_saludo($nombre);
            $cuerpo .= '<p style="margin:0 0 16px;">Con el mayor respeto le informamos que, al revisar su cuenta, '
                . 'notamos cuota(s) vencida(s) pendiente(s) de pago en el contrato <strong>#' . $contratoId . '</strong>'
                . ' (' . htmlspecialchars($unidad, ENT_QUOTES, 'UTF-8') . ').</p>';
            $cuerpo .= '<p style="margin:0 0 16px;">Sabemos que a veces los plazos se nos escapan; '
                . 'le agradeceríamos mucho si pudiera regularizar a su conveniencia. '
                . 'Estamos a su disposición para cualquier aclaración.</p>';
            $cuerpo .= marina_alertas_tabla_cuotas_html($vencidas);
            $cuerpo .= '<p style="margin:0;">Gracias por su confianza y comprensión.</p>';
            return [
                'ok' => true,
                'codigo' => $codigo,
                'etiqueta' => $defs[$codigo]['etiqueta'],
                'asunto' => 'Recordatorio amable — cuotas vencidas (contrato #' . $contratoId . ')',
                'html' => marina_alertas_html_wrap('Recordatorio amable — cuotas vencidas', $cuerpo),
                'nota' => $nota,
            ];

        case 'contrato_por_vencer':
            $cuerpo = marina_alertas_saludo($nombre);
            $cuerpo .= '<p style="margin:0 0 16px;">Le escribimos con cariño para recordarle que su contrato '
                . '<strong>#' . $contratoId . '</strong> finalizará el próximo '
                . '<strong>' . htmlspecialchars(fechaFormato($fechaFin), ENT_QUOTES, 'UTF-8') . '</strong>.</p>';
            $cuerpo .= '<p style="margin:0 0 16px;">Si desea renovar, ampliar su estadía o conversar opciones, '
                . 'con gusto le atenderemos con anticipación para que todo quede resuelto sin apuros.</p>';
            $cuerpo .= '<p style="margin:0 0 16px;color:#64748b;font-size:15px;">Unidad: '
                . htmlspecialchars($unidad, ENT_QUOTES, 'UTF-8') . '</p>';
            $cuerpo .= '<p style="margin:0;">Será un placer seguir recibiéndole en nuestra marina.</p>';
            return [
                'ok' => true,
                'codigo' => $codigo,
                'etiqueta' => $defs[$codigo]['etiqueta'],
                'asunto' => 'Recordatorio: su contrato finaliza en 7 días',
                'html' => marina_alertas_html_wrap('Su contrato finaliza en una semana', $cuerpo),
                'nota' => $nota,
            ];

        case 'contrato_finalizado':
            $cuerpo = marina_alertas_saludo($nombre);
            $cuerpo .= '<p style="margin:0 0 16px;">Su contrato <strong>#' . $contratoId . '</strong> ha concluido '
                . 'y queremos agradecerle sinceramente por haber compartido este tiempo en nuestra marina.</p>';
            $cuerpo .= '<p style="margin:0 0 16px;">Fue un honor atenderle en '
                . htmlspecialchars($unidad, ENT_QUOTES, 'UTF-8') . '.</p>';
            $cuerpo .= '<p style="margin:0 0 16px;">Le deseamos fair winds and following seas — '
                . 'vuelva pronto; siempre será bienvenido/a.</p>';
            $cuerpo .= '<p style="margin:0;">Con cariño, todo el equipo de Marina.</p>';
            return [
                'ok' => true,
                'codigo' => $codigo,
                'etiqueta' => $defs[$codigo]['etiqueta'],
                'asunto' => 'Gracias por confiar en nosotros — hasta pronto',
                'html' => marina_alertas_html_wrap('Gracias por su visita', $cuerpo),
                'nota' => $nota,
            ];

        case 'contrato_finalizado_deuda':
            $cuerpo = marina_alertas_saludo($nombre);
            $cuerpo .= '<p style="margin:0 0 16px;">Esperamos que haya disfrutado su estadía con nosotros. '
                . 'Le confirmamos que su contrato <strong>#' . $contratoId . '</strong> ha finalizado.</p>';
            $cuerpo .= '<p style="margin:0 0 16px;">Al revisar su cuenta, observamos un saldo pendiente. '
                . 'Le agradeceríamos, cuando le sea posible, regularizar lo siguiente:</p>';
            $cuerpo .= '<ul style="margin:0 0 18px;padding-left:20px;">'
                . '<li>Cuotas: <strong>' . dinero(900.00) . '</strong></li>'
                . '<li>Electricidad: <strong>' . dinero(125.50) . '</strong></li>'
                . '<li><strong>Total pendiente: ' . dinero(1025.50) . '</strong></li></ul>';
            $cuerpo .= '<p style="margin:0;">Quedamos atentos para ayudarle con el proceso de pago de la forma más cómoda para usted.</p>';
            return [
                'ok' => true,
                'codigo' => $codigo,
                'etiqueta' => $defs[$codigo]['etiqueta'],
                'asunto' => 'Contrato finalizado — detalle de saldo pendiente',
                'html' => marina_alertas_html_wrap('Contrato finalizado — saldo pendiente', $cuerpo),
                'nota' => $nota,
            ];

        case 'bienvenida':
            $cuerpo = marina_alertas_saludo($nombre);
            $cuerpo .= '<p style="margin:0 0 16px;">Es un verdadero placer darle la bienvenida a nuestra marina. '
                . 'Su contrato <strong>#' . $contratoId . '</strong> ha quedado registrado para '
                . '<strong>' . htmlspecialchars($unidad, ENT_QUOTES, 'UTF-8') . '</strong>, con vigencia desde el '
                . '<strong>' . htmlspecialchars(fechaFormato(date('Y-m-d')), ENT_QUOTES, 'UTF-8') . '</strong> hasta el '
                . '<strong>' . htmlspecialchars(fechaFormato($fechaFin), ENT_QUOTES, 'UTF-8') . '</strong>.</p>';
            $cuerpo .= '<p style="margin:0 0 16px;">Queremos que se sienta acompañado/a en todo momento. '
                . 'Si necesita orientación sobre servicios, cuotas o cualquier detalle de su estadía, nuestro equipo estará encantado de atenderle.</p>';
            $cuerpo .= '<p style="margin:0;">Le deseamos una excelente experiencia junto al mar.</p>';
            return [
                'ok' => true,
                'codigo' => $codigo,
                'etiqueta' => $defs[$codigo]['etiqueta'],
                'asunto' => 'Bienvenida a Marina — contrato #' . $contratoId,
                'html' => marina_alertas_html_wrap('¡Bienvenido/a a bordo!', $cuerpo),
                'nota' => $nota,
            ];

        case 'estado_cuenta':
            $cuerpo = marina_alertas_saludo($nombre);
            $cuerpo .= '<p style="margin:0 0 16px;">Le compartimos el estado de cuenta de su contrato '
                . '<strong>#' . $contratoId . '</strong> (' . htmlspecialchars($unidad, ENT_QUOTES, 'UTF-8') . '), '
                . 'correspondiente al período '
                . htmlspecialchars(fechaFormato(date('Y-m-d', strtotime('-3 months'))) . ' – ' . fechaFormato($fechaFin), ENT_QUOTES, 'UTF-8') . '.</p>';
            $cuerpo .= '<p style="margin:0 0 8px;font-size:15px;color:#475569;"><strong>Resumen cuotas</strong></p>';
            $cuerpo .= '<p style="margin:0 0 16px;">Total: ' . dinero(1350.00) . ' · Pagado: ' . dinero(450.00)
                . ' · <strong>Saldo: ' . dinero(900.00) . '</strong></p>';
            $cuerpo .= marina_alertas_tabla_cuotas_html([
                ['numero' => 1, 'vencimiento' => date('Y-m-d', strtotime('-60 days')), 'saldo' => 0],
                ['numero' => 2, 'vencimiento' => date('Y-m-d', strtotime('-30 days')), 'saldo' => 450.00],
                ['numero' => 3, 'vencimiento' => date('Y-m-d', strtotime('+30 days')), 'saldo' => 450.00],
            ]);
            $cuerpo .= '<p style="margin:0 0 8px;font-size:15px;color:#475569;"><strong>Electricidad</strong></p>';
            $cuerpo .= '<p style="margin:0 0 16px;">Facturado: ' . dinero(125.50) . ' · Pagado: ' . dinero(0)
                . ' · Saldo: ' . dinero(125.50) . '</p>';
            $cuerpo .= '<p style="margin:0;font-size:15px;color:#64748b;">Ante cualquier duda sobre su cuenta, estaremos encantados de ayudarle.</p>';
            return [
                'ok' => true,
                'codigo' => $codigo,
                'etiqueta' => $defs[$codigo]['etiqueta'],
                'asunto' => 'Estado de cuenta — contrato #' . $contratoId,
                'html' => marina_alertas_html_wrap('Estado de cuenta', $cuerpo),
                'nota' => $nota,
            ];

        default:
            return null;
    }
}

function marina_alertas_seed_config(PDO $pdo): void
{
    foreach (marina_alertas_definiciones() as $codigo => $def) {
        try {
            $st = $pdo->prepare('SELECT 1 FROM alertas_config WHERE codigo = ? LIMIT 1');
            $st->execute([$codigo]);
            if ($st->fetchColumn()) {
                continue;
            }
            $pdo->prepare('INSERT INTO alertas_config (codigo, etiqueta, programada, activo) VALUES (?, ?, ?, 1)')
                ->execute([$codigo, $def['etiqueta'], $def['programada'] ? 1 : 0]);
        } catch (Throwable $e) {
            // tabla ausente
        }
    }
}
