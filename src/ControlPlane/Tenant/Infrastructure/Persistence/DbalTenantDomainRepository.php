<?php

declare(strict_types=1);

namespace App\ControlPlane\Tenant\Infrastructure\Persistence;

use App\ControlPlane\Tenant\Domain\Repository\TenantDomainRepositoryInterface;
use InvalidArgumentException;

final readonly class DbalTenantDomainRepository implements TenantDomainRepositoryInterface
{
    public function __construct(
        private ControlPlaneSchemaGuard $schemaGuard,
    ) {}

    public function listByTenantId(int $tenantId): array
    {
        $this->schemaGuard->requireReady('list tenant domains');

        /** @var list<array{id: int|string, domain: string, is_primary: int|string}> $rows */
        $rows = $this->schemaGuard->connection()->fetchAllAssociative(
            'SELECT id, domain, is_primary FROM tenant_domains WHERE tenant_id = ? ORDER BY is_primary DESC, id ASC',
            [$tenantId],
        );

        return array_map(
            static fn(array $row): array => [
                'id' => (int) $row['id'],
                'domain' => (string) $row['domain'],
                'is_primary' => (bool) $row['is_primary'],
            ],
            $rows,
        );
    }

    public function findPrimaryDomain(int $tenantId): ?string
    {
        $this->schemaGuard->requireReady('get primary tenant domain');

        $domain = $this->schemaGuard->connection()->fetchOne(
            'SELECT domain FROM tenant_domains WHERE tenant_id = ? AND is_primary = 1 ORDER BY id ASC LIMIT 1',
            [$tenantId],
        );

        return $domain === false ? null : (string) $domain;
    }

    public function add(int $tenantId, string $domain, bool $isPrimary): void
    {
        $domain = strtolower(trim($domain));
        if ($domain === '') {
            throw new InvalidArgumentException('Domain is required');
        }

        $conn = $this->schemaGuard->connection();
        $this->schemaGuard->requireReady('create tenant domain');

        if ($conn->fetchOne('SELECT id FROM tenant_domains WHERE domain = ?', [$domain]) !== false) {
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
    }

    public function delete(int $domainId): void
    {
        $this->schemaGuard->requireReady('delete tenant domain');
        $this->schemaGuard->connection()->delete('tenant_domains', ['id' => $domainId]);
    }

    public function setPrimary(int $tenantId, int $domainId): void
    {
        $conn = $this->schemaGuard->connection();
        $this->schemaGuard->requireReady('set primary tenant domain');

        $conn->update('tenant_domains', ['is_primary' => 0], ['tenant_id' => $tenantId]);
        $conn->update('tenant_domains', ['is_primary' => 1], ['id' => $domainId, 'tenant_id' => $tenantId]);
    }
}