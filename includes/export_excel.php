<?php
/**
 * Exportación a Excel (.xls): tabla HTML que Excel abre con columnas y filas correctas.
 * Opcional: filas de pie (totales), cada fila con la misma cantidad de columnas que encabezados.
 */
declare(strict_types=1);

/**
 * @param list<string|int|float|bool|null> $headers
 * @param list<list<mixed>> $rows
 * @param list<list<mixed>>|null $filasPie Filas al final (ej. totales), mismas columnas que encabezados
 * @param string|null $titulo Título mostrado en la primera fila (negrita, tamaño mayor). Si null o vacío, se deduce de $nombreBase.
 */
function exportarExcel(string $nombreBase, array $headers, array $rows, ?array $filasPie = null, ?string $titulo = null): void
{
    $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $nombreBase) ?: 'reporte';
    $filename .= '_' . date('Ymd_His') . '.xls';

    if (ob_get_length()) {
        @ob_end_clean();
    }

    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $ncol = count($headers);

    $subtitulo = $titulo !== null ? trim($titulo) : '';
    if ($subtitulo === '') {
        $subtitulo = str_replace('_', ' ', $nombreBase);
        if (function_exists('mb_convert_case')) {
            $subtitulo = (string) mb_convert_case($subtitulo, MB_CASE_TITLE, 'UTF-8');
        } else {
            $subtitulo = (string) ucwords($subtitulo);
        }
    }

    $imgSrc = marinaExcelOrigenImagenLogo();

    echo "\xEF\xBB\xBF";
    echo '<html xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta charset="UTF-8"><meta http-equiv="Content-Type" content="text/html; charset=UTF-8"></head><body>';
    // border=0 en la hoja: los bordes de la grilla se aplican solo a encabezados y datos
    echo '<table border="0" cellspacing="0" cellpadding="0" style="width:100%;border-collapse:collapse;">';

    echo '<thead>';
    if ($ncol > 0) {
        echo marinaExcelFilaSoloLogo($imgSrc, $ncol);
        echo marinaExcelFilasVaciasEncabezado(5, $ncol);
        echo marinaExcelFilaTitulosSobreTabla('Vista Mar Marina Panamá', $subtitulo, $ncol);
    }
    echo '<tr style="background-color:#d9e2f3;font-weight:bold;">';
    $bordeEnc = 'border:1px solid #8c8c8c; padding:4px;';
    foreach ($headers as $h) {
        echo '<th style="' . $bordeEnc . '">' . htmlspecialchars((string) $h, ENT_QUOTES, 'UTF-8') . '</th>';
    }
    echo '</tr></thead><tbody>';

    foreach ($rows as $row) {
        echo '<tr>';
        $row = array_values($row);
        for ($i = 0; $i < $ncol; $i++) {
            echo marinaExcelCelda($row[$i] ?? '');
        }
        echo '</tr>';
    }

    if ($filasPie !== null) {
        foreach ($filasPie as $pie) {
            echo '<tr style="background-color:#e2efd9;font-weight:bold;">';
            $pie = array_values($pie);
            for ($i = 0; $i < $ncol; $i++) {
                echo marinaExcelCelda($pie[$i] ?? '');
            }
            echo '</tr>';
        }
    }

    echo '</tbody></table></body></html>';
    exit;
}

/**
 * Ruta del archivo de logo bajo la raíz del proyecto (img/1.png).
 */
function marinaExcelRutaArchivoLogo(): ?string
{
    $candidatos = [];
    if (defined('MARINA_ROOT')) {
        $candidatos[] = rtrim((string) MARINA_ROOT, '/\\') . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . '1.png';
    }
    $candidatos[] = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . '1.png';
    foreach ($candidatos as $p) {
        if (is_file($p) && is_readable($p)) {
            return $p;
        }
    }
    return null;
}

/**
 * src del <img>: URL http(s) absoluta para Excel (el formato .xls vía HTML no muestra bien data: URI);
 * si no hay petición web, data URI a partir del archivo.
 */
function marinaExcelOrigenImagenLogo(): string
{
    $ruta = marinaExcelRutaArchivoLogo();
    if ($ruta === null) {
        return '';
    }
    $url = marinaExcelUrlPublicaImagenLogo();
    if ($url !== '') {
        return $url;
    }
    $bin = @file_get_contents($ruta);
    if ($bin === false) {
        return '';
    }
    return 'data:image/png;base64,' . base64_encode($bin);
}

function marinaExcelUrlPublicaImagenLogo(): string
{
    if (empty($_SERVER['HTTP_HOST'] ?? null) || !defined('MARINA_URL')) {
        return '';
    }
    $host = (string) $_SERVER['HTTP_HOST'];
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);
    $scheme = $https ? 'https' : 'http';
    $base = rtrim((string) MARINA_URL, '/');

    return $scheme . '://' . $host . $base . '/img/1.png';
}

/** Celdas de grilla: borde reforzado (la tabla principal va sin borde; las filas superiores se dejan en blanco). */
function marinaExcelEstiloBordeCelda(): string
{
    return 'border:1px solid #8c8c8c;';
}

/**
 * Filas de separación sin bordes ni rayas, solo altura, entre logo y títulos sobre la tabla.
 */
function marinaExcelFilasVaciasEncabezado(int $cantidad, int $ncol): string
{
    if ($cantidad < 1 || $ncol < 1) {
        return '';
    }
    $s = 'height:12pt;'
        . 'mso-style-name:Normal;'
        . 'border:0; border-width:0; border-style:none;'
        . 'mso-border-alt:none; mso-border-top-alt:0.0pt none; mso-border-left-alt:0.0pt none;'
        . 'mso-border-bottom-alt:0.0pt none; mso-border-right-alt:0.0pt none;'
        . 'background:#ffffff; padding:0; mso-line-height-rule:exactly;';
    $out = '';
    for ($i = 0; $i < $cantidad; $i++) {
        $out .= '<tr><td colspan="' . (string) $ncol
            . '" style="' . $s . '">&nbsp;</td></tr>';
    }
    return $out;
}

/**
 * Primera fila: solo logo a la izquierda; celdas vacías sin bordes.
 *
 * @param string $imagenSrc Valor de src: URL pública o data URI
 */
function marinaExcelFilaSoloLogo(string $imagenSrc, int $ncol): string
{
    if ($ncol <= 0) {
        return '';
    }
    $img = '';
    if ($imagenSrc !== '') {
        $img = '<img src="'
            . htmlspecialchars($imagenSrc, ENT_QUOTES, 'UTF-8')
            . '" alt="" style="height:64px;width:auto;max-width:220px;display:block;" />';
    }
    $sin = ' border:0; border-width:0; mso-border-alt:none; background:#ffffff;';
    if ($ncol === 1) {
        return '<tr><th style="text-align:left; vertical-align:top; padding:6px;' . $sin . '">' . $img . '</th></tr>';
    }
    $out = '<tr>'
        . '<th style="text-align:left;vertical-align:top;width:1%; padding:6px; white-space:nowrap;' . $sin . '">' . $img . '</th>'
        . '<th colspan="' . (string) ($ncol - 1) . '" style="' . $sin . '">&nbsp;</th>'
        . '</tr>';
    return $out;
}

/**
 * Marca y nombre del reporte, centrados, inmediatamente encima de la fila de encabezados de la tabla.
 */
function marinaExcelFilaTitulosSobreTabla(string $marca, string $nombreReporte, int $ncol): string
{
    if ($ncol <= 0) {
        return '';
    }
    $marcaE = htmlspecialchars($marca, ENT_QUOTES, 'UTF-8');
    $repE = htmlspecialchars($nombreReporte, ENT_QUOTES, 'UTF-8');
    $texto = '<div style="font-size:13pt;font-weight:bold;margin-bottom:6px;">' . $marcaE . '</div>'
        . '<div style="font-size:16pt;font-weight:bold;">' . $repE . '</div>';
    $s = 'text-align:center;vertical-align:middle;'
        . 'border:0; border-width:0; mso-border-alt:none; background:#ffffff;'
        . 'padding:8px 10px;';

    return '<tr><th colspan="' . (string) $ncol . '" style="' . $s . '">' . $texto . '</th></tr>';
}

function marinaExcelCelda($v): string
{
    $b = marinaExcelEstiloBordeCelda() . ' padding:4px;';
    if ($v === null) {
        $v = '';
    }
    if (is_bool($v)) {
        $v = $v ? 'Sí' : 'No';
    }
    if (is_int($v)) {
        return '<td style="' . $b . 'text-align:right;">' . htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8') . '</td>';
    }
    if (is_float($v)) {
        $txt = number_format($v, 2, '.', '');

        return '<td style="' . $b . 'mso-number-format:&quot;#,##0.00&quot;;text-align:right;">'
            . htmlspecialchars($txt, ENT_QUOTES, 'UTF-8') . '</td>';
    }

    return '<td style="' . $b . '">' . htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8') . '</td>';
}
