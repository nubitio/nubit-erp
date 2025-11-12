<?php

namespace App\Core\Service;

readonly class TenantManager
{
    private string $tenantsConfigFilePath;

    private string $tenantsConfigContent;

    public function __construct()
    {
        $this->tenantsConfigFilePath = dirname(__DIR__, 4) . '/config/tenants.json';

        if (!file_exists($this->tenantsConfigFilePath)) {
            file_put_contents($this->tenantsConfigFilePath, '[]');
        }

        $configContent = file_get_contents($this->tenantsConfigFilePath);

        if (false === $configContent) {
            throw new \RuntimeException('Could not read tenants config file');
        }

        $this->tenantsConfigContent = $configContent;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getTenants(): array
    {
        /** @var array<int, array<string, mixed>> $tenants */
        $tenants = json_decode($this->tenantsConfigContent, true);

        return $tenants;
    }

    /**
     * @param array<string, mixed> $tenant
     */
    public function addTenant(array $tenant): void
    {
        /** @var array<int, array<string, mixed>> $data */
        $data = json_decode($this->tenantsConfigContent, true);

        $data[] = [
            'id' => count($data) + 1,
            'name' => $tenant['name'],
            'connection' => $tenant['connection'],
        ];

        file_put_contents($this->tenantsConfigFilePath, json_encode($data));
    }

    public function deleteTenant(int $id): void
    {
        /** @var array<int, array<string, mixed>> $data */
        $data = json_decode($this->tenantsConfigContent, true);

        foreach ($data as $key => $tenant) {
            if ($tenant['id'] === $id) {
                unset($data[$key]);
            }
        }

        file_put_contents($this->tenantsConfigFilePath, json_encode($data));
    }
}
