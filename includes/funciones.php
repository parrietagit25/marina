<?php
/**
 * Helpers generales
 */

function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function redirigir(string $url, int $codigo = 302): void {
    header('Location: ' . $url, true, $codigo);
    exit;
}

function fechaFormato(?string $fecha, string $formato = 'd/m/Y'): string {
    if ($fecha === null || $fecha === '') return '';
    $t = strtotime($fecha);
    return $t ? date($formato, $t) : $fecha;
}

function fechaHoraFormato(?string $fecha, string $formato = 'd/m/Y H:i'): string {
    if ($fecha === null || $fecha === '') return '';
    $t = strtotime($fecha);
    return $t ? date($formato, $t) : $fecha;
}

function dinero(float $n): string {
    return number_format($n, 2, ',', '.');
}

/** Etiqueta UI: acreditación / tipo_movimiento ingreso en BD (sigue siendo `ingreso`). */
function marina_ui_credito(): string {
    return 'Crédito';
}

/** Etiqueta UI: cargo / tipo_movimiento costo en BD (sigue siendo `costo`). */
function marina_ui_debito(): string {
    return 'Débito';
}

function obtener(string $key, $default = '') {
    return $_GET[$key] ?? $_POST[$key] ?? $default;
}

function enviado(): bool {
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

/** Porcentaje del tamaño base del texto (100 = tamaño actual del sistema). Rango 80–125. */
function marina_config_font_size_percent(PDO $pdo): int {
    try {
        $st = $pdo->prepare("SELECT valor FROM marina_config WHERE clave = 'font_size_percent' LIMIT 1");
        $st->execute();
        $v = $st->fetchColumn();
        if ($v !== false && $v !== null && $v !== '') {
            return max(80, min(125, (int) $v));
        }
    } catch (Throwable $e) {
        // tabla ausente en instalaciones muy antiguas
    }
    return 100;
}

/**
 * Termina el contrato y libera slip/inmueble para nuevos contratos.
 * Conserva la última ubicación (muelle/slip o grupo/inmueble) en la fila para reportes e historial;
 * el mapa y la ocupación siguen usando solo contratos con estado activo.
 *
 * @return null si OK, o mensaje de error
 */
function marina_contrato_liberar(PDO $pdo, int $id): ?string
{
    if ($id < 1) {
        return 'Contrato no válido.';
    }
    try {
        $st = $pdo->prepare('SELECT id, estado, slip_id, inmueble_id FROM contratos WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return 'Contrato no encontrado.';
        }
        $est = (string) ($row['estado'] ?? 'activo');
        if ($est !== 'activo') {
            return 'El contrato ya está liberado.';
        }
        $uid = function_exists('usuarioId') ? usuarioId() : null;
        $slipId = (int) ($row['slip_id'] ?? 0);
        $inmId = (int) ($row['inmueble_id'] ?? 0);

        if ($slipId > 0) {
            $pdo->prepare('
                UPDATE contratos SET
                    estado = \'terminado\',
                    activo = 0,
                    grupo_id = NULL,
                    inmueble_id = NULL,
                    updated_by = ?
                WHERE id = ? AND estado = \'activo\'
            ')->execute([$uid, $id]);
        } elseif ($inmId > 0) {
            $pdo->prepare('
                UPDATE contratos SET
                    estado = \'terminado\',
                    activo = 0,
                    muelle_id = NULL,
                    slip_id = NULL,
                    updated_by = ?
                WHERE id = ? AND estado = \'activo\'
            ')->execute([$uid, $id]);
        } else {
            $pdo->prepare('
                UPDATE contratos SET
                    estado = \'terminado\',
                    activo = 0,
                    muelle_id = NULL,
                    slip_id = NULL,
                    grupo_id = NULL,
                    inmueble_id = NULL,
                    updated_by = ?
                WHERE id = ? AND estado = \'activo\'
            ')->execute([$uid, $id]);
        }

        return null;
    } catch (Throwable $e) {
        return 'No se pudo liberar la unidad.';
    }
}

/**
 * Normaliza RUC/cédula para detectar duplicados (espacios, guiones, puntos, etc.).
 */
function marina_normalizar_documento_identidad(string $doc): string {
    $doc = trim($doc);
    if ($doc === '') {
        return '';
    }
    $doc = str_replace(["\xc2\xa0", ' ', '-', '.', '_', '/', '\\'], '', $doc);
    return strtoupper($doc);
}

/**
 * Si ya existe otro proveedor con el mismo documento normalizado, devuelve esa fila.
 *
 * @param int $excluirId ID a ignorar (al editar el mismo registro); 0 = ninguno
 * @return array{id:int,nombre:string,documento:?string}|null
 */
function marina_proveedor_documento_duplicado(PDO $pdo, string $documentoNormalizado, int $excluirId = 0): ?array {
    if ($documentoNormalizado === '') {
        return null;
    }
    $st = $pdo->query("SELECT id, nombre, documento FROM proveedores WHERE documento IS NOT NULL AND TRIM(documento) <> ''");
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if ((int) $row['id'] === $excluirId) {
            continue;
        }
        $n = marina_normalizar_documento_identidad((string) ($row['documento'] ?? ''));
        if ($n !== '' && $n === $documentoNormalizado) {
            return $row;
        }
    }
    return null;
}

/**
 * Nombre de cliente para comparar duplicados (trim, espacios internos, sin distinguir mayúsculas).
 */
function marina_normalizar_nombre_cliente(string $n): string {
    $n = trim($n);
    if ($n === '') {
        return '';
    }
    $n = preg_replace('/\s+/u', ' ', $n);
    if (function_exists('mb_strtolower')) {
        return mb_strtolower($n, 'UTF-8');
    }

    return strtolower($n);
}

/**
 * Teléfono para comparar duplicados (espacios, guiones, paréntesis, etc.).
 */
function marina_normalizar_telefono_cliente(string $t): string {
    $t = trim($t);
    if ($t === '') {
        return '';
    }

    return str_replace(["\xc2\xa0", ' ', '-', '.', '(', ')', '_', '/', '\\', '+'], '', $t);
}

/**
 * Correo para comparar duplicados (trim, minúsculas).
 */
function marina_normalizar_email_cliente(string $email): string {
    $email = trim($email);
    if ($email === '') {
        return '';
    }
    if (function_exists('mb_strtolower')) {
        return mb_strtolower($email, 'UTF-8');
    }

    return strtolower($email);
}

/**
 * Devuelve mensaje si otro cliente ya usa el mismo nombre, documento, teléfono o correo (solo compara campos no vacíos).
 *
 * @param int $excluirId 0 en alta; en edición, id del registro actual
 */
function marina_cliente_mensaje_si_duplicado(
    PDO $pdo,
    string $nombre,
    string $documento,
    string $telefono,
    string $email,
    int $excluirId
): ?string {
    $nombreNorm = marina_normalizar_nombre_cliente($nombre);
    if ($nombreNorm === '') {
        return null;
    }
    $docNorm = marina_normalizar_documento_identidad($documento);
    $telNorm = marina_normalizar_telefono_cliente($telefono);
    $emailNorm = marina_normalizar_email_cliente($email);

    if ($excluirId > 0) {
        $st = $pdo->prepare('SELECT id, nombre, documento, telefono, email FROM clientes WHERE id != ?');
        $st->execute([$excluirId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $rows = $pdo->query('SELECT id, nombre, documento, telefono, email FROM clientes')->fetchAll(PDO::FETCH_ASSOC);
    }

    foreach ($rows as $row) {
        $oid = (int) ($row['id'] ?? 0);
        if ($nombreNorm === marina_normalizar_nombre_cliente((string) ($row['nombre'] ?? ''))) {
            return 'Ya existe un cliente con el mismo nombre (ID ' . $oid . ').';
        }
        if ($docNorm !== '' && $docNorm === marina_normalizar_documento_identidad((string) ($row['documento'] ?? ''))) {
            return 'Ya existe un cliente con el mismo documento (ID ' . $oid . ').';
        }
        if ($telNorm !== '' && $telNorm === marina_normalizar_telefono_cliente((string) ($row['telefono'] ?? ''))) {
            return 'Ya existe un cliente con el mismo teléfono (ID ' . $oid . ').';
        }
        if ($emailNorm !== '' && $emailNorm === marina_normalizar_email_cliente((string) ($row['email'] ?? ''))) {
            return 'Ya existe un cliente con el mismo correo electrónico (ID ' . $oid . ').';
        }
    }

    return null;
}

require_once __DIR__ . '/eliminar_dependencias.php';
