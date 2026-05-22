<?php
/**
 * Exportación a Excel (.xlsx) con ZipArchive; respaldo HTML (.xls) si no hay extensión zip.
 */
declare(strict_types=1);

/**
 * @param list<string|int|float|bool|null> $headers
 * @param list<list<mixed>> $rows
 * @param list<list<mixed>>|null $filasPie Filas al final (ej. totales), mismas columnas que encabezados
 * @param string|null $titulo Título del reporte. Si null o vacío, se deduce de $nombreBase.
 */
function exportarExcel(string $nombreBase, array $headers, array $rows, ?array $filasPie = null, ?string $titulo = null): void
{
    $base = preg_replace('/[^a-zA-Z0-9_-]/', '_', $nombreBase) ?: 'reporte';
    $stamp = date('Ymd_His');

    if (class_exists('ZipArchive')) {
        marinaExportarExcelXlsxZip($base . '_' . $stamp, $headers, $rows, $filasPie, $titulo, $nombreBase);
        return;
    }

    marinaExportarExcelHtml($base . '_' . $stamp . '.xls', $headers, $rows, $filasPie, $titulo, $nombreBase);
}

/**
 * @param list<string|int|float|bool|null> $headers
 * @param list<list<mixed>> $rows
 * @param list<list<mixed>>|null $filasPie
 */
function marinaExportarExcelXlsxZip(string $filename, array $headers, array $rows, ?array $filasPie, ?string $titulo, string $nombreBase): void
{
    $ncol = count($headers);
    $subtitulo = marinaExcelSubtitulo($titulo, $nombreBase);
    $sheetRows = [];

    if ($ncol > 0) {
        $sheetRows[] = ['Vista Mar Marina Panamá'];
        $sheetRows[] = [$subtitulo];
        $sheetRows[] = array_fill(0, $ncol, '');
        $sheetRows[] = array_fill(0, $ncol, '');
    }
    $sheetRows[] = array_values($headers);

    foreach ($rows as $row) {
        $sheetRows[] = marinaExcelNormalizarFila($row, $ncol);
    }
    if ($filasPie !== null) {
        foreach ($filasPie as $pie) {
            $sheetRows[] = marinaExcelNormalizarFila($pie, $ncol);
        }
    }

    $sheetXml = marinaXlsxConstruirSheetXml($sheetRows);
    $tmp = tempnam(sys_get_temp_dir(), 'marxlsx_');
    if ($tmp === false) {
        marinaExportarExcelHtml($filename . '.xls', $headers, $rows, $filasPie, $titulo, $nombreBase);
        return;
    }

    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
        @unlink($tmp);
        marinaExportarExcelHtml($filename . '.xls', $headers, $rows, $filasPie, $titulo, $nombreBase);
        return;
    }

    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '</Types>');
    $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>');
    $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
        . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets><sheet name="Datos" sheetId="1" r:id="rId1"/></sheets></workbook>');
    $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        . '</Relationships>');
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
    $zip->close();

    if (ob_get_length()) {
        @ob_end_clean();
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
    header('Content-Length: ' . (string) filesize($tmp));
    header('Pragma: no-cache');
    header('Expires: 0');
    readfile($tmp);
    @unlink($tmp);
    exit;
}

/**
 * @param list<list<mixed>> $sheetRows
 */
function marinaXlsxConstruirSheetXml(array $sheetRows): string
{
    $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<sheetData>';
    $r = 1;
    foreach ($sheetRows as $row) {
        $xml .= '<row r="' . $r . '">';
        $c = 0;
        foreach ($row as $val) {
            $c++;
            $ref = marinaXlsxColLetter($c - 1) . $r;
            [$txt, $tipo] = marinaXlsxValorCelda($val);
            if ($tipo === 'n') {
                $xml .= '<c r="' . $ref . '" t="n"><v>' . $txt . '</v></c>';
            } else {
                $xml .= '<c r="' . $ref . '" t="inlineStr"><is><t>'
                    . marinaXlsxEsc($txt) . '</t></is></c>';
            }
        }
        $xml .= '</row>';
        $r++;
    }
    $xml .= '</sheetData></worksheet>';

    return $xml;
}

function marinaXlsxColLetter(int $index): string
{
    $s = '';
    $n = $index + 1;
    while ($n > 0) {
        $n--;
        $s = chr(65 + ($n % 26)) . $s;
        $n = intdiv($n, 26);
    }

    return $s;
}

/** @return array{0: string, 1: 'n'|'str'} */
function marinaXlsxValorCelda($v): array
{
    if ($v === null) {
        return ['', 'str'];
    }
    if (is_bool($v)) {
        return [$v ? 'Sí' : 'No', 'str'];
    }
    if (is_int($v)) {
        return [(string) $v, 'n'];
    }
    if (is_float($v)) {
        return [sprintf('%.10F', $v), 'n'];
    }

    return [(string) $v, 'str'];
}

function marinaXlsxEsc(string $s): string
{
    return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

/**
 * @param list<mixed> $row
 * @return list<mixed>
 */
function marinaExcelNormalizarFila(array $row, int $ncol): array
{
    $row = array_values($row);
    $out = [];
    for ($i = 0; $i < $ncol; $i++) {
        $out[] = $row[$i] ?? '';
    }

    return $out;
}

function marinaExcelSubtitulo(?string $titulo, string $nombreBase): string
{
    $subtitulo = $titulo !== null ? trim($titulo) : '';
    if ($subtitulo !== '') {
        return $subtitulo;
    }
    $subtitulo = str_replace('_', ' ', $nombreBase);
    if (function_exists('mb_convert_case')) {
        return (string) mb_convert_case($subtitulo, MB_CASE_TITLE, 'UTF-8');
    }

    return (string) ucwords($subtitulo);
}

/**
 * @param list<string|int|float|bool|null> $headers
 * @param list<list<mixed>> $rows
 * @param list<list<mixed>>|null $filasPie
 */
function marinaExportarExcelHtml(string $filename, array $headers, array $rows, ?array $filasPie, ?string $titulo, string $nombreBase): void
{
    if (ob_get_length()) {
        @ob_end_clean();
    }

    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $ncol = count($headers);
    $subtitulo = marinaExcelSubtitulo($titulo, $nombreBase);
    $imgSrc = marinaExcelOrigenImagenLogo();

    echo "\xEF\xBB\xBF";
    echo '<html xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta charset="UTF-8"><meta http-equiv="Content-Type" content="text/html; charset=UTF-8"></head><body>';
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

function marinaExcelEstiloBordeCelda(): string
{
    return 'border:1px solid #8c8c8c;';
}

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

    return '<tr>'
        . '<th style="text-align:left;vertical-align:top;width:1%; padding:6px; white-space:nowrap;' . $sin . '">' . $img . '</th>'
        . '<th colspan="' . (string) ($ncol - 1) . '" style="' . $sin . '">&nbsp;</th>'
        . '</tr>';
}

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
