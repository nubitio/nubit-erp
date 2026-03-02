<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\SalesV2ReconcileCommand;
use App\Core\Context\TenantContext;
use App\Core\Service\TenantConnectionSwitcher;
use App\Core\Service\TenantManager;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Exception as DriverException;
use Doctrine\DBAL\Exception\TableNotFoundException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class SalesV2ReconcileCommandTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Test 1: Empty tables — both day queries and per-sale query return [] → 0
    // -------------------------------------------------------------------------
    public function testEmptyTablesExitsSuccess(): void
    {
        $connection = $this->createConnectionStub();
        $connection->method('fetchAllAssociative')->willReturn([]);

        $tester = new CommandTester($this->newCommand($connection));
        $exitCode = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringNotContainsString('MISMATCH', $tester->getDisplay());
        self::assertStringNotContainsString('[ERROR]', $tester->getDisplay());
    }

    // -------------------------------------------------------------------------
    // Test 2: Day-level count mismatch → exits 1, output contains 'MISMATCH'
    // -------------------------------------------------------------------------
    public function testDayLevelCountMismatchExitsFailure(): void
    {
        $day = (new \DateTimeImmutable('today'))->format('Y-m-d');

        $legacyRows = [['d' => $day, 'cnt' => 5, 'total_amount' => '100.00']];
        $v2Rows = [['d' => $day, 'cnt' => 3, 'total_amount' => '100.00']];

        $connection = $this->createConnectionMock();
        $connection->expects(self::exactly(3))
            ->method('fetchAllAssociative')
            ->willReturnOnConsecutiveCalls($legacyRows, $v2Rows, []);

        $tester = new CommandTester($this->newCommand($connection));
        $exitCode = $tester->execute([]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('MISMATCH', $tester->getDisplay());
    }

    // -------------------------------------------------------------------------
    // Test 3: Day-level amount mismatch (same count, diff > tolerance) → exits 1
    // -------------------------------------------------------------------------
    public function testDayLevelAmountMismatchExitsFailure(): void
    {
        $day = (new \DateTimeImmutable('today'))->format('Y-m-d');

        $legacyRows = [['d' => $day, 'cnt' => 5, 'total_amount' => '100.00']];
        $v2Rows = [['d' => $day, 'cnt' => 5, 'total_amount' => '99.00']];

        $connection = $this->createConnectionMock();
        $connection->expects(self::exactly(3))
            ->method('fetchAllAssociative')
            ->willReturnOnConsecutiveCalls($legacyRows, $v2Rows, []);

        $tester = new CommandTester($this->newCommand($connection));
        $exitCode = $tester->execute(['--tolerance' => '0.01']);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('MISMATCH', $tester->getDisplay());
    }

    // -------------------------------------------------------------------------
    // Test 4: Day-level matches within tolerance (diff=0.005, tol=0.01) → exits 0
    // -------------------------------------------------------------------------
    public function testDayLevelWithinToleranceExitsSuccess(): void
    {
        $day = (new \DateTimeImmutable('today'))->format('Y-m-d');

        $legacyRows = [['d' => $day, 'cnt' => 5, 'total_amount' => '100.005']];
        $v2Rows = [['d' => $day, 'cnt' => 5, 'total_amount' => '100.00']];

        $connection = $this->createConnectionMock();
        $connection->expects(self::exactly(3))
            ->method('fetchAllAssociative')
            ->willReturnOnConsecutiveCalls($legacyRows, $v2Rows, []);

        $tester = new CommandTester($this->newCommand($connection));
        $exitCode = $tester->execute(['--tolerance' => '0.01']);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringNotContainsString('MISMATCH', $tester->getDisplay());
        self::assertStringContainsString('OK', $tester->getDisplay());
    }

    // -------------------------------------------------------------------------
    // Test 5: TableNotFoundException thrown → exits 0, output contains 'Skipping'
    // -------------------------------------------------------------------------
    public function testTableNotFoundExceptionExitsSuccessWithWarning(): void
    {
        $connection = $this->createConnectionStub();
        $connection->method('fetchAllAssociative')
            ->willThrowException($this->makeTableNotFoundException());

        $tester = new CommandTester($this->newCommand($connection));
        $exitCode = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Skipping', $tester->getDisplay());
    }

    // -------------------------------------------------------------------------
    // Test 6: Per-sale rounding mismatch (day OK, per-sale diff=0.02) → exits 1
    // -------------------------------------------------------------------------
    public function testPerSaleRoundingMismatchExitsFailure(): void
    {
        $day = (new \DateTimeImmutable('today'))->format('Y-m-d');

        $legacyRows = [['d' => $day, 'cnt' => 1, 'total_amount' => '50.00']];
        $v2Rows = [['d' => $day, 'cnt' => 1, 'total_amount' => '50.00']];
        $perSaleRows = [[
            'movimiento_id' => 42,
            'legacy_total' => '50.00',
            'v2_total' => '49.98',
            'diff' => '0.02',
        ]];

        $connection = $this->createConnectionMock();
        $connection->expects(self::exactly(3))
            ->method('fetchAllAssociative')
            ->willReturnOnConsecutiveCalls($legacyRows, $v2Rows, $perSaleRows);

        $tester = new CommandTester($this->newCommand($connection));
        $exitCode = $tester->execute(['--tolerance' => '0.01']);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('42', $tester->getDisplay());
        self::assertStringContainsString('0.02', $tester->getDisplay());
    }

    // -------------------------------------------------------------------------
    // Test 7: --days window — verify $fromDate SQL param contains correct date
    // -------------------------------------------------------------------------
    public function testDaysWindowPassesCorrectFromDateToQuery(): void
    {
        $expectedFrom = (new \DateTimeImmutable('today'))
            ->modify('-2 days')
            ->format('Y-m-d 00:00:00');

        $connection = $this->createConnectionStub();

        $capturedParams = [];
        $connection->method('fetchAllAssociative')
            ->willReturnCallback(
                function (string $sql, array $params) use (&$capturedParams): array {
                    $capturedParams[] = $params;
                    return [];
                }
            );

        $tester = new CommandTester($this->newCommand($connection));
        $tester->execute(['--days' => '3']);

        // First call is the legacy day-level query (positional param).
        self::assertNotEmpty($capturedParams);
        self::assertSame($expectedFrom, $capturedParams[0][0]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** @return Connection&MockObject */
    private function createConnectionMock(): Connection
    {
        return $this->createMock(Connection::class);
    }

    /** @return Connection&\PHPUnit\Framework\MockObject\Stub */
    private function createConnectionStub(): Connection
    {
        return $this->createStub(Connection::class);
    }

    private function newCommand(Connection $connection): SalesV2ReconcileCommand
    {
        $tenantManager = $this->createStub(TenantManager::class);
        $tenantManager->method('getTenants')->willReturn([]);

        $command = new SalesV2ReconcileCommand($connection);
        $command->initDependencies(
            $tenantManager,
            $this->createStub(TenantConnectionSwitcher::class),
            new TenantContext(),
            $this->createStub(LoggerInterface::class)
        );

        return $command;
    }

    private function makeTableNotFoundException(): TableNotFoundException
    {
        $driverException = new class ('table not found', 0) extends \RuntimeException implements DriverException {
            public function getSQLState(): ?string
            {
                return null;
            }
        };

        return new TableNotFoundException($driverException, null);
    }
}
