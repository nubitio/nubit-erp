<?php

namespace App\Core\Infrastructure\Doctrine\DBAL;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;

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

    public function switchTenant(string $tenantName): void
    {
        $tenants = json_decode(file_get_contents(dirname(__DIR__, 5) . '/config/tenants.json'), true);

        if (!in_array($tenantName, array_column($tenants, 'name'))) {
            throw new \Exception('Tenant not found');
        }

        $tenant = array_values(array_filter($tenants, fn ($item) => $item['name'] === $tenantName))[0];

        if ($this->isConnected) {
            $this->close();
        }

        $this->tenantParams['dbname'] = $tenant['connection'];
        $this->tenantParams['user'] = $tenant['connection'];
        $this->connect();
    }

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

    public function getParams(): array
    {
        return $this->tenantParams;
    }

    public function close()
    {
        $this->isConnected = false;
        $this->_conn = null;
    }
}