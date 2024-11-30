<?php

namespace App\Core\Infrastructure\Service;

use App\Core\Infrastructure\Doctrine\DBAL\TenantConnection;
use Doctrine\Persistence\ConnectionRegistry;

readonly class TenantConnectionSwitcher
{
    public function __construct(
        private ConnectionRegistry $connectionRegistry,
    ) {
    }

    public function switchConnection(string $tenant): void
    {
        /** @var TenantConnection $connection */
        $connection = $this->connectionRegistry->getConnection();
        $connection->switchTenant($tenant);
    }
}
