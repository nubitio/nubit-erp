<?php

declare(strict_types=1);

namespace App\Treasury\Repository;

use Doctrine\DBAL\Connection;

final readonly class GatewayRouteRepository
{
    public function __construct(
        private Connection $connection,
    ) {}

    /** @return array{account_id: string, ledger_code: string}|null */
    public function find(string $branch, int $method, string $currency): ?array
    {
        $row = $this->connection->fetchAssociative(<<<'SQL'
            SELECT r.account_id, r.ledger_code
            FROM treasury_gateway_route r
            JOIN treasury_account a ON a.id = r.account_id AND a.kind = 'clearing' AND a.active = 1
                AND a.currency = r.currency AND (a.branch_id IS NULL OR a.branch_id = r.branch_id)
            WHERE r.branch_id = ? AND r.payment_method_id = ? AND r.currency = ?
            SQL, [$branch, $method, $currency]);

        return (
            $row === false
                ? null
                : ['account_id' => (string) $row['account_id'], 'ledger_code' => (string) $row['ledger_code']]
        );
    }

    public function save(string $branch, int $method, string $currency, string $accountId, string $ledgerCode): void
    {
        $this->connection->executeStatement(
            <<<'SQL'
                INSERT INTO treasury_gateway_route (branch_id, payment_method_id, currency, account_id, ledger_code)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE account_id = VALUES(account_id), ledger_code = VALUES(ledger_code)
                SQL,
            [$branch, $method, $currency, $accountId, $ledgerCode],
        );
    }
}
