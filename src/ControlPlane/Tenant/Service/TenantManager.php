<?php

declare(strict_types=1);

namespace App\ControlPlane\Tenant\Service;

use App\Core\Platform\Tenant\TenantRegistryInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\Persistence\ConnectionRegistry;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

readonly class TenantManager implements TenantRegistryInterface
{
    private const int CACHE_TTL = 300;

    public function __construct(
        #[Autowire('%env(TENANTS_CONFIG_PATH)%')]
        private string $tenantsConfigFilePath,
        #[Autowire('%env(default::TENANTS_SOURCE)%')]
        private string $tenantsSource,
        private ConnectionRegistry $connectionRegistry,
        private LoggerInterface $logger,
        private CacheInterface $cache,
    ) {
        if ($this->shouldUseYaml() && '' === trim($this->tenantsConfigFilePath)) {
            throw new RuntimeException('Tenants config path is required when using YAML tenants source');
        }

        if ($this->shouldUseYaml()) {
            $this->ensureConfigFile();
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getTenants(): array
    {
        return $this->readTenants();
    }

    /**
     * @return array<int, array<string, mixed>>
     * @throws DbalException
     */
    public function getDeletedTenants(): array
    {
        if (!$this->shouldUseDatabase()) {
            return [];
        }

        $connection = $this->getControlPlaneConnection();
        if (!$this->isControlPlaneReady($connection)) {
            return [];
        }

        $sql = 'SELECT t.id, t.name, t.connection, t.plan, t.deleted_at,
                       d.domain AS primary_domain
                FROM tenants t
                LEFT JOIN tenant_domains d ON d.tenant_id = t.id AND d.is_primary = 1
                WHERE t.status = \'deleted\'
                ORDER BY t.deleted_at DESC';

        return $connection->fetchAllAssociative($sql);
    }

    /**
     * @param array<string, mixed> $tenant
     *
     * @throws DbalException
     */
    public function addTenant(array $tenant): void
    {
        $name = trim((string)($tenant['name'] ?? ''));
        $connection = trim((string)($tenant['connection'] ?? ''));

        if ('' === $name || '' === $connection) {
            throw new InvalidArgumentException('Tenant name and connection are required');
        }

        if ($this->shouldUseDatabase()) {
            $this->addTenantInDatabase($name, $connection);
            $this->invalidateTenantCache($name);

            return;
        }

        $this->addTenantInYaml($name, $connection);
    }

    public function deleteTenant(int $id): void
    {
        $tenant = $this->getTenant($id);

        if ($this->shouldUseDatabase()) {
            $this->deleteTenantInDatabase($id);
        } else {
            $this->deleteTenantInYaml($id);
        }

        if ($tenant !== null) {
            $this->invalidateTenantCache((string)($tenant['name'] ?? ''));
            $this->invalidateTenantDomainCaches($id);
        }
    }

    /**
     * @param array<string, mixed> $tenant
     */
    public function updateTenant(int $id, array $tenant): void
    {
        $name = trim((string)($tenant['name'] ?? ''));
        $connection = trim((string)($tenant['connection'] ?? ''));

        if ('' === $name || '' === $connection) {
            throw new InvalidArgumentException('Tenant name and connection are required');
        }

        $oldTenant = $this->getTenant($id);

        if ($this->shouldUseDatabase()) {
            $this->updateTenantInDatabase($id, $name, $connection);
        } else {
            $this->updateTenantInYaml($id, $name, $connection);
        }

        if ($oldTenant !== null) {
            $this->invalidateTenantCache((string)($oldTenant['name'] ?? ''));
        }
        $this->invalidateTenantCache($name);
        $this->invalidateTenantDomainCaches($id);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getTenant(int $id): ?array
    {
        if ($this->shouldUseDatabase()) {
            return $this->getTenantFromDatabase($id);
        }

        return array_find($this->readTenantsFromYaml(), fn ($tenant) => (int)($tenant['id'] ?? 0) === $id);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getTenantByName(string $name): ?array
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        $cacheKey = 'tenant_name_' . hash('xxh3', $name);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($name): ?array {
            $item->expiresAfter(self::CACHE_TTL);

            if ($this->shouldUseDatabase()) {
                return $this->getTenantByNameFromDatabase($name);
            }

            return array_find($this->readTenantsFromYaml(), fn ($tenant) => ($tenant['name'] ?? '') === $name);
        });
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getTenantByDomain(string $domain): ?array
    {
        $domain = trim($domain);
        if ($domain === '') {
            return null;
        }

        $cacheKey = 'tenant_domain_' . hash('xxh3', $domain);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($domain): ?array {
            $item->expiresAfter(self::CACHE_TTL);

            if ($this->shouldUseDatabase()) {
                return $this->getTenantByDomainFromDatabase($domain);
            }

            return null;
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     * @throws DbalException
     */
    public function getTenantDomains(int $tenantId): array
    {
        $conn = $this->getControlPlaneConnection();
        $this->requireControlPlaneReady('list tenant domains');

        /** @var array<int, array<string, mixed>> $rows */
        $rows = $conn->fetchAllAssociative(
            'SELECT id, domain, is_primary FROM tenant_domains WHERE tenant_id = ? ORDER BY is_primary DESC, id ASC',
            [$tenantId],
        );

        return $rows;
    }

    /**
     * @throws DbalException
     */
    public function addTenantDomain(int $tenantId, string $domain, bool $isPrimary = false): void
    {
        $domain = strtolower(trim($domain));
        if ($domain === '') {
            throw new InvalidArgumentException('Domain is required');
        }

        $conn = $this->getControlPlaneConnection();
        $this->requireControlPlaneReady('create tenant domain');

        $existing = $conn->fetchOne('SELECT id FROM tenant_domains WHERE domain = ?', [$domain]);
        if ($existing !== false) {
            throw new InvalidArgumentException(sprintf('Domain "%s" already exists', $domain));
        }

        if ($isPrimary) {
            $conn->update('tenant_domains', ['is_primary' => 0], ['tenant_id' => $tenantId]);
        }

        $conn->insert('tenant_domains', [
            'tenant_id' => $tenantId,
            'domain' => $domain,
            'is_primary' => $isPrimary ? 1 : 0,
        ]);

        $this->cache->delete('tenant_domain_' . hash('xxh3', $domain));
    }

    /**
     * @throws DbalException
     */
    public function deleteTenantDomain(int $domainId): void
    {
        $conn = $this->getControlPlaneConnection();
        $this->requireControlPlaneReady('delete tenant domain');

        $row = $conn->fetchAssociative('SELECT domain FROM tenant_domains WHERE id = ?', [$domainId]);
        $conn->delete('tenant_domains', ['id' => $domainId]);

        if ($row !== false) {
            $this->cache->delete('tenant_domain_' . hash('xxh3', (string)$row['domain']));
        }
    }

    /**
     * @throws DbalException
     */
    public function setPrimaryTenantDomain(int $tenantId, int $domainId): void
    {
        $conn = $this->getControlPlaneConnection();
        $this->requireControlPlaneReady('set primary tenant domain');

        $conn->update('tenant_domains', ['is_primary' => 0], ['tenant_id' => $tenantId]);
        $conn->update('tenant_domains', ['is_primary' => 1], ['id' => $domainId, 'tenant_id' => $tenantId]);

        $this->invalidateTenantDomainCaches($tenantId);
    }

    /**
     * @throws DbalException
     */
    public function getPrimaryDomain(int $tenantId): ?string
    {
        $conn = $this->getControlPlaneConnection();
        $this->requireControlPlaneReady('get primary tenant domain');

        $domain = $conn->fetchOne(
            'SELECT domain FROM tenant_domains WHERE tenant_id = ? AND is_primary = 1 ORDER BY id ASC LIMIT 1',
            [$tenantId],
        );

        if ($domain === false) {
            return null;
        }

        return (string)$domain;
    }

    /**
     * @return array<int, array<string, mixed>>
     * @throws DbalException
     */
    private function readTenants(): array
    {
        if ($this->shouldUseDatabase()) {
            return $this->readTenantsFromDatabase();
        }

        return $this->readTenantsFromYaml();
    }

    /**
     * @return array<int, array<string, mixed>>
     * @throws DbalException
     */
    private function readTenantsFromDatabase(): array
    {
        $connection = $this->getControlPlaneConnection();
        if (!$this->isControlPlaneReady($connection)) {
            return $this->fallbackToYamlOrFail('read tenants');
        }

        $sql = 'SELECT t.id, t.name, t.connection, t.status, t.plan,'
            . ' t.backup_frequency, t.backup_retention_days,'
            . ' d.domain AS primary_domain'
            . ' FROM tenants t'
            . ' LEFT JOIN tenant_domains d ON d.tenant_id = t.id AND d.is_primary = 1'
            . " WHERE t.status != 'deleted'"
            . ' ORDER BY t.id ASC';

        /** @var array<int, array<string, mixed>> $rows */
        $rows = $connection->fetchAllAssociative($sql);

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function readTenantsFromYaml(): array
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

    /**
     * @throws DbalException
     */
    private function addTenantInDatabase(string $name, string $connection): void
    {
        $conn = $this->getControlPlaneConnection();
        if (!$this->isControlPlaneReady($conn)) {
            $this->fallbackToYamlOrFail('create tenant');
            $this->addTenantInYaml($name, $connection);

            return;
        }

        $existing = $conn->fetchOne('SELECT id FROM tenants WHERE name = ?', [$name]);
        if ($existing !== false) {
            throw new InvalidArgumentException(sprintf('Tenant "%s" already exists', $name));
        }

        $now = new \DateTimeImmutable('now')->format('Y-m-d H:i:s');
        $conn->insert('tenants', [
            'name' => $name,
            'connection' => $connection,
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * @throws DbalException
     */
    private function updateTenantInDatabase(int $id, string $name, string $connection): void
    {
        $conn = $this->getControlPlaneConnection();
        if (!$this->isControlPlaneReady($conn)) {
            $this->fallbackToYamlOrFail('update tenant');
            $this->updateTenantInYaml($id, $name, $connection);

            return;
        }

        $existing = $conn->fetchOne('SELECT id FROM tenants WHERE name = ? AND id <> ?', [$name, $id]);
        if ($existing !== false) {
            throw new InvalidArgumentException(sprintf('Tenant "%s" already exists', $name));
        }

        $now = new \DateTimeImmutable('now')->format('Y-m-d H:i:s');
        $affected = $conn->update('tenants', [
            'name' => $name,
            'connection' => $connection,
            'updated_at' => $now,
        ], ['id' => $id]);

        if ($affected === 0) {
            throw new InvalidArgumentException('Tenant not found');
        }
    }

    /**
     * @throws DbalException
     */
    private function deleteTenantInDatabase(int $id): void
    {
        $conn = $this->getControlPlaneConnection();
        if (!$this->isControlPlaneReady($conn)) {
            $this->fallbackToYamlOrFail('delete tenant');
            $this->deleteTenantInYaml($id);

            return;
        }

        $now = new \DateTimeImmutable('now')->format('Y-m-d H:i:s');
        $conn->update('tenants', [
            'status' => 'deleted',
            'deleted_at' => $now,
            'updated_at' => $now,
        ], ['id' => $id]);
    }

    public function purgeTenant(int $id): void
    {
        $conn = $this->getControlPlaneConnection();
        $this->requireControlPlaneReady('purge tenant');

        $tenant = $conn->fetchAssociative('SELECT id, status FROM tenants WHERE id = ?', [$id]);
        if ($tenant === false) {
            throw new InvalidArgumentException('Tenant not found');
        }

        if (($tenant['status'] ?? '') !== 'deleted') {
            throw new InvalidArgumentException('Only deleted tenants can be purged. Soft-delete it first.');
        }

        $conn->delete('tenants', ['id' => $id]);
    }

    /**
     * @throws DbalException
     */
    public function suspendTenant(int $id): void
    {
        $conn = $this->getControlPlaneConnection();
        $this->requireControlPlaneReady('suspend tenant');

        $now = new \DateTimeImmutable('now')->format('Y-m-d H:i:s');
        $affected = $conn->update('tenants', [
            'status' => 'suspended',
            'updated_at' => $now,
        ], ['id' => $id, 'status' => 'active']);

        if ($affected === 0) {
            throw new InvalidArgumentException('Active tenant not found');
        }

        $tenant = $this->getTenant($id);
        if ($tenant !== null) {
            $this->invalidateTenantCache((string)($tenant['name'] ?? ''));
            $this->invalidateTenantDomainCaches($id);
        }
    }

    /**
     * @throws DbalException
     */
    public function unsuspendTenant(int $id): void
    {
        $conn = $this->getControlPlaneConnection();
        $this->requireControlPlaneReady('unsuspend tenant');

        $now = new \DateTimeImmutable('now')->format('Y-m-d H:i:s');
        $affected = $conn->update('tenants', [
            'status' => 'active',
            'updated_at' => $now,
        ], ['id' => $id, 'status' => 'suspended']);

        if ($affected === 0) {
            throw new InvalidArgumentException('Suspended tenant not found');
        }

        $tenant = $this->getTenant($id);
        if ($tenant !== null) {
            $this->invalidateTenantCache((string)($tenant['name'] ?? ''));
            $this->invalidateTenantDomainCaches($id);
        }
    }

    public function restoreTenant(int $id): void
    {
        $conn = $this->getControlPlaneConnection();
        $this->requireControlPlaneReady('restore tenant');

        $now = new \DateTimeImmutable('now')->format('Y-m-d H:i:s');
        $affected = $conn->update('tenants', [
            'status' => 'active',
            'deleted_at' => null,
            'updated_at' => $now,
        ], ['id' => $id, 'status' => 'deleted']);

        if ($affected === 0) {
            throw new InvalidArgumentException('Deleted tenant not found');
        }

        $this->invalidateTenantDomainCaches($id);
    }

    /**
     * @throws DbalException
     */
    public function updateBackupFrequency(int $id, int $frequency): void
    {
        if ($frequency < 0) {
            throw new InvalidArgumentException('Backup frequency must be 0 or greater');
        }

        $conn = $this->getControlPlaneConnection();
        $this->requireControlPlaneReady('update backup frequency');

        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $affected = $conn->update('tenants', [
            'backup_frequency' => $frequency,
            'updated_at' => $now,
        ], ['id' => $id]);

        if ($affected === 0) {
            $exists = $conn->fetchOne('SELECT id FROM tenants WHERE id = ?', [$id]);
            if ($exists === false) {
                throw new InvalidArgumentException('Tenant not found');
            }
        }

        $tenant = $this->getTenant($id);
        if ($tenant !== null) {
            $this->invalidateTenantCache((string) ($tenant['name'] ?? ''));
        }
    }

    /**
     * @throws DbalException
     */
    public function updateBackupRetention(int $id, int $days): void
    {
        if ($days < 0) {
            throw new InvalidArgumentException('Backup retention days must be 0 or greater');
        }

        $conn = $this->getControlPlaneConnection();
        $this->requireControlPlaneReady('update backup retention');

        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $affected = $conn->update('tenants', [
            'backup_retention_days' => $days,
            'updated_at' => $now,
        ], ['id' => $id]);

        if ($affected === 0) {
            $exists = $conn->fetchOne('SELECT id FROM tenants WHERE id = ?', [$id]);
            if ($exists === false) {
                throw new InvalidArgumentException('Tenant not found');
            }
        }

        $tenant = $this->getTenant($id);
        if ($tenant !== null) {
            $this->invalidateTenantCache((string) ($tenant['name'] ?? ''));
        }
    }

    /**
     * @throws DbalException
     */
    public function changePlan(int $id, string $plan): void
    {
        $plan = strtolower(trim($plan));
        if ($plan === '') {
            throw new InvalidArgumentException('Plan is required');
        }

        $conn = $this->getControlPlaneConnection();
        $this->requireControlPlaneReady('change tenant plan');

        $planExists = $conn->fetchOne('SELECT name FROM plans WHERE name = ?', [$plan]);
        if ($planExists === false) {
            throw new InvalidArgumentException(sprintf('Invalid plan "%s"', $plan));
        }

        $now = new \DateTimeImmutable('now')->format('Y-m-d H:i:s');
        $affected = $conn->update('tenants', [
            'plan' => $plan,
            'updated_at' => $now,
        ], ['id' => $id]);

        if ($affected === 0) {
            $exists = $conn->fetchOne('SELECT id FROM tenants WHERE id = ?', [$id]);
            if ($exists === false) {
                throw new InvalidArgumentException('Tenant not found');
            }
        }

        $tenant = $this->getTenant($id);
        if ($tenant !== null) {
            $this->invalidateTenantCache((string)($tenant['name'] ?? ''));
            $this->invalidateTenantDomainCaches($id);
        }
    }

    /**
     * @return array<string, mixed>|null
     * @throws DbalException
     */
    private function getTenantFromDatabase(int $id): ?array
    {
        $conn = $this->getControlPlaneConnection();
        if (!$this->isControlPlaneReady($conn)) {
            $this->fallbackToYamlOrFail('get tenant');

            return array_find($this->readTenantsFromYaml(), fn ($tenant) => (int)($tenant['id'] ?? 0) === $id);
        }

        /** @var array<string, mixed>|false $row */
        $row = $conn->fetchAssociative(
            'SELECT id, name, connection, status, plan, backup_frequency, backup_retention_days'
            . ' FROM tenants WHERE id = ?',
            [$id],
        );

        if ($row === false) {
            return null;
        }

        return $row;
    }

    /**
     * @return array<string, mixed>|null
     * @throws DbalException
     */
    private function getTenantByNameFromDatabase(string $name): ?array
    {
        $conn = $this->getControlPlaneConnection();
        if (!$this->isControlPlaneReady($conn)) {
            $this->fallbackToYamlOrFail('get tenant by name');

            return array_find($this->readTenantsFromYaml(), fn ($tenant) => ($tenant['name'] ?? '') === $name);
        }

        /** @var array<string, mixed>|false $row */
        $row = $conn->fetchAssociative(
            'SELECT id, name, connection, status, plan, backup_frequency, backup_retention_days'
            . ' FROM tenants WHERE name = ? AND status != ?',
            [$name, 'deleted'],
        );

        if ($row === false) {
            return null;
        }

        return $row;
    }

    /**
     * @return array<string, mixed>|null
     * @throws DbalException
     */
    private function getTenantByDomainFromDatabase(string $domain): ?array
    {
        $conn = $this->getControlPlaneConnection();
        if (!$this->isControlPlaneReady($conn)) {
            $this->fallbackToYamlOrFail('get tenant by domain');

            return null;
        }

        /** @var array<string, mixed>|false $row */
        $row = $conn->fetchAssociative(
            'SELECT t.id, t.name, t.connection, t.status, t.plan,'
            . ' t.backup_frequency, t.backup_retention_days'
            . ' FROM tenant_domains d '
            . 'INNER JOIN tenants t ON t.id = d.tenant_id '
            . 'WHERE d.domain = ? AND t.status != ?',
            [$domain, 'deleted'],
        );

        if ($row === false) {
            return null;
        }

        return $row;
    }

    private function addTenantInYaml(string $name, string $connection): void
    {
        $data = $this->readTenantsFromYaml();

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

    private function updateTenantInYaml(int $id, string $name, string $connection): void
    {
        $data = $this->readTenantsFromYaml();
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

    private function deleteTenantInYaml(int $id): void
    {
        $data = $this->readTenantsFromYaml();
        $data = array_values(array_filter($data, static fn (array $tenant): bool => (int)($tenant['id'] ?? 0) !== $id));
        $this->writeTenants($data);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fallbackToYamlOrFail(string $operation): array
    {
        if ($this->yamlFallbackAvailable()) {
            $this->logger->warning('Control plane not ready. Falling back to YAML.', [
                'operation' => $operation,
            ]);

            return $this->readTenantsFromYaml();
        }

        throw new RuntimeException(sprintf(
            'Control plane database not ready and YAML fallback unavailable while attempting to %s.',
            $operation
        ));
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
        $ids = array_map(static fn (array $tenant): int => (int)($tenant['id'] ?? 0), $tenants);

        return (int)(max($ids ?: [0]) + 1);
    }

    private function shouldUseDatabase(): bool
    {
        return !$this->shouldUseYaml();
    }

    private function shouldUseYaml(): bool
    {
        $source = strtolower(trim($this->tenantsSource));

        if ($source === '') {
            $source = 'db';
        }

        return $source === 'yaml';
    }

    private function getControlPlaneConnection(): Connection
    {
        return $this->connectionRegistry->getConnection('control_plane');
    }

    private function isControlPlaneReady(Connection $connection): bool
    {
        try {
            return $connection->createSchemaManager()->tablesExist(['tenants']);
        } catch (DbalException $exception) {
            $this->logger->warning('Failed to verify control plane schema', [
                'exception' => $exception,
            ]);

            return false;
        }
    }

    private function yamlFallbackAvailable(): bool
    {
        if ('' === trim($this->tenantsConfigFilePath)) {
            return false;
        }

        return file_exists($this->tenantsConfigFilePath);
    }

    private function requireControlPlaneReady(string $operation): void
    {
        $conn = $this->getControlPlaneConnection();
        if ($this->isControlPlaneReady($conn)) {
            return;
        }

        throw new RuntimeException(sprintf('Control plane database not ready while attempting to %s.', $operation));
    }

    private function invalidateTenantCache(string $tenantName): void
    {
        if ($tenantName === '') {
            return;
        }

        $this->cache->delete('tenant_name_' . hash('xxh3', $tenantName));
    }

    private function invalidateTenantDomainCaches(int $tenantId): void
    {
        try {
            $domains = $this->getTenantDomains($tenantId);
            foreach ($domains as $domain) {
                $this->cache->delete('tenant_domain_' . hash('xxh3', (string)($domain['domain'] ?? '')));
            }
        } catch (\Throwable) {
            // Domain cache invalidation is the best effort
        }
    }
}
