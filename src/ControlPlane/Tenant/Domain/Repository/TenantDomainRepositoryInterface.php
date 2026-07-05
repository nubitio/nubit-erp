<?php

declare(strict_types=1);

namespace App\ControlPlane\Tenant\Domain\Repository;

interface TenantDomainRepositoryInterface
{
    /**
     * @return list<array{id: int, domain: string, is_primary: bool}>
     */
    public function listByTenantId(int $tenantId): array;

    public function findPrimaryDomain(int $tenantId): ?string;

    public function add(int $tenantId, string $domain, bool $isPrimary): void;

    public function delete(int $domainId): void;

    public function setPrimary(int $tenantId, int $domainId): void;
}
