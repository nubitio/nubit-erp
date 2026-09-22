<?php

declare(strict_types=1);

namespace App\Reporting\Ple;

use App\Organization\Service\CompanyContext;
use Doctrine\DBAL\Connection;

/**
 * Genera el «Registro de Compras» electrónico (PLE 8.1) a partir de las
 * recepciones de mercadería (tabla goods_receipts) para un periodo dado.
 *
 * Los campos del comprobante del proveedor (tipo, serie, número) se toman de
 * las columnas supplier_doc_* de la recepción. Si no fueron capturados, el tipo
 * de documento cae a «00» y la serie/número quedan vacíos; el documento debe
 * completarse para que el libro sea válido ante SUNAT.
 */
final readonly class PurchaseLedgerExporter
{
    private const BOOK_CODE = '080100';

    public function __construct(
        private Connection $connection,
        private CompanyContext $companyContext,
    ) {}

    /**
     * @param string $period Periodo en formato AAAAMM
     *
     * @return array{filename: string, content: string}
     */
    public function export(string $period): array
    {
        $built = $this->buildPackage($period);

        return [
            'filename' => $built['filename'],
            'content' => $built['content'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function preview(string $period): array
    {
        $built = $this->buildPackage($period);

        return [
            'key' => 'purchases',
            'pleCode' => 'LE8.1',
            'label' => 'Registro de Compras',
            'bookCode' => self::BOOK_CODE,
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
     *     totals: array{taxable: float, igv: float, total: float}
     * }
     */
    private function buildPackage(string $period): array
    {
        $rows = $this->fetchRows($period);
        $periodKey = $period . '00';

        $content = '';
        $previewRows = [];
        $totals = ['taxable' => 0.0, 'igv' => 0.0, 'total' => 0.0];
        $correlative = 0;

        foreach ($rows as $row) {
            ++$correlative;
            $taxable = (float) ($row['subtotal'] ?? 0);
            $igv = (float) ($row['tax_total'] ?? 0);
            $total = (float) ($row['grand_total'] ?? 0);

            $content .= $this->buildRecord($periodKey, $correlative, $row);
            $previewRows[] = $this->toPreviewRow($correlative, $row, $taxable, $igv, $total);

            $totals['taxable'] += $taxable;
            $totals['igv'] += $igv;
            $totals['total'] += $total;
        }

        $ruc = $this->companyContext->getId();
        $filename = PleWriter::fileName($ruc, $period, self::BOOK_CODE, $rows !== []);

        return [
            'filename' => $filename,
            'content' => $content,
            'recordCount' => count($rows),
            'sizeBytes' => strlen($content),
            'rows' => $previewRows,
            'totals' => [
                'taxable' => round($totals['taxable'], 2),
                'igv' => round($totals['igv'], 2),
                'total' => round($totals['total'], 2),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function toPreviewRow(int $index, array $row, float $taxable, float $igv, float $total): array
    {
        return [
            'index' => $index,
            'issueDate' => PleWriter::date($this->toDate($row['issued_at'] ?? null)),
            'documentType' => (string) ($row['supplier_doc_type'] ?? '00'),
            'series' => (string) ($row['supplier_doc_series'] ?? ''),
            'number' => (string) ($row['supplier_doc_number'] ?? ''),
            'counterpartyDoc' => (string) ($row['supplier_doc'] ?? ''),
            'counterpartyName' => (string) ($row['supplier_name'] ?? ''),
            'taxable' => round($taxable, 2),
            'igv' => round($igv, 2),
            'total' => round($total, 2),
            'state' => '1',
            'stateLabel' => 'Activo',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchRows(string $period): array
    {
        [$start, $end] = $this->periodBounds($period);

        $sql = <<<'SQL'
                SELECT
                    gr.supplier_doc_type,
                    gr.supplier_doc_series,
                    gr.supplier_doc_number,
                    gr.issued_at,
                    gr.subtotal,
                    gr.tax_total,
                    gr.grand_total,
                    gr.currency,
                    gr.exchange_rate,
                    p.ruc          AS supplier_doc,
                    p.razon_social AS supplier_name
                FROM goods_receipts gr
                LEFT JOIN proveedor p ON p.proveedor_id = gr.supplier_id
                WHERE gr.issued_at BETWEEN ? AND ?
                  AND gr.deleted_at IS NULL
                  AND gr.status = 'POSTED'
                ORDER BY gr.issued_at, gr.supplier_doc_series, gr.supplier_doc_number
            SQL;

        /** @var list<array<string, mixed>> $rows */
        $rows = $this->connection->fetchAllAssociative($sql, [$start, $end . ' 23:59:59']);

        return $rows;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function buildRecord(string $periodKey, int $correlative, array $row): string
    {
        $cuo = sprintf('M%06d', $correlative);

        [$docTypeId, $docNumber] = $this->supplierDocument((string) ($row['supplier_doc'] ?? ''));

        $taxable = (float) ($row['subtotal'] ?? 0);
        $igv = (float) ($row['tax_total'] ?? 0);
        $total = (float) ($row['grand_total'] ?? 0);
        $currency = (string) ($row['currency'] ?? 'PEN');
        $exchangeRate = PleWriter::amount($row['exchange_rate'] ?? '1');

        $issueDate = $this->toDate($row['issued_at'] ?? null);

        return PleWriter::record([
            $periodKey, // 1  Periodo
            $cuo, // 2  CUO
            $cuo, // 3  Correlativo del asiento
            PleWriter::date($issueDate), // 4  Fecha de emisión del comprobante
            '', // 5  Fecha de vencimiento/pago
            (string) ($row['supplier_doc_type'] ?? '00'), // 6  Tipo de comprobante (Tabla 10)
            (string) ($row['supplier_doc_series'] ?? ''), // 7  Serie / Año DUA
            (string) ($row['supplier_doc_number'] ?? ''), // 8  Número / DUA
            '', // 9  Código dependencia aduanera (Tabla 11)
            $docTypeId, // 10 Tipo doc. identidad proveedor (Tabla 2)
            $docNumber, // 11 Número doc. identidad proveedor
            (string) ($row['supplier_name'] ?? ''), // 12 Razón social del proveedor
            PleWriter::amount($taxable), // 13 Base imponible gravadas (op. gravadas)
            PleWriter::amount($igv), // 14 IGV / IPM de 13
            '0.00', // 15 Base imponible gravadas y no gravadas
            '0.00', // 16 IGV / IPM de 15
            '0.00', // 17 Base imponible no gravadas
            '0.00', // 18 IGV / IPM de 17
            '0.00', // 19 Valor de adquisiciones no gravadas
            '0.00', // 20 ISC
            '0.00', // 21 ICBPER
            '0.00', // 22 Otros tributos y cargos
            PleWriter::amount($total), // 23 Importe total
            $currency, // 24 Moneda (Tabla 4)
            $exchangeRate, // 25 Tipo de cambio
            '', // 26 Fecha emisión doc. modificado
            '', // 27 Tipo doc. modificado (Tabla 10)
            '', // 28 Serie doc. modificado
            '', // 29 Número doc. modificado / DUA
            '', // 30 Fecha constancia detracción
            '', // 31 Número constancia detracción
            '', // 32 Marca de comprobante sujeto a retención
            '', // 33 Clasificación bienes/servicios (Tabla 30)
            '', // 34 Identificación contrato/proyecto
            '', // 35 Error tipo 1
            '', // 36 Indicador cancelado con medio de pago
            '1', // 37 Estado
        ]);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function supplierDocument(string $doc): array
    {
        $doc = trim($doc);
        if ($doc === '') {
            return ['0', ''];
        }

        return match (strlen($doc)) {
            11 => ['6', $doc],
            8 => ['1', $doc],
            default => ['0', $doc],
        };
    }

    private function toDate(mixed $value): ?\DateTimeImmutable
    {
        if ($value instanceof \DateTimeImmutable) {
            return $value;
        }
        if (is_string($value) && $value !== '') {
            return new \DateTimeImmutable($value);
        }

        return null;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function periodBounds(string $period): array
    {
        $start = \DateTimeImmutable::createFromFormat('Ymd', $period . '01');
        if ($start === false) {
            throw new \InvalidArgumentException('Periodo inválido, se espera AAAAMM.');
        }
        $end = $start->modify('last day of this month');

        return [$start->format('Y-m-d'), $end->format('Y-m-d')];
    }
}
