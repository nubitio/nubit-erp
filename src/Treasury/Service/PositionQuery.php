<?php

declare(strict_types=1);

namespace App\Treasury\Service;

use App\Efact\Domain\Money;
use App\Treasury\Domain\AccountKind;
use App\Treasury\Domain\TreasuryAccount;
use App\Treasury\Repository\TreasuryAccountRepository;
use App\Treasury\Repository\TreasuryMovementRepository;

/**
 * Money position: one balance per account, and totals per currency (never mixed, decision 3).
 * Ledger accounts sum their movements; a cash register reads its drawer.
 */
final readonly class PositionQuery
{
    public function __construct(
        private TreasuryAccountRepository $accounts,
        private TreasuryMovementRepository $movements,
        private DrawerReader $drawers,
    ) {}

    /** @return list<array{account: TreasuryAccount, balance: string}> */
    public function accounts(): array
    {
        return array_map(fn(TreasuryAccount $account): array => [
            'account' => $account,
            'balance' => $this->balanceOf($account),
        ], $this->accounts->all());
    }

    /** @return array<string, string> currency => total across accounts, suspense included */
    public function totalsByCurrency(): array
    {
        $totals = [];
        foreach ($this->accounts() as $row) {
            $currency = $row['account']->currency;
            $totals[$currency] = Money::toString(Money::money($totals[$currency] ?? '0')->plus(Money::money(
                $row['balance'],
            )));
        }
        ksort($totals);

        return $totals;
    }

    private function balanceOf(TreasuryAccount $account): string
    {
        if ($account->kind === AccountKind::CashRegister) {
            return $account->cashRegisterId === null ? '0.00' : $this->drawers->balanceOf($account->cashRegisterId);
        }

        return $this->movements->balance($account->id);
    }
}
