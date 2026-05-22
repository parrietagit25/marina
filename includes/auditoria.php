<?php
/**
 * Auditoría / seguimiento de actividad por usuario.
 */
declare(strict_types=1);

/** Etiquetas legibles por página del sistema */
function marina_auditoria_etiqueta_modulo(string $pagina): string
{
    $map = [
        'dashboard' => 'Inicio',
        'usuarios' => 'Usuarios',
        'bancos' => 'Bancos',
        'cuentas' => 'Cuentas',
        'configuracion' => 'Configuración',
        'seguimiento' => 'Seguimiento',
        'movimiento-bancario' => 'Movimientos bancarios',
        'formas-pago' => 'Tipos de movimiento',
        'proveedores' => 'Proveedores',
        'gastos' => 'Gastos / facturas',
        'partidas' => 'Partidas',
        'combustible-pedidos' => 'Combustible pedidos',
        'combustible-despacho' => 'Combustible despacho',
        'combustible-ajuste' => 'Combustible ajuste',
        'combustible-precios' => 'Combustible precios',
        'clientes' => 'Clientes',
        'muelles' => 'Muelles',
        'slips' => 'Slips',
        'grupos' => 'Grupos',
        'inmuebles' => 'Inmuebles',
        'tarifas' => 'Tarifas',
        'contratos' => 'Contratos',
        'contratos-electricidad' => 'Electricidad contrato',
        'login' => 'Inicio de sesión',
    ];

    return $map[$pagina] ?? ucfirst(str_replace('-', ' ', $pagina));
}

/** @return list<array{modulo: string, tabla: string, campo: string}> */
function marina_seguimiento_tablas_historicas(): array
{
    return [
        ['modulo' => 'Clientes', 'tabla' => 'clientes', 'campo' => 'nombre'],
        ['modulo' => 'Bancos', 'tabla' => 'bancos', 'campo' => 'nombre'],
        ['modulo' => 'Cuentas', 'tabla' => 'cuentas', 'campo' => 'nombre'],
        ['modulo' => 'Tipos de movimiento', 'tabla' => 'formas_pago', 'campo' => 'nombre'],
        ['modulo' => 'Proveedores', 'tabla' => 'proveedores', 'campo' => 'nombre'],
        ['modulo' => 'Partidas', 'tabla' => 'partidas', 'campo' => 'nombre'],
        ['modulo' => 'Gastos', 'tabla' => 'gastos', 'campo' => 'referencia'],
        ['modulo' => 'Muelles', 'tabla' => 'muelles', 'campo' => 'nombre'],
        ['modulo' => 'Slips', 'tabla' => 'slips', 'campo' => 'nombre'],
        ['modulo' => 'Grupos', 'tabla' => 'grupos', 'campo' => 'nombre'],
        ['modulo' => 'Inmuebles', 'tabla' => 'inmuebles', 'campo' => 'nombre'],
        ['modulo' => 'Tarifas', 'tabla' => 'tarifas', 'campo' => 'nombre'],
        ['modulo' => 'Contratos', 'tabla' => 'contratos', 'campo' => 'numero_recibo'],
        ['modulo' => 'Movimientos bancarios', 'tabla' => 'movimientos_bancarios', 'campo' => 'descripcion'],
        ['modulo' => 'Comb. pedidos', 'tabla' => 'combustible_pedidos', 'campo' => 'numero_factura'],
        ['modulo' => 'Comb. despacho', 'tabla' => 'combustible_despachos', 'campo' => 'embarcacion'],
        ['modulo' => 'Comb. ajuste', 'tabla' => 'combustible_ajustes', 'campo' => 'motivo'],
        ['modulo' => 'Comb. precios', 'tabla' => 'combustible_precios', 'campo' => 'tipo_combustible'],
        ['modulo' => 'Cuotas', 'tabla' => 'cuotas', 'campo' => 'numero_cuota'],
        ['modulo' => 'Pagos cuota', 'tabla' => 'cuotas_movimientos', 'campo' => 'referencia'],
        ['modulo' => 'Usuarios', 'tabla' => 'usuarios', 'campo' => 'nombre'],
    ];
}

function marina_auditoria_ip_cliente(): ?string
{
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
    if ($ip === null || $ip === '') {
        return null;
    }
    $ip = trim(explode(',', (string) $ip)[0]);

    return substr($ip, 0, 45);
}

function marina_auditoria_tabla_existe(PDO $pdo): bool
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    try {
        $st = $pdo->query("SHOW TABLES LIKE 'auditoria_eventos'");
        $cache = (bool) $st->fetchColumn();
    } catch (Throwable $e) {
        $cache = false;
    }

    return $cache;
}

/** Unifica collation en UNION (tablas legacy vs auditoria_eventos). */
function marina_auditoria_collate_expr(string $sqlExpression): string
{
    return 'CAST(' . $sqlExpression . ' AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci';
}

function marina_auditoria_collate_lit(PDO $pdo, string $value): string
{
    return marina_auditoria_collate_expr($pdo->quote($value));
}

function marina_auditoria_registrar(
    PDO $pdo,
    string $pagina,
    string $accion,
    string $descripcion,
    ?int $entidadId = null,
    ?string $modulo = null
): void {
    if (!marina_auditoria_tabla_existe($pdo)) {
        return;
    }
    $uid = function_exists('usuarioId') ? usuarioId() : null;
    if (!$uid) {
        return;
    }
    $modulo = $modulo ?? marina_auditoria_etiqueta_modulo($pagina);
    $descripcion = trim($descripcion);
    if ($descripcion === '') {
        $descripcion = $accion . ' en ' . $modulo;
    }
    if (strlen($descripcion) > 500) {
        $descripcion = substr($descripcion, 0, 497) . '...';
    }
    try {
        $pdo->prepare('
            INSERT INTO auditoria_eventos (usuario_id, pagina, accion, modulo, entidad_id, descripcion, ip)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ')->execute([
            $uid,
            substr($pagina, 0, 80),
            substr($accion, 0, 40),
            substr($modulo, 0, 120),
            $entidadId > 0 ? $entidadId : null,
            $descripcion,
            marina_auditoria_ip_cliente(),
        ]);
    } catch (Throwable $e) {
        // no interrumpir la operación principal
    }
}

function marina_auditoria_descripcion_desde_post(string $accion): string
{
    $partes = [];
    foreach (['nombre', 'email', 'referencia', 'descripcion', 'observaciones', 'numero_factura', 'embarcacion', 'documento'] as $k) {
        if (!empty($_POST[$k])) {
            $partes[] = trim((string) $_POST[$k]);
        }
    }
    if ($partes !== []) {
        return implode(' — ', array_slice($partes, 0, 2));
    }
    if (!empty($_POST['id'])) {
        return 'id ' . (int) $_POST['id'];
    }

    return $accion;
}

/** Registra POST relevantes del front controller */
function marina_auditoria_desde_request(PDO $pdo, string $pagina): void
{
    if (!enviado() || !usuarioId()) {
        return;
    }
    $accion = trim((string) ($_POST['accion'] ?? $_GET['accion'] ?? ''));
    if ($accion === '' || in_array($accion, ['exportar', 'excel'], true)) {
        return;
    }
    $entidadId = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
    $desc = marina_auditoria_descripcion_desde_post($accion);
    if ($accion === 'eliminar') {
        $desc = 'Eliminó registro' . ($entidadId > 0 ? ' #' . $entidadId : '') . ($desc !== 'eliminar' ? ': ' . $desc : '');
    } elseif ($accion === 'crear') {
        $desc = 'Creó: ' . $desc;
    } elseif (in_array($accion, ['editar', 'guardar', 'actualizar', 'guardar_permisos'], true)) {
        $desc = ($accion === 'guardar_permisos' ? 'Actualizó permisos (rol)' : 'Modificó: ') . $desc;
    }
    marina_auditoria_registrar($pdo, $pagina, $accion, $desc, $entidadId > 0 ? $entidadId : null);
}

/**
 * @return array{
 *   total: int,
 *   por_usuario: list<array{usuario_id: int, nombre: string, total: int}>,
 *   por_dia: list<array{fecha: string, total: int}>,
 *   por_modulo: list<array{modulo: string, total: int}>,
 *   eventos: list<array<string, mixed>>
 * }
 */
function marina_seguimiento_datos(PDO $pdo, string $desde, string $hasta, int $filtroUsuario = 0): array
{
    $desdeDt = $desde . ' 00:00:00';
    $hastaDt = $hasta . ' 23:59:59';
    $params = [$desdeDt, $hastaDt];
    $filtroSql = '';
    if ($filtroUsuario > 0) {
        $filtroSql = ' AND t.usuario_id = ?';
        $params[] = $filtroUsuario;
    }

    $unionParts = [];
    $unionParams = [];

    if (marina_auditoria_tabla_existe($pdo)) {
        $unionParts[] = '
            SELECT ae.usuario_id,
                ' . marina_auditoria_collate_expr('ae.modulo') . ' AS modulo,
                ' . marina_auditoria_collate_expr('ae.accion') . ' AS accion,
                ' . marina_auditoria_collate_expr('ae.descripcion') . ' AS descripcion,
                ae.entidad_id,
                ae.created_at AS fecha,
                ' . marina_auditoria_collate_expr('ae.pagina') . ' AS pagina
            FROM auditoria_eventos ae
            WHERE ae.created_at BETWEEN ? AND ?
        ';
        $unionParams[] = $desdeDt;
        $unionParams[] = $hastaDt;
    }

    foreach (marina_seguimiento_tablas_historicas() as $def) {
        $tabla = preg_replace('/[^a-z_]/', '', $def['tabla']);
        $campo = preg_replace('/[^a-z_]/', '', $def['campo']);
        if ($tabla === '' || $campo === '') {
            continue;
        }
        try {
            $chk = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($tabla));
            if (!$chk->fetchColumn()) {
                continue;
            }
            $cols = $pdo->query("SHOW COLUMNS FROM `{$tabla}`")->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('created_by', $cols, true) || !in_array('created_at', $cols, true)) {
                continue;
            }
        } catch (Throwable $e) {
            continue;
        }
        $modulo = $def['modulo'];
        $descCrear = marina_auditoria_collate_expr(
            'CONCAT(' . $pdo->quote($modulo . ': ') . ', COALESCE(CAST(t.`' . $campo . '` AS CHAR CHARACTER SET utf8mb4), CONCAT(\'#\', t.id)))'
        );
        $unionParts[] = '
            SELECT t.created_by AS usuario_id,
                ' . marina_auditoria_collate_lit($pdo, $modulo) . ' AS modulo,
                ' . marina_auditoria_collate_lit($pdo, 'crear') . ' AS accion,
                ' . $descCrear . ' AS descripcion,
                t.id AS entidad_id,
                t.created_at AS fecha,
                ' . marina_auditoria_collate_lit($pdo, '') . ' AS pagina
            FROM `' . $tabla . '` t
            WHERE t.created_at BETWEEN ? AND ? AND t.created_by IS NOT NULL
        ';
        $unionParams[] = $desdeDt;
        $unionParams[] = $hastaDt;

        if (in_array('updated_by', $cols, true) && in_array('updated_at', $cols, true)) {
            $descEditar = marina_auditoria_collate_expr(
                'CONCAT(' . $pdo->quote('Editó ' . $modulo . ': ') . ', COALESCE(CAST(t.`' . $campo . '` AS CHAR CHARACTER SET utf8mb4), CONCAT(\'#\', t.id)))'
            );
            $unionParts[] = '
                SELECT t.updated_by AS usuario_id,
                    ' . marina_auditoria_collate_lit($pdo, $modulo) . ' AS modulo,
                    ' . marina_auditoria_collate_lit($pdo, 'editar') . ' AS accion,
                    ' . $descEditar . ' AS descripcion,
                    t.id AS entidad_id,
                    t.updated_at AS fecha,
                    ' . marina_auditoria_collate_lit($pdo, '') . ' AS pagina
                FROM `' . $tabla . '` t
                WHERE t.updated_at BETWEEN ? AND ? AND t.updated_by IS NOT NULL
                  AND (t.updated_at > t.created_at OR t.updated_by <> COALESCE(t.created_by, 0))
            ';
            $unionParams[] = $desdeDt;
            $unionParams[] = $hastaDt;
        }
    }

    if ($unionParts === []) {
        return [
            'total' => 0,
            'por_usuario' => [],
            'por_dia' => [],
            'por_modulo' => [],
            'eventos' => [],
        ];
    }

    $nombreUsuario = marina_auditoria_collate_expr(
        "COALESCE(u.nombre, CONCAT('Usuario #', t.usuario_id))"
    );

    $sqlBase = 'SELECT t.usuario_id, ' . $nombreUsuario . ' AS usuario_nombre, t.modulo, t.accion, t.descripcion, t.entidad_id, t.fecha, t.pagina
        FROM (' . implode(' UNION ALL ', $unionParts) . ') t
        LEFT JOIN usuarios u ON u.id = t.usuario_id
        WHERE t.usuario_id IS NOT NULL' . $filtroSql;

    $allParams = array_merge($unionParams, $filtroUsuario > 0 ? [$filtroUsuario] : []);

    $porUsuario = [];
    $stU = $pdo->prepare('
        SELECT t.usuario_id, ' . $nombreUsuario . ' AS nombre, COUNT(*) AS total
        FROM (' . implode(' UNION ALL ', $unionParts) . ') t
        LEFT JOIN usuarios u ON u.id = t.usuario_id
        WHERE t.usuario_id IS NOT NULL ' . $filtroSql . '
        GROUP BY t.usuario_id, ' . $nombreUsuario . '
        ORDER BY total DESC
        LIMIT 15
    ');
    $stU->execute($allParams);
    while ($r = $stU->fetch(PDO::FETCH_ASSOC)) {
        $porUsuario[] = [
            'usuario_id' => (int) $r['usuario_id'],
            'nombre' => (string) $r['nombre'],
            'total' => (int) $r['total'],
        ];
    }

    $porDia = [];
    $stD = $pdo->prepare("
        SELECT DATE(t.fecha) AS fecha, COUNT(*) AS total
        FROM (" . implode(' UNION ALL ', $unionParts) . ") t
        WHERE t.usuario_id IS NOT NULL {$filtroSql}
        GROUP BY DATE(t.fecha)
        ORDER BY fecha ASC
    ");
    $stD->execute($allParams);
    while ($r = $stD->fetch(PDO::FETCH_ASSOC)) {
        $porDia[] = ['fecha' => (string) $r['fecha'], 'total' => (int) $r['total']];
    }

    $porModulo = [];
    $stM = $pdo->prepare("
        SELECT t.modulo, COUNT(*) AS total
        FROM (" . implode(' UNION ALL ', $unionParts) . ") t
        WHERE t.usuario_id IS NOT NULL {$filtroSql}
        GROUP BY t.modulo
        ORDER BY total DESC
        LIMIT 20
    ");
    $stM->execute($allParams);
    while ($r = $stM->fetch(PDO::FETCH_ASSOC)) {
        $porModulo[] = ['modulo' => (string) $r['modulo'], 'total' => (int) $r['total']];
    }

    $total = 0;
    $stT = $pdo->prepare("SELECT COUNT(*) FROM (" . implode(' UNION ALL ', $unionParts) . ") t WHERE t.usuario_id IS NOT NULL {$filtroSql}");
    $stT->execute($allParams);
    $total = (int) $stT->fetchColumn();

    $eventos = [];
    $stE = $pdo->prepare($sqlBase . ' ORDER BY t.fecha DESC LIMIT 400');
    $stE->execute($allParams);
    while ($r = $stE->fetch(PDO::FETCH_ASSOC)) {
        $eventos[] = [
            'usuario_id' => (int) $r['usuario_id'],
            'usuario_nombre' => (string) ($r['usuario_nombre'] ?? ''),
            'modulo' => (string) $r['modulo'],
            'accion' => (string) $r['accion'],
            'descripcion' => (string) $r['descripcion'],
            'entidad_id' => $r['entidad_id'] !== null ? (int) $r['entidad_id'] : null,
            'fecha' => (string) $r['fecha'],
            'pagina' => (string) ($r['pagina'] ?? ''),
        ];
    }

    return [
        'total' => $total,
        'por_usuario' => $porUsuario,
        'por_dia' => $porDia,
        'por_modulo' => $porModulo,
        'eventos' => $eventos,
    ];
}
