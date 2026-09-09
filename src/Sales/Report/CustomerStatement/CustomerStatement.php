<?php

declare(strict_types=1);

namespace App\Sales\Report\CustomerStatement;

/**
 * A customer's cuenta corriente for a date range: chronological charges and
 * abonos with a running balance, period totals, and an aging breakdown of the
 * still-open balance at the cut-off date.
 *
 * Built only from `sales` (credit sales / `receivable_account = 1`, opening
 * balances included) and `sale_payments` — see issue #366. When #367 lands the
 * consolidated customer ledger, this becomes a read over that instead.
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
