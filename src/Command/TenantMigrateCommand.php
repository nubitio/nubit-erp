<?php

declare(strict_types=1);

namespace App\Command;

use App\Core\Application\Command\TenantAwareCommand;
use App\Core\Service\TenantConnectionSwitcher;
use App\Core\Service\TenantManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Doctrine\Migrations\DependencyFactory;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:tenants:migrate',
    description: 'Run Doctrine migrations for all tenants.',
)]
class TenantMigrateCommand extends TenantAwareCommand
{
    public function __construct(
        #[Autowire(service: 'doctrine.migrations.dependency_factory')]
        private readonly DependencyFactory $dependencyFactory,
        TenantManager $tenantManager,
        TenantConnectionSwitcher $tenantConnectionSwitcher,
        LoggerInterface $logger,
    ) {
        parent::__construct($tenantManager, $tenantConnectionSwitcher, $logger);
    }

    protected function executeTenantCommand(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $this->dependencyFactory->getMetadataStorage()->ensureInitialized();

        $targetVersion = $this->dependencyFactory
            ->getVersionAliasResolver()
            ->resolveVersionAlias('latest');

        $plan = $this->dependencyFactory
            ->getMigrationPlanCalculator()
            ->getPlanUntilVersion($targetVersion);

        if ($plan->count() === 0) {
            $io->writeln('No migrations to execute.');

            return Command::SUCCESS;
        }

        $migratorConfiguration = $this->dependencyFactory
            ->getConsoleInputMigratorConfigurationFactory()
            ->getMigratorConfiguration($input);

        $this->dependencyFactory->getMigrator()->migrate($plan, $migratorConfiguration);

        $io->writeln('Migrations executed.');

        return Command::SUCCESS;
    }
}
