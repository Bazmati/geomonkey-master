<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Service générique d'export CSV.
 * - Delimiter ";" : ouverture directe dans Excel FR
 * - BOM UTF-8 : accents corrects dans Excel
 * - StreamedResponse : aucun buffer mémoire, même sur des milliers de lignes
 */
class CsvExporter
{
    private const DELIMITER = ';';

    /**
     * @param array<int, string> $headers     En-têtes de colonnes
     * @param iterable           $records      Entités (ou tableaux) à exporter
     * @param callable           $rowFormatter fn($record): array — une ligne = un tableau de cellules
     */
    public function response(string $filename, array $headers, iterable $records, callable $rowFormatter): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($headers, $records, $rowFormatter): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM UTF-8 pour Excel
            fputcsv($out, $headers, self::DELIMITER);

            foreach ($records as $record) {
                fputcsv($out, $rowFormatter($record), self::DELIMITER);
            }

            fclose($out);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set(
            'Content-Disposition',
            $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename)
        );

        return $response;
    }
}