<?php

namespace App\Core\Service;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

readonly class TenantManager
{
    private string $tenantsConfigFilePath;

    public function __construct()
    {
        $this->tenantsConfigFilePath = dirname(__DIR__, 3) . '/config/tenants.yaml';
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
        $name = trim((string) ($tenant['name'] ?? ''));
        $connection = trim((string) ($tenant['connection'] ?? ''));

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

        foreach ($data as $key => $tenant) {
            if ($tenant['id'] === $id) {
                unset($data[$key]);
            }
        }

        $this->writeTenants(array_values($data));
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
        $ids = array_map(static fn (array $tenant): int => (int) ($tenant['id'] ?? 0), $tenants);

        return (int) (max($ids ?: [0]) + 1);
    }
}
