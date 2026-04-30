<?php

declare(strict_types=1);

namespace App\Tests\Core\Application\Command;

use Nubit\Platform\Symfony\Console\TenantAwareCommand;
use Nubit\Platform\Tenant\Context\TenantContext;
use Nubit\Platform\Exception\ServiceException;
use App\ControlPlane\Tenant\Service\TenantConnectionSwitcher;
use App\ControlPlane\Tenant\Service\TenantManager;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class TenantAwareCommandTest extends TestCase
{
    public function testExecutesWithDefaultConnectionWhenNoTenantsExist(): void
    {
        $tenantManager = $this->createMock(TenantManager::class);
        $tenantManager->method('getTenants')->willReturn([]);

        $command = new DummyTenantAwareCommand();
        $command->initDependencies(
            $tenantManager,
            $this->createMock(TenantConnectionSwitcher::class),
            new TenantContext(),
            $this->createMock(LoggerInterface::class)
        );

        $tester = new CommandTester($command);
        $exitCode = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertSame(['default'], $command->executedTenants);
        self::assertStringContainsString('No tenants found', $tester->getDisplay());
    }

    public function testFailsWhenRequestedTenantDoesNotExist(): void
    {
        $tenantManager = $this->createMock(TenantManager::class);
        $tenantManager->method('getTenants')->willReturn([
            ['id' => 1, 'name' => 'acme'],
        ]);
        $tenantManager->method('getTenantByName')->with('missing')->willReturn(null);

        $command = new DummyTenantAwareCommand();
        $command->initDependencies(
            $tenantManager,
            $this->createMock(TenantConnectionSwitcher::class),
            new TenantContext(),
            $this->createMock(LoggerInterface::class)
        );

        $tester = new CommandTester($command);
        $exitCode = $tester->execute(['--tenant' => 'missing']);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('Tenant "missing" not found.', $tester->getDisplay());
    }

    public function testExecutesRequestedTenantAndSwitchesContext(): void
    {
        $tenantManager = $this->createMock(TenantManager::class);
        $tenantManager->method('getTenants')->willReturn([
            ['id' => 1, 'name' => 'acme', 'primary_domain' => 'acme.example.test'],
        ]);
        $tenantManager->method('getTenantByName')->with('acme')->willReturn(
            ['id' => 1, 'name' => 'acme', 'primary_domain' => 'acme.example.test']
        );

        $switcher = $this->createMock(TenantConnectionSwitcher::class);
        $switcher->expects(self::once())->method('switchConnection')->with('acme');
        $tenantContext = new TenantContext();

        $command = new DummyTenantAwareCommand();
        $command->initDependencies(
            $tenantManager,
            $switcher,
            $tenantContext,
            $this->createMock(LoggerInterface::class)
        );

        $tester = new CommandTester($command);
        $exitCode = $tester->execute(['--tenant' => 'acme']);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertSame(['acme'], $command->executedTenants);
        self::assertSame('acme', $tenantContext->getTenantName());
    }

    public function testAggregatesFailuresAcrossAllTenants(): void
    {
        $tenantManager = $this->createMock(TenantManager::class);
        $tenantManager->method('getTenants')->willReturn([
            ['id' => 1, 'name' => 'acme'],
            ['id' => 2, 'name' => 'globex'],
        ]);

        $switcher = $this->createMock(TenantConnectionSwitcher::class);
        $switcher->expects(self::exactly(2))->method('switchConnection');

        $command = new DummyTenantAwareCommand();
        $command->resultByTenant = [
            'acme' => Command::SUCCESS,
            'globex' => Command::FAILURE,
        ];
        $command->initDependencies(
            $tenantManager,
            $switcher,
            new TenantContext(),
            $this->createMock(LoggerInterface::class)
        );

        $tester = new CommandTester($command);
        $exitCode = $tester->execute([]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('Failed tenants: globex', $tester->getDisplay());
    }

    public function testHandlesServiceExceptionDuringTenantExecution(): void
    {
        $tenantManager = $this->createMock(TenantManager::class);
        $tenantManager->method('getTenants')->willReturn([
            ['id' => 1, 'name' => 'acme'],
        ]);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');

        $command = new DummyTenantAwareCommand();
        $command->throwingTenant = 'acme';
        $command->initDependencies(
            $tenantManager,
            $this->createMock(TenantConnectionSwitcher::class),
            new TenantContext(),
            $logger
        );

        $tester = new CommandTester($command);
        $exitCode = $tester->execute([]);

        self::assertSame(Command::FAILURE, $exitCode);
    }

    public function testBuildParallelCommandArgsIncludesCustomArgsAndOptions(): void
    {
        $command = new DummyTenantAwareCommand();
        $reflection = new \ReflectionObject($command);
        $method = $reflection->getMethod('buildParallelCommandArgs');

        $input = new \Symfony\Component\Console\Input\ArrayInput([
            'ids' => ['id-1', 'id-2'],
            '--opt' => 'value',
            '--flag' => true,
            '--multi' => ['x', 'y'],
            '--parallel' => 8,
        ], $command->getDefinition());

        /** @var list<string> $args */
        $args = $method->invoke($command, $input, 'app:test:tenant-aware-dummy', 'acme');

        self::assertContains('bin/console', $args);
        self::assertContains('app:test:tenant-aware-dummy', $args);
        self::assertContains('--tenant=acme', $args);
        self::assertContains('id-1', $args);
        self::assertContains('id-2', $args);
        self::assertContains('--opt=value', $args);
        self::assertContains('--flag', $args);
        self::assertContains('--multi=x', $args);
        self::assertContains('--multi=y', $args);
    }
}

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class DummyTenantAwareCommand extends TenantAwareCommand
{
    /** @var list<string> */
    public array $executedTenants = [];

    /** @var array<string, int> */
    public array $resultByTenant = [];

    public ?string $throwingTenant = null;

    public function __construct()
    {
        parent::__construct('app:test:tenant-aware-dummy');
    }

    protected function configure(): void
    {
        parent::configure();
        $this->addArgument('ids', InputArgument::IS_ARRAY | InputArgument::OPTIONAL, 'IDs');
        $this->addOption('opt', null, InputOption::VALUE_REQUIRED, 'Option with value');
        $this->addOption('flag', null, InputOption::VALUE_NONE, 'Boolean flag');
        $this->addOption('multi', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Multi option');
    }

    protected function executeTenantCommand(InputInterface $input, OutputInterface $output): int
    {
        $tenant = $this->currentTenantName ?? 'default';
        $this->executedTenants[] = $tenant;

        if ($this->throwingTenant === $tenant) {
            throw new ServiceException('boom');
        }

        return $this->resultByTenant[$tenant] ?? Command::SUCCESS;
    }
}
