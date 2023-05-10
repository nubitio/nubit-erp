<?php

namespace App\Core\Infrastructure\Doctrine\DBAL;

use App\Core\Domain\Exception\ServiceException;
use Doctrine\Common\EventManager;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Exception;
use Symfony\Component\Security\Core\Exception\RuntimeException;

class TenantConnection extends Connection
{
    protected array $tenantParams;
    protected bool $isConnected = false;
    protected bool $autoCommit = true;

    public function __construct(array $params, Driver $driver, ?Configuration $config = null, ?EventManager $eventManager = null)
    {
        $this->tenantParams = $params;
        parent::__construct($params, $driver, $config, $eventManager);
    }

    /**
     * @throws ServiceException
     * @throws Exception
     * @throws \Doctrine\DBAL\Exception
     */
    public function switchTenant(string $tenantName): void
    {
        /** @var array<string, array<string, mixed>> $tenants */
        $tenants = [];
        $tenantsConfigFilePath = dirname(__DIR__, 5) . '/config/tenants.json';
        if (file_exists($tenantsConfigFilePath)) {
            $configContent = file_get_contents($tenantsConfigFilePath);

            if ($configContent === false) {
                throw new RuntimeException('Error al leer el archivo de configuración de los tenants');
            }

            /** @var array<string, array<string, mixed>> $tenants */
            $tenants = json_decode($configContent, true);
        }

        if (!in_array($tenantName, array_column($tenants, 'name'))) {
            throw new ServiceException('Tenant not found');
        }

        $tenant = array_values(array_filter($tenants, fn($item) => $item['name'] === $tenantName))[0];

        if ($this->isConnected) {
            $this->close();
        }

        $this->tenantParams['dbname'] = $tenant['connection'];
        $this->tenantParams['user'] = $tenant['connection'];
        $this->connect();
    }

    /**
     * @throws Exception
     * @throws \Doctrine\DBAL\Exception
     */
    public function connect(): bool
    {
        if ($this->isConnected) {
            return false;
        }

        $this->_conn = $this->_driver->connect($this->tenantParams);
        $this->isConnected = true;

        if ($this->autoCommit !== true) {
            $this->beginTransaction();
        }

        return true;
    }

    /** @inheritdoc */
    public function getParams(): array
    {
        return $this->tenantParams;
    }

    public function close(): void
    {
        $this->isConnected = false;
        $this->_conn = null;
    }
}