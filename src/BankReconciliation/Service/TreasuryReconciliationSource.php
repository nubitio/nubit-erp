<?php

declare(strict_types=1);

namespace App\BankReconciliation\Service;

use App\Settings\Capability\CapabilityKey;
use App\Settings\Capability\TenantCapabilityResolver;
use Brick\Math\BigDecimal;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;

/**
 * Treasury movements as the single source a bank statement line is reconciled against (ADR-021 wave 3).
 *
 * It only reads: the movement ledger is Treasury's, and the match rows are this context's. A bank account is
 * reconciled against the movements of its own Treasury account, which also fixes a gap of the old three-source
 * search: it compared amount and date with any payment, whatever bank account the statement belonged to.
 *
 * When Treasury is off for the tenant, or the bank account has no Treasury account yet, {@see activeFor()} is
 * false and callers keep using the old sources.
 */
final readonly class TreasuryReconciliationSource
{
    public function __construct(
        private Connection $connection,
        private TenantCapabilityResolver $capabilities,
    ) {}

    public function enabled(): bool
    {
        return $this->capabilities->isEnabled(CapabilityKey::TreasuryAccounts);
    }

    /** Treasury is on and at least one bank account already has its Treasury account. */
    public function anyBankAccount(): bool
    {
        return (
            $this->enabled()
            && $this->connection->fetchOne(
                'SELECT 1 FROM treasury_account WHERE bank_account_id IS NOT NULL AND active = 1 LIMIT 1',
            ) !== false
        );
    }

    public function activeFor(string $bankAccountId): bool
    {
        return $this->enabled() && $this->treasuryAccountOf($bankAccountId) !== null;
    }

    /**
     * Movements of the bank account's Treasury account that move exactly `$signedAmount` (negative = money left)
     * within the window and are not reconciled yet.
     *
     * @return list<array{id: string, date: DateTimeImmutable, label: string}>
     */
    public function candidates(
        string $bankAccountId,
        BigDecimal $signedAmount,
        DateTimeImmutable $from,
        DateTimeImmutable $to,
    ): array {
        $rows = $this->connection->fetchAllAssociative(
            <<<'SQL'
                SELECT m.id, m.occurred_on, m.description, m.reference
                FROM treasury_movement m
                JOIN treasury_account a ON a.id = m.account_id
                LEFT JOIN bank_reconciliation_match r ON r.treasury_movement_id = m.id
                WHERE a.bank_account_id = :bank
                  AND r.id IS NULL
                  AND m.signed_amount = :amount
                  AND m.occurred_on BETWEEN :from AND :to
                ORDER BY m.occurred_on DESC
                SQL,
            [
                'bank' => $bankAccountId,
                'amount' => $signedAmount->toScale(2)->__toString(),
                'from' => $from->format('Y-m-d'),
                'to' => $to->format('Y-m-d'),
            ],
        );

        return array_map(static fn(array $row): array => [
            'id' => (string) $row['id'],
            'date' => new DateTimeImmutable((string) $row['occurred_on']),
            'label' => self::label($row),
        ], $rows);
    }

    /**
     * Movements still waiting for a statement line, newest first. `$bankAccountId` null means every bank account.
     *
     * @return list<array{id: string, amount: string, date: string, label: string, account: string}>
     */
    public function unreconciled(
        ?string $bankAccountId,
        ?DateTimeImmutable $from,
        ?DateTimeImmutable $to,
        int $limit,
    ): array {
        $where = ['a.bank_account_id IS NOT NULL', 'r.id IS NULL'];
        $params = [];
        if ($bankAccountId !== null) {
            $where[] = 'a.bank_account_id = :bank';
            $params['bank'] = $bankAccountId;
        }
        if ($from !== null) {
            $where[] = 'm.occurred_on >= :from';
            $params['from'] = $from->format('Y-m-d');
        }
        if ($to !== null) {
            $where[] = 'm.occurred_on <= :to';
            $params['to'] = $to->format('Y-m-d');
        }

        $rows = $this->connection->fetchAllAssociative(
            sprintf('SELECT m.id, m.signed_amount, m.occurred_on, m.description, m.reference, a.name AS account_name
                 FROM treasury_movement m
                 JOIN treasury_account a ON a.id = m.account_id
                 LEFT JOIN bank_reconciliation_match r ON r.treasury_movement_id = m.id
                 WHERE %s
                 ORDER BY m.occurred_on DESC, m.created_at DESC
                 LIMIT %d', implode(' AND ', $where), max(1, $limit)),
            $params,
        );

        return array_map(static fn(array $row): array => [
            'id' => (string) $row['id'],
            'amount' => number_format((float) $row['signed_amount'], 2, '.', ''),
            'date' => (string) $row['occurred_on'],
            'label' => self::label($row),
            'account' => (string) $row['account_name'],
        ], $rows);
    }

    /** True when the movement is on the Treasury account of that bank account. */
    public function belongsToBankAccount(string $movementId, string $bankAccountId): bool
    {
        return $this->connection->fetchOne(
            'SELECT 1 FROM treasury_movement m JOIN treasury_account a ON a.id = m.account_id WHERE m.id = :id AND a.bank_account_id = :bank',
            ['id' => $movementId, 'bank' => $bankAccountId],
        ) !== false;
    }

    private function treasuryAccountOf(string $bankAccountId): ?string
    {
        $id = $this->connection->fetchOne('SELECT id FROM treasury_account WHERE bank_account_id = :bank AND active = 1', [
            'bank' => $bankAccountId,
        ]);

        return $id === false ? null : (string) $id;
    }

    /** @param array<string, mixed> $row */
    private static function label(array $row): string
    {
        $description = is_string($row['description'] ?? null) ? $row['description'] : 'Movimiento';
        $reference = is_string($row['reference'] ?? null) && $row['reference'] !== '' ? ' · ' . $row['reference'] : '';

        return $description . $reference;
    }
}
