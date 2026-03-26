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
 */
function exportarExcel(string $nombreBase, array $headers, array $rows, ?array $filasPie = null): void
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

    echo "\xEF\xBB\xBF";
    echo '<html xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta charset="UTF-8"><meta http-equiv="Content-Type" content="text/html; charset=UTF-8"></head><body>';
    echo '<table border="1" cellspacing="0" cellpadding="4">';

    echo '<thead><tr style="background-color:#d9e2f3;font-weight:bold;">';
    foreach ($headers as $h) {
        echo '<th>' . htmlspecialchars((string) $h, ENT_QUOTES, 'UTF-8') . '</th>';
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

function marinaExcelCelda($v): string
{
    if ($v === null) {
        $v = '';
    }
    if (is_bool($v)) {
        $v = $v ? 'Sí' : 'No';
    }
    if (is_int($v)) {
        return '<td style="text-align:right;">' . htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8') . '</td>';
    }
    if (is_float($v)) {
        $txt = number_format($v, 2, '.', '');

        return '<td style="mso-number-format:&quot;#,##0.00&quot;;text-align:right;">'
            . htmlspecialchars($txt, ENT_QUOTES, 'UTF-8') . '</td>';
    }

    return '<td>' . htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8') . '</td>';
}
