<?php

declare(strict_types=1);

namespace App\ControlPlane\Tenant\Infrastructure\Persistence;

use App\ControlPlane\Tenant\Domain\Model\TenantSnapshot;
use App\ControlPlane\Tenant\Domain\Model\TenantStatus;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Dev-only YAML-backed tenant store.
 */
final class YamlTenantStore
{
    public function __construct(
        private readonly string $configFilePath,
        private readonly bool $bootstrapFile = false,
    ) {
        if ('' === trim($this->configFilePath)) {
            throw new RuntimeException('Tenants config path is required when using YAML tenants source');
        }

        if ($this->bootstrapFile) {
            $this->ensureConfigFile();
        }
    }

    /**
     * @return list<TenantSnapshot>
     */
    public function listAll(): array
    {
        return array_map(static fn(array $row): TenantSnapshot => TenantSnapshot::fromArray($row), $this->readRaw());
    }

    public function findById(int $id): ?TenantSnapshot
    {
        foreach ($this->readRaw() as $row) {
            if ((int) ($row['id'] ?? 0) === $id) {
                return TenantSnapshot::fromArray($row);
            }
        }

        return null;
    }

    public function findByName(string $name): ?TenantSnapshot
    {
        foreach ($this->readRaw() as $row) {
            if (($row['name'] ?? '') === $name) {
                return TenantSnapshot::fromArray($row);
            }
        }

        return null;
    }

    public function create(string $name, string $connection): void
    {
        $data = $this->readRaw();

        foreach ($data as $existing) {
            if (($existing['name'] ?? '') === $name) {
                throw new InvalidArgumentException(sprintf('Tenant "%s" already exists', $name));
            }
        }

        $data[] = [
            'id' => $this->nextId($data),
            'name' => $name,
            'connection' => $connection,
            'status' => TenantStatus::Active->value,
        ];

        $this->writeRaw($data);
    }

    public function update(int $id, string $name, string $connection): void
    {
        $data = $this->readRaw();
        $found = false;

        foreach ($data as &$existing) {
            if ((int) ($existing['id'] ?? 0) === $id) {
                $existing['name'] = $name;
                $existing['connection'] = $connection;
                $found = true;

                continue;
            }

            if (($existing['name'] ?? '') === $name) {
                throw new InvalidArgumentException(sprintf('Tenant "%s" already exists', $name));
            }
        }

        unset($existing);

        if (!$found) {
            throw new InvalidArgumentException('Tenant not found');
        }

        $this->writeRaw(array_values($data));
    }

    public function delete(int $id): void
    {
        $data = array_values(array_filter(
            $this->readRaw(),
            static fn(array $tenant): bool => (int) ($tenant['id'] ?? 0) !== $id,
        ));

        $this->writeRaw($data);
    }

    public function isAvailable(): bool
    {
        return '' !== trim($this->configFilePath) && file_exists($this->configFilePath);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function readRaw(): array
    {
        $configContent = file_get_contents($this->configFilePath);

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

        /** @var list<array<string, mixed>> $clean */
        $clean = array_values(array_filter($tenants, is_array(...)));

        return $clean;
    }

    /**
     * @param list<array<string, mixed>> $tenants
     */
    private function writeRaw(array $tenants): void
    {
        file_put_contents($this->configFilePath, Yaml::dump($tenants, 2, 4), \LOCK_EX);
    }

    private function ensureConfigFile(): void
    {
        $dir = dirname($this->configFilePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        if (!file_exists($this->configFilePath) || '' === trim((string) file_get_contents($this->configFilePath))) {
            $this->writeRaw([]);
        }
    }

    /**
     * @param list<array<string, mixed>> $tenants
     */
    private function nextId(array $tenants): int
    {
        $ids = array_map(static fn(array $tenant): int => (int) ($tenant['id'] ?? 0), $tenants);

        return (int) (max($ids ?: [0]) + 1);
    }
}
