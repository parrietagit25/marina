<?php
/**
 * Marketing: plantillas, campañas y envío vía Resend.
 */
declare(strict_types=1);

function marina_config_valor(PDO $pdo, string $clave, string $default = ''): string
{
    try {
        $st = $pdo->prepare('SELECT valor FROM marina_config WHERE clave = ? LIMIT 1');
        $st->execute([$clave]);
        $v = $st->fetchColumn();
        if ($v === false || $v === null) {
            return $default;
        }

        return (string) $v;
    } catch (Throwable $e) {
        return $default;
    }
}

function marina_config_guardar(PDO $pdo, string $clave, string $valor): void
{
    $pdo->prepare('INSERT INTO marina_config (clave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)')
        ->execute([$clave, $valor]);
}

/** @return array{api_key: string, from_email: string} */
function marina_marketing_resend_config(PDO $pdo): array
{
    return [
        'api_key' => trim(marina_config_valor($pdo, 'resend_api_key', '')),
        'from_email' => trim(marina_config_valor($pdo, 'resend_from_email', '')),
    ];
}

function marina_marketing_resend_configurado(PDO $pdo): bool
{
    $c = marina_marketing_resend_config($pdo);

    return $c['api_key'] !== '' && $c['from_email'] !== '';
}

/**
 * @return list<string>
 */
function marina_marketing_parsear_emails_manual(string $texto): array
{
    $texto = str_replace(["\r\n", "\r", "\n", ';'], [',', ',', ',', ','], $texto);
    $partes = explode(',', $texto);
    $out = [];
    foreach ($partes as $p) {
        $p = marina_normalizar_email_cliente(trim($p));
        if ($p !== '' && filter_var($p, FILTER_VALIDATE_EMAIL)) {
            $out[$p] = $p;
        }
    }

    return array_values($out);
}

/**
 * @param list<string> $tiposFiltro códigos CAT, MYT, etc. Vacío = todos con email
 * @return list<array{cliente_id: int, email: string, nombre: string}>
 */
function marina_marketing_clientes_destinatarios(PDO $pdo, array $tiposFiltro): array
{
    $sql = "SELECT id, nombre, email, tipo_embarcacion FROM clientes WHERE email IS NOT NULL AND TRIM(email) <> ''";
    $params = [];
    if ($tiposFiltro !== []) {
        $placeholders = implode(',', array_fill(0, count($tiposFiltro), '?'));
        $sql .= " AND tipo_embarcacion IN ($placeholders)";
        $params = $tiposFiltro;
    }
    $sql .= ' ORDER BY nombre';
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $out = [];
    $seen = [];
    while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
        $email = marina_normalizar_email_cliente((string) ($row['email'] ?? ''));
        if ($email === '' || isset($seen[$email])) {
            continue;
        }
        $seen[$email] = true;
        $out[] = [
            'cliente_id' => (int) ($row['id'] ?? 0),
            'email' => $email,
            'nombre' => (string) ($row['nombre'] ?? ''),
        ];
    }

    return $out;
}

/**
 * @param list<string> $tiposFiltro
 * @param list<string> $emailsManual
 * @return list<array{cliente_id: ?int, email: string, nombre: string}>
 */
function marina_marketing_armar_destinatarios(PDO $pdo, string $modo, array $tiposFiltro, array $emailsManual): array
{
    $modo = in_array($modo, ['clientes', 'manual', 'mixto'], true) ? $modo : 'clientes';
    $map = [];

    if ($modo === 'clientes' || $modo === 'mixto') {
        foreach (marina_marketing_clientes_destinatarios($pdo, $tiposFiltro) as $d) {
            $map[$d['email']] = $d;
        }
    }
    if ($modo === 'manual' || $modo === 'mixto') {
        foreach ($emailsManual as $email) {
            $email = marina_normalizar_email_cliente($email);
            if ($email === '') {
                continue;
            }
            if (!isset($map[$email])) {
                $map[$email] = ['cliente_id' => null, 'email' => $email, 'nombre' => ''];
            }
        }
    }

    return array_values($map);
}

function marina_marketing_personalizar(string $texto, string $nombre): string
{
    return str_replace(
        ['{{nombre}}', '{{NOMBRE}}', '{{Nombre}}'],
        [$nombre, $nombre, $nombre],
        $texto
    );
}

/**
 * @return array{ok: bool, id: ?string, error: ?string}
 */
function marina_resend_enviar_email(string $apiKey, string $from, string $to, string $subject, string $html): array
{
    if ($apiKey === '' || $from === '' || $to === '') {
        return ['ok' => false, 'id' => null, 'error' => 'Configuración Resend incompleta o destinatario vacío.'];
    }

    $payload = json_encode([
        'from' => $from,
        'to' => [$to],
        'subject' => $subject,
        'html' => $html,
    ], JSON_UNESCAPED_UNICODE);

    if (!function_exists('curl_init')) {
        return ['ok' => false, 'id' => null, 'error' => 'cURL no disponible en el servidor.'];
    }

    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_TIMEOUT => 30,
    ]);
    $body = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($body === false) {
        return ['ok' => false, 'id' => null, 'error' => $curlErr ?: 'Error de red al contactar Resend.'];
    }

    $data = json_decode((string) $body, true);
    if ($httpCode >= 200 && $httpCode < 300 && is_array($data) && !empty($data['id'])) {
        return ['ok' => true, 'id' => (string) $data['id'], 'error' => null];
    }

    $errMsg = 'Resend HTTP ' . $httpCode;
    if (is_array($data) && !empty($data['message'])) {
        $errMsg = (string) $data['message'];
    } elseif (is_string($body) && strlen($body) < 500) {
        $errMsg = $body;
    }

    return ['ok' => false, 'id' => null, 'error' => $errMsg];
}

function marina_marketing_actualizar_totales_campana(PDO $pdo, int $campanaId): void
{
    $st = $pdo->prepare("
        SELECT
            COUNT(*) AS total,
            SUM(estado = 'enviado') AS enviados,
            SUM(estado = 'fallido') AS fallidos,
            SUM(estado = 'pendiente') AS pendientes
        FROM marketing_envios WHERE campana_id = ?
    ");
    $st->execute([$campanaId]);
    $r = $st->fetch(PDO::FETCH_ASSOC) ?: [];
    $total = (int) ($r['total'] ?? 0);
    $enviados = (int) ($r['enviados'] ?? 0);
    $fallidos = (int) ($r['fallidos'] ?? 0);
    $pendientes = (int) ($r['pendientes'] ?? 0);

    $estado = 'enviando';
    if ($pendientes === 0 && $total > 0) {
        $estado = $fallidos > 0 && $enviados === 0 ? 'error' : 'completada';
    } elseif ($total === 0) {
        $estado = 'error';
    }

    $pdo->prepare('
        UPDATE marketing_campanas
        SET total_destinatarios = ?, total_enviados = ?, total_fallidos = ?, estado = ?,
            finalizado_at = CASE WHEN ? = 0 THEN NOW() ELSE finalizado_at END
        WHERE id = ?
    ')->execute([$total, $enviados, $fallidos, $estado, $pendientes, $campanaId]);
}

/**
 * Procesa un lote de envíos pendientes.
 *
 * @return array{procesados: int, pendientes: int, enviados: int, fallidos: int}
 */
function marina_marketing_procesar_lote(PDO $pdo, int $campanaId, int $limite = 8): array
{
    $cfg = marina_marketing_resend_config($pdo);
    if ($cfg['api_key'] === '' || $cfg['from_email'] === '') {
        throw new RuntimeException('Configure la API key y el remitente de Resend en Configuración.');
    }

    $stCamp = $pdo->prepare('SELECT c.*, p.asunto, p.cuerpo_html FROM marketing_campanas c JOIN marketing_plantillas p ON p.id = c.plantilla_id WHERE c.id = ?');
    $stCamp->execute([$campanaId]);
    $camp = $stCamp->fetch(PDO::FETCH_ASSOC);
    if (!$camp) {
        throw new RuntimeException('Campaña no encontrada.');
    }

    $pdo->prepare("UPDATE marketing_campanas SET estado = 'enviando', iniciado_at = COALESCE(iniciado_at, NOW()) WHERE id = ?")
        ->execute([$campanaId]);

    $st = $pdo->prepare("SELECT * FROM marketing_envios WHERE campana_id = ? AND estado = 'pendiente' ORDER BY id LIMIT " . (int) $limite);
    $st->execute([$campanaId]);
    $pendientes = $st->fetchAll(PDO::FETCH_ASSOC);

    $asuntoBase = (string) ($camp['asunto'] ?? '');
    $cuerpoBase = (string) ($camp['cuerpo_html'] ?? '');
    $procesados = 0;

    foreach ($pendientes as $env) {
        $procesados++;
        $nombre = (string) ($env['nombre_dest'] ?? '');
        $email = (string) ($env['email'] ?? '');
        $asunto = marina_marketing_personalizar($asuntoBase, $nombre);
        $html = marina_marketing_personalizar($cuerpoBase, $nombre);

        $res = marina_resend_enviar_email($cfg['api_key'], $cfg['from_email'], $email, $asunto, $html);
        if ($res['ok']) {
            $pdo->prepare("UPDATE marketing_envios SET estado = 'enviado', resend_id = ?, error_mensaje = NULL, enviado_at = NOW() WHERE id = ?")
                ->execute([$res['id'], (int) $env['id']]);
        } else {
            $pdo->prepare("UPDATE marketing_envios SET estado = 'fallido', error_mensaje = ?, enviado_at = NOW() WHERE id = ?")
                ->execute([$res['error'], (int) $env['id']]);
        }
        usleep(150000);
    }

    marina_marketing_actualizar_totales_campana($pdo, $campanaId);

    $stP = $pdo->prepare("SELECT COUNT(*) FROM marketing_envios WHERE campana_id = ? AND estado = 'pendiente'");
    $stP->execute([$campanaId]);
    $quedan = (int) $stP->fetchColumn();

    $stT = $pdo->prepare('SELECT total_enviados, total_fallidos FROM marketing_campanas WHERE id = ?');
    $stT->execute([$campanaId]);
    $tot = $stT->fetch(PDO::FETCH_ASSOC) ?: [];

    return [
        'procesados' => $procesados,
        'pendientes' => $quedan,
        'enviados' => (int) ($tot['total_enviados'] ?? 0),
        'fallidos' => (int) ($tot['total_fallidos'] ?? 0),
    ];
}

function marina_marketing_estado_etiqueta(string $estado): string
{
    $map = [
        'pendiente' => 'Pendiente',
        'enviando' => 'Enviando',
        'completada' => 'Completada',
        'error' => 'Con errores',
        'enviado' => 'Enviado',
        'fallido' => 'No enviado',
    ];

    return $map[$estado] ?? $estado;
}

/**
 * Plantillas HTML base para el editor de correos (inline styles, ~600px).
 *
 * @return array<string, array{label: string, grupo: string, html: string}>
 */
function marina_marketing_plantillas_base(): array
{
    $pie = '<div style="padding:20px 24px;text-align:center;font-size:12px;color:#94a3b8;background:#f1f5f9;border-top:1px solid #e2e8f0;">'
        . '© Marina · Coronado Vista Mar<br>'
        . '<span style="font-size:11px;">Si no desea recibir estos correos, responda a este mensaje.</span></div>';

    return [
        'promo' => [
            'label' => 'Promo marina',
            'grupo' => 'Promociones',
            'html' => '<div style="max-width:600px;margin:0 auto;font-family:Arial,Helvetica,sans-serif;background:#ffffff;border:1px solid #e2e8f0;">'
                . '<div style="background:linear-gradient(135deg,#0f172a 0%,#1e3a5f 100%);padding:28px 24px;text-align:center;">'
                . '<h1 style="margin:0;color:#ffffff;font-size:26px;font-weight:700;">Marina</h1>'
                . '<p style="margin:8px 0 0;color:#94a3b8;font-size:14px;">Coronado Vista Mar</p></div>'
                . '<div style="padding:32px 28px;background:#f8fafc;color:#1e293b;line-height:1.6;">'
                . '<p style="margin:0 0 16px;font-size:16px;">Hola <strong>{{nombre}}</strong>,</p>'
                . '<p style="margin:0 0 20px;font-size:15px;">Tenemos novedades especiales para usted. Aproveche esta promoción por tiempo limitado.</p>'
                . '<p style="text-align:center;margin:28px 0;">'
                . '<a href="#" style="background:#0d6efd;color:#ffffff;padding:14px 32px;text-decoration:none;border-radius:8px;font-weight:600;display:inline-block;font-size:15px;">Ver promoción</a></p>'
                . '<p style="margin:0;font-size:14px;color:#64748b;">Quedamos atentos a sus consultas.</p></div>'
                . $pie . '</div>',
        ],
        'descuento' => [
            'label' => 'Oferta con descuento',
            'grupo' => 'Promociones',
            'html' => '<div style="max-width:600px;margin:0 auto;font-family:Arial,Helvetica,sans-serif;background:#ffffff;border:1px solid #fecaca;">'
                . '<div style="background:#dc2626;padding:20px 24px;text-align:center;">'
                . '<p style="margin:0 0 6px;color:#fecaca;font-size:13px;text-transform:uppercase;letter-spacing:1px;">Oferta exclusiva</p>'
                . '<p style="margin:0;color:#ffffff;font-size:42px;font-weight:800;line-height:1;">20% OFF</p>'
                . '<p style="margin:8px 0 0;color:#fecaca;font-size:14px;">Solo para clientes de la marina</p></div>'
                . '<div style="padding:28px 24px;color:#1e293b;line-height:1.6;">'
                . '<p style="margin:0 0 14px;font-size:16px;">Hola <strong>{{nombre}}</strong>,</p>'
                . '<p style="margin:0 0 18px;font-size:15px;">Por tiempo limitado, disfrute de un <strong>20% de descuento</strong> en [servicio o producto]. Válido hasta el <strong>[fecha]</strong>.</p>'
                . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:24px 0;background:#fef2f2;border-radius:8px;border:1px dashed #fca5a5;">'
                . '<tr><td style="padding:16px 20px;text-align:center;">'
                . '<p style="margin:0;font-size:13px;color:#991b1b;">Código promocional</p>'
                . '<p style="margin:6px 0 0;font-size:22px;font-weight:700;color:#dc2626;letter-spacing:2px;">MARINA20</p></td></tr></table>'
                . '<p style="text-align:center;margin:24px 0 0;">'
                . '<a href="#" style="background:#dc2626;color:#ffffff;padding:14px 36px;text-decoration:none;border-radius:8px;font-weight:600;display:inline-block;">Aprovechar oferta</a></p></div>'
                . $pie . '</div>',
        ],
        'temporada' => [
            'label' => 'Temporada alta',
            'grupo' => 'Promociones',
            'html' => '<div style="max-width:600px;margin:0 auto;font-family:Arial,Helvetica,sans-serif;background:#ffffff;">'
                . '<div style="background:linear-gradient(180deg,#0ea5e9 0%,#0284c7 100%);padding:36px 24px;text-align:center;">'
                . '<p style="margin:0 0 8px;color:#e0f2fe;font-size:13px;text-transform:uppercase;letter-spacing:1.5px;">Temporada 2026</p>'
                . '<h1 style="margin:0;color:#ffffff;font-size:28px;font-weight:700;">¡El mar nos espera!</h1></div>'
                . '<div style="padding:30px 26px;color:#0f172a;line-height:1.65;background:#f0f9ff;">'
                . '<p style="margin:0 0 16px;font-size:16px;">Estimado/a <strong>{{nombre}}</strong>,</p>'
                . '<p style="margin:0 0 20px;font-size:15px;">La temporada está por comenzar. Reserve su espacio, planifique mantenimiento y aproveche tarifas preferenciales para socios.</p>'
                . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>'
                . '<td width="33%" style="padding:8px;text-align:center;vertical-align:top;">'
                . '<div style="background:#ffffff;border-radius:8px;padding:16px 10px;border:1px solid #bae6fd;">'
                . '<p style="margin:0;font-size:24px;">⚓</p><p style="margin:8px 0 0;font-size:13px;font-weight:600;">Amarre</p></div></td>'
                . '<td width="33%" style="padding:8px;text-align:center;vertical-align:top;">'
                . '<div style="background:#ffffff;border-radius:8px;padding:16px 10px;border:1px solid #bae6fd;">'
                . '<p style="margin:0;font-size:24px;">⛽</p><p style="margin:8px 0 0;font-size:13px;font-weight:600;">Combustible</p></div></td>'
                . '<td width="33%" style="padding:8px;text-align:center;vertical-align:top;">'
                . '<div style="background:#ffffff;border-radius:8px;padding:16px 10px;border:1px solid #bae6fd;">'
                . '<p style="margin:0;font-size:24px;">🔧</p><p style="margin:8px 0 0;font-size:13px;font-weight:600;">Servicios</p></div></td>'
                . '</tr></table>'
                . '<p style="text-align:center;margin:28px 0 0;">'
                . '<a href="#" style="background:#0284c7;color:#ffffff;padding:14px 32px;text-decoration:none;border-radius:8px;font-weight:600;display:inline-block;">Ver beneficios</a></p></div>'
                . $pie . '</div>',
        ],
        'combustible' => [
            'label' => 'Promo combustible',
            'grupo' => 'Promociones',
            'html' => '<div style="max-width:600px;margin:0 auto;font-family:Arial,Helvetica,sans-serif;background:#ffffff;border:1px solid #d1d5db;">'
                . '<div style="background:#1e293b;padding:24px 28px;">'
                . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>'
                . '<td style="vertical-align:middle;"><p style="margin:0;color:#f8fafc;font-size:22px;font-weight:700;">⛽ Combustible marina</p>'
                . '<p style="margin:6px 0 0;color:#94a3b8;font-size:14px;">Precio preferencial para socios</p></td>'
                . '<td style="text-align:right;vertical-align:middle;"><span style="background:#f59e0b;color:#1e293b;padding:8px 14px;border-radius:6px;font-weight:700;font-size:14px;">AHORRE</span></td>'
                . '</tr></table></div>'
                . '<div style="padding:28px 26px;color:#334155;line-height:1.6;">'
                . '<p style="margin:0 0 14px;font-size:16px;">Hola <strong>{{nombre}}</strong>,</p>'
                . '<p style="margin:0 0 18px;font-size:15px;">Recargue en nuestra estación y obtenga condiciones especiales en diésel marino. Presente este correo al momento de la compra.</p>'
                . '<p style="margin:0 0 8px;font-size:14px;color:#64748b;">Horario de estación:</p>'
                . '<p style="margin:0 0 20px;font-size:15px;font-weight:600;">Lunes a domingo · 6:00 a.m. – 8:00 p.m.</p>'
                . '<p style="text-align:center;margin:0;">'
                . '<a href="#" style="background:#f59e0b;color:#1e293b;padding:14px 32px;text-decoration:none;border-radius:8px;font-weight:700;display:inline-block;">Ver detalles</a></p></div>'
                . $pie . '</div>',
        ],
        'evento' => [
            'label' => 'Invitación a evento',
            'grupo' => 'Comunicación',
            'html' => '<div style="max-width:600px;margin:0 auto;font-family:Arial,Helvetica,sans-serif;background:#ffffff;border:1px solid #e2e8f0;">'
                . '<div style="padding:32px 28px 20px;text-align:center;background:#faf5ff;">'
                . '<p style="margin:0 0 10px;color:#7c3aed;font-size:12px;text-transform:uppercase;letter-spacing:1.5px;font-weight:600;">Está invitado/a</p>'
                . '<h1 style="margin:0;color:#1e1b4b;font-size:26px;font-weight:700;">[Nombre del evento]</h1>'
                . '<p style="margin:12px 0 0;color:#6b7280;font-size:15px;">Una experiencia exclusiva para la comunidad náutica</p></div>'
                . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#ffffff;">'
                . '<tr><td style="padding:20px 28px;border-top:1px solid #ede9fe;border-bottom:1px solid #ede9fe;">'
                . '<table role="presentation" width="100%"><tr>'
                . '<td width="50%" style="padding:8px 12px 8px 0;vertical-align:top;">'
                . '<p style="margin:0;font-size:12px;color:#7c3aed;text-transform:uppercase;">Fecha</p>'
                . '<p style="margin:4px 0 0;font-size:16px;font-weight:600;color:#1e293b;">Sábado 15 de junio</p></td>'
                . '<td width="50%" style="padding:8px 0 8px 12px;vertical-align:top;border-left:1px solid #ede9fe;">'
                . '<p style="margin:0;font-size:12px;color:#7c3aed;text-transform:uppercase;">Hora</p>'
                . '<p style="margin:4px 0 0;font-size:16px;font-weight:600;color:#1e293b;">10:00 a.m.</p></td>'
                . '</tr></table></td></tr></table>'
                . '<div style="padding:28px 28px;color:#374151;line-height:1.65;">'
                . '<p style="margin:0 0 14px;font-size:16px;">Estimado/a <strong>{{nombre}}</strong>,</p>'
                . '<p style="margin:0 0 20px;font-size:15px;">Le invitamos a participar en nuestro próximo evento en la marina. Cupos limitados — confirme su asistencia a la brevedad.</p>'
                . '<p style="text-align:center;margin:0;">'
                . '<a href="#" style="background:#7c3aed;color:#ffffff;padding:14px 36px;text-decoration:none;border-radius:8px;font-weight:600;display:inline-block;">Confirmar asistencia</a></p></div>'
                . $pie . '</div>',
        ],
        'bienvenida' => [
            'label' => 'Bienvenida cliente',
            'grupo' => 'Comunicación',
            'html' => '<div style="max-width:600px;margin:0 auto;font-family:Arial,Helvetica,sans-serif;background:#ffffff;">'
                . '<div style="background:linear-gradient(135deg,#059669 0%,#047857 100%);padding:32px 24px;text-align:center;">'
                . '<h1 style="margin:0;color:#ffffff;font-size:26px;font-weight:700;">¡Bienvenido/a a bordo!</h1>'
                . '<p style="margin:10px 0 0;color:#d1fae5;font-size:15px;">Marina · Coronado Vista Mar</p></div>'
                . '<div style="padding:30px 28px;color:#1f2937;line-height:1.65;">'
                . '<p style="margin:0 0 16px;font-size:17px;">Hola <strong>{{nombre}}</strong>,</p>'
                . '<p style="margin:0 0 18px;font-size:15px;">Es un placer darle la bienvenida a nuestra marina. A partir de hoy forma parte de una comunidad que comparte la pasión por el mar.</p>'
                . '<ul style="margin:0 0 22px;padding-left:20px;font-size:15px;color:#4b5563;">'
                . '<li style="margin-bottom:8px;">Horarios y servicios disponibles</li>'
                . '<li style="margin-bottom:8px;">Contacto de administración y seguridad</li>'
                . '<li>Beneficios exclusivos para socios</li></ul>'
                . '<p style="text-align:center;margin:0;">'
                . '<a href="#" style="background:#059669;color:#ffffff;padding:14px 32px;text-decoration:none;border-radius:8px;font-weight:600;display:inline-block;">Conocer la marina</a></p></div>'
                . $pie . '</div>',
        ],
        'recordatorio' => [
            'label' => 'Recordatorio amable',
            'grupo' => 'Comunicación',
            'html' => '<div style="max-width:600px;margin:0 auto;font-family:Arial,Helvetica,sans-serif;background:#ffffff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;">'
                . '<div style="padding:24px 28px;background:#fffbeb;border-bottom:3px solid #f59e0b;">'
                . '<p style="margin:0;font-size:18px;font-weight:600;color:#92400e;">📋 Recordatorio</p></div>'
                . '<div style="padding:28px 28px;color:#374151;line-height:1.65;">'
                . '<p style="margin:0 0 14px;font-size:16px;">Estimado/a <strong>{{nombre}}</strong>,</p>'
                . '<p style="margin:0 0 18px;font-size:15px;">Le recordamos amablemente que tiene pendiente: <strong>[descripción del trámite o pago]</strong>.</p>'
                . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f9fafb;border-radius:6px;border:1px solid #e5e7eb;">'
                . '<tr><td style="padding:16px 20px;">'
                . '<p style="margin:0 0 4px;font-size:13px;color:#6b7280;">Fecha límite</p>'
                . '<p style="margin:0;font-size:16px;font-weight:600;color:#111827;">[fecha]</p></td></tr></table>'
                . '<p style="margin:20px 0 0;font-size:14px;color:#6b7280;">Si ya realizó el trámite, puede ignorar este mensaje. Ante cualquier duda, estamos para ayudarle.</p></div>'
                . $pie . '</div>',
        ],
        'hero' => [
            'label' => 'Imagen destacada',
            'grupo' => 'Diseño',
            'html' => '<div style="max-width:600px;margin:0 auto;font-family:Arial,Helvetica,sans-serif;background:#ffffff;border:1px solid #e2e8f0;">'
                . '<div style="background:#cbd5e1;height:220px;text-align:center;line-height:220px;color:#64748b;font-size:14px;">'
                . '[ Inserte aquí su imagen — 600×220 px ]</div>'
                . '<div style="padding:32px 28px;color:#1e293b;line-height:1.6;">'
                . '<h2 style="margin:0 0 12px;font-size:24px;color:#0f172a;">Título de su promoción</h2>'
                . '<p style="margin:0 0 18px;font-size:15px;color:#475569;">Hola <strong>{{nombre}}</strong>, compartimos una novedad importante para usted y su embarcación.</p>'
                . '<p style="margin:0 0 24px;font-size:15px;color:#475569;">Describa aquí los beneficios, fechas y condiciones de la promoción. Mantenga el mensaje claro y directo.</p>'
                . '<p style="text-align:center;margin:0;">'
                . '<a href="#" style="background:#0f172a;color:#ffffff;padding:14px 40px;text-decoration:none;border-radius:6px;font-weight:600;display:inline-block;font-size:15px;">Más información</a></p></div>'
                . $pie . '</div>',
        ],
        'newsletter' => [
            'label' => 'Boletín / newsletter',
            'grupo' => 'Diseño',
            'html' => '<div style="max-width:600px;margin:0 auto;font-family:Arial,Helvetica,sans-serif;background:#f8fafc;">'
                . '<div style="background:#0f172a;padding:22px 24px;text-align:center;">'
                . '<p style="margin:0;color:#ffffff;font-size:20px;font-weight:700;">Boletín Marina</p>'
                . '<p style="margin:6px 0 0;color:#94a3b8;font-size:13px;">Novedades del mes</p></div>'
                . '<div style="padding:24px 20px;">'
                . '<p style="margin:0 0 20px;font-size:15px;color:#334155;">Hola <strong>{{nombre}}</strong>, este es el resumen de lo más relevante:</p>'
                . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:16px;background:#ffffff;border-radius:8px;border:1px solid #e2e8f0;">'
                . '<tr><td style="padding:18px 20px;">'
                . '<p style="margin:0 0 6px;font-size:12px;color:#0d6efd;font-weight:600;text-transform:uppercase;">Sección 1</p>'
                . '<p style="margin:0 0 8px;font-size:17px;font-weight:600;color:#0f172a;">Título de noticia</p>'
                . '<p style="margin:0;font-size:14px;color:#64748b;line-height:1.5;">Breve descripción. <a href="#" style="color:#0d6efd;">Leer más →</a></p></td></tr></table>'
                . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:16px;background:#ffffff;border-radius:8px;border:1px solid #e2e8f0;">'
                . '<tr><td style="padding:18px 20px;">'
                . '<p style="margin:0 0 6px;font-size:12px;color:#0d6efd;font-weight:600;text-transform:uppercase;">Sección 2</p>'
                . '<p style="margin:0 0 8px;font-size:17px;font-weight:600;color:#0f172a;">Otra novedad</p>'
                . '<p style="margin:0;font-size:14px;color:#64748b;line-height:1.5;">Breve descripción. <a href="#" style="color:#0d6efd;">Leer más →</a></p></td></tr></table>'
                . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:8px;border:1px solid #e2e8f0;">'
                . '<tr><td style="padding:18px 20px;">'
                . '<p style="margin:0 0 6px;font-size:12px;color:#0d6efd;font-weight:600;text-transform:uppercase;">Sección 3</p>'
                . '<p style="margin:0 0 8px;font-size:17px;font-weight:600;color:#0f172a;">Eventos y actividades</p>'
                . '<p style="margin:0;font-size:14px;color:#64748b;line-height:1.5;">Breve descripción. <a href="#" style="color:#0d6efd;">Leer más →</a></p></td></tr></table></div>'
                . $pie . '</div>',
        ],
        'dos_columnas' => [
            'label' => 'Dos columnas',
            'grupo' => 'Diseño',
            'html' => '<div style="max-width:600px;margin:0 auto;font-family:Arial,Helvetica,sans-serif;background:#ffffff;border:1px solid #e2e8f0;">'
                . '<div style="padding:24px 24px 16px;text-align:center;background:#f1f5f9;">'
                . '<h1 style="margin:0;font-size:22px;color:#0f172a;">Compare y elija</h1>'
                . '<p style="margin:8px 0 0;font-size:14px;color:#64748b;">Dos opciones para <strong>{{nombre}}</strong></p></div>'
                . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>'
                . '<td width="50%" style="padding:16px 12px 16px 20px;vertical-align:top;border-right:1px solid #e2e8f0;">'
                . '<div style="background:#eff6ff;border-radius:8px;padding:20px 16px;text-align:center;border:1px solid #bfdbfe;">'
                . '<p style="margin:0 0 8px;font-size:13px;color:#1d4ed8;font-weight:600;">OPCIÓN A</p>'
                . '<p style="margin:0 0 12px;font-size:20px;font-weight:700;color:#0f172a;">Plan básico</p>'
                . '<p style="margin:0 0 16px;font-size:14px;color:#475569;line-height:1.5;">Descripción breve del plan o servicio.</p>'
                . '<a href="#" style="color:#1d4ed8;font-size:14px;font-weight:600;text-decoration:none;">Seleccionar →</a></div></td>'
                . '<td width="50%" style="padding:16px 20px 16px 12px;vertical-align:top;">'
                . '<div style="background:#f0fdf4;border-radius:8px;padding:20px 16px;text-align:center;border:1px solid #86efac;">'
                . '<p style="margin:0 0 8px;font-size:13px;color:#15803d;font-weight:600;">OPCIÓN B</p>'
                . '<p style="margin:0 0 12px;font-size:20px;font-weight:700;color:#0f172a;">Plan premium</p>'
                . '<p style="margin:0 0 16px;font-size:14px;color:#475569;line-height:1.5;">Descripción breve del plan o servicio.</p>'
                . '<a href="#" style="color:#15803d;font-size:14px;font-weight:600;text-decoration:none;">Seleccionar →</a></div></td>'
                . '</tr></table>'
                . '<div style="padding:16px 24px 24px;text-align:center;">'
                . '<a href="#" style="background:#0f172a;color:#ffffff;padding:12px 28px;text-decoration:none;border-radius:6px;font-weight:600;display:inline-block;font-size:14px;">Contactar asesor</a></div>'
                . $pie . '</div>',
        ],
        'simple' => [
            'label' => 'Texto simple',
            'grupo' => 'Diseño',
            'html' => '<div style="max-width:600px;margin:0 auto;font-family:Georgia,serif;padding:32px 28px;color:#334155;background:#ffffff;border:1px solid #e2e8f0;">'
                . '<p style="font-size:17px;line-height:1.7;margin:0 0 16px;">Estimado/a {{nombre}},</p>'
                . '<p style="font-size:16px;line-height:1.7;margin:0 0 16px;">Escriba aquí su mensaje…</p>'
                . '<p style="font-size:16px;margin:0;">Saludos cordiales,<br><strong>Equipo Marina</strong></p></div>',
        ],
    ];
}
