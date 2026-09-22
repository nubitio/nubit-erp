<?php

declare(strict_types=1);

namespace App\ControlPlane\Tenant\Command;

use App\Efact\Migrations\TenantMigrationFactory;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Version\Direction;
use Doctrine\Migrations\Version\ExecutionResult;
use Doctrine\Persistence\ConnectionRegistry;
use Nubit\Platform\Symfony\Console\PerTenantCommand;
use Nubit\Platform\Tenant\Contract\TenantRegistryInterface;
use Nubit\Platform\Tenant\Model\TenantDescriptor;
use Nubit\Platform\Tenant\Runtime\TenantRuntime;
use Nubit\Platform\Tenant\Runtime\TenantRuntimeActor;
use Override;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;
use Symfony\Component\Process\Process;

#[AsCommand(name: 'app:tenants:migrate', description: 'Run Doctrine migrations for all tenants.')]
class TenantMigrateCommand extends PerTenantCommand
{
    public const int DEFAULT_CONCURRENCY = 8;

    public function __construct(
        private readonly TenantMigrationFactory $migrationFactory,
        private readonly ConnectionRegistry $connectionRegistry,
        private readonly LoggerInterface $migrationLogger,
        private readonly TenantRegistryInterface $tenantRegistry,
        private readonly TenantRuntime $tenantRuntime,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        parent::configure();

        $this->addOption(
            'baseline-existing-schema',
            null,
            InputOption::VALUE_NONE,
            'Mark all available tenant migrations as executed without running SQL. Use only after loading a baseline schema dump.',
        );
    }

    /**
     * PerTenantCommand holds a process lock named after the command. Parallel
     * workers are spawned as `app:tenants:migrate --tenant=…`; if they took
     * that same lock they would exit success without migrating. The
     * coordinator lock lives here; workers skip it.
     */
    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $this->tenants = $this->tenantRegistry->getTenants();

        if ($this->tenants === []) {
            $io->note('No tenants provisioned — nothing to do.');

            return Command::SUCCESS;
        }

        $tenantName = $input->getOption('tenant');
        if (is_string($tenantName) && $tenantName !== '') {
            return $this->runSingleTenant($tenantName, $input, $output, $io);
        }

        $concurrency = self::resolveConcurrency(
            $input->getOption('parallel'),
            ($environmentConcurrency = getenv('TENANT_MIGRATE_CONCURRENCY')) === false
                ? null
                : $environmentConcurrency,
        );

        $lock = new LockFactory(new FlockStore())->createLock('app:tenants:migrate.coordinator');
        if (!$lock->acquire()) {
            $io->writeln('The command is already running in another process.');

            return Command::SUCCESS;
        }

        try {
            if ($concurrency === 1) {
                return $this->runAllSequential($input, $output, $io);
            }

            return $this->runAllParallel($input, $io, $concurrency);
        } finally {
            $lock->release();
        }
    }

    /**
     * @param mixed $parallelOption Symfony --parallel value (false when omitted)
     */
    public static function resolveConcurrency(mixed $parallelOption, ?string $envValue): int
    {
        if ($parallelOption === false) {
            $fromEnv = $envValue !== null && $envValue !== '' ? (int) $envValue : self::DEFAULT_CONCURRENCY;

            return max(1, $fromEnv);
        }

        if ($parallelOption === null || $parallelOption === '' || $parallelOption === 0 || $parallelOption === '0') {
            return self::DEFAULT_CONCURRENCY;
        }

        return max(1, (int) $parallelOption);
    }

    private function runSingleTenant(
        string $tenantName,
        InputInterface $input,
        OutputInterface $output,
        SymfonyStyle $io,
    ): int {
        $tenant = $this->tenantRegistry->getTenantByName($tenantName);
        if ($tenant === null) {
            $io->error(sprintf('Tenant "%s" not found.', $tenantName));

            return Command::FAILURE;
        }

        $io->title('Tenant: ' . $tenantName);

        return $this->tenantRuntime->run(
            $tenant,
            function (TenantDescriptor $descriptor) use ($input, $output): int {
                $this->currentTenantName = $descriptor->name;

                return $this->executeTenantCommand($input, $output);
            },
            new TenantRuntimeActor(
                actorIdentifier: 'cli:app:tenants:migrate',
                channel: 'cli',
                commandName: 'app:tenants:migrate',
            ),
        );
    }

    private function runAllSequential(InputInterface $input, OutputInterface $output, SymfonyStyle $io): int
    {
        $failed = [];
        foreach ($this->tenants as $tenant) {
            $name = (string) $tenant['name'];
            $result = $this->runSingleTenant($name, $input, $output, $io);
            if ($result !== Command::SUCCESS) {
                $failed[] = $name;
            }
        }

        if ($failed !== []) {
            $io->error('Failed tenants: ' . implode(', ', array_unique($failed)));

            return Command::FAILURE;
        }

        $io->success('Done!');

        return Command::SUCCESS;
    }

    private function runAllParallel(InputInterface $input, SymfonyStyle $io, int $concurrency): int
    {
        $io->info(sprintf('Migrating %d tenants (concurrency: %d)', count($this->tenants), $concurrency));

        /** @var array<string, Process> $running */
        $running = [];
        $queue = array_map(static fn(array $t): string => (string) $t['name'], $this->tenants);
        $failed = [];
        $succeeded = [];

        while ($queue !== [] || $running !== []) {
            while (count($running) < $concurrency && $queue !== []) {
                $name = array_shift($queue);
                $process = new Process($this->workerCommandArgs($input, $name));
                $process->setTimeout(null);
                $process->start();
                $running[$name] = $process;
                $io->writeln(sprintf('  <info>Started</info> %s', $name));
            }

            foreach ($running as $name => $process) {
                if ($process->isRunning()) {
                    continue;
                }

                unset($running[$name]);
                if ($process->isSuccessful()) {
                    $succeeded[] = $name;
                    $io->writeln(sprintf('  <fg=green>Done</> %s', $name));
                    continue;
                }

                $failed[] = $name;
                $this->migrationLogger->error('Tenant migration failed', [
                    'tenant' => $name,
                    'output' => $process->getErrorOutput(),
                    'stdout' => $process->getOutput(),
                ]);
                $io->writeln(sprintf('  <fg=red>Failed</> %s', $name));
            }

            if ($running !== []) {
                usleep(100_000);
            }
        }

        $io->writeln(sprintf('Succeeded: %d, Failed: %d', count($succeeded), count($failed)));
        if ($failed !== []) {
            $io->error('Failed tenants: ' . implode(', ', $failed));

            return Command::FAILURE;
        }

        $io->success('All tenants migrated');

        return Command::SUCCESS;
    }

    /** @return list<string> */
    private function workerCommandArgs(InputInterface $input, string $tenantName): array
    {
        $args = [PHP_BINARY, 'bin/console', 'app:tenants:migrate', '--tenant=' . $tenantName, '--no-interaction'];
        if ($input->getOption('baseline-existing-schema')) {
            $args[] = '--baseline-existing-schema';
        }

        return $args;
    }

    #[Override]
    protected function executeTenantCommand(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dependencyFactory = $this->migrationFactory->createDependencyFactory(
            $this->connectionRegistry,
            $this->migrationLogger,
        );

        $dependencyFactory->getMetadataStorage()->ensureInitialized();

        if ($input->getOption('baseline-existing-schema')) {
            $marked = $this->markAvailableMigrationsExecuted($dependencyFactory);
            $io->writeln(sprintf('Baseline recorded. %d migration(s) marked as executed.', $marked));

            return Command::SUCCESS;
        }

        $targetVersion = $dependencyFactory->getVersionAliasResolver()->resolveVersionAlias('latest');

        $plan = $dependencyFactory->getMigrationPlanCalculator()->getPlanUntilVersion($targetVersion);

        if ($plan->count() === 0) {
            $io->writeln('No migrations to execute.');

            return Command::SUCCESS;
        }

        $migratorConfiguration = $dependencyFactory
            ->getConsoleInputMigratorConfigurationFactory()
            ->getMigratorConfiguration($input);

        $dependencyFactory->getMigrator()->migrate($plan, $migratorConfiguration);

        $io->writeln('Migrations executed.');

        return Command::SUCCESS;
    }

    private function markAvailableMigrationsExecuted(DependencyFactory $dependencyFactory): int
    {
        $metadataStorage = $dependencyFactory->getMetadataStorage();
        $executedMigrations = $metadataStorage->getExecutedMigrations();
        $marked = 0;

        foreach ($dependencyFactory->getMigrationRepository()->getMigrations()->getItems() as $availableMigration) {
            $version = $availableMigration->getVersion();
            if ($executedMigrations->hasMigration($version)) {
                continue;
            }

            $metadataStorage->complete(new ExecutionResult($version, Direction::UP, new \DateTimeImmutable()));
            ++$marked;
        }

        return $marked;
    }
}
