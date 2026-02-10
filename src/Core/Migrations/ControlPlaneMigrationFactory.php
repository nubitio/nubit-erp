<?php

declare(strict_types=1);

namespace App\Core\Migrations;

use Doctrine\Migrations\Configuration\Configuration;
use Doctrine\Migrations\Configuration\Connection\ConnectionRegistryConnection;
use Doctrine\Migrations\Configuration\Migration\ExistingConfiguration;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Persistence\ConnectionRegistry;
use Psr\Log\LoggerInterface;

final class ControlPlaneMigrationFactory
{
    public function __construct(
        private readonly string $projectDir,
    ) {
    }

    public function createDependencyFactory(
        ConnectionRegistry $registry,
        ?LoggerInterface $logger = null,
    ): DependencyFactory {
        $configuration = new Configuration();
        $configuration->setConnectionName('control_plane');
        $configuration->addMigrationsDirectory(
            'DoctrineMigrationsControlPlane',
            $this->projectDir . '/migrations_control_plane',
        );

        $configurationLoader = new ExistingConfiguration($configuration);
        $connectionLoader = ConnectionRegistryConnection::withSimpleDefault($registry, 'control_plane');

        return DependencyFactory::fromConnection($configurationLoader, $connectionLoader, $logger);
    }
}
