<?php

declare(strict_types=1);

namespace App\Core\Service;

use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

readonly class TenantManager
{
    public function __construct(
        #[Autowire('%env(TENANTS_CONFIG_PATH)%')]
        private string $tenantsConfigFilePath,
    ) {
        if ('' === trim($this->tenantsConfigFilePath)) {
            throw new RuntimeException('Tenants config path is required');
        }

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
            throw new InvalidArgumentException('Tenant name and connection are required');
        }

        $data = $this->readTenants();

        foreach ($data as $existingTenant) {
            if (($existingTenant['name'] ?? '') === $name) {
                throw new InvalidArgumentException(sprintf('Tenant "%s" already exists', $name));
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

        $this->writeTenants($data);
    }

    public function updateTenant(int $id, array $tenant): void
    {
        $name = trim((string)($tenant['name'] ?? ''));
        $connection = trim((string)($tenant['connection'] ?? ''));

        if ('' === $name || '' === $connection) {
            throw new InvalidArgumentException('Tenant name and connection are required');
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
                throw new InvalidArgumentException(sprintf('Tenant "%s" already exists', $name));
            }
        }

        unset($existingTenant);

        if (!$found) {
            throw new InvalidArgumentException('Tenant not found');
        }

        $this->writeTenants(array_values($data));
    }

    public function getTenant(int $id): ?array
    {
        return array_find($this->readTenants(), fn($tenant) => (int)($tenant['id'] ?? 0) === $id);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function readTenants(): array
    {
        $configContent = file_get_contents($this->tenantsConfigFilePath);

        if (false === $configContent) {
            throw new RuntimeException('Could not read tenants config file');
        }

        if ('' === trim($configContent)) {
            return [];
        }

        try {
            /** @var array<int, array<string, mixed>>|null $tenants */
            $tenants = Yaml::parse($configContent);
        } catch (ParseException $parseException) {
            throw new RuntimeException('Could not parse tenants config file', 0, $parseException);
        }

        if (!is_array($tenants)) {
            return [];
        }

        /** @var array<int, array<string, mixed>> $cleanTenants */
        $cleanTenants = array_values(array_filter($tenants, is_array(...)));

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
}
