<?php

declare(strict_types=1);

namespace App\Treasury\Report;

use App\Efact\Domain\Money;
use App\Treasury\Domain\AccountKind;
use App\Treasury\Service\PositionQuery;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;

/**
 * The owner's one-page answer to "how much do I have, who owes me, what do I owe, and what needs me today".
 * Everything is per currency and never converted (decision 3). It covers the whole business, every branch.
 *
 * It only reads: money from Treasury's own position, and what is owed from the sales and supplier-bill tables,
 * with the same filters the receivables and payables screens use (not voided, balance left, overdue / due in 7 /
 * due in 30 days).
 *
 * The projection is deliberately cautious: what you have today, plus receivables that fall due within 30 days
 * (overdue ones are not counted, nobody knows when they will be paid), minus every payable due within 30 days
 * and everything already overdue (it is owed now).
 */
final readonly class FinanceSummaryQuery
{
    public function __construct(
        private PositionQuery $position,
        private OpenItemsQuery $openItems,
        private FinanceAttentionQuery $attention,
    ) {}

    /**
     * @return array{
     *     asOf: string,
     *     currencies: list<array{
     *         currency: string,
     *         position: array{total: string, cash: string, banks: string, wallets: string, suspense: string},
     *         receivables: array{balance: string, overdue: string, dueWithinSevenDays: string, dueWithinThirtyDays: string, count: int, overdueCount: int},
     *         payables: array{balance: string, overdue: string, dueWithinSevenDays: string, dueWithinThirtyDays: string, count: int, overdueCount: int},
     *         projectedInThirtyDays: string,
     *     }>,
     *     attention: array{unreconciledBankMovements: int, suspense: list<array{currency: string, balance: string}>, accountsWithoutOpeningBalance: list<string>},
     * }
     */
    public function summarize(DateTimeImmutable $today): array
    {
        $position = $this->positionByCurrency();
        $receivables = $this->openItems->byCurrency(
            'sales',
            "receivable_account = 1 AND balance_due > 0 AND status <> 'VOIDED' AND deleted_at IS NULL",
            $today,
        );
        $payables = $this->openItems->byCurrency('supplier_bill', "balance_due > 0 AND status <> 'VOID'", $today);

        $currencies = array_unique([...array_keys($position), ...array_keys($receivables), ...array_keys($payables)]);
        sort($currencies);

        $rows = [];
        foreach ($currencies as $currency) {
            $pos = $position[$currency] ?? self::emptyPosition();
            $in = $receivables[$currency] ?? OpenItemsQuery::none();
            $out = $payables[$currency] ?? OpenItemsQuery::none();

            $projected = Money::money($pos['total'])
                ->plus(Money::money($in['dueWithinThirtyDays']))
                ->minus(Money::money($out['dueWithinThirtyDays']))
                ->minus(Money::money($out['overdue']));

            $rows[] = [
                'currency' => $currency,
                'position' => $pos,
                'receivables' => $in,
                'payables' => $out,
                'projectedInThirtyDays' => Money::toString($projected),
            ];
        }

        return [
            'asOf' => $today->format('Y-m-d'),
            'currencies' => $rows,
            'attention' => [
                'unreconciledBankMovements' => $this->attention->unreconciledBankMovements(),
                'suspense' => $this->attention->suspense(),
                'accountsWithoutOpeningBalance' => $this->attention->accountsWithoutOpeningBalance(),
            ],
        ];
    }

    /** @return array<string, array{total: string, cash: string, banks: string, wallets: string, suspense: string}> */
    private function positionByCurrency(): array
    {
        $byCurrency = [];
        foreach ($this->position->accounts() as $row) {
            $currency = $row['account']->currency;
            $byCurrency[$currency] ??= [
                'total' => '0',
                'cash' => '0',
                'banks' => '0',
                'wallets' => '0',
                'suspense' => '0',
            ];
            $key = match ($row['account']->kind) {
                AccountKind::CashRegister => 'cash',
                AccountKind::Bank => 'banks',
                AccountKind::Wallet => 'wallets',
                AccountKind::Suspense => 'suspense',
            };
            $byCurrency[$currency][$key] = Money::toString(Money::money(
                $byCurrency[$currency][$key],
            )->plus(Money::money($row['balance'])));
            $byCurrency[$currency]['total'] = Money::toString(Money::money(
                $byCurrency[$currency]['total'],
            )->plus(Money::money($row['balance'])));
        }

        foreach ($byCurrency as $currency => $values) {
            foreach ($values as $key => $value) {
                $byCurrency[$currency][$key] = Money::toString(Money::money($value));
            }
        }

        return $byCurrency;
    }

    /** @return array{total: string, cash: string, banks: string, wallets: string, suspense: string} */
    private static function emptyPosition(): array
    {
        return ['total' => '0.00', 'cash' => '0.00', 'banks' => '0.00', 'wallets' => '0.00', 'suspense' => '0.00'];
    }
}
