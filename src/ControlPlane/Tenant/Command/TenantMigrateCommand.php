<?php

declare(strict_types=1);

namespace App\ControlPlane\Tenant\Command;

use Nubit\Platform\Symfony\Console\TenantAwareCommand;
use App\Efact\Migrations\TenantMigrationFactory;
use Doctrine\Persistence\ConnectionRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:tenants:migrate',
    description: 'Run Doctrine migrations for all tenants.',
)]
class TenantMigrateCommand extends TenantAwareCommand
{
    public function __construct(
        private readonly TenantMigrationFactory $migrationFactory,
        private readonly ConnectionRegistry $connectionRegistry,
        private readonly LoggerInterface $migrationLogger,
    ) {
        parent::__construct();
    }

    protected function executeTenantCommand(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dependencyFactory = $this->migrationFactory->createDependencyFactory(
            $this->connectionRegistry,
            $this->migrationLogger,
        );

        $dependencyFactory->getMetadataStorage()->ensureInitialized();

        $targetVersion = $dependencyFactory
            ->getVersionAliasResolver()
            ->resolveVersionAlias('latest');

        $plan = $dependencyFactory
            ->getMigrationPlanCalculator()
            ->getPlanUntilVersion($targetVersion);

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
}
