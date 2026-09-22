<?php

declare(strict_types=1);

namespace App\Reporting\Ple;

use App\Accounting\Message\EconomicEvent;
use App\Organization\Service\CompanyContext;
use Doctrine\DBAL\Connection;

/**
 * Genera el Libro Diario (PLE 5.1) y el Libro Mayor (PLE 6.1) a partir de los
 * asientos de partida doble del periodo.
 *
 * Ambos libros comparten la estructura de 21 campos de SUNAT. El Diario se
 * ordena por fecha y asiento; el Mayor, por cuenta y luego fecha. El CUO se
 * deriva del número de asiento para que coincida entre los dos archivos.
 *
 * No incluye asiento de apertura ni de cierre: solo movimientos operativos
 * (ventas, compras y caja) del periodo seleccionado.
 */
final readonly class AccountingBookExporter
{
    public const string BOOK_DIARY = '050100';

    public const string BOOK_MAYOR = '060100';

    private const string ORDER_DIARY = 'e.entry_date ASC, e.entry_no ASC, l.line_no ASC';

    private const string ORDER_MAYOR = 'a.code ASC, e.entry_date ASC, e.entry_no ASC, l.line_no ASC';

    public function __construct(
        private Connection $connection,
        private CompanyContext $companyContext,
    ) {}

    /**
     * @return array{filename: string, content: string}
     */
    public function exportDiary(string $period): array
    {
        return $this->toDownload($this->buildPackage($period, 'diary'));
    }

    /**
     * @return array{filename: string, content: string}
     */
    public function exportMayor(string $period): array
    {
        return $this->toDownload($this->buildPackage($period, 'ledger'));
    }

    /**
     * @return array<string, mixed>
     */
    public function previewDiary(string $period): array
    {
        return $this->toPreview(
            $this->buildPackage($period, 'diary'),
            'diary',
            'LE05.1',
            'Libro Diario',
            self::BOOK_DIARY,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function previewMayor(string $period): array
    {
        return $this->toPreview(
            $this->buildPackage($period, 'ledger'),
            'ledger',
            'LE06.1',
            'Libro Mayor',
            self::BOOK_MAYOR,
        );
    }

    /**
     * @param array{
     *     filename: string,
     *     content: string,
     *     recordCount: int,
     *     sizeBytes: int,
     *     rows: list<array<string, mixed>>,
     *     totals: array{taxable: float, igv: float, total: float, debit: float, credit: float}
     * } $built
     *
     * @return array{filename: string, content: string}
     */
    private function toDownload(array $built): array
    {
        return [
            'filename' => $built['filename'],
            'content' => $built['content'],
        ];
    }

    /**
     * @param array{
     *     filename: string,
     *     content: string,
     *     recordCount: int,
     *     sizeBytes: int,
     *     rows: list<array<string, mixed>>,
     *     totals: array{taxable: float, igv: float, total: float, debit: float, credit: float}
     * } $built
     *
     * @return array<string, mixed>
     */
    private function toPreview(array $built, string $key, string $pleCode, string $label, string $bookCode): array
    {
        return [
            'key' => $key,
            'kind' => 'journal',
            'pleCode' => $pleCode,
            'label' => $label,
            'bookCode' => $bookCode,
            'filename' => $built['filename'],
            'recordCount' => $built['recordCount'],
            'sizeBytes' => $built['sizeBytes'],
            'status' => $built['recordCount'] > 0 ? 'ready' : 'empty',
            'statusLabel' => $built['recordCount'] > 0 ? 'Listo para enviar' : 'Sin registros',
            'rows' => $built['rows'],
            'totals' => $built['totals'],
        ];
    }

    /**
     * @return array{
     *     filename: string,
     *     content: string,
     *     recordCount: int,
     *     sizeBytes: int,
     *     rows: list<array<string, mixed>>,
     *     totals: array{taxable: float, igv: float, total: float, debit: float, credit: float}
     * }
     */
    private function buildPackage(string $period, string $book): array
    {
        $rows = $this->fetchRows($period, $book);
        $periodKey = $period . '00';
        $companyRuc = $this->companyContext->getId();
        $bookCode = $book === 'ledger' ? self::BOOK_MAYOR : self::BOOK_DIARY;

        $content = '';
        $previewRows = [];
        $totals = ['taxable' => 0.0, 'igv' => 0.0, 'total' => 0.0, 'debit' => 0.0, 'credit' => 0.0];
        $index = 0;

        foreach ($rows as $row) {
            ++$index;
            $debit = JournalPleDates::money($row['debit'] ?? 0);
            $credit = JournalPleDates::money($row['credit'] ?? 0);
            $content .= JournalPleFormatter::buildRecord($periodKey, $row, $companyRuc);
            $previewRows[] = JournalPleFormatter::toPreviewRow($index, $row, $debit, $credit);
            $totals['debit'] += $debit;
            $totals['credit'] += $credit;
        }

        $totals['debit'] = round($totals['debit'], 2);
        $totals['credit'] = round($totals['credit'], 2);

        return [
            'filename' => PleWriter::fileName($companyRuc, $period, $bookCode, $rows !== []),
            'content' => $content,
            'recordCount' => count($rows),
            'sizeBytes' => strlen($content),
            'rows' => $previewRows,
            'totals' => $totals,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchRows(string $period, string $book): array
    {
        [$start, $end] = JournalPleDates::periodBounds($period);
        $orderBy = $book === 'ledger' ? self::ORDER_MAYOR : self::ORDER_DIARY;

        $sql = <<<SQL
                SELECT
                    e.entry_no,
                    e.entry_date,
                    e.description,
                    e.source,
                    e.currency,
                    l.line_no,
                    l.debit,
                    l.credit,
                    l.memo,
                    a.code AS account_code,
                    a.name AS account_name,
                    s.series AS sale_series,
                    s.number AS sale_number,
                    (
                        SELECT td.document_type
                        FROM tax_document td
                        WHERE td.sale_id = s.id
                        ORDER BY td.issue_date ASC, td.tax_document_id ASC
                        LIMIT 1
                    ) AS sale_doc_type,
                    gr.supplier_doc_type,
                    gr.supplier_doc_series,
                    gr.supplier_doc_number,
                    p.ruc AS supplier_ruc
                FROM journal_entry_line l
                INNER JOIN journal_entry e ON e.id = l.journal_entry_id
                INNER JOIN chart_of_accounts a ON a.id = l.account_id
                LEFT JOIN sales s ON e.source = :sale_source AND s.id = e.source_id
                LEFT JOIN goods_receipts gr ON e.source = :purchase_source AND gr.id = e.source_id
                LEFT JOIN proveedor p ON p.proveedor_id = gr.supplier_id
                WHERE e.entry_date BETWEEN :start AND :end
                  AND (l.debit <> 0 OR l.credit <> 0)
                ORDER BY {$orderBy}
            SQL;

        return $this->connection->fetchAllAssociative($sql, [
            'sale_source' => EconomicEvent::SOURCE_SALE,
            'purchase_source' => EconomicEvent::SOURCE_PURCHASE,
            'start' => $start,
            'end' => $end,
        ]);
    }
}
