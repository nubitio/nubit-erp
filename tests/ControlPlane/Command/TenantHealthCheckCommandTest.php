<?php

declare(strict_types=1);

namespace App\Tests\ControlPlane\Command;

use App\ControlPlane\Tenant\Command\TenantHealthCheckCommand;
use App\ControlPlane\Tenant\Service\TenantHealthChecker;
use App\ControlPlane\Tenant\Application\TenantService;
use App\Efact\Doctrine\DBAL\TenantConnection;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\Persistence\ConnectionRegistry;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class TenantHealthCheckCommandTest extends TestCase
{
    public function testChecksSpecificTenantAndReturnsSuccessWhenHealthy(): void
    {
        $tenantService = $this->createMock(TenantService::class);
        $tenantService
            ->method('getTenantByName')
            ->with('acme')
            ->willReturn([
                'id' => 1,
                'name' => 'acme',
                'connection' => 'acme_db',
            ]);

        $command = $this->newCommand($tenantService, $this->healthyTenantConnection());

        $tester = new CommandTester($command);
        $exitCode = $tester->execute(['tenant' => 'acme']);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('All tenants healthy', $tester->getDisplay());
    }

    public function testReturnsSuccessWhenNoTenantsExist(): void
    {
        $tenantService = $this->createMock(TenantService::class);
        $tenantService->method('getTenants')->willReturn([]);

        $command = $this->newCommand($tenantService, $this->healthyTenantConnection());

        $tester = new CommandTester($command);
        $exitCode = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('No tenants found', $tester->getDisplay());
    }

    public function testReturnsFailureWhenAtLeastOneTenantIsUnhealthy(): void
    {
        $tenantService = $this->createMock(TenantService::class);
        $tenantService
            ->method('getTenants')
            ->willReturn([
                ['id' => 1, 'name' => 'acme'],
                ['id' => 2, 'name' => 'globex'],
            ]);
        $tenantService
            ->method('getTenantByName')
            ->willReturnMap([
                ['acme', ['id' => 1, 'name' => 'acme', 'connection' => 'acme_db']],
                ['globex', null],
            ]);

        $command = $this->newCommand($tenantService, $this->healthyTenantConnection());

        $tester = new CommandTester($command);
        $exitCode = $tester->execute([]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('Some tenants have health issues', $tester->getDisplay());
    }

    private function newCommand(
        TenantService $tenantService,
        TenantConnection $tenantConnection,
    ): TenantHealthCheckCommand {
        $registry = $this->createMock(ConnectionRegistry::class);
        $registry->method('getConnection')->with('tenant')->willReturn($tenantConnection);

        $checker = new TenantHealthChecker($registry, $tenantService, $this->createMock(LoggerInterface::class));

        return new TenantHealthCheckCommand($checker, $tenantService);
    }

    private function healthyTenantConnection(): TenantConnection
    {
        $schema = $this->createStub(AbstractSchemaManager::class);
        $schema->method('listTableNames')->willReturn(['user', 'tenant_settings', 'branch']);

        $connection = $this->createMock(TenantConnection::class);
        $connection->method('switchTenantParams');
        $connection->method('executeQuery')->with('SELECT 1');
        $connection->method('createSchemaManager')->willReturn($schema);

        return $connection;
    }
}
