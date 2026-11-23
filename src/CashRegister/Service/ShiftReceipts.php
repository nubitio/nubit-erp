<?php

declare(strict_types=1);

namespace App\CashRegister\Service;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

/** The receipts a shift issued, grouped by type, as its reports print them. */
readonly class ShiftReceipts
{
    /** Receipt types of SUNAT: boleta, factura and credit note. */
    private const array DOCUMENT_TYPES = ['03' => 'Boletas', '01' => 'Facturas', '07' => 'N. de crédito'];

    public function __construct(
        private Connection $connection,
    ) {}

    /**
     * Receipts of the shift by type, with the numbering range of each series. A credit note takes away from what
     * the shift sold, so it carries a negative sign.
     *
     * @return array{rows: list<array<string, mixed>>, count: int, taxable: string, igv: string, total: string}
     * @throws Exception
     */
    public function forSession(int $sessionId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            "SELECT td.document_type, td.series, COUNT(*) AS quantity, MIN(td.number + 0) AS first_number,
                    MAX(td.number + 0) AS last_number, COALESCE(SUM(td.taxable_amount), 0) AS taxable,
                    COALESCE(SUM(td.igv_amount), 0) AS igv, COALESCE(SUM(td.total), 0) AS total
             FROM tax_document td
             JOIN sales s ON s.id = td.sale_id
             WHERE s.cash_session_id = ? AND s.deleted_at IS NULL AND s.status <> 'VOIDED'
               AND td.document_type IN ('01', '03', '07')
             GROUP BY td.document_type, td.series
             ORDER BY td.document_type, td.series",
            [$sessionId],
        );

        $byType = [];
        foreach ($rows as $row) {
            $type = (string) $row['document_type'];
            $sign = $type === '07' ? -1 : 1;
            $byType[$type] ??= [
                'count' => 0,
                'ranges' => [],
                'taxable' => BigDecimal::zero(),
                'igv' => BigDecimal::zero(),
                'total' => BigDecimal::zero(),
            ];
            $byType[$type]['count'] += (int) $row['quantity'];
            $first = sprintf('%s-%08d', $row['series'], (int) $row['first_number']);
            $last = sprintf('%08d', (int) $row['last_number']);
            $byType[$type]['ranges'][] = $first === sprintf('%s-%s', $row['series'], $last)
                ? $first
                : sprintf('%s a %s', $first, $last);
            foreach (['taxable', 'igv', 'total'] as $field) {
                $byType[$type][$field] = $byType[$type][$field]->plus(BigDecimal::of(
                    (string) $row[$field],
                )->multipliedBy($sign));
            }
        }

        $out = [];
        $count = 0;
        $taxable = BigDecimal::zero();
        $igv = BigDecimal::zero();
        $total = BigDecimal::zero();
        foreach (self::DOCUMENT_TYPES as $type => $label) {
            if (!array_key_exists($type, $byType)) {
                continue;
            }
            $entry = $byType[$type];
            $count += $entry['count'];
            $taxable = $taxable->plus($entry['taxable']);
            $igv = $igv->plus($entry['igv']);
            $total = $total->plus($entry['total']);
            $out[] = [
                'type' => $type,
                'label' => $label,
                'count' => $entry['count'],
                'ranges' => $entry['ranges'],
                'taxable' => $this->money($entry['taxable']),
                'igv' => $this->money($entry['igv']),
                'total' => $this->money($entry['total']),
            ];
        }

        return [
            'rows' => $out,
            'count' => $count,
            'taxable' => $this->money($taxable),
            'igv' => $this->money($igv),
            'total' => $this->money($total),
        ];
    }

    private function money(mixed $value): string
    {
        return BigDecimal::of((string) ($value ?? '0'))->toScale(2, RoundingMode::HALF_UP)->__toString();
    }
}
