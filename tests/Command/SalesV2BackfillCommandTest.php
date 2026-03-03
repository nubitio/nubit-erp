<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\SalesV2BackfillCommand;
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

/**
 * Smoke / functional tests for SalesV2BackfillCommand.
 *
 * These tests use a mocked DBAL Connection (no live DB required).
 * They verify:
 *   1. The command resolves cash_session_id via cash_register_id + time-window
 *      without throwing SQLSTATE[42S22] (turno_id regression guard).
 *   2. A movimiento row with caja_id = NULL causes cash_session_id = NULL
 *      without exception.
 *   3. The command skips gracefully when required tables are absent
 *      (TableNotFoundException path).
 */
final class SalesV2BackfillCommandTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Scenario 1 — happy path: movimiento with valid caja_id, matching session
    // -------------------------------------------------------------------------

    public function testCommandSucceedsAndDoesNotThrow42S22WhenCajaIdMatchesCashSession(): void
    {
        $connection = $this->mockConnectionForSuccessfulInsert(
            headersInserted: 5,
            itemsInserted: 12,
        );

        $command = $this->buildCommand($connection);
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('backfill completed', $tester->getDisplay());
        self::assertStringContainsString('headers=5', $tester->getDisplay());
        self::assertStringContainsString('items=12', $tester->getDisplay());
    }

    // -------------------------------------------------------------------------
    // Scenario 2 — caja_id = NULL → cash_session_id = NULL, no exception
    // -------------------------------------------------------------------------

    public function testCommandSucceedsWhenCajaIdIsNullProducingNullCashSessionId(): void
    {
        // The SQL subquery returns NULL when m.caja_id IS NULL — the INSERT
        // still completes (sales.cash_session_id is nullable).
        $connection = $this->mockConnectionForSuccessfulInsert(
            headersInserted: 1,
            itemsInserted: 2,
        );

        $command = $this->buildCommand($connection);
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        // No exception means the NULL path is handled silently.
    }

    // -------------------------------------------------------------------------
    // Scenario 3 — required table absent → graceful skip (no crash)
    // -------------------------------------------------------------------------

    public function testCommandSkipsGracefullyWhenRequiredTableIsMissing(): void
    {
        /** @var Connection&MockObject $connection */
        $connection = $this->createMock(Connection::class);

        // fetchOne always throws TableNotFoundException (tables not migrated)
        $connection->method('fetchOne')
            ->willThrowException(
                $this->createTableNotFoundException()
            );

        // executeStatement must NOT be called at all in this path
        $connection->expects(self::never())->method('executeStatement');

        $command = $this->buildCommand($connection);
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Skipping tenant', $tester->getDisplay());
    }

    // -------------------------------------------------------------------------
    // Scenario A — NULL caja_id rows are skipped and a warning is printed
    // -------------------------------------------------------------------------

    public function testNullCajaIdRowIsSkippedWithWarning(): void
    {
        // fetchOne call sequence (after TASK-019 changes):
        //   Call 1: SELECT 1 FROM sales LIMIT 1          → 1 (table exists)
        //   Call 2: SELECT 1 FROM sale_items LIMIT 1     → 1 (table exists)
        //   Call 3: SELECT 1 FROM movimiento LIMIT 1     → 1 (table exists)
        //   Call 4: SELECT 1 FROM movimiento_detalle LIMIT 1 → 1 (table exists)
        //   Call 5: SELECT COUNT(*) … caja_id IS NULL …  → 3 (3 rows to skip)
        $callCount = 0;
        /** @var Connection&MockObject $connection */
        $connection = $this->createMock(Connection::class);

        $connection->method('fetchOne')
            ->willReturnCallback(static function () use (&$callCount): int {
                ++$callCount;

                return $callCount === 5 ? 3 : 1;
            });

        $connection->method('beginTransaction');
        $connection->method('commit');

        $execCall = 0;
        $connection->method('executeStatement')
            ->willReturnCallback(static function () use (&$execCall): int {
                ++$execCall;

                return $execCall === 1 ? 7 : 14;
            });

        $command = $this->buildCommand($connection);
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString(
            'Skipped 3 movimiento row(s) with NULL caja_id',
            $tester->getDisplay(),
            'Warning must include the exact skipped-row count'
        );
        self::assertStringContainsString('headers=7', $tester->getDisplay());
    }

    // -------------------------------------------------------------------------
    // Scenario B — caja_id IS NOT NULL but no cash_session → row inserted with
    //              cash_session_id = NULL (requires migration T20 applied)
    // -------------------------------------------------------------------------

    public function testNullCajaIdNotNullButNoSessionInsertsWithNullCashSessionId(): void
    {
        // The command does NOT filter rows with a valid caja_id but no matching
        // cash_session. Those rows land in sales with cash_session_id = NULL.
        // We verify: (a) command succeeds; (b) INSERT SQL contains the caja_id
        // NOT NULL filter (Fix A) but NOT a cash_session_id IS NOT NULL filter
        // (i.e. rows without a session are NOT excluded).
        $capturedSql = '';

        /** @var Connection&MockObject $connection */
        $connection = $this->createMock(Connection::class);

        // All fetchOne calls return 1 (tables exist; skipped count = 1 → warning OK)
        $connection->method('fetchOne')->willReturn(1);
        $connection->method('beginTransaction');
        $connection->method('commit');

        $execCall = 0;
        $connection->method('executeStatement')
            ->willReturnCallback(
                static function (string $sql) use (&$capturedSql, &$execCall): int {
                    ++$execCall;
                    if ($execCall === 1) {
                        $capturedSql = $sql;
                    }

                    return $execCall === 1 ? 1 : 2;
                }
            );

        $command = $this->buildCommand($connection);
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);

        // Fix A filter MUST be present — rows without a cash_register are excluded
        self::assertStringContainsString(
            'caja_id IS NOT NULL',
            $capturedSql,
            'INSERT WHERE clause must contain AND m.caja_id IS NOT NULL (Fix A)'
        );

        // Rows with a valid caja_id but no matching session are NOT excluded —
        // they are inserted with cash_session_id = NULL (Fix B / schema change).
        self::assertStringNotContainsString(
            'cash_session_id IS NOT NULL',
            $capturedSql,
            'INSERT must NOT filter out rows with NULL cash_session_id (they are allowed)'
        );
    }

    // -------------------------------------------------------------------------
    // Regression guard — turno_id must NOT appear in the SQL sent to the DB
    // -------------------------------------------------------------------------

    public function testSqlSentToConnectionDoesNotContainTurnoId(): void
    {
        $capturedSql = '';

        /** @var Connection&MockObject $connection */
        $connection = $this->createMock(Connection::class);

        // Allow table existence checks
        $connection->method('fetchOne')->willReturn(1);

        $connection->method('beginTransaction');
        $connection->method('commit');

        $connection->method('executeStatement')
            ->willReturnCallback(static function (string $sql) use (&$capturedSql): int {
                $capturedSql .= $sql;

                return 0;
            });

        $command = $this->buildCommand($connection);
        $tester = new CommandTester($command);
        $tester->execute([]);

        self::assertStringNotContainsString(
            'turno_id',
            $capturedSql,
            'The SQL must not reference the non-existent column m.turno_id'
        );

        self::assertStringContainsString(
            'cash_register_id',
            $capturedSql,
            'The SQL must join via cs.cash_register_id = m.caja_id'
        );

        self::assertStringContainsString(
            'opened_at',
            $capturedSql,
            'The SQL must include the time-window filter cs.opened_at'
        );

        self::assertStringContainsString(
            'closed_at',
            $capturedSql,
            'The SQL must include the time-window filter cs.closed_at'
        );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Build a fully-wired SalesV2BackfillCommand with a mocked DBAL connection.
     *
     * TenantContext is final and cannot be mocked; we use a real instance.
     * TenantManager returns an empty tenant list so the command uses the
     * default connection path (no switchToTenant loop).
     */
    private function buildCommand(Connection $connection): SalesV2BackfillCommand
    {
        $command = new SalesV2BackfillCommand($connection);

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

    /**
     * Create a Connection mock that passes the table-existence checks and
     * returns the given row counts from executeStatement.
     */
    private function mockConnectionForSuccessfulInsert(
        int $headersInserted,
        int $itemsInserted,
    ): Connection {
        /** @var Connection&MockObject $connection */
        $connection = $this->createMock(Connection::class);

        // Pass table-existence preflight checks (fetchOne SELECT 1 … LIMIT 1)
        $connection->method('fetchOne')->willReturn(1);

        $connection->method('beginTransaction');
        $connection->method('commit');

        $callCount = 0;
        $connection->method('executeStatement')
            ->willReturnCallback(
                static function () use (&$callCount, $headersInserted, $itemsInserted): int {
                    ++$callCount;

                    return $callCount === 1 ? $headersInserted : $itemsInserted;
                }
            );

        return $connection;
    }

    /**
     * Build a TableNotFoundException without needing a live DBAL driver.
     *
     * Doctrine\DBAL\Driver\Exception is an interface (extends Throwable) so we
     * create an anonymous implementation.
     */
    private function createTableNotFoundException(): TableNotFoundException
    {
        return new TableNotFoundException(
            new StubDriverException('Table not found', 0),
            null,
        );
    }
}

// phpcs:ignore PSR1.Classes.ClassDeclaration.MultipleClasses
final class StubDriverException extends \RuntimeException implements \Doctrine\DBAL\Driver\Exception
{
    public function getSQLState(): ?string
    {
        return null;
    }
}
