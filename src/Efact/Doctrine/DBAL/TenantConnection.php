<?php

declare(strict_types=1);

namespace App\Efact\Doctrine\DBAL;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Exception;
use Nubit\Platform\Exception\ServiceException;
use Override;
use Symfony\Component\Security\Core\Exception\RuntimeException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * @phpstan-import-type Params from \Doctrine\DBAL\DriverManager
 */
class TenantConnection extends Connection
{
    protected bool $isConnected = false;

    protected bool $autoCommit = true;

    public function __construct(
        /** @var Params */
        protected array $tenantParams,
        Driver $driver,
        ?Configuration $config = null,
    ) {
        parent::__construct($this->tenantParams, $driver, $config);
    }

    /**
     * @throws ServiceException
     * @throws Exception
     * @throws \Doctrine\DBAL\Exception
     */
    public function switchTenant(string $tenantName): void
    {
        $tenant = $this->resolveTenantFromYaml($tenantName);
        $this->switchTenantParams($tenant);
    }

    /**
     * @param array<string, mixed> $tenant
     * @throws ServiceException
     * @throws Exception
     * @throws \Doctrine\DBAL\Exception
     */
    public function switchTenantParams(array $tenant): void
    {
        $connection = trim((string) ($tenant['connection'] ?? ''));
        if ($connection === '') {
            throw new ServiceException('Tenant connection is required');
        }

        if (
            $this->isConnected
            && $connection === ($this->tenantParams['dbname'] ?? null)
            && $connection === ($this->tenantParams['user'] ?? null)
        ) {
            return;
        }

        if ($this->isConnected) {
            $this->close();
        }

        $this->tenantParams['dbname'] = $connection;
        $this->tenantParams['user'] = $connection;
        $this->connect();
    }

    /**
     * @throws Exception
     * @throws \Doctrine\DBAL\Exception
     */
    #[Override]
    protected function connect(): Driver\Connection
    {
        if ($this->_conn !== null) {
            return $this->_conn;
        }

        $connection = $this->getDriver()->connect($this->tenantParams);
        $this->_conn = $connection;
        $this->isConnected = true;

        if (!$this->autoCommit) {
            $this->beginTransaction();
        }

        return $connection;
    }

    /**
     * @return Params
     */
    #[Override]
    public function getParams(): array
    {
        return $this->tenantParams;
    }

    #[Override]
    public function close(): void
    {
        parent::close();
        $this->isConnected = false;
    }

    private function resolveConfigPath(): string
    {
        $envPath = (string) ($_ENV['TENANTS_CONFIG_PATH'] ?? getenv('TENANTS_CONFIG_PATH') ?: '');
        if ('' !== $envPath) {
            return $this->normalizePath($envPath);
        }

        // Base project dir from src/Efact/Doctrine/DBAL => up 4 levels.
        return dirname(__DIR__, 4) . '/config/tenants.yaml';
    }

    private function normalizePath(string $path): string
    {
        if (str_starts_with($path, '/')) {
            return $path;
        }

        return dirname(__DIR__, 4) . '/' . ltrim($path, '/');
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveTenantFromYaml(string $tenantName): array
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

        if (!in_array($tenantName, array_column($tenants, 'name'), true)) {
            throw new ServiceException('Tenant not found');
        }

        $tenant = array_values(array_filter($tenants, fn(array $item): bool => $item['name'] === $tenantName))[0];

        return $tenant;
    }
}
