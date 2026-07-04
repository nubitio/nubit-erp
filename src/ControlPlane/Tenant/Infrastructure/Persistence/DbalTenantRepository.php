<?php

declare(strict_types=1);

namespace App\ControlPlane\Tenant\Infrastructure\Persistence;

use App\ControlPlane\Tenant\Domain\Model\TenantSnapshot;
use App\ControlPlane\Tenant\Domain\Model\TenantStatus;
use App\ControlPlane\Tenant\Domain\Repository\TenantRepositoryInterface;
use Doctrine\DBAL\Exception as DbalException;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use RuntimeException;

final readonly class DbalTenantRepository implements TenantRepositoryInterface
{
    public function __construct(
        private ControlPlaneSchemaGuard $schemaGuard,
        private YamlTenantStore $yamlStore,
        private LoggerInterface $logger,
        #[\Symfony\Component\DependencyInjection\Attribute\Autowire('%env(default::TENANTS_SOURCE)%')]
        private string $tenantsSource = 'db',
    ) {}

    private function usesYamlPrimary(): bool
    {
        return TenantSourceMode::fromEnv($this->tenantsSource) === TenantSourceMode::Yaml;
    }

    public function listActive(): array
    {
        if ($this->usesYamlPrimary()) {
            return array_values(array_filter(
                $this->yamlStore->listAll(),
                static fn(TenantSnapshot $tenant): bool => $tenant->status !== TenantStatus::Deleted,
            ));
        }

        if (!$this->schemaGuard->isReady()) {
            return $this->fallbackYaml('read tenants', fn(): array => array_values(array_filter(
                $this->yamlStore->listAll(),
                static fn(TenantSnapshot $tenant): bool => $tenant->status !== TenantStatus::Deleted,
            )));
        }

        $sql = <<<'SQL'
            SELECT t.id, t.name, t.connection, t.status, t.plan,
                   t.backup_frequency, t.backup_retention_days,
                   d.domain AS primary_domain
            FROM tenants t
            LEFT JOIN tenant_domains d ON d.tenant_id = t.id AND d.is_primary = 1
            WHERE t.status != 'deleted'
            ORDER BY t.id ASC
            SQL;

        return $this->mapRows($this->schemaGuard->connection()->fetchAllAssociative($sql));
    }

    public function listDeleted(): array
    {
        if (!$this->schemaGuard->isReady()) {
            return [];
        }

        $sql = <<<'SQL'
            SELECT t.id, t.name, t.connection, t.plan, t.deleted_at,
                   d.domain AS primary_domain
            FROM tenants t
            LEFT JOIN tenant_domains d ON d.tenant_id = t.id AND d.is_primary = 1
            WHERE t.status = 'deleted'
            ORDER BY t.deleted_at DESC
            SQL;

        return $this->mapRows($this->schemaGuard->connection()->fetchAllAssociative($sql));
    }

    public function findById(int $id): ?TenantSnapshot
    {
        if ($this->usesYamlPrimary()) {
            return $this->yamlStore->findById($id);
        }

        if (!$this->schemaGuard->isReady()) {
            return $this->fallbackYaml('get tenant', fn(): ?TenantSnapshot => $this->yamlStore->findById($id));
        }

        $row = $this->schemaGuard->connection()->fetchAssociative(
            'SELECT id, name, connection, status, plan, backup_frequency, backup_retention_days FROM tenants WHERE id = ?',
            [$id],
        );

        return $row === false ? null : TenantSnapshot::fromArray($row);
    }

    public function findByName(string $name): ?TenantSnapshot
    {
        if ($this->usesYamlPrimary()) {
            return $this->yamlStore->findByName($name);
        }

        if (!$this->schemaGuard->isReady()) {
            return $this->fallbackYaml('get tenant by name', fn(): ?TenantSnapshot => $this->yamlStore->findByName($name));
        }

        $row = $this->schemaGuard->connection()->fetchAssociative(
            'SELECT id, name, connection, status, plan, backup_frequency, backup_retention_days FROM tenants WHERE name = ? AND status != ?',
            [$name, TenantStatus::Deleted->value],
        );

        return $row === false ? null : TenantSnapshot::fromArray($row);
    }

    public function findByDomain(string $domain): ?TenantSnapshot
    {
        if ($this->usesYamlPrimary()) {
            return null;
        }

        if (!$this->schemaGuard->isReady()) {
            $this->fallbackYaml('get tenant by domain', static fn(): null => null);

            return null;
        }

        $row = $this->schemaGuard->connection()->fetchAssociative(
            <<<'SQL'
                SELECT t.id, t.name, t.connection, t.status, t.plan,
                       t.backup_frequency, t.backup_retention_days
                FROM tenant_domains d
                INNER JOIN tenants t ON t.id = d.tenant_id
                WHERE d.domain = ? AND t.status != ?
                SQL,
            [$domain, TenantStatus::Deleted->value],
        );

        return $row === false ? null : TenantSnapshot::fromArray($row);
    }

    public function create(string $name, string $connection): void
    {
        if ($this->usesYamlPrimary()) {
            $this->yamlStore->create($name, $connection);

            return;
        }

        if (!$this->schemaGuard->isReady()) {
            $this->fallbackYaml('create tenant', function () use ($name, $connection): void {
                $this->yamlStore->create($name, $connection);
            });

            return;
        }

        $conn = $this->schemaGuard->connection();
        if ($conn->fetchOne('SELECT id FROM tenants WHERE name = ?', [$name]) !== false) {
            throw new InvalidArgumentException(sprintf('Tenant "%s" already exists', $name));
        }

        $now = $this->now();
        $conn->insert('tenants', [
            'name' => $name,
            'connection' => $connection,
            'status' => TenantStatus::Active->value,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function update(int $id, string $name, string $connection): void
    {
        if ($this->usesYamlPrimary()) {
            $this->yamlStore->update($id, $name, $connection);

            return;
        }

        if (!$this->schemaGuard->isReady()) {
            $this->fallbackYaml('update tenant', function () use ($id, $name, $connection): void {
                $this->yamlStore->update($id, $name, $connection);
            });

            return;
        }

        $conn = $this->schemaGuard->connection();
        if ($conn->fetchOne('SELECT id FROM tenants WHERE name = ? AND id <> ?', [$name, $id]) !== false) {
            throw new InvalidArgumentException(sprintf('Tenant "%s" already exists', $name));
        }

        $affected = $conn->update('tenants', [
            'name' => $name,
            'connection' => $connection,
            'updated_at' => $this->now(),
        ], ['id' => $id]);

        if ($affected === 0) {
            throw new InvalidArgumentException('Tenant not found');
        }
    }

    public function softDelete(int $id): void
    {
        if ($this->usesYamlPrimary()) {
            $this->yamlStore->delete($id);

            return;
        }

        if (!$this->schemaGuard->isReady()) {
            $this->fallbackYaml('delete tenant', function () use ($id): void {
                $this->yamlStore->delete($id);
            });

            return;
        }

        $now = $this->now();
        $this->schemaGuard->connection()->update('tenants', [
            'status' => TenantStatus::Deleted->value,
            'deleted_at' => $now,
            'updated_at' => $now,
        ], ['id' => $id]);
    }

    public function restore(int $id): void
    {
        $this->schemaGuard->requireReady('restore tenant');

        $affected = $this->schemaGuard->connection()->update('tenants', [
            'status' => TenantStatus::Active->value,
            'deleted_at' => null,
            'updated_at' => $this->now(),
        ], ['id' => $id, 'status' => TenantStatus::Deleted->value]);

        if ($affected === 0) {
            throw new InvalidArgumentException('Deleted tenant not found');
        }
    }

    public function purge(int $id): void
    {
        $this->schemaGuard->requireReady('purge tenant');

        $conn = $this->schemaGuard->connection();
        $tenant = $conn->fetchAssociative('SELECT id, status FROM tenants WHERE id = ?', [$id]);

        if ($tenant === false) {
            throw new InvalidArgumentException('Tenant not found');
        }

        if (($tenant['status'] ?? '') !== TenantStatus::Deleted->value) {
            throw new InvalidArgumentException('Only deleted tenants can be purged. Soft-delete it first.');
        }

        $conn->delete('tenants', ['id' => $id]);
    }

    public function suspend(int $id): void
    {
        $this->schemaGuard->requireReady('suspend tenant');

        $affected = $this->schemaGuard->connection()->update('tenants', [
            'status' => TenantStatus::Suspended->value,
            'updated_at' => $this->now(),
        ], ['id' => $id, 'status' => TenantStatus::Active->value]);

        if ($affected === 0) {
            throw new InvalidArgumentException('Active tenant not found');
        }
    }

    public function unsuspend(int $id): void
    {
        $this->schemaGuard->requireReady('unsuspend tenant');

        $affected = $this->schemaGuard->connection()->update('tenants', [
            'status' => TenantStatus::Active->value,
            'updated_at' => $this->now(),
        ], ['id' => $id, 'status' => TenantStatus::Suspended->value]);

        if ($affected === 0) {
            throw new InvalidArgumentException('Suspended tenant not found');
        }
    }

    public function changePlan(int $id, string $plan): void
    {
        $this->schemaGuard->requireReady('change tenant plan');

        if (!$this->planExists($plan)) {
            throw new InvalidArgumentException(sprintf('Invalid plan "%s"', $plan));
        }

        $conn = $this->schemaGuard->connection();
        $affected = $conn->update('tenants', [
            'plan' => $plan,
            'updated_at' => $this->now(),
        ], ['id' => $id]);

        if ($affected === 0 && $conn->fetchOne('SELECT id FROM tenants WHERE id = ?', [$id]) === false) {
            throw new InvalidArgumentException('Tenant not found');
        }
    }

    public function updateBackupFrequency(int $id, int $frequency): void
    {
        if ($frequency < 0) {
            throw new InvalidArgumentException('Backup frequency must be 0 or greater');
        }

        $this->schemaGuard->requireReady('update backup frequency');
        $this->updateBackupField($id, 'backup_frequency', $frequency);
    }

    public function updateBackupRetention(int $id, int $days): void
    {
        if ($days < 0) {
            throw new InvalidArgumentException('Backup retention days must be 0 or greater');
        }

        $this->schemaGuard->requireReady('update backup retention');
        $this->updateBackupField($id, 'backup_retention_days', $days);
    }

    public function planExists(string $plan): bool
    {
        $this->schemaGuard->requireReady('validate plan');

        return $this->schemaGuard->connection()->fetchOne('SELECT name FROM plans WHERE name = ?', [$plan]) !== false;
    }

    private function updateBackupField(int $id, string $field, int $value): void
    {
        $conn = $this->schemaGuard->connection();
        $affected = $conn->update('tenants', [
            $field => $value,
            'updated_at' => $this->now(),
        ], ['id' => $id]);

        if ($affected === 0 && $conn->fetchOne('SELECT id FROM tenants WHERE id = ?', [$id]) === false) {
            throw new InvalidArgumentException('Tenant not found');
        }
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return list<TenantSnapshot>
     */
    private function mapRows(array $rows): array
    {
        return array_map(
            static fn(array $row): TenantSnapshot => TenantSnapshot::fromArray($row),
            $rows,
        );
    }

    /**
     * @template T
     * @param callable(): T $fallback
     * @return T
     */
    private function fallbackYaml(string $operation, callable $fallback): mixed
    {
        if ($this->yamlStore->isAvailable()) {
            $this->logger->warning('Control plane not ready. Falling back to YAML.', [
                'operation' => $operation,
            ]);

            return $fallback();
        }

        throw new RuntimeException(sprintf(
            'Control plane database not ready and YAML fallback unavailable while attempting to %s.',
            $operation,
        ));
    }

    private function now(): string
    {
        return new \DateTimeImmutable('now')->format('Y-m-d H:i:s');
    }
}