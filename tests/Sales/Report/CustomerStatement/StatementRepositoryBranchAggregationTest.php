<?php

declare(strict_types=1);

namespace App\Tests\Sales\Report\CustomerStatement;

use App\Sales\Report\CustomerStatement\StatementRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;

final class StatementRepositoryBranchAggregationTest extends TestCase
{
    public function testNewAndHistoricalCollectionsUseApplicationAmountsPerBranch(): void
    {
        $url = getenv('STATEMENT_TEST_DATABASE_URL');
        if (!is_string($url) || $url === '') {
            self::markTestSkipped('Requires an isolated MariaDB connection for temporary-table SQL coverage.');
        }

        $connection = DriverManager::getConnection($this->connectionParameters($url));
        try {
            $this->createTemporarySchema($connection);
            $this->insertNewCollection($connection);
            $this->insertHistoricalCollection($connection);

            $repository = new StatementRepository($connection);
            $branchA = $repository->payments(55, '2026-06-30', 'branch-a');
            $branchB = $repository->payments(55, '2026-06-30', 'branch-b');

            self::assertSame(['60.00', '60.00'], array_column($branchA, 'amount'));
            self::assertSame(['40.00', '40.00'], array_column($branchB, 'amount'));
            self::assertSame(
                '100.00',
                number_format((float) $branchA[0]['amount'] + (float) $branchB[0]['amount'], 2, '.', ''),
                'The new global collection remains 100.00 across branch views.',
            );
            self::assertSame(
                '100.00',
                number_format((float) $branchA[1]['amount'] + (float) $branchB[1]['amount'], 2, '.', ''),
                'The historical collection remains 100.00 across branch views.',
            );
        } finally {
            $connection->close();
        }
    }

    /**
     * @return array{driver: string, host: string, port: int, user: string, password: string, dbname: string, charset: string, serverVersion: string}
     */
    private function connectionParameters(string $url): array
    {
        $parts = parse_url($url);
        self::assertIsArray($parts);
        parse_str($parts['query'] ?? '', $query);

        return [
            'driver' => 'pdo_mysql',
            'host' => (string) ($parts['host'] ?? 'database'),
            'port' => (int) ($parts['port'] ?? 3306),
            'user' => urldecode((string) ($parts['user'] ?? '')),
            'password' => urldecode((string) ($parts['pass'] ?? '')),
            'dbname' => ltrim((string) ($parts['path'] ?? ''), '/'),
            'charset' => (string) ($query['charset'] ?? 'utf8mb4'),
            'serverVersion' => (string) ($query['serverVersion'] ?? '10.11.0-MariaDB'),
        ];
    }

    private function createTemporarySchema(Connection $connection): void
    {
        $connection->executeStatement('CREATE TEMPORARY TABLE customer_account_entries (
                id BIGINT PRIMARY KEY,
                customer_id INT NOT NULL,
                entry_type VARCHAR(32) NOT NULL,
                occurred_on DATE NOT NULL,
                credit DECIMAL(12, 2) NOT NULL,
                sale_id VARCHAR(255) NULL,
                source VARCHAR(40) NULL,
                source_id VARCHAR(64) NULL,
                branch_id VARCHAR(36) NULL
            )');
        $connection->executeStatement('CREATE TEMPORARY TABLE customer_payment_applications (
                id BIGINT PRIMARY KEY,
                customer_account_entry_id BIGINT NOT NULL,
                sale_id VARCHAR(255) NOT NULL,
                applied_amount DECIMAL(12, 2) NOT NULL
            )');
        $connection->executeStatement('CREATE TEMPORARY TABLE sales (
                id VARCHAR(255) PRIMARY KEY,
                branch_id VARCHAR(36) NOT NULL,
                series VARCHAR(16) NULL,
                number VARCHAR(32) NULL
            )');
        $connection->executeStatement("INSERT INTO sales (id, branch_id, series, number) VALUES
                ('new-a', 'branch-a', 'F001', '1'),
                ('new-b', 'branch-b', 'F001', '2'),
                ('old-a', 'branch-a', 'F001', '3'),
                ('old-b', 'branch-b', 'F001', '4')");
    }

    private function insertNewCollection(Connection $connection): void
    {
        $connection->executeStatement(
            "INSERT INTO customer_account_entries
                (id, customer_id, entry_type, occurred_on, credit, source, source_id)
             VALUES (1, 55, 'payment', '2026-06-01', 100.00, 'on_account_collect', 'new-collection')",
        );
        $connection->executeStatement("INSERT INTO customer_payment_applications
                (id, customer_account_entry_id, sale_id, applied_amount)
             VALUES (1, 1, 'new-a', 60.00), (2, 1, 'new-b', 40.00)");
    }

    private function insertHistoricalCollection(Connection $connection): void
    {
        $connection->executeStatement(
            "INSERT INTO customer_account_entries
                (id, customer_id, entry_type, occurred_on, credit, sale_id, source, source_id)
             VALUES
                (2, 55, 'payment', '2026-06-02', 60.00, 'old-a', 'on_account_collect', 'old-collection'),
                (3, 55, 'payment', '2026-06-02', 40.00, 'old-b', 'on_account_collect', 'old-collection')",
        );
        $connection->executeStatement("INSERT INTO customer_payment_applications
                (id, customer_account_entry_id, sale_id, applied_amount)
             VALUES (3, 2, 'old-a', 60.00), (4, 3, 'old-b', 40.00)");
    }
}
