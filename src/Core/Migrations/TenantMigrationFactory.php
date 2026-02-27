<?php

declare(strict_types=1);

namespace App\Core\Migrations;

use Doctrine\Migrations\Configuration\Configuration;
use Doctrine\Migrations\Configuration\Connection\ConnectionRegistryConnection;
use Doctrine\Migrations\Configuration\Migration\ExistingConfiguration;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Persistence\ConnectionRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class TenantMigrationFactory
{
    public function __construct(
        #[Autowire(param: 'kernel.project_dir')]
        private string $projectDir,
    ) {
    }

    public function createDependencyFactory(
        ConnectionRegistry $registry,
        ?LoggerInterface $logger = null,
    ): DependencyFactory {
        $configuration = new Configuration();
        $configuration->setConnectionName('tenant');
        $configuration->addMigrationsDirectory(
            'DoctrineMigrations',
            $this->projectDir . '/migrations',
        );

        $configurationLoader = new ExistingConfiguration($configuration);
        $connectionLoader = ConnectionRegistryConnection::withSimpleDefault($registry, 'tenant');

        return DependencyFactory::fromConnection($configurationLoader, $connectionLoader, $logger);
    }
}
