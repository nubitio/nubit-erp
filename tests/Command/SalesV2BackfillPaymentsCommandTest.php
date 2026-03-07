<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\SalesV2BackfillPaymentsCommand;
use App\Core\Context\TenantContext;
use App\Core\Service\TenantConnectionSwitcher;
use App\Core\Service\TenantManager;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\TableNotFoundException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class SalesV2BackfillPaymentsCommandTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function buildCommand(Connection $connection): SalesV2BackfillPaymentsCommand
    {
        $command = new SalesV2BackfillPaymentsCommand($connection);
        /** @var TenantManager&MockObject $tenantManager */
        $tenantManager = $this->createMock(TenantManager::class);
        $tenantManager->method('getTenants')->willReturn([]);
        $command->initDependencies(
            $tenantManager,
            $this->createMock(TenantConnectionSwitcher::class),
            new TenantContext(),
            new NullLogger(),
        );

        return $command;
    }

    /** @param list<int> $executeStatementReturnValues */
    private function mockConnectionWithInserts(array $executeStatementReturnValues = [1]): Connection
    {
        /** @var Connection&MockObject $connection */
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn(1);
        $connection->method('beginTransaction');
        $connection->method('commit');

        $callCount = 0;
        $connection->method('executeStatement')
            ->willReturnCallback(
                static function () use (&$callCount, $executeStatementReturnValues): int {
                    $value = $executeStatementReturnValues[$callCount] ?? 0;
                    ++$callCount;

                    return $value;
                }
            );

        return $connection;
    }

    private function createTableNotFoundException(): TableNotFoundException
    {
        return new TableNotFoundException(
            new SalesV2BackfillPaymentsStubDriverException('Table not found', 0),
            null,
        );
    }

    // -------------------------------------------------------------------------
    // Table-missing guard
    // -------------------------------------------------------------------------

    public function testSkipsGracefullyWhenRequiredTablesAreMissing(): void
    {
        /** @var Connection&MockObject $connection */
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willThrowException($this->createTableNotFoundException());
        $connection->expects(self::never())->method('executeStatement');

        $tester = new CommandTester($this->buildCommand($connection));
        $exitCode = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Skipping tenant', $tester->getDisplay());
    }

    // -------------------------------------------------------------------------
    // Cash payment method not found
    // -------------------------------------------------------------------------

    public function testFailsWhenCashPaymentMethodIsNotFound(): void
    {
        /** @var Connection&MockObject $connection */
        $connection = $this->createMock(Connection::class);
        // First 3 calls succeed (table probes), remaining return false (no cash PM)
        $callCount = 0;
        $connection->method('fetchOne')
            ->willReturnCallback(static function () use (&$callCount): mixed {
                ++$callCount;
                return $callCount <= 3 ? 1 : false;
            });

        $tester = new CommandTester($this->buildCommand($connection));
        $exitCode = $tester->execute([]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('Cash payment method not found', $tester->getDisplay());
    }

    // -------------------------------------------------------------------------
    // Backfill (normal mode)
    // -------------------------------------------------------------------------

    public function testBackfillInsertsRowsAndReportsCount(): void
    {
        $tester = new CommandTester($this->buildCommand($this->mockConnectionWithInserts([5])));
        $exitCode = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Created 5 sale_payments', $tester->getDisplay());
    }

    public function testBackfillReportsNothingToDoWhenZeroInserted(): void
    {
        $tester = new CommandTester($this->buildCommand($this->mockConnectionWithInserts([0])));
        $exitCode = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('No sales without payment were found', $tester->getDisplay());
    }

    public function testBackfillInsertSqlUsesLeftJoinMovimiento(): void
    {
        $capturedSql = '';
        /** @var Connection&MockObject $connection */
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn(1);
        $connection->method('beginTransaction');
        $connection->method('commit');
        $connection->method('executeStatement')
            ->willReturnCallback(static function (string $sql) use (&$capturedSql): int {
                $capturedSql = $sql;

                return 1;
            });

        (new CommandTester($this->buildCommand($connection)))->execute([]);

        self::assertStringContainsString(
            'LEFT JOIN movimiento m ON m.movimiento_id = s.legacy_movimiento_id',
            $capturedSql,
            'INSERT must LEFT JOIN movimiento to resolve real payment method'
        );
    }

    public function testBackfillInsertSqlUsesCoalesceForPaymentMethodId(): void
    {
        $capturedSql = '';
        /** @var Connection&MockObject $connection */
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn(1);
        $connection->method('beginTransaction');
        $connection->method('commit');
        $connection->method('executeStatement')
            ->willReturnCallback(static function (string $sql) use (&$capturedSql): int {
                $capturedSql = $sql;

                return 1;
            });

        (new CommandTester($this->buildCommand($connection)))->execute([]);

        self::assertStringContainsString(
            'COALESCE(m.tipo_pago, :cash_payment_method_id)',
            $capturedSql,
            'INSERT must use COALESCE(m.tipo_pago, :cash_payment_method_id) for payment_method_id'
        );
    }

    public function testBackfillInsertSqlDoesNotHardcodeCashPaymentMethodId(): void
    {
        $capturedSql = '';
        /** @var Connection&MockObject $connection */
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn(1);
        $connection->method('beginTransaction');
        $connection->method('commit');
        $connection->method('executeStatement')
            ->willReturnCallback(static function (string $sql) use (&$capturedSql): int {
                $capturedSql = $sql;

                return 0;
            });

        (new CommandTester($this->buildCommand($connection)))->execute([]);

        // The old hardcoded literal `:cash_payment_method_id AS payment_method_id` (without COALESCE)
        // must no longer appear in the INSERT SELECT.
        self::assertStringNotContainsString(
            ':cash_payment_method_id AS payment_method_id',
            $capturedSql,
            'INSERT must not assign :cash_payment_method_id directly; COALESCE must be used'
        );
    }

    // -------------------------------------------------------------------------
    // Dry-run (backfill)
    // -------------------------------------------------------------------------

    public function testDryRunReportsCountWithoutInserting(): void
    {
        /** @var Connection&MockObject $connection */
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn(7);
        $connection->expects(self::never())->method('executeStatement');

        $tester = new CommandTester($this->buildCommand($connection));
        $exitCode = $tester->execute(['--dry-run' => true]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Dry run', $tester->getDisplay());
    }

    // -------------------------------------------------------------------------
    // --fix-legacy option
    // -------------------------------------------------------------------------

    public function testFixLegacyUpdatesRowsAndReportsCount(): void
    {
        /** @var Connection&MockObject $connection */
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn(1);
        $connection->method('beginTransaction');
        $connection->method('commit');
        $connection->method('executeStatement')->willReturn(3);

        $tester = new CommandTester($this->buildCommand($connection));
        $exitCode = $tester->execute(['--fix-legacy' => true]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Updated 3 sale_payments', $tester->getDisplay());
    }

    public function testFixLegacyReportsNothingToDoWhenZeroUpdated(): void
    {
        /** @var Connection&MockObject $connection */
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn(1);
        $connection->method('beginTransaction');
        $connection->method('commit');
        $connection->method('executeStatement')->willReturn(0);

        $tester = new CommandTester($this->buildCommand($connection));
        $exitCode = $tester->execute(['--fix-legacy' => true]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('No legacy sale_payments rows needed correction', $tester->getDisplay());
    }

    public function testFixLegacyUpdateSqlUsesInnerJoinMovimiento(): void
    {
        $capturedSql = '';
        /** @var Connection&MockObject $connection */
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn(1);
        $connection->method('beginTransaction');
        $connection->method('commit');
        $connection->method('executeStatement')
            ->willReturnCallback(static function (string $sql) use (&$capturedSql): int {
                $capturedSql = $sql;

                return 1;
            });

        (new CommandTester($this->buildCommand($connection)))->execute(['--fix-legacy' => true]);

        self::assertStringContainsString(
            'INNER JOIN movimiento m ON m.movimiento_id = s.legacy_movimiento_id',
            $capturedSql,
            '--fix-legacy UPDATE must INNER JOIN movimiento'
        );
        self::assertStringContainsString(
            'sp.payment_method_id = m.tipo_pago',
            $capturedSql,
            '--fix-legacy UPDATE must set payment_method_id from movimiento.tipo_pago'
        );
        self::assertStringContainsString(
            'sp.last_modified_at = NOW()',
            $capturedSql,
            '--fix-legacy UPDATE must refresh last_modified_at'
        );
    }

    public function testFixLegacyDryRunReportsCountWithoutUpdating(): void
    {
        /** @var Connection&MockObject $connection */
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn(4);
        $connection->expects(self::never())->method('executeStatement');

        $tester = new CommandTester($this->buildCommand($connection));
        $exitCode = $tester->execute(['--fix-legacy' => true, '--dry-run' => true]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Dry run', $tester->getDisplay());
        self::assertStringContainsString('fix-legacy', $tester->getDisplay());
    }

    public function testFixLegacyRollsBackOnException(): void
    {
        /** @var Connection&MockObject $connection */
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn(1);
        $connection->method('beginTransaction');
        $connection->expects(self::once())->method('rollBack');
        $connection->method('executeStatement')
            ->willThrowException(new \RuntimeException('DB error'));

        $tester = new CommandTester($this->buildCommand($connection));
        $exitCode = $tester->execute(['--fix-legacy' => true]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('Failed to fix legacy sale payments', $tester->getDisplay());
    }

    public function testBackfillRollsBackOnException(): void
    {
        /** @var Connection&MockObject $connection */
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn(1);
        $connection->method('beginTransaction');
        $connection->expects(self::once())->method('rollBack');
        $connection->method('executeStatement')
            ->willThrowException(new \RuntimeException('DB error'));

        $tester = new CommandTester($this->buildCommand($connection));
        $exitCode = $tester->execute([]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('Failed to create sale payments', $tester->getDisplay());
    }
}

// phpcs:ignore PSR1.Classes.ClassDeclaration.MultipleClasses
final class SalesV2BackfillPaymentsStubDriverException extends \RuntimeException implements \Doctrine\DBAL\Driver\Exception
{
    public function getSQLState(): ?string
    {
        return null;
    }
}
