<?php

declare(strict_types=1);

namespace App\Reporting\Ple;

use App\Efact\Service\CompanyContext;
use Doctrine\DBAL\Connection;

/**
 * Genera el «Registro de Ventas e Ingresos» electrónico (PLE 14.1) a partir de
 * los comprobantes emitidos (tabla tax_document) para un periodo dado.
 *
 * La estructura sigue el formato 14.1 de SUNAT. Cada registro contiene los
 * campos en el orden definido por la resolución, separados por «|».
 *
 * Nota: los importes se reportan en la moneda del comprobante. SUNAT exige el
 * tipo de cambio cuando la moneda no es PEN para su conversión.
 */
final readonly class SalesLedgerExporter
{
    private const BOOK_CODE = '140100';

    /** Tipos de comprobante incluidos en el Registro de Ventas (Tabla 10). */
    private const SALE_DOC_TYPES = ['01', '03', '07', '08'];

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
            'key' => 'sales',
            'pleCode' => 'LE14.1',
            'label' => 'Registro de Ventas',
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
            $amounts = $this->resolveAmounts($row);
            $content .= $this->buildRecord($periodKey, $correlative, $row);
            $previewRows[] = $this->toPreviewRow($correlative, $row, $amounts);

            if ($amounts['state'] === '1') {
                $totals['taxable'] += $amounts['taxable'];
                $totals['igv'] += $amounts['igv'];
                $totals['total'] += $amounts['total'];
            }
        }

        $ruc = $this->companyContext->getId();
        $filename = PleWriter::fileName($ruc, $period, self::BOOK_CODE, $rows !== []);

        return [
            'filename' => $filename,
            'content' => $content,
            'recordCount' => count($rows),
            'sizeBytes' => strlen($content),
            'rows' => $previewRows,
            'totals' => $totals,
        ];
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array{taxable: float, igv: float, total: float, state: string}
     */
    private function resolveAmounts(array $row): array
    {
        $isVoided = $row['void_ticket'] !== null || $row['void_correlation'] !== null;
        $taxable = (float) ($row['taxable_amount'] ?? 0);
        $igv = (float) ($row['igv_amount'] ?? 0);
        $total = (float) ($row['total'] ?? 0);
        $sign = (string) $row['document_type'] === '07' ? -1 : 1;

        if ($isVoided) {
            return [
                'taxable' => 0.0,
                'igv' => 0.0,
                'total' => 0.0,
                'state' => '2',
            ];
        }

        return [
            'taxable' => $taxable * $sign,
            'igv' => $igv * $sign,
            'total' => $total * $sign,
            'state' => '1',
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @param array{taxable: float, igv: float, total: float, state: string} $amounts
     *
     * @return array<string, mixed>
     */
    private function toPreviewRow(int $index, array $row, array $amounts): array
    {
        return [
            'index' => $index,
            'issueDate' => PleWriter::date($this->toDate($row['issue_date'] ?? null)),
            'documentType' => (string) $row['document_type'],
            'series' => (string) $row['series'],
            'number' => (string) $row['number'],
            'counterpartyDoc' => (string) ($row['customer_doc'] ?? ''),
            'counterpartyName' => (string) ($row['customer_name'] ?? 'VARIOS'),
            'taxable' => round($amounts['taxable'], 2),
            'igv' => round($amounts['igv'], 2),
            'total' => round($amounts['total'], 2),
            'state' => $amounts['state'],
            'stateLabel' => $amounts['state'] === '2' ? 'Anulado' : 'Activo',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchRows(string $period): array
    {
        [$start, $end] = $this->periodBounds($period);

        $placeholders = implode(',', array_fill(0, count(self::SALE_DOC_TYPES), '?'));

        $sql = <<<SQL
                SELECT
                    td.document_type,
                    td.series,
                    td.number,
                    td.issue_date,
                    td.taxable_amount,
                    td.igv_amount,
                    td.total,
                    td.currency,
                    td.exchange_rate,
                    td.operation_type,
                    td.void_ticket,
                    td.void_correlation,
                    c.ruc          AS customer_doc,
                    c.razon_social AS customer_name,
                    rel.document_type AS modified_doc_type,
                    rel.series        AS modified_series,
                    rel.number        AS modified_number,
                    rel.issue_date    AS modified_issue_date
                FROM tax_document td
                LEFT JOIN cliente c ON c.id = td.customer_id
                LEFT JOIN tax_document rel ON rel.tax_document_id = td.related_tax_document_id
                WHERE td.issue_date BETWEEN ? AND ?
                  AND td.document_type IN ($placeholders)
                ORDER BY td.issue_date, td.series, CAST(td.number AS UNSIGNED)
            SQL;

        $params = array_merge([$start, $end], self::SALE_DOC_TYPES);

        /** @var list<array<string, mixed>> $rows */
        $rows = $this->connection->fetchAllAssociative($sql, $params);

        return $rows;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function buildRecord(string $periodKey, int $correlative, array $row): string
    {
        $cuo = sprintf('M%06d', $correlative);
        [$docTypeId, $docNumber] = $this->customerDocument((string) ($row['customer_doc'] ?? ''));

        $amounts = $this->resolveAmounts($row);
        $taxable = $amounts['taxable'];
        $igv = $amounts['igv'];
        $total = $amounts['total'];
        $state = $amounts['state'];
        $currency = (string) ($row['currency'] ?? 'PEN');
        $exchangeRate = PleWriter::amount($row['exchange_rate'] ?? '1');

        $issueDate = $this->toDate($row['issue_date'] ?? null);
        $modifiedDate = $this->toDate($row['modified_issue_date'] ?? null);

        return PleWriter::record([
            $periodKey, // 1  Periodo
            $cuo, // 2  CUO
            $cuo, // 3  Correlativo del asiento
            PleWriter::date($issueDate), // 4  Fecha de emisión
            '', // 5  Fecha de vencimiento/pago
            (string) $row['document_type'], // 6  Tipo de comprobante (Tabla 10)
            (string) $row['series'], // 7  Serie
            (string) $row['number'], // 8  Número
            '', // 9  Número final (rango de tickets)
            $docTypeId, // 10 Tipo doc. identidad cliente (Tabla 2)
            $docNumber, // 11 Número doc. identidad cliente
            (string) ($row['customer_name'] ?? 'VARIOS'), // 12 Razón social / nombre del cliente
            '0.00', // 13 Valor facturado de exportación
            PleWriter::amount($taxable), // 14 Base imponible gravada
            '0.00', // 15 Descuento de la base imponible
            PleWriter::amount($igv), // 16 IGV / IPM
            '0.00', // 17 Descuento del IGV / IPM
            '0.00', // 18 Importe exonerado
            '0.00', // 19 Importe inafecto
            '0.00', // 20 ISC
            '0.00', // 21 Base imponible IVAP
            '0.00', // 22 IVAP
            '0.00', // 23 ICBPER
            '0.00', // 24 Otros tributos y cargos
            PleWriter::amount($total), // 25 Importe total
            $currency, // 26 Moneda (Tabla 4)
            $exchangeRate, // 27 Tipo de cambio
            PleWriter::date($modifiedDate), // 28 Fecha emisión doc. modificado
            (string) ($row['modified_doc_type'] ?? ''), // 29 Tipo doc. modificado (Tabla 10)
            (string) ($row['modified_series'] ?? ''), // 30 Serie doc. modificado
            '', // 31 Código de dependencia aduanera (DAM)
            (string) ($row['modified_number'] ?? ''), // 32 Número doc. modificado
            '', // 33 Identificación contrato/proyecto
            '', // 34 Error tipo 1 (inconsistencia TC)
            '', // 35 Indicador cancelado con medio de pago
            $state, // 36 Estado
        ]);
    }

    /**
     * Deriva el tipo (Tabla 2) y número de documento de identidad del cliente a
     * partir del número: 11 dígitos = RUC (6), 8 dígitos = DNI (1), otro = sin
     * documento (0). Sin cliente se reporta como consumidor varios.
     *
     * @return array{0: string, 1: string}
     */
    private function customerDocument(string $doc): array
    {
        $doc = trim($doc);
        if ($doc === '') {
            return ['0', '00000000'];
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
