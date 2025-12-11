<?php

declare(strict_types=1);

namespace App\Core\Service;

use App\Core\Doctrine\DBAL\TenantConnection;
use App\Core\Exception\ServiceException;
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
