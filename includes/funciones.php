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

/** Formato de fecha en pantallas operativas (año con 2 dígitos: 16/05/26). */
const MARINA_FMT_FECHA_PANTALLA = 'd/m/y';

/** Formato de fecha en reportes (año completo: 16/05/2026). */
const MARINA_FMT_FECHA_REPORTE = 'd/m/Y';

const MARINA_FMT_FECHA_HORA_PANTALLA = 'd/m/y H:i';

const MARINA_FMT_FECHA_HORA_REPORTE = 'd/m/Y H:i';

function fechaFormato(?string $fecha, string $formato = MARINA_FMT_FECHA_PANTALLA): string {
    if ($fecha === null || $fecha === '') {
        return '';
    }
    $t = strtotime($fecha);
    return $t ? date($formato, $t) : $fecha;
}

/** Fecha en reportes (año de 4 dígitos). */
function fechaFormatoReporte(?string $fecha): string {
    return fechaFormato($fecha, MARINA_FMT_FECHA_REPORTE);
}

function fechaHoraFormato(?string $fecha, string $formato = MARINA_FMT_FECHA_HORA_PANTALLA): string {
    if ($fecha === null || $fecha === '') {
        return '';
    }
    $t = strtotime($fecha);
    return $t ? date($formato, $t) : $fecha;
}

function fechaHoraFormatoReporte(?string $fecha): string {
    return fechaHoraFormato($fecha, MARINA_FMT_FECHA_HORA_REPORTE);
}

function dinero(float $n): string {
    return number_format($n, 2, ',', '.');
}

/**
 * Alerta de vencimiento para mapas (contrato activo).
 * vencido = fecha fin anterior a hoy; por_vencer = fin dentro de 7 días inclusive; ok = resto.
 *
 * @return 'ok'|'por_vencer'|'vencido'
 */
function marina_contrato_alerta_vencimiento(?string $fechaFin, ?string $hoy = null): string
{
    $fechaFin = trim((string) $fechaFin);
    if ($fechaFin === '') {
        return 'ok';
    }
    try {
        $fin = new DateTime($fechaFin);
        $hoyDt = new DateTime($hoy ?? date('Y-m-d'));
    } catch (Exception $e) {
        return 'ok';
    }
    $hoyDt->setTime(0, 0, 0);
    $fin->setTime(0, 0, 0);
    if ($fin < $hoyDt) {
        return 'vencido';
    }
    $limite = clone $hoyDt;
    $limite->modify('+7 days');
    if ($fin <= $limite) {
        return 'por_vencer';
    }

    return 'ok';
}

/**
 * Clases y etiqueta para unidad ocupada en mapa marina / grupos.
 *
 * @return array{slip: string, pill: string, label: string}
 */
function marina_mapa_estilo_ocupacion(string $alertaVenc, string $prefijo = 'mapa-marina-slip'): array
{
    if ($alertaVenc === 'vencido') {
        return [
            'slip' => $prefijo . '--ocupado ' . $prefijo . '--vencido',
            'pill' => 'mapa-slip-pill--vencido',
            'badge' => 'bg-danger',
            'label' => 'Vencido',
        ];
    }
    if ($alertaVenc === 'por_vencer') {
        return [
            'slip' => $prefijo . '--ocupado ' . $prefijo . '--por-vencer',
            'pill' => 'mapa-slip-pill--por-vencer',
            'badge' => 'mapa-badge-por-vencer',
            'label' => 'Por vencer',
        ];
    }

    return [
        'slip' => $prefijo . '--ocupado',
        'pill' => 'mapa-slip-pill--ocupado',
        'badge' => 'bg-success',
        'label' => 'Ocupado',
    ];
}

/** Días de estadía inclusive entre dos fechas (DATE). */
function marina_contrato_dias_estadia(string $fechaInicio, string $fechaFin): int
{
    try {
        $d1 = new DateTime($fechaInicio);
        $d2 = new DateTime($fechaFin);
    } catch (Exception $e) {
        return 0;
    }
    if ($d2 < $d1) {
        return 0;
    }
    return (int) $d1->diff($d2)->days + 1;
}

/** ITBMS % desde formulario: vacío = sin impuesto (null). */
function marina_contrato_parse_impuesto_porcentaje(string $raw): ?float
{
    $raw = trim($raw);
    if ($raw === '') {
        return null;
    }
    $n = (float) str_replace(',', '.', $raw);
    if ($n < 0) {
        return null;
    }
    return round($n, 2);
}

/**
 * @return array{subtotal_dia: float, subtotal_pie: float, subtotal: float, impuesto: float, total: float}|null
 */
function marina_contrato_calcular_montos(
    PDO $pdo,
    string $fechaInicio,
    string $fechaFin,
    int $tarifaDiaId,
    int $tarifaPieId,
    float $cantidadPies,
    ?float $impuestoPorcentaje
): ?array {
    $subtotalDia = 0.0;
    $subtotalPie = 0.0;
    if ($tarifaDiaId > 0) {
        $st = $pdo->prepare("SELECT precio_dia, COALESCE(tipo, 'dia') AS tipo FROM tarifas WHERE id = ?");
        $st->execute([$tarifaDiaId]);
        $t = $st->fetch(PDO::FETCH_ASSOC);
        if ($t && (string) ($t['tipo'] ?? 'dia') === 'dia') {
            $dias = marina_contrato_dias_estadia($fechaInicio, $fechaFin);
            if ($dias > 0) {
                $subtotalDia = round((float) $t['precio_dia'] * $dias, 2);
            }
        }
    }
    if ($tarifaPieId > 0 && $cantidadPies > 0) {
        $st = $pdo->prepare("SELECT precio_dia, COALESCE(tipo, 'dia') AS tipo FROM tarifas WHERE id = ?");
        $st->execute([$tarifaPieId]);
        $t = $st->fetch(PDO::FETCH_ASSOC);
        if ($t && (string) ($t['tipo'] ?? 'dia') === 'pie') {
            $subtotalPie = round((float) $t['precio_dia'] * $cantidadPies, 2);
        }
    }
    $subtotal = round($subtotalDia + $subtotalPie, 2);
    if ($subtotal <= 0 && $tarifaDiaId <= 0 && $tarifaPieId <= 0) {
        return null;
    }
    $impuesto = 0.0;
    if ($impuestoPorcentaje !== null && $impuestoPorcentaje > 0) {
        $impuesto = round($subtotal * $impuestoPorcentaje / 100, 2);
    }
    return [
        'subtotal_dia' => $subtotalDia,
        'subtotal_pie' => $subtotalPie,
        'subtotal' => $subtotal,
        'impuesto' => $impuesto,
        'total' => round($subtotal + $impuesto, 2),
    ];
}

/**
 * Resumen de cuotas por contrato_id (mapas, modales). Misma lógica de pagado que reporte de cuotas.
 *
 * @return array<int, list<array{numero_cuota: int, monto: float, pagado: float, saldo: float, fecha_vencimiento: string, estado: string}>>
 */
function marina_cuotas_resumen_por_contrato(PDO $pdo): array
{
    $cuotasByContrato = [];
    $stCuotas = $pdo->query("
        SELECT c.id, c.contrato_id, c.numero_cuota, c.monto, c.fecha_vencimiento, c.fecha_pago AS fecha_pago_legacy,
               COALESCE(SUM(CASE WHEN m.tipo IN ('pago','abono') THEN m.monto ELSE 0 END), 0) AS pagado_mov
        FROM cuotas c
        LEFT JOIN cuotas_movimientos m ON m.cuota_id = c.id
        GROUP BY c.id, c.contrato_id, c.numero_cuota, c.monto, c.fecha_vencimiento, c.fecha_pago
        ORDER BY c.contrato_id, c.numero_cuota
    ");
    $hoy = date('Y-m-d');
    while ($q = $stCuotas->fetch(PDO::FETCH_ASSOC)) {
        $cid = (int) ($q['contrato_id'] ?? 0);
        if ($cid < 1) {
            continue;
        }
        $monto = (float) ($q['monto'] ?? 0);
        $pagadoMov = (float) ($q['pagado_mov'] ?? 0);
        if ($pagadoMov > 0.00001) {
            $pagado = $pagadoMov;
        } elseif (!empty($q['fecha_pago_legacy'])) {
            $pagado = $monto;
        } else {
            $pagado = 0.0;
        }
        $saldo = max(0, $monto - $pagado);
        $fv = (string) ($q['fecha_vencimiento'] ?? '');
        if ($saldo <= 0.00001) {
            $estado = 'Pagada';
        } elseif ($fv !== '' && $fv < $hoy) {
            $estado = 'Vencida';
        } else {
            $estado = 'Pendiente';
        }
        if (!isset($cuotasByContrato[$cid])) {
            $cuotasByContrato[$cid] = [];
        }
        $cuotasByContrato[$cid][] = [
            'numero_cuota' => (int) ($q['numero_cuota'] ?? 0),
            'monto' => $monto,
            'pagado' => $pagado,
            'saldo' => $saldo,
            'fecha_vencimiento' => $fv,
            'estado' => $estado,
        ];
    }

    return $cuotasByContrato;
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
