<?php

declare(strict_types=1);

namespace App\Tests\ControlPlane\Command;

use App\ControlPlane\Tenant\Command\TenantUsagePruneCommand;
use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ConnectionRegistry;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class TenantUsagePruneCommandTest extends TestCase
{
    private ConnectionRegistry&MockObject $registry;
    private Connection&MockObject $connection;

    protected function setUp(): void
    {
        $this->registry = $this->createMock(ConnectionRegistry::class);
        $this->connection = $this->createMock(Connection::class);
        $this->registry
            ->method('getConnection')
            ->with('control_plane')
            ->willReturn($this->connection);
    }

    public function testDryRunDoesNotDeleteRows(): void
    {
        $this->connection
            ->expects($this->once())
            ->method('fetchOne')
            ->willReturn(12);

        $this->connection
            ->expects($this->never())
            ->method('executeStatement');

        $tester = new CommandTester(new TenantUsagePruneCommand($this->registry));
        $exitCode = $tester->execute([
            '--days' => '30',
            '--dry-run' => true,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Dry run', $tester->getDisplay());
        $this->assertStringContainsString('12 rows', $tester->getDisplay());
    }

    public function testExecuteDeletesRows(): void
    {
        $this->connection
            ->expects($this->once())
            ->method('fetchOne')
            ->willReturn(20);

        $this->connection
            ->expects($this->once())
            ->method('executeStatement')
            ->willReturn(17);

        $tester = new CommandTester(new TenantUsagePruneCommand($this->registry));
        $exitCode = $tester->execute([
            '--days' => '90',
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Deleted 17 row', $tester->getDisplay());
    }
}
