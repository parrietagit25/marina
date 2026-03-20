<?php
/**
 * Exportador simple compatible con Excel (TSV con extensión .xls).
 */
declare(strict_types=1);

function exportarExcel(string $nombreBase, array $headers, array $rows): void
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

    $out = fopen('php://output', 'wb');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $headers, "\t");

    foreach ($rows as $row) {
        $line = [];
        foreach ($row as $v) {
            $line[] = is_scalar($v) || $v === null ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE);
        }
        fputcsv($out, $line, "\t");
    }

    fclose($out);
    exit;
}

