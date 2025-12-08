<?php

namespace App\Core\Service;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

readonly class TenantManager
{
    private const string ENV_CONFIG_PATH = 'TENANTS_CONFIG_PATH';

    private string $tenantsConfigFilePath;

    public function __construct()
    {
        $this->tenantsConfigFilePath = $this->resolveConfigPath();
        $this->ensureConfigFile();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getTenants(): array
    {
        return $this->readTenants();
    }

    /**
     * @param array<string, mixed> $tenant
     */
    public function addTenant(array $tenant): void
    {
        $name = trim((string)($tenant['name'] ?? ''));
        $connection = trim((string)($tenant['connection'] ?? ''));

        if ('' === $name || '' === $connection) {
            throw new \InvalidArgumentException('Tenant name and connection are required');
        }

        $data = $this->readTenants();

        foreach ($data as $existingTenant) {
            if (($existingTenant['name'] ?? '') === $name) {
                throw new \InvalidArgumentException(sprintf('Tenant "%s" already exists', $name));
            }
        }

        $data[] = [
            'id' => $this->nextId($data),
            'name' => $name,
            'connection' => $connection,
        ];

        $this->writeTenants($data);
    }

    public function deleteTenant(int $id): void
    {
        $data = $this->readTenants();

        $data = array_values(array_filter($data, static fn(array $tenant): bool => (int)($tenant['id'] ?? 0) !== $id));

        $this->writeTenants(array_values($data));
    }

    public function updateTenant(int $id, array $tenant): void
    {
        $name = trim((string)($tenant['name'] ?? ''));
        $connection = trim((string)($tenant['connection'] ?? ''));

        if ('' === $name || '' === $connection) {
            throw new \InvalidArgumentException('Tenant name and connection are required');
        }

        $data = $this->readTenants();
        $found = false;

        foreach ($data as &$existingTenant) {
            if ((int)($existingTenant['id'] ?? 0) === $id) {
                $existingTenant['name'] = $name;
                $existingTenant['connection'] = $connection;
                $found = true;

                continue;
            }

            if (($existingTenant['name'] ?? '') === $name) {
                throw new \InvalidArgumentException(sprintf('Tenant "%s" already exists', $name));
            }
        }
        unset($existingTenant);

        if (!$found) {
            throw new \InvalidArgumentException('Tenant not found');
        }

        $this->writeTenants(array_values($data));
    }

    public function getTenant(int $id): ?array
    {
        foreach ($this->readTenants() as $tenant) {
            if ((int)($tenant['id'] ?? 0) === $id) {
                return $tenant;
            }
        }

        return null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function readTenants(): array
    {
        $configContent = file_get_contents($this->tenantsConfigFilePath);

        if (false === $configContent) {
            throw new \RuntimeException('Could not read tenants config file');
        }

        if ('' === trim($configContent)) {
            return [];
        }

        try {
            /** @var array<int, array<string, mixed>>|null $tenants */
            $tenants = Yaml::parse($configContent);
        } catch (ParseException $exception) {
            throw new \RuntimeException('Could not parse tenants config file', 0, $exception);
        }

        if (!is_array($tenants)) {
            return [];
        }

        /** @var array<int, array<string, mixed>> $cleanTenants */
        $cleanTenants = array_values(array_filter($tenants, 'is_array'));

        return $cleanTenants;
    }

    private function ensureConfigFile(): void
    {
        $dir = dirname($this->tenantsConfigFilePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        if (!file_exists($this->tenantsConfigFilePath)) {
            $this->writeTenants([]);

            return;
        }

        if ('' === trim((string)file_get_contents($this->tenantsConfigFilePath))) {
            $this->writeTenants([]);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $tenants
     */
    private function writeTenants(array $tenants): void
    {
        $yaml = Yaml::dump($tenants, 2, 4);
        file_put_contents($this->tenantsConfigFilePath, $yaml, \LOCK_EX);
    }

    /**
     * @param array<int, array<string, mixed>> $tenants
     */
    private function nextId(array $tenants): int
    {
        $ids = array_map(static fn(array $tenant): int => (int)($tenant['id'] ?? 0), $tenants);

        return (int)(max($ids ?: [0]) + 1);
    }

    private function resolveConfigPath(): string
    {
        $envPath = (string)($_ENV[self::ENV_CONFIG_PATH] ?? getenv(self::ENV_CONFIG_PATH) ?: '');
        if ('' !== $envPath) {
            return $this->normalizePath($envPath);
        }

        // Base project dir from src/Core/Service => up 3 levels.
        return dirname(__DIR__, 3) . '/config/tenants.yaml';
    }

    private function normalizePath(string $path): string
    {
        if (str_starts_with($path, '/')) {
            return $path;
        }

        return dirname(__DIR__, 3) . '/' . ltrim($path, '/');
    }
}
