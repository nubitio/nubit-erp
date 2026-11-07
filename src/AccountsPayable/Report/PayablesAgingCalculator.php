<?php

declare(strict_types=1);

namespace App\AccountsPayable\Report;

use App\Efact\Domain\Money;
use DateTimeImmutable;

/**
 * Buckets outstanding supplier-bill balances by days past due at a cut-off date:
 * not yet due / 1–30 / 31–60 / 61–90 / 90+. Currencies are never added together.
 *
 * @phpstan-type BillRow array{supplierId: string, ruc: string, supplier: string, currency: string, document: string, issueDate: string, dueDate: string, balanceDue: string}
 * @phpstan-type Row array<string, string|int>
 */
final readonly class PayablesAgingCalculator
{
    public const string NOT_DUE = 'not_due';

    public const string DAYS_1_30 = '1-30';

    public const string DAYS_31_60 = '31-60';

    public const string DAYS_61_90 = '61-90';

    public const string DAYS_90_PLUS = '90+';

    public const array BUCKETS = [
        self::NOT_DUE,
        self::DAYS_1_30,
        self::DAYS_31_60,
        self::DAYS_61_90,
        self::DAYS_90_PLUS,
    ];

    /**
     * @param list<BillRow> $bills
     *
     * @return array{detail: list<Row>, suppliers: list<Row>, totals: list<Row>}
     */
    public function calculate(array $bills, string $asOf): array
    {
        $cutoff = new DateTimeImmutable($asOf);
        $detail = [];
        /** @var array<string, AgingTotals> $suppliers */
        $suppliers = [];
        /** @var array<string, AgingTotals> $totals */
        $totals = [];

        foreach ($bills as $bill) {
            $balance = Money::of($bill['balanceDue']);
            if ($balance->isLessThanOrEqualTo(0) || $bill['issueDate'] > $asOf) {
                continue;
            }

            $daysOverdue = $this->daysOverdue($bill['dueDate'], $cutoff);
            $bucket = $this->bucket($daysOverdue);

            $detail[] = [
                'supplierId' => $bill['supplierId'],
                'ruc' => $bill['ruc'],
                'supplier' => $bill['supplier'],
                'currency' => $bill['currency'],
                'document' => $bill['document'],
                'issueDate' => $bill['issueDate'],
                'dueDate' => $bill['dueDate'],
                'daysOverdue' => $daysOverdue,
                'bucket' => $bucket,
                'balanceDue' => Money::toString($balance),
            ];

            $supplierKey = $bill['currency'] . '|' . $bill['supplierId'];
            $suppliers[$supplierKey] ??= new AgingTotals(
                $bill['supplierId'],
                $bill['ruc'],
                $bill['supplier'],
                $bill['currency'],
            );
            $suppliers[$supplierKey]->add($bucket, $balance);

            $totals[$bill['currency']] ??= new AgingTotals('', '', 'TOTAL', $bill['currency']);
            $totals[$bill['currency']]->add($bucket, $balance);
        }

        return [
            'detail' => $this->sorted($detail, ['supplier', 'dueDate']),
            'suppliers' => $this->sorted(array_map(
                static fn(AgingTotals $t): array => $t->toRow(),
                array_values($suppliers),
            ), ['currency', 'supplier']),
            'totals' => $this->sorted(array_map(
                static fn(AgingTotals $t): array => $t->toRow(),
                array_values($totals),
            ), ['currency']),
        ];
    }

    private function daysOverdue(string $dueDate, DateTimeImmutable $cutoff): int
    {
        $due = new DateTimeImmutable($dueDate);

        return $due > $cutoff ? 0 : (int) $due->diff($cutoff)->days;
    }

    private function bucket(int $daysOverdue): string
    {
        return match (true) {
            $daysOverdue <= 0 => self::NOT_DUE,
            $daysOverdue <= 30 => self::DAYS_1_30,
            $daysOverdue <= 60 => self::DAYS_31_60,
            $daysOverdue <= 90 => self::DAYS_61_90,
            default => self::DAYS_90_PLUS,
        };
    }

    /**
     * @param list<Row> $rows
     * @param list<string> $keys
     *
     * @return list<Row>
     */
    private function sorted(array $rows, array $keys): array
    {
        usort($rows, static function (array $a, array $b) use ($keys): int {
            foreach ($keys as $key) {
                $cmp = strcmp((string) $a[$key], (string) $b[$key]);
                if ($cmp !== 0) {
                    return $cmp;
                }
            }

            return 0;
        });

        return $rows;
    }
}
