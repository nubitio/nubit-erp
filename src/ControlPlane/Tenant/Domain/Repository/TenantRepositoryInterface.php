<?php

declare(strict_types=1);

namespace App\ControlPlane\Tenant\Domain\Repository;

use App\ControlPlane\Tenant\Domain\Model\TenantSnapshot;

interface TenantRepositoryInterface
{
    /**
     * @return list<TenantSnapshot>
     */
    public function listActive(): array;

    /**
     * @return list<TenantSnapshot>
     */
    public function listDeleted(): array;

    public function findById(int $id): ?TenantSnapshot;

    public function findByName(string $name): ?TenantSnapshot;

    public function findByDomain(string $domain): ?TenantSnapshot;

    public function create(string $name, string $connection): void;

    public function update(int $id, string $name, string $connection): void;

    public function softDelete(int $id): void;

    public function restore(int $id): void;

    public function purge(int $id): void;

    public function suspend(int $id): void;

    public function unsuspend(int $id): void;

    public function changePlan(int $id, string $plan): void;

    public function updateBackupFrequency(int $id, int $frequency): void;

    public function updateBackupRetention(int $id, int $days): void;

    public function planExists(string $plan): bool;
}