<?php

declare(strict_types=1);

namespace App\Tests\Sales\Report\CustomerStatement;

use App\Sales\Report\CustomerStatement\StatementRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;

final class StatementRepositoryTest extends TestCase
{
    public function testChargesQueryExcludesVoidedSales(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects(self::once())
            ->method('fetchAllAssociative')
            ->with(
                self::callback(
                    static fn(string $sql): bool => (
                        str_contains($sql, 's.branch_id = :branch')
                        && str_contains($sql, "AND (s.status IS NULL OR s.status <> 'VOIDED')")
                    ),
                ),
                ['customer' => 55, 'date_to' => '2026-05-31', 'branch' => 'branch-1'],
            )
            ->willReturn([]);

        self::assertSame([], new StatementRepository($connection)->charges(55, '2026-05-31', 'branch-1'));
    }

    public function testPaymentsQueryUsesPersistedCollectionIdentityForSingleStatementAbono(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects(self::once())
            ->method('fetchAllAssociative')
            ->with(
                self::callback(
                    static fn(string $sql): bool => (
                        str_contains($sql, "e.source = 'on_account_collect'")
                        && str_contains($sql, 'SUM(a.applied_amount)')
                        && str_contains($sql, 'customer_payment_applications')
                        && str_contains($sql, 'applied_sale.branch_id = :collect_branch')
                        && str_contains($sql, "e.entry_type = 'credit_note' AND s.branch_id = :branch")
                        && str_contains($sql, "e.entry_type = 'advance' AND e.branch_id = :advance_branch")
                        && str_contains($sql, 'prior.source_id = e.source_id')
                        && !str_contains($sql, "'charge_reversal'")
                    ),
                ),
                [
                    'customer' => 55,
                    'date_to' => '2026-05-31',
                    'branch' => 'branch-1',
                    'collect_branch' => 'branch-1',
                    'advance_branch' => 'branch-1',
                ],
            )
            ->willReturn([]);

        self::assertSame([], new StatementRepository($connection)->payments(55, '2026-05-31', 'branch-1'));
    }

    public function testAdvanceApplicationMovesBalanceBetweenStatementsOnlyOnce(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $connection->executeStatement(
            'CREATE TABLE customer_account_entries (id INT, customer_id INT, branch_id TEXT, entry_type TEXT)',
        );
        $connection->executeStatement('CREATE TABLE sales (id TEXT, customer_id INT, branch_id TEXT)');
        $connection->executeStatement(
            'CREATE TABLE customer_payment_applications (id INT, customer_account_entry_id INT, sale_id TEXT, applied_amount TEXT, applied_at TEXT)',
        );
        $connection->executeStatement("INSERT INTO customer_account_entries VALUES (1, 55, 'A', 'advance')");
        $connection->executeStatement("INSERT INTO sales VALUES ('other', 55, 'B'), ('same', 55, 'A')");
        $connection->executeStatement("INSERT INTO customer_payment_applications VALUES
            (1, 1, 'other', '30.00', '2026-05-10 12:00:00'),
            (2, 1, 'same', '15.00', '2026-05-10 12:00:00')");

        $repository = new StatementRepository($connection);
        self::assertSame(
            ['charges' => [], 'payments' => []],
            $repository->crossBranchAdvanceApplications(55, '2026-05-09', 'A'),
        );
        $origin = $repository->crossBranchAdvanceApplications(55, '2026-05-10', 'A');
        $destination = $repository->crossBranchAdvanceApplications(55, '2026-05-10', 'B');
        self::assertSame(['30.00'], array_column($origin['charges'], 'amount'));
        self::assertSame([], $origin['payments']);
        self::assertSame([], $destination['charges']);
        self::assertSame(['30.00'], array_column($destination['payments'], 'amount'));
        self::assertSame(
            ['charges' => [], 'payments' => []],
            $repository->crossBranchAdvanceApplications(55, '2026-05-10', 'C'),
        );
    }
}
