<?php
/**
 * Permisos por usuario: menú, editar y eliminar (global).
 */
declare(strict_types=1);

/** Páginas siempre accesibles con sesión iniciada */
function marina_permisos_paginas_libres(): array
{
    return ['login', 'logout'];
}

/** Alias: varias rutas comparten el mismo permiso de menú */
function marina_permisos_alias_pagina(): array
{
    return [
        'contratos-electricidad' => 'contratos',
    ];
}

/**
 * Definición del menú (todas las opciones configurables en Rol).
 *
 * @return list<array{seccion: string, items: list<array{pagina: string, etiqueta: string, icono?: string}>}>
 */
function marina_menu_permisos_definicion(): array
{
    return [
        [
            'seccion' => 'General',
            'items' => [
                ['pagina' => 'dashboard', 'etiqueta' => 'Inicio', 'icono' => 'layout-dashboard'],
                ['pagina' => 'manual', 'etiqueta' => 'Manual', 'icono' => 'book-open'],
            ],
        ],
        [
            'seccion' => 'Mantenimiento',
            'items' => [
                ['pagina' => 'usuarios', 'etiqueta' => 'Usuarios', 'icono' => 'users'],
                ['pagina' => 'bancos', 'etiqueta' => 'Bancos', 'icono' => 'landmark'],
                ['pagina' => 'cuentas', 'etiqueta' => 'Cuentas', 'icono' => 'wallet-cards'],
                ['pagina' => 'configuracion', 'etiqueta' => 'Configuración', 'icono' => 'type'],
                ['pagina' => 'seguimiento', 'etiqueta' => 'Seguimiento', 'icono' => 'activity'],
            ],
        ],
        [
            'seccion' => 'Banco',
            'items' => [
                ['pagina' => 'movimiento-bancario', 'etiqueta' => 'Registrar movimientos bancarios', 'icono' => 'banknote'],
                ['pagina' => 'reporte-estado-cuenta-bancarias', 'etiqueta' => 'Estado de cuenta bancaria', 'icono' => 'file-text'],
                ['pagina' => 'saldos-cuentas-bancarias', 'etiqueta' => 'Saldos de cuentas bancarias', 'icono' => 'landmark'],
                ['pagina' => 'formas-pago', 'etiqueta' => 'Tipo de movimientos', 'icono' => 'arrow-right-left'],
            ],
        ],
        [
            'seccion' => 'Costo o Gastos',
            'items' => [
                ['pagina' => 'proveedores', 'etiqueta' => 'Proveedores', 'icono' => 'truck'],
                ['pagina' => 'gastos', 'etiqueta' => 'Factura / Pagar', 'icono' => 'receipt'],
                ['pagina' => 'reporte-proveedores-estado-cuenta', 'etiqueta' => 'Estado de cuenta proveedor', 'icono' => 'file-text'],
                ['pagina' => 'partidas', 'etiqueta' => 'Partidas', 'icono' => 'network'],
            ],
        ],
        [
            'seccion' => 'Combustible',
            'items' => [
                ['pagina' => 'combustible-pedidos', 'etiqueta' => 'Pedidos', 'icono' => 'shopping-cart'],
                ['pagina' => 'combustible-despacho', 'etiqueta' => 'Despacho', 'icono' => 'fuel'],
                ['pagina' => 'combustible-ajuste', 'etiqueta' => 'Ajuste', 'icono' => 'sliders-horizontal'],
                ['pagina' => 'combustible-precios', 'etiqueta' => 'Precio x galón', 'icono' => 'tag'],
                ['pagina' => 'reporte-combustible', 'etiqueta' => 'Reporte combustible', 'icono' => 'file-bar-chart'],
            ],
        ],
        [
            'seccion' => 'Marina Ingresos',
            'items' => [
                ['pagina' => 'clientes', 'etiqueta' => 'Clientes', 'icono' => 'user-round'],
                ['pagina' => 'muelles', 'etiqueta' => 'Muelles'],
                ['pagina' => 'slips', 'etiqueta' => 'Slips'],
                ['pagina' => 'grupos', 'etiqueta' => 'Grupos'],
                ['pagina' => 'inmuebles', 'etiqueta' => 'Inmuebles'],
                ['pagina' => 'mapa-marina', 'etiqueta' => 'Mapa Marina', 'icono' => 'anchor'],
                ['pagina' => 'mapa-grupos', 'etiqueta' => 'Mapa Grupos', 'icono' => 'building-2'],
                ['pagina' => 'tarifas', 'etiqueta' => 'Tarifas', 'icono' => 'badge-dollar-sign'],
                ['pagina' => 'contratos', 'etiqueta' => 'Contratos'],
                ['pagina' => 'reporte-cuotas', 'etiqueta' => 'Reporte de cuotas', 'icono' => 'file-bar-chart'],
            ],
        ],
        [
            'seccion' => 'Reportes',
            'items' => [
                ['pagina' => 'reporte-ingresos', 'etiqueta' => 'Reporte de ingreso'],
                ['pagina' => 'reporte-ingreso-dia', 'etiqueta' => 'Ingreso x Día'],
                ['pagina' => 'reporte-egresos', 'etiqueta' => 'Reporte de egresos'],
                ['pagina' => 'reportes', 'etiqueta' => 'Reporte de ingresos y egresos', 'icono' => 'bar-chart-3'],
                ['pagina' => 'reporte-ingresos-egresos', 'etiqueta' => 'Ingresos y egresos (detalle)'],
                ['pagina' => 'reporte-marina-contratos', 'etiqueta' => 'Reporte Marina → contratos'],
                ['pagina' => 'reporte-inmuebles-contratos', 'etiqueta' => 'Reporte Inmuebles → contratos'],
                ['pagina' => 'reporte-cliente-aislado', 'etiqueta' => 'Clientes por movimientos bancarios'],
                ['pagina' => 'reporte-electricidad', 'etiqueta' => 'Electricidad (contratos)'],
                ['pagina' => 'reporte-ocupacion', 'etiqueta' => 'Reporte de cobranzas'],
                ['pagina' => 'reporte-recaudo', 'etiqueta' => 'Reporte de recaudo'],
            ],
        ],
    ];
}

/** @return list<string> */
function marina_permisos_todas_paginas(): array
{
    $out = [];
    foreach (marina_menu_permisos_definicion() as $sec) {
        foreach ($sec['items'] as $it) {
            $out[] = $it['pagina'];
        }
    }

    return array_values(array_unique($out));
}

function marina_permisos_normalizar_pagina(string $pagina): string
{
    $pagina = preg_replace('/[^a-z0-9_-]/', '', $pagina) ?: 'dashboard';
    $alias = marina_permisos_alias_pagina();

    return $alias[$pagina] ?? $pagina;
}

/** @return array{paginas: list<string>, editar: bool, eliminar: bool, acceso_total: bool} */
function marina_permisos_parse_json(?string $json): array
{
    if ($json === null || trim($json) === '') {
        return [
            'paginas' => marina_permisos_todas_paginas(),
            'editar' => true,
            'eliminar' => true,
            'acceso_total' => true,
        ];
    }
    $data = json_decode($json, true);
    if (!is_array($data)) {
        return marina_permisos_parse_json(null);
    }
    $paginas = [];
    if (!empty($data['paginas']) && is_array($data['paginas'])) {
        foreach ($data['paginas'] as $p) {
            $p = marina_permisos_normalizar_pagina((string) $p);
            if (in_array($p, marina_permisos_todas_paginas(), true)) {
                $paginas[] = $p;
            }
        }
    }
    $paginas = array_values(array_unique($paginas));

    return [
        'paginas' => $paginas,
        'editar' => !empty($data['editar']),
        'eliminar' => !empty($data['eliminar']),
        'acceso_total' => false,
    ];
}

/** @return array{paginas: list<string>, editar: bool, eliminar: bool, acceso_total: bool} */
function marina_permisos_desde_db(PDO $pdo, int $usuarioId): array
{
    $st = $pdo->prepare('SELECT permisos_json FROM usuarios WHERE id = ? LIMIT 1');
    $st->execute([$usuarioId]);
    $json = $st->fetchColumn();

    return marina_permisos_parse_json($json === false ? null : ($json !== null ? (string) $json : null));
}

function marina_permisos_hidratar_sesion(PDO $pdo, int $usuarioId): void
{
    $perm = marina_permisos_desde_db($pdo, $usuarioId);
    $_SESSION['permisos'] = [
        'paginas' => $perm['paginas'],
        'editar' => $perm['editar'],
        'eliminar' => $perm['eliminar'],
        'acceso_total' => $perm['acceso_total'],
    ];
}

/** @return array{paginas: list<string>, editar: bool, eliminar: bool, acceso_total: bool} */
function marina_permisos_sesion(): array
{
    $p = $_SESSION['permisos'] ?? null;
    if (!is_array($p)) {
        return marina_permisos_parse_json(null);
    }

    return [
        'paginas' => is_array($p['paginas'] ?? null) ? array_values($p['paginas']) : [],
        'editar' => !empty($p['editar']),
        'eliminar' => !empty($p['eliminar']),
        'acceso_total' => !empty($p['acceso_total']),
    ];
}

function marina_permiso_acceso_total(): bool
{
    return marina_permisos_sesion()['acceso_total'];
}

function marina_permiso_puede_pagina(string $pagina): bool
{
    $pagina = marina_permisos_normalizar_pagina($pagina);
    if (in_array($pagina, marina_permisos_paginas_libres(), true)) {
        return true;
    }
    $perm = marina_permisos_sesion();
    if ($perm['acceso_total']) {
        return true;
    }

    return in_array($pagina, $perm['paginas'], true);
}

function marina_permiso_puede_editar(): bool
{
    $perm = marina_permisos_sesion();

    return $perm['acceso_total'] || $perm['editar'];
}

function marina_permiso_puede_eliminar(): bool
{
    $perm = marina_permisos_sesion();

    return $perm['acceso_total'] || $perm['eliminar'];
}

function marina_permiso_puede_gestionar_roles(): bool
{
    return marina_permiso_puede_pagina('usuarios') && marina_permiso_puede_editar();
}

function marina_permiso_verificar_pagina(string $pagina): void
{
    $pagina = marina_permisos_normalizar_pagina($pagina);
    if (marina_permiso_puede_pagina($pagina)) {
        return;
    }
    $perm = marina_permisos_sesion();
    foreach ($perm['paginas'] as $p) {
        if ($p !== $pagina) {
            redirigir(MARINA_URL . '/index.php?p=' . rawurlencode($p) . '&err=' . rawurlencode('No tiene permiso para acceder a esa sección.'));
        }
    }
    cerrarSesion();
    header('Location: ' . MARINA_URL . '/index.php?p=login&err=' . rawurlencode('Su usuario no tiene secciones habilitadas. Contacte al administrador.'));
    exit;
}

function marina_permiso_bloquear_acciones_globales(): void
{
    if (!enviado()) {
        return;
    }
    $accion = trim((string) ($_POST['accion'] ?? $_GET['accion'] ?? ''));
    if ($accion === '') {
        return;
    }
    $pActual = trim((string) ($_GET['p'] ?? ''));
    if ($accion === 'guardar_permisos' && $pActual === 'usuarios') {
        return;
    }
    if ($accion === 'eliminar' && !marina_permiso_puede_eliminar()) {
        redirigir(MARINA_URL . '/index.php?p=' . ($pActual !== '' ? $pActual : 'dashboard') . '&err=' . rawurlencode('No tiene permiso para eliminar registros.'));
    }
    if (in_array($accion, ['crear', 'editar', 'guardar', 'actualizar', 'guardar_movimiento', 'registrar'], true) && !marina_permiso_puede_editar()) {
        redirigir(MARINA_URL . '/index.php?p=' . ($pActual !== '' ? $pActual : 'dashboard') . '&err=' . rawurlencode('No tiene permiso para crear o editar registros.'));
    }
}

/**
 * @param list<string> $paginas
 * @return array{paginas: list<string>, editar: bool, eliminar: bool}
 */
function marina_permisos_armar_json(array $paginas, bool $editar, bool $eliminar): array
{
    $validas = marina_permisos_todas_paginas();
    $paginas = array_values(array_unique(array_filter(array_map(
        static fn ($p) => marina_permisos_normalizar_pagina((string) $p),
        $paginas
    ), static fn ($p) => in_array($p, $validas, true))));

    return [
        'paginas' => $paginas,
        'editar' => $editar ? 1 : 0,
        'eliminar' => $eliminar ? 1 : 0,
    ];
}

function marina_permisos_guardar_usuario(PDO $pdo, int $usuarioId, array $paginas, bool $editar, bool $eliminar): void
{
    $payload = marina_permisos_armar_json($paginas, $editar, $eliminar);
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    $pdo->prepare('UPDATE usuarios SET permisos_json = ?, updated_by = ? WHERE id = ?')
        ->execute([$json, usuarioId(), $usuarioId]);

    if ($usuarioId === usuarioId()) {
        marina_permisos_hidratar_sesion($pdo, $usuarioId);
    }
}

/**
 * Enlace del menú lateral.
 */
function marina_menu_enlace(string $pagina, string $etiqueta, string $paginaActual, ?string $icono = null): void
{
    if (!marina_permiso_puede_pagina($pagina)) {
        return;
    }
    $activo = ($paginaActual === $pagina)
        || ($pagina === 'contratos' && $paginaActual === 'contratos-electricidad');
    $cls = 'list-group-item list-group-item-action' . ($activo ? ' active' : '');
    $href = MARINA_URL . '/index.php?p=' . rawurlencode($pagina);
    echo "<a class='" . $cls . "' href='" . $href . "'>";
    if ($icono !== null && $icono !== '') {
        echo "<i data-lucide='" . htmlspecialchars($icono, ENT_QUOTES, 'UTF-8') . "' class='menu-ico'></i>";
    }
    echo htmlspecialchars($etiqueta, ENT_QUOTES, 'UTF-8');
    echo '</a>';
}

/**
 * ¿Mostrar sección del acordeón del menú?
 *
 * @param list<array{pagina: string, etiqueta: string, icono?: string}> $items
 */
function marina_menu_seccion_visible(array $items): bool
{
    foreach ($items as $it) {
        if (marina_permiso_puede_pagina($it['pagina'])) {
            return true;
        }
    }

    return false;
}

/**
 * ¿Expandir sección porque la página actual pertenece a ella?
 *
 * @param list<array{pagina: string, etiqueta: string, icono?: string}> $items
 */
function marina_menu_seccion_activa(array $items, string $paginaActual): bool
{
    $paginaActual = marina_permisos_normalizar_pagina($paginaActual);
    foreach ($items as $it) {
        if ($it['pagina'] === $paginaActual) {
            return true;
        }
    }
    if ($paginaActual === 'contratos-electricidad') {
        foreach ($items as $it) {
            if ($it['pagina'] === 'contratos') {
                return true;
            }
        }
    }

    return false;
}
