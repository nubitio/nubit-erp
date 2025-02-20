<?php

namespace App\Core\Infrastructure\Service;

use App\Core\Domain\Exception\ServiceException;
use App\Core\Infrastructure\Doctrine\DBAL\TenantConnection;
use Doctrine\DBAL\Driver\Exception;
use Doctrine\Persistence\ConnectionRegistry;

readonly class TenantConnectionSwitcher
{
    public function __construct(
        private ConnectionRegistry $connectionRegistry,
    ) {
    }

    /**
     * @throws ServiceException
     * @throws Exception
     * @throws \Doctrine\DBAL\Exception
     */
    public function switchConnection(string $tenant): void
    {
        /** @var TenantConnection $connection */
        $connection = $this->connectionRegistry->getConnection();
        $connection->switchTenant($tenant);
    }
}
