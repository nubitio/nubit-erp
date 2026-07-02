<?php

declare(strict_types=1);

namespace App\ControlPlane\Billing\Service;

use App\ControlPlane\Tenant\Service\TenantManager;
use App\ControlPlane\Tenant\Service\TenantResourceCatalog;
use App\Efact\Doctrine\DBAL\TenantConnection;
use Doctrine\Persistence\ConnectionRegistry;
use Nubit\Platform\Exception\QuotaExceededException;
use Nubit\Platform\Exception\ServiceException;
use Nubit\Platform\Quota\Contract\QuotaEnforcerInterface;
use Nubit\Platform\Tenant\Context\TenantContext;

final class TenantQuotaService implements QuotaEnforcerInterface, TenantUsageSummaryInterface
{
    /**
     * @var array<string, bool>
     */
    private array $heldLocks = [];

    public function __construct(
        private TenantContext $tenantContext,
        private TenantManager $tenantManager,
        private PlanManager $planManager,
        private TenantResourceCatalog $resourceCatalog,
        private ConnectionRegistry $connectionRegistry,
    ) {}

    /**
     * @return array<string, array<string, int>>
     */
    public function getPlans(): array
    {
        $result = [];
        foreach ($this->planManager->getPlans() as $plan) {
            $result[(string) $plan['name']] = (array) $plan['limits'];
        }

        return $result;
    }

    public function check(string $resource): QuotaCheckResult
    {
        if (!in_array($resource, $this->resourceCatalog->quotaResources(), true)) {
            return new QuotaCheckResult(false, $resource, 0, 0);
        }

        $tenantName = $this->tenantContext->getTenantName();
        if ($tenantName === null) {
            return new QuotaCheckResult(true, $resource, 0, -1);
        }

        $tenant = $this->tenantManager->getTenantByName($tenantName);
        if ($tenant === null) {
            // A resolved tenant context without a control-plane record must fail closed.
            return new QuotaCheckResult(false, $resource, 0, 0);
        }

        $plan = (string) ($tenant['plan'] ?? 'free');
        $limits = $this->planManager->getLimitsForPlan($plan);
        $limit = $limits[$resource] ?? -1;

        if ($limit === -1) {
            return new QuotaCheckResult(true, $resource, 0, -1);
        }

        /** @var TenantConnection $connection */
        $connection = $this->connectionRegistry->getConnection('tenant');
        $connection->switchTenantParams($tenant);
        $existingTables = $connection->createSchemaManager()->listTableNames();

        $tableName = $this->resolveTableNameFromList($resource, $existingTables);
        if ($tableName === null) {
            // Unknown schema state: fail closed for quota-protected resources.
            return new QuotaCheckResult(false, $resource, 0, 0);
        }

        $this->acquireQuotaLock($connection, $tenantName, $resource);

        $current = (int) $connection->fetchOne(
            'SELECT COUNT(*) FROM ' . $connection->quoteSingleIdentifier($tableName),
        );

        return new QuotaCheckResult($current < $limit, $resource, $current, $limit);
    }

    public function enforce(string $resource): void
    {
        $result = $this->check($resource);
        if (!$result->allowed) {
            if ($result->limit === 0 && $result->current === 0) {
                throw new ServiceException(
                    sprintf('Quota check failed closed for resource "%s"', $result->resource),
                    429,
                );
            }

            throw new QuotaExceededException($result->resource, $result->current, $result->limit);
        }
    }

    /**
     * @return array<string, array{current: int, limit: int}>
     */
    public function getUsageSummary(string $tenantName): array
    {
        $tenant = $this->tenantManager->getTenantByName($tenantName);
        if ($tenant === null) {
            throw new \InvalidArgumentException(sprintf('Tenant "%s" not found', $tenantName));
        }

        $plan = (string) ($tenant['plan'] ?? 'free');
        $limits = $this->planManager->getLimitsForPlan($plan);

        /** @var TenantConnection $connection */
        $connection = $this->connectionRegistry->getConnection('tenant');
        $connection->switchTenantParams($tenant);

        $summary = [];
        $existingTables = $connection->createSchemaManager()->listTableNames();
        foreach ($this->resourceCatalog->quotaResources() as $resource) {
            $limit = $limits[$resource] ?? -1;
            $tableName = $this->resolveTableNameFromList($resource, $existingTables);

            try {
                if ($tableName === null) {
                    $current = 0;
                } else {
                    $current = (int) $connection->fetchOne(
                        'SELECT COUNT(*) FROM ' . $connection->quoteSingleIdentifier($tableName),
                    );
                }
            } catch (\Throwable) {
                $current = 0;
            }

            $summary[$resource] = ['current' => $current, 'limit' => $limit];
        }

        try {
            $storageBytes = (int) $connection->fetchOne(
                'SELECT COALESCE(ROUND(SUM(data_length + index_length), 0), 0)
                 FROM information_schema.tables WHERE table_schema = ?',
                [(string) ($tenant['connection'] ?? $tenantName)],
            );
        } catch (\Throwable) {
            $storageBytes = 0;
        }
        $storageLimitMb = (int) ($limits['storage_mb'] ?? -1);
        $storageLimitBytes = $storageLimitMb === -1 ? -1 : $storageLimitMb * 1024 * 1024;
        $summary['storage_bytes'] = ['current' => $storageBytes, 'limit' => $storageLimitBytes];

        return $summary;
    }

    /**
     * @param list<string> $existingTables
     */
    private function resolveTableNameFromList(string $resource, array $existingTables): ?string
    {
        foreach ($this->resourceCatalog->tablesForResource($resource) as $tableName) {
            if (in_array($tableName, $existingTables, true)) {
                return $tableName;
            }
        }

        return null;
    }

    public function releaseLocks(): void
    {
        if ($this->heldLocks === []) {
            return;
        }

        /** @var TenantConnection $connection */
        $connection = $this->connectionRegistry->getConnection('tenant');
        foreach (array_keys($this->heldLocks) as $lockName) {
            try {
                $connection->fetchOne('SELECT RELEASE_LOCK(?)', [$lockName]);
            } catch (\Throwable) {
                // best effort unlock
            }
        }

        $this->heldLocks = [];
    }

    private function acquireQuotaLock(TenantConnection $connection, string $tenantName, string $resource): void
    {
        $lockName = sprintf('quota:%s:%s', $tenantName, $resource);
        if (isset($this->heldLocks[$lockName])) {
            return;
        }

        $acquired = (int) $connection->fetchOne('SELECT GET_LOCK(?, 5)', [$lockName]);
        if ($acquired !== 1) {
            throw new ServiceException('Could not acquire quota lock', 429);
        }

        $this->heldLocks[$lockName] = true;
    }
}
