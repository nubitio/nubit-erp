<?php

declare(strict_types=1);

namespace App\Treasury\Report;

use App\Efact\Domain\Money;
use App\Treasury\Domain\AccountKind;
use App\Treasury\Service\PositionQuery;
use Doctrine\DBAL\Connection;

/** What needs the owner's attention beyond the numbers: loose ends in how the money is recorded. */
final readonly class FinanceAttentionQuery
{
    public function __construct(
        private Connection $connection,
        private PositionQuery $position,
    ) {}

    /** Movements on bank accounts still waiting for a statement line (wallets are not reconciled). */
    public function unreconciledBankMovements(): int
    {
        return (int) $this->connection->fetchOne('SELECT COUNT(*) FROM treasury_movement m
             JOIN treasury_account a ON a.id = m.account_id
             LEFT JOIN bank_reconciliation_match r ON r.treasury_movement_id = m.id
             WHERE a.bank_account_id IS NOT NULL AND r.id IS NULL');
    }

    /**
     * Banks and wallets that never got an opening balance: until they do, their balance only counts what moved
     * since Treasury began, and can even be negative.
     *
     * @return list<string>
     */
    public function accountsWithoutOpeningBalance(): array
    {
        /** @var list<string> $names */
        $names = $this->connection->fetchFirstColumn("SELECT a.name FROM treasury_account a
             WHERE a.active = 1 AND a.kind IN ('bank', 'wallet')
               AND NOT EXISTS (
                   SELECT 1 FROM treasury_movement m
                   WHERE m.account_id = a.id AND m.source_type = 'opening_balance'
               )
             ORDER BY a.name");

        return $names;
    }

    /** @return list<array{currency: string, balance: string}> money parked in a suspense account, per currency */
    public function suspense(): array
    {
        $out = [];
        foreach ($this->position->accounts() as $row) {
            if ($row['account']->kind === AccountKind::Suspense && !Money::money($row['balance'])->isZero()) {
                $out[] = ['currency' => $row['account']->currency, 'balance' => $row['balance']];
            }
        }

        return $out;
    }
}
