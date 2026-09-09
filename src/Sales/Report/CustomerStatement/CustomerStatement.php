<?php

declare(strict_types=1);

namespace App\Sales\Report\CustomerStatement;

/**
 * A customer's cuenta corriente for a date range: chronological charges and
 * abonos with a running balance, period totals, and an aging breakdown of the
 * still-open balance at the cut-off date.
 *
 * Since #384 the movements are read from the customer ledger
 * `customer_account_entries` (issue #367); `sales` is still joined for the
 * document label and the `balance_due` the aging buckets by.
 */
final readonly class CustomerStatement
{
    /**
     * @param list<StatementLine>      $lines
     * @param array<string, string>    $aging bucket label => amount, e.g. {"0-30": "120.00", ...}
     */
    public function __construct(
        public int $customerId,
        public string $customerName,
        public string $customerDoc,
        public string $dateFrom,
        public string $dateTo,
        public string $openingBalance,
        public array $lines,
        public string $totalCharges,
        public string $totalPayments,
        public string $closingBalance,
        public array $aging,
    ) {}
}
