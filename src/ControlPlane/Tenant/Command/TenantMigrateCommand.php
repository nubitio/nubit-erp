<?php

declare(strict_types=1);

namespace App\ControlPlane\Tenant\Command;

use App\Efact\Migrations\TenantMigrationFactory;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Version\Direction;
use Doctrine\Migrations\Version\ExecutionResult;
use Doctrine\Persistence\ConnectionRegistry;
use Nubit\Platform\Symfony\Console\PerTenantCommand;
use Override;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:tenants:migrate', description: 'Run Doctrine migrations for all tenants.')]
class TenantMigrateCommand extends PerTenantCommand
{
    public function __construct(
        private readonly TenantMigrationFactory $migrationFactory,
        private readonly ConnectionRegistry $connectionRegistry,
        private readonly LoggerInterface $migrationLogger,
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
