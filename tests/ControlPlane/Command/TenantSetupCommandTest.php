<?php

declare(strict_types=1);

namespace App\Tests\ControlPlane\Command;

use App\ControlPlane\Tenant\Command\TenantSetupCommand;
use App\ControlPlane\Tenant\Port\InitialTenantSetupPortInterface;
use App\ControlPlane\Tenant\Port\InitialTenantSetupRequest;
use App\ControlPlane\Tenant\Service\TenantConnectionSwitcher;
use Nubit\Platform\Tenant\Context\TenantContext;
use Nubit\Platform\Tenant\Contract\TenantRegistryInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class TenantSetupCommandTest extends TestCase
{
    public function testUsesDefaultValuesWhenRunNonInteractivelyWithoutOptions(): void
    {
        $initialSetupService = $this->createMock(InitialTenantSetupPortInterface::class);
        $initialSetupService
            ->expects(self::once())
            ->method('configure')
            ->with(self::callback(function (InitialTenantSetupRequest $input): bool {
                self::assertSame('admin', $input->adminUsername);
                self::assertSame('Admin.3000', $input->adminPassword);
                self::assertSame('20123456789', $input->companyRuc);
                self::assertSame('Empresa Demo', $input->companyBusinessName);
                self::assertSame('Demo', $input->companyTradeName);
                self::assertSame('Av. Principal 123', $input->companyFiscalAddress);
                self::assertSame('999999999', $input->companyPhone);
                self::assertSame('Principal', $input->branchName);
                self::assertSame('Av. Principal 123', $input->branchAddress);
                self::assertSame('999999999', $input->branchPhone);

                return true;
            }));

        $command = $this->newCommand($initialSetupService);
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Initial setup completed.', $tester->getDisplay());
    }

    public function testUsesOptionValuesWhenProvided(): void
    {
        $initialSetupService = $this->createMock(InitialTenantSetupPortInterface::class);
        $initialSetupService
            ->expects(self::once())
            ->method('configure')
            ->with(self::callback(function (InitialTenantSetupRequest $input): bool {
                self::assertSame('ops-admin', $input->adminUsername);
                self::assertSame('s3cret', $input->adminPassword);
                self::assertSame('20987654321', $input->companyRuc);
                self::assertSame('Acme Corp', $input->companyBusinessName);
                self::assertSame('Acme', $input->companyTradeName);
                self::assertSame('Street 42', $input->companyFiscalAddress);
                self::assertSame('988000111', $input->companyPhone);
                self::assertSame('Sucursal Norte', $input->branchName);
                self::assertSame('Avenida Norte 111', $input->branchAddress);
                self::assertSame('977000222', $input->branchPhone);

                return true;
            }));

        $command = $this->newCommand($initialSetupService);
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([
            '--admin-user' => 'ops-admin',
            '--admin-pass' => 's3cret',
            '--company-ruc' => '20987654321',
            '--company-name' => 'Acme Corp',
            '--company-trade' => 'Acme',
            '--company-address' => 'Street 42',
            '--company-phone' => '988000111',
            '--branch-name' => 'Sucursal Norte',
            '--branch-address' => 'Avenida Norte 111',
            '--branch-phone' => '977000222',
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Initial setup completed.', $tester->getDisplay());
    }

    public function testReturnsSuccessAndWarnsWhenSetupThrows(): void
    {
        $initialSetupService = $this->createStub(InitialTenantSetupPortInterface::class);
        $initialSetupService->method('configure')->willThrowException(new \RuntimeException('already configured'));

        $command = $this->newCommand($initialSetupService);
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Initial setup skipped: already configured', $tester->getDisplay());
    }

    private function newCommand(InitialTenantSetupPortInterface $initialSetupService): TenantSetupCommand
    {
        $tenantRegistry = $this->createStub(TenantRegistryInterface::class);
        $tenantRegistry
            ->method('getTenants')
            ->willReturn([['id' => 1, 'name' => 'test', 'primary_domain' => 'test.localhost']]);
        $tenantRegistry
            ->method('getTenantByName')
            ->willReturn(['id' => 1, 'name' => 'test', 'primary_domain' => 'test.localhost']);

        $command = new TenantSetupCommand($initialSetupService);
        $command->initDependencies(
            $tenantRegistry,
            $this->createMock(TenantConnectionSwitcher::class),
            new TenantContext(),
            $this->createMock(LoggerInterface::class),
        );

        return $command;
    }
}
