<?php

declare(strict_types=1);

namespace App\ControlPlane\Tenant\Command;

use Doctrine\Migrations\DependencyFactory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:control-plane:migrate',
    description: 'Run Doctrine migrations for the control plane database.',
)]
class ControlPlaneMigrateCommand extends Command
{
    public function __construct(
        #[Autowire(service: 'doctrine.migrations.control_plane_dependency_factory')]
        private readonly DependencyFactory $dependencyFactory,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
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
