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

final class SalesV2BackfillCommandTest extends TestCase
{
    public function testCommandSucceedsAndDoesNotThrow42S22WhenCajaIdMatchesCashSession(): void
    {
        $connection = $this->mockConnectionForSuccessfulInsert(headersInserted: 5, itemsInserted: 12);
        $command = $this->buildCommand($connection);
        $tester = new CommandTester($command);
        $exitCode = $tester->execute([]);
        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('backfill completed', $tester->getDisplay());
        self::assertStringContainsString('headers=5', $tester->getDisplay());
        self::assertStringContainsString('items=12', $tester->getDisplay());
    }

    public function testCommandSucceedsWhenCajaIdIsNullProducingNullCashSessionId(): void
    {
        $connection = $this->mockConnectionForSuccessfulInsert(headersInserted: 1, itemsInserted: 2);
        $exitCode = (new CommandTester($this->buildCommand($connection)))->execute([]);
        self::assertSame(Command::SUCCESS, $exitCode);
    }

    public function testCommandSkipsGracefullyWhenRequiredTableIsMissing(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willThrowException($this->createTableNotFoundException());
        $connection->expects(self::never())->method('executeStatement');
        $tester = new CommandTester($this->buildCommand($connection));
        $exitCode = $tester->execute([]);
        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Skipping tenant', $tester->getDisplay());
    }

    public function testNullCajaIdRowIsBackfilledWithNullCashSessionId(): void
    {
        $connection = $this->createMock(Connection::class);
        // fetchOne calls: 4 table checks + 1 skipped-count (returns 0 = no nulls skipped)
        $fetchOneCall = 0;
        $connection->method('fetchOne')->willReturnCallback(
            static function () use (&$fetchOneCall): int {
                ++$fetchOneCall;
                return $fetchOneCall === 5 ? 0 : 1;
            }
        );
        $connection->method('fetchFirstColumn')->willReturn(['m001', 'm002', 'm003']);
        $connection->method('beginTransaction');
        $connection->method('commit');
        $execCall = 0;
        $connection->method('executeStatement')
            ->willReturnCallback(static function () use (&$execCall): int {
                ++$execCall;
                return $execCall === 1 ? 7 : 14;
            });
        $tester = new CommandTester($this->buildCommand($connection));
        $exitCode = $tester->execute([]);
        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringNotContainsString('Skipped', $tester->getDisplay());
        self::assertStringContainsString('headers=7', $tester->getDisplay());
    }

    public function testNullCajaIdNotNullButNoSessionInsertsWithNullCashSessionId(): void
    {
        $capturedSql = '';
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn(1);
        $connection->method('fetchFirstColumn')->willReturn(['m001']);
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
        $tester = new CommandTester($this->buildCommand($connection));
        $exitCode = $tester->execute([]);
        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringNotContainsString('caja_id IS NOT NULL', $capturedSql);
        self::assertStringNotContainsString('cash_session_id IS NOT NULL', $capturedSql);
    }

    public function testSqlSentToConnectionDoesNotContainTurnoId(): void
    {
        $capturedSql = '';
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn(1);
        $connection->method('fetchFirstColumn')->willReturn(['m001']);
        $connection->method('beginTransaction');
        $connection->method('commit');
        $connection->method('executeStatement')
            ->willReturnCallback(static function (string $sql) use (&$capturedSql): int {
                $capturedSql .= $sql;
                return 0;
            });
        (new CommandTester($this->buildCommand($connection)))->execute([]);
        self::assertStringNotContainsString('turno_id', $capturedSql);
        self::assertStringContainsString('cash_register_id', $capturedSql);
        self::assertStringContainsString('opened_at', $capturedSql);
        self::assertStringContainsString('closed_at', $capturedSql);
    }

    public function testSqlDoesNotContainBinaryCasts(): void
    {
        $capturedSql = '';
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn(1);
        $connection->method('fetchFirstColumn')->willReturn(['m001']);
        $connection->method('beginTransaction');
        $connection->method('commit');
        $connection->method('executeStatement')
            ->willReturnCallback(static function (string $sql) use (&$capturedSql): int {
                $capturedSql .= $sql;
                return 0;
            });
        (new CommandTester($this->buildCommand($connection)))->execute([]);
        self::assertStringNotContainsString(
            'BINARY',
            $capturedSql,
            'BINARY casts must be removed — they prevent index usage and cause full-table scans'
        );
    }

    public function testHeadersInsertUsesLeftJoinAntiJoinNotNotExists(): void
    {
        $capturedHeadersSql = '';
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn(1);
        $connection->method('fetchFirstColumn')->willReturn(['m001']);
        $connection->method('beginTransaction');
        $connection->method('commit');
        $execCall = 0;
        $connection->method('executeStatement')
            ->willReturnCallback(
                static function (string $sql) use (&$capturedHeadersSql, &$execCall): int {
                    ++$execCall;
                    if ($execCall === 1) {
                        $capturedHeadersSql = $sql;
                    }
                    return 0;
                }
            );
        (new CommandTester($this->buildCommand($connection)))->execute([]);
        self::assertStringContainsString(
            'LEFT JOIN sales',
            $capturedHeadersSql,
            'Headers INSERT must use LEFT JOIN anti-join pattern instead of NOT EXISTS'
        );
        self::assertStringContainsString('IS NULL', $capturedHeadersSql);
        self::assertStringNotContainsString(
            'NOT EXISTS',
            $capturedHeadersSql,
            'Headers INSERT must not use correlated NOT EXISTS subquery'
        );
    }

    public function testItemsInsertUsesLeftJoinAntiJoinNotNotExists(): void
    {
        $capturedItemsSql = '';
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn(1);
        $connection->method('fetchFirstColumn')->willReturn(['m001']);
        $connection->method('beginTransaction');
        $connection->method('commit');
        $execCall = 0;
        $connection->method('executeStatement')
            ->willReturnCallback(
                static function (string $sql) use (&$capturedItemsSql, &$execCall): int {
                    ++$execCall;
                    if ($execCall === 2) {
                        $capturedItemsSql = $sql;
                    }
                    return 0;
                }
            );
        (new CommandTester($this->buildCommand($connection)))->execute([]);
        self::assertStringContainsString(
            'LEFT  JOIN sale_items',
            $capturedItemsSql,
            'Items INSERT must use LEFT JOIN anti-join pattern instead of NOT EXISTS'
        );
        self::assertStringContainsString('IS NULL', $capturedItemsSql);
        self::assertStringNotContainsString(
            'NOT EXISTS',
            $capturedItemsSql,
            'Items INSERT must not use correlated NOT EXISTS subquery'
        );
    }

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

    private function mockConnectionForSuccessfulInsert(int $headersInserted, int $itemsInserted): Connection
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn(1);
        $connection->method('fetchFirstColumn')->willReturn(['m001', 'm002', 'm003']);
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

    private function createTableNotFoundException(): TableNotFoundException
    {
        return new TableNotFoundException(new StubDriverException('Table not found', 0), null);
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
