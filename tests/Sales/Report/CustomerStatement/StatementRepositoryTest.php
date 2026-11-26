<?php

declare(strict_types=1);

namespace App\Tests\Sales\Report\CustomerStatement;

use App\Sales\Report\CustomerStatement\StatementRepository;
use Doctrine\DBAL\Connection;
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
                    static fn(string $sql): bool => str_contains($sql, 's.branch_id = :branch')
                        && str_contains($sql, "AND (s.status IS NULL OR s.status <> 'VOIDED')"),
                ),
                ['customer' => 55, 'date_to' => '2026-05-31', 'branch' => 'branch-1'],
            )
            ->willReturn([]);

        self::assertSame([], new StatementRepository($connection)->charges(55, '2026-05-31', 'branch-1'));
    }

    public function testPaymentsQueryLeavesOutVoidedSaleChargeReversal(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects(self::once())
            ->method('fetchAllAssociative')
            ->with(
                self::callback(
                    static fn(string $sql): bool => (
                        str_contains($sql, "e.entry_type IN ('payment', 'credit_note') AND s.branch_id = :branch")
                        && str_contains($sql, "e.entry_type = 'advance' AND e.branch_id = :advance_branch")
                        && !str_contains($sql, "'charge_reversal'")
                    ),
                ),
                ['customer' => 55, 'date_to' => '2026-05-31', 'branch' => 'branch-1', 'advance_branch' => 'branch-1'],
            )
            ->willReturn([]);

        self::assertSame([], new StatementRepository($connection)->payments(55, '2026-05-31', 'branch-1'));
    }
}
