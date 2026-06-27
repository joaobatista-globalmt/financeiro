<?php
/**
 * CsvExporter - Exportação para CSV
 *
 * Gera CSV com BOM UTF-8 e separador ; para compatibilidade com Excel BR.
 * Headers HTTP corretos para download.
 */

declare(strict_types=1);

final class CsvExporter
{
    /**
     * Envia CSV para download.
     *
     * @param string $filename Nome do arquivo (sem extensão)
     * @param array $headers Cabeçalhos das colunas
     * @param array $rows Linhas de dados (array de arrays)
     */
    public static function download(string $filename, array $headers, array $rows): void
    {
        $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $filename);
        $filename = $filename . '_' . date('Ymd_His') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');

        $out = fopen('php://output', 'w');

        // BOM UTF-8 para Excel reconhecer acentos
        fwrite($out, "\xEF\xBB\xBF");

        // Cabeçalhos
        fputcsv($out, $headers, ';');

        // Linhas
        foreach ($rows as $row) {
            fputcsv($out, $row, ';');
        }

        fclose($out);
        exit;
    }
}