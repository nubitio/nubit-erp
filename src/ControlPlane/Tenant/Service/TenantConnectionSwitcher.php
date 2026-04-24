<?php

declare(strict_types=1);

namespace App\ControlPlane\Tenant\Service;

use App\Core\Doctrine\DBAL\TenantConnection;
use App\Core\Exception\ServiceException;
use App\Core\Platform\Tenant\TenantConnectionSwitcherInterface;
use Doctrine\DBAL\Driver\Exception;
use Doctrine\Persistence\ConnectionRegistry;

readonly class TenantConnectionSwitcher implements TenantConnectionSwitcherInterface
{
    public function __construct(
        private ConnectionRegistry $connectionRegistry,
        private TenantManager $tenantManager,
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
        $tenantData = $this->tenantManager->getTenantByName($tenant);
        if ($tenantData === null) {
            throw new ServiceException('Tenant not found');
        }

        $connection->switchTenantParams($tenantData);
    }
}
