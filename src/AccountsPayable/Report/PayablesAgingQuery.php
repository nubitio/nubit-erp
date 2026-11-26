<?php

declare(strict_types=1);

namespace App\AccountsPayable\Report;

use App\AccountsPayable\Entity\SupplierBill;
use Doctrine\DBAL\Connection;

/**
 * Loads a branch's open supplier bills and delegates bucketing to
 * {@see PayablesAgingCalculator}. Balances are the current ones, so the cut-off
 * date can only look forward (projected maturities), never into the past.
 *
 * @phpstan-import-type BillRow from PayablesAgingCalculator
 */
final readonly class PayablesAgingQuery
{
    public function __construct(
        private Connection $connection,
        private PayablesAgingCalculator $calculator,
    ) {}

    /**
     * @return array{detail: list<array<string, string|int>>, suppliers: list<array<string, string|int>>, totals: list<array<string, string|int>>}
     */
    public function execute(string $branchId, string $asOf): array
    {
        // The report's authorized branch can differ from the session's ORM filter.
        $bills = $this->connection->fetchAllAssociative(
            <<<'SQL'
                SELECT b.supplier_id, s.ruc, s.razon_social, b.currency, b.document_type,
                       b.series, b.number, b.issue_date, b.due_date, b.balance_due
                FROM supplier_bill b
                JOIN proveedor s ON s.proveedor_id = b.supplier_id
                WHERE b.branch_id = :branch AND b.balance_due > 0 AND b.status <> :void
                SQL,
            ['branch' => $branchId, 'void' => SupplierBill::VOID],
        );

        $rows = array_map(static function (array $bill): array {
            $series = trim((string) $bill['series']);
            $number = trim((string) $bill['number']);
            $reference = implode('-', array_filter([$series, $number], static fn(string $part): bool => $part !== ''));

            return [
                'supplierId' => (string) $bill['supplier_id'],
                'ruc' => (string) $bill['ruc'],
                'supplier' => (string) $bill['razon_social'],
                'currency' => (string) $bill['currency'],
                'document' => trim(sprintf('%s %s', (string) $bill['document_type'], $reference)),
                'issueDate' => (string) $bill['issue_date'],
                'dueDate' => (string) $bill['due_date'],
                'balanceDue' => (string) $bill['balance_due'],
            ];
        }, $bills);

        return $this->calculator->calculate($rows, $asOf);
    }
}
