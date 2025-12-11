<?php

namespace App\Core\Doctrine\DBAL;

use App\Core\Exception\ServiceException;
use Doctrine\Common\EventManager;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Exception;
use Symfony\Component\Security\Core\Exception\RuntimeException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class TenantConnection extends Connection
{
    protected bool $isConnected = false;

    protected bool $autoCommit = true;

    public function __construct(
        protected array $tenantParams,
        Driver $driver,
        ?Configuration $config = null,
        ?EventManager $eventManager = null
    ) {
        parent::__construct($this->tenantParams, $driver, $config, $eventManager);
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
        $tenantsConfigFilePath = $this->resolveConfigPath();
        if (file_exists($tenantsConfigFilePath)) {
            $configContent = file_get_contents($tenantsConfigFilePath);

            if (false === $configContent) {
                throw new RuntimeException('Error al leer el archivo de configuración de los tenants');
            }

            try {
                /** @var array<string, array<string, mixed>>|null $tenants */
                $tenants = Yaml::parse($configContent) ?? [];
            } catch (ParseException $exception) {
                throw new RuntimeException('Error al parsear el archivo de tenants', 0, $exception);
            }

            if (!is_array($tenants)) {
                $tenants = [];
            }
        }

        if (!in_array($tenantName, array_column($tenants, 'name'))) {
            throw new ServiceException('Tenant not found');
        }

        $tenant = array_values(array_filter($tenants, fn ($item): bool => $item['name'] === $tenantName))[0];

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
    #[\Override]
    public function connect(): Driver\Connection
    {
        if ($this->isConnected) {
            return $this->_conn;
        }

        $this->_conn = $this->getDriver()->connect($this->tenantParams);
        $this->isConnected = true;

        if (!$this->autoCommit) {
            $this->beginTransaction();
        }

        return $this->_conn;
    }

    #[\Override]
    public function getParams(): array
    {
        return $this->tenantParams;
    }

    #[\Override]
    public function close(): void
    {
        $this->isConnected = false;
        $this->_conn = null;
    }

    private function resolveConfigPath(): string
    {
        $envPath = (string) ($_ENV['TENANTS_CONFIG_PATH'] ?? getenv('TENANTS_CONFIG_PATH') ?: '');
        if ('' !== $envPath) {
            return $this->normalizePath($envPath);
        }

        // Base project dir from src/Core/Doctrine/DBAL => up 4 levels.
        return dirname(__DIR__, 4) . '/config/tenants.yaml';
    }

    private function normalizePath(string $path): string
    {
        if (str_starts_with($path, '/')) {
            return $path;
        }

        return dirname(__DIR__, 4) . '/' . ltrim($path, '/');
    }
}
