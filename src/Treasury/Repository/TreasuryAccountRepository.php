<?php

declare(strict_types=1);

namespace App\Treasury\Repository;

use App\Treasury\Domain\AccountKind;
use App\Treasury\Domain\TreasuryAccount;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;

final readonly class TreasuryAccountRepository
{
    private const string COLUMNS = 'id, kind, name, currency, branch_id, cash_register_id, bank_account_id, active';

    public function __construct(
        private Connection $connection,
    ) {}

    public function add(TreasuryAccount $account): void
    {
        $this->connection->insert('treasury_account', [
            'id' => $account->id,
            'kind' => $account->kind->value,
            'name' => $account->name,
            'currency' => $account->currency,
            'branch_id' => $account->branchId,
            'cash_register_id' => $account->cashRegisterId,
            'bank_account_id' => $account->bankAccountId,
            'active' => $account->active ? 1 : 0,
            'created_at' => new DateTimeImmutable()->format('Y-m-d H:i:s'),
        ]);
    }

    public function setActive(string $id, bool $active): void
    {
        $this->connection->update('treasury_account', ['active' => $active ? 1 : 0], ['id' => $id]);
    }

    public function find(string $id): ?TreasuryAccount
    {
        return $this->one('WHERE id = :id', ['id' => $id]);
    }

    public function findByCashRegister(int $cashRegisterId): ?TreasuryAccount
    {
        return $this->one('WHERE cash_register_id = :id', ['id' => $cashRegisterId]);
    }

    public function findByBankAccount(string $bankAccountId): ?TreasuryAccount
    {
        return $this->one('WHERE bank_account_id = :id', ['id' => $bankAccountId]);
    }

    public function findSuspense(string $branchId, string $currency): ?TreasuryAccount
    {
        return $this->one('WHERE kind = :kind AND branch_id = :branch AND currency = :currency AND active = 1', [
            'kind' => AccountKind::Suspense->value,
            'branch' => $branchId,
            'currency' => $currency,
        ]);
    }

    /** @return list<TreasuryAccount> */
    public function all(bool $onlyActive = true): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT '
            . self::COLUMNS
            . ' FROM treasury_account'
            . ($onlyActive ? ' WHERE active = 1' : '')
            . ' ORDER BY kind, name',
        );

        return array_map(self::hydrate(...), $rows);
    }

    public function mappedAccountId(string $branchId, int $paymentMethodId, string $currency): ?string
    {
        $id = $this->connection->fetchOne(
            'SELECT account_id FROM treasury_payment_method_account WHERE branch_id = :branch AND payment_method_id = :method AND currency = :currency',
            ['branch' => $branchId, 'method' => $paymentMethodId, 'currency' => $currency],
        );

        return $id === false ? null : (string) $id;
    }

    public function selectableAccount(string $id): ?TreasuryAccount
    {
        return $this->one("WHERE id = :id AND active = 1 AND kind <> 'clearing'", ['id' => $id]);
    }

    public function gatewayAccount(string $id, string $branchId): ?TreasuryAccount
    {
        return $this->one("WHERE id = :id AND kind = 'clearing' AND active = 1 AND (branch_id IS NULL OR branch_id = :branch)", [
            'id' => $id,
            'branch' => $branchId,
        ]);
    }

    public function mappedBankAccount(string $branchId, ?int $paymentMethodId, string $currency): ?TreasuryAccount
    {
        $id = $this->connection->fetchOne(
            <<<'SQL'
                SELECT a.id FROM treasury_payment_method_account m
                JOIN treasury_account a ON a.id = m.account_id
                WHERE m.branch_id = :branch AND m.payment_method_id = :method AND m.currency = :currency
                  AND a.kind = 'bank' AND a.active = 1 AND a.currency = m.currency
                  AND (a.branch_id IS NULL OR a.branch_id = m.branch_id)
                SQL,
            ['branch' => $branchId, 'method' => $paymentMethodId, 'currency' => $currency],
        );

        return is_string($id) ? $this->find($id) : null;
    }

    /** @return list<array{branchId: string, paymentMethodId: int, currency: string, accountId: string}> */
    public function mappings(): array
    {
        /** @var list<array{branch_id: string, payment_method_id: int|string, currency: string, account_id: string}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT branch_id, payment_method_id, currency, account_id FROM treasury_payment_method_account ORDER BY branch_id, payment_method_id, currency',
        );

        return array_map(static fn(array $row): array => [
            'branchId' => $row['branch_id'],
            'paymentMethodId' => (int) $row['payment_method_id'],
            'currency' => $row['currency'],
            'accountId' => $row['account_id'],
        ], $rows);
    }

    public function map(string $branchId, int $paymentMethodId, string $currency, string $accountId): void
    {
        $key = ['branch_id' => $branchId, 'payment_method_id' => $paymentMethodId, 'currency' => $currency];
        if ($this->mappedAccountId($branchId, $paymentMethodId, $currency) === null) {
            $this->connection->insert('treasury_payment_method_account', $key + ['account_id' => $accountId]);

            return;
        }
        $this->connection->update('treasury_payment_method_account', ['account_id' => $accountId], $key);
    }

    public function unmap(string $branchId, int $paymentMethodId, string $currency): void
    {
        $this->connection->delete('treasury_payment_method_account', [
            'branch_id' => $branchId,
            'payment_method_id' => $paymentMethodId,
            'currency' => $currency,
        ]);
    }

    /** @param array<string, scalar> $params */
    private function one(string $where, array $params): ?TreasuryAccount
    {
        $row = $this->connection->fetchAssociative(
            'SELECT ' . self::COLUMNS . ' FROM treasury_account ' . $where,
            $params,
        );

        return $row === false ? null : self::hydrate($row);
    }

    /** @param array<string, mixed> $row */
    private static function hydrate(array $row): TreasuryAccount
    {
        return new TreasuryAccount(
            id: (string) $row['id'],
            kind: AccountKind::from((string) $row['kind']),
            name: (string) $row['name'],
            currency: (string) $row['currency'],
            branchId: $row['branch_id'] === null ? null : (string) $row['branch_id'],
            cashRegisterId: $row['cash_register_id'] === null ? null : (int) $row['cash_register_id'],
            bankAccountId: $row['bank_account_id'] === null ? null : (string) $row['bank_account_id'],
            active: (bool) $row['active'],
        );
    }
}
