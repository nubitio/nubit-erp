<?php

declare(strict_types=1);

namespace App\ControlPlane\Tenant\Service;

use App\ControlPlane\Tenant\Application\TenantService;
use App\Efact\Doctrine\DBAL\TenantConnection;
use Doctrine\DBAL\Driver\Exception;
use Doctrine\Persistence\ConnectionRegistry;
use Nubit\Platform\Exception\ServiceException;
use Nubit\Platform\Tenant\Contract\TenantConnectionSwitcherInterface;

readonly class TenantConnectionSwitcher implements TenantConnectionSwitcherInterface
{
    public function __construct(
        private ConnectionRegistry $connectionRegistry,
        private TenantService $tenantService,
    ) {}

    /**
     * @throws ServiceException
     * @throws Exception
     * @throws \Doctrine\DBAL\Exception
     */
    public function switchConnection(string $tenant): void
    {
        /** @var TenantConnection $connection */
        $connection = $this->connectionRegistry->getConnection();
        $tenantData = $this->tenantService->getTenantByName($tenant);
        if ($tenantData === null) {
            throw new ServiceException('Tenant not found');
        }

        $connection->switchTenantParams($tenantData);
    }
}
