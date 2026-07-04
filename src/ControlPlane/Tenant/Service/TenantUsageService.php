<?php

declare(strict_types=1);

namespace App\ControlPlane\Tenant\Service;

use App\ControlPlane\Tenant\Application\TenantService;
use App\Efact\Doctrine\DBAL\TenantConnection;
use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ConnectionRegistry;
use Psr\Log\LoggerInterface;

final readonly class TenantUsageService
{
    public function __construct(
        private ConnectionRegistry $connectionRegistry,
        private TenantService $tenantService,
        private TenantResourceCatalog $resourceCatalog,
        private LoggerInterface $logger,
    ) {}

    /**
     * @return array<string, int>
     */
    public function collectMetrics(string $tenantName): array
    {
        $tenant = $this->tenantService->getTenantByName($tenantName);
        if ($tenant === null) {
            throw new \InvalidArgumentException(sprintf('Tenant "%s" not found', $tenantName));
        }

        /** @var TenantConnection $connection */
        $connection = $this->connectionRegistry->getConnection('tenant');
        $connection->switchTenantParams($tenant);

        $schemaManager = $connection->createSchemaManager();
        $existingTables = $schemaManager->listTableNames();

        $metrics = [];
        foreach ($this->resourceCatalog->metricResources() as $metricName) {
            $tableName = $this->resolveTableName($metricName, $existingTables);
            if ($tableName === null) {
                $metrics[$metricName] = 0;

                continue;
            }

            try {
                $count = (int) $connection->fetchOne(
                    'SELECT COUNT(*) FROM ' . $connection->quoteSingleIdentifier($tableName),
                );
                $metrics[$metricName] = $count;
            } catch (\Throwable $e) {
                $this->logger->warning('Failed to count rows in table', [
                    'tenant' => $tenantName,
                    'table' => $tableName,
                    'exception' => $e,
                ]);
                $metrics[$metricName] = -1;
            }
        }

        try {
            /** @var string|false $size */
            $size = $connection->fetchOne('SELECT ROUND(SUM(data_length + index_length), 0) '
            . 'FROM information_schema.tables WHERE table_schema = ?', [$tenant['connection'] ?? $tenantName]);
            $metrics['storage_bytes'] = $size !== false ? (int) $size : 0;
        } catch (\Throwable $e) {
            $this->logger->warning('Failed to get database size', [
                'tenant' => $tenantName,
                'exception' => $e,
            ]);
            $metrics['storage_bytes'] = -1;
        }

        return $metrics;
    }

    /**
     * @param array<string, int> $metrics
     */
    public function storeMetrics(string $tenantName, array $metrics): void
    {
        $tenant = $this->tenantService->getTenantByName($tenantName);
        if ($tenant === null) {
            return;
        }

        $tenantId = (int) ($tenant['id'] ?? 0);
        if ($tenantId === 0) {
            return;
        }

        /** @var Connection $controlPlane */
        $controlPlane = $this->connectionRegistry->getConnection('control_plane');
        $now = new \DateTimeImmutable()->format('Y-m-d H:i:s');

        foreach ($metrics as $metricName => $value) {
            $controlPlane->executeStatement(
                'INSERT INTO tenant_usage (tenant_id, collected_at, metric_name, metric_value) VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE metric_value = VALUES(metric_value)',
                [
                    $tenantId,
                    $now,
                    $metricName,
                    $value,
                ],
            );
        }
    }

    /**
     * @return array<string, int>
     */
    public function getLatestMetrics(string $tenantName): array
    {
        $tenant = $this->tenantService->getTenantByName($tenantName);
        if ($tenant === null) {
            throw new \InvalidArgumentException(sprintf('Tenant "%s" not found', $tenantName));
        }

        $tenantId = (int) ($tenant['id'] ?? 0);
        if ($tenantId <= 0) {
            return [];
        }

        /** @var Connection $controlPlane */
        $controlPlane = $this->connectionRegistry->getConnection('control_plane');

        /** @var array<int, array{metric_name: string, metric_value: int}> $rows */
        $rows = $controlPlane->fetchAllAssociative('SELECT tu.metric_name, tu.metric_value
             FROM tenant_usage tu
             INNER JOIN (
                 SELECT metric_name, MAX(collected_at) AS max_collected
                 FROM tenant_usage
                 WHERE tenant_id = ?
                 GROUP BY metric_name
             ) latest ON latest.metric_name = tu.metric_name AND latest.max_collected = tu.collected_at
             WHERE tu.tenant_id = ?', [$tenantId, $tenantId]);

        $result = [];
        foreach ($rows as $row) {
            $result[(string) $row['metric_name']] = (int) $row['metric_value'];
        }

        return $result;
    }

    /**
     * @return array<int, array{metric_name: string, collected_at: string, metric_value: int}>
     */
    public function getRecentHistory(string $tenantName, int $hours = 24, int $limit = 500): array
    {
        $tenant = $this->tenantService->getTenantByName($tenantName);
        if ($tenant === null) {
            throw new \InvalidArgumentException(sprintf('Tenant "%s" not found', $tenantName));
        }

        $tenantId = (int) ($tenant['id'] ?? 0);
        if ($tenantId <= 0) {
            return [];
        }

        $hours = max(1, $hours);
        $limit = max(10, min($limit, 5000));
        $cutoff = new \DateTimeImmutable(sprintf('-%d hours', $hours))->format('Y-m-d H:i:s');

        /** @var Connection $controlPlane */
        $controlPlane = $this->connectionRegistry->getConnection('control_plane');
        /** @var array<int, array{metric_name: string, collected_at: string, metric_value: int}> $rows */
        $rows = $controlPlane->fetchAllAssociative('SELECT metric_name, collected_at, metric_value
             FROM tenant_usage
             WHERE tenant_id = ? AND collected_at >= ?
             ORDER BY collected_at DESC
             LIMIT ' . $limit, [$tenantId, $cutoff]);

        return $rows;
    }

    /**
     * @return array<string, array{previous: int, current: int, delta: int}>
     */
    public function getUsageDeltas(string $tenantName, int $hours = 24): array
    {
        $tenant = $this->tenantService->getTenantByName($tenantName);
        if ($tenant === null) {
            throw new \InvalidArgumentException(sprintf('Tenant "%s" not found', $tenantName));
        }

        $tenantId = (int) ($tenant['id'] ?? 0);
        if ($tenantId <= 0) {
            return [];
        }

        /** @var \Doctrine\DBAL\Connection $controlPlane */
        $controlPlane = $this->connectionRegistry->getConnection('control_plane');
        $cutoff = new \DateTimeImmutable(sprintf('-%d hours', max(1, $hours)))->format('Y-m-d H:i:s');

        $oldestRows = $controlPlane->fetchAllAssociative(
            'SELECT tu.metric_name, tu.metric_value
             FROM tenant_usage tu
             INNER JOIN (
                 SELECT metric_name, MIN(collected_at) AS min_collected
                 FROM tenant_usage
                 WHERE tenant_id = ? AND collected_at >= ?
                 GROUP BY metric_name
             ) oldest ON oldest.metric_name = tu.metric_name AND oldest.min_collected = tu.collected_at
             WHERE tu.tenant_id = ?',
            [$tenantId, $cutoff, $tenantId],
        );

        $oldest = [];
        foreach ($oldestRows as $row) {
            $oldest[(string) $row['metric_name']] = (int) $row['metric_value'];
        }

        $newestRows = $controlPlane->fetchAllAssociative(
            'SELECT tu.metric_name, tu.metric_value
             FROM tenant_usage tu
             INNER JOIN (
                 SELECT metric_name, MAX(collected_at) AS max_collected
                 FROM tenant_usage
                 WHERE tenant_id = ? AND collected_at >= ?
                 GROUP BY metric_name
             ) newest ON newest.metric_name = tu.metric_name AND newest.max_collected = tu.collected_at
             WHERE tu.tenant_id = ?',
            [$tenantId, $cutoff, $tenantId],
        );

        $result = [];
        foreach ($newestRows as $row) {
            $metric = (string) $row['metric_name'];
            $current = (int) $row['metric_value'];
            $previous = $oldest[$metric] ?? $current;
            $result[$metric] = [
                'previous' => $previous,
                'current' => $current,
                'delta' => $current - $previous,
            ];
        }

        return $result;
    }

    /**
     * @param list<string> $existingTables
     */
    private function resolveTableName(string $resource, array $existingTables): ?string
    {
        foreach ($this->resourceCatalog->tablesForResource($resource) as $tableName) {
            if (in_array($tableName, $existingTables, true)) {
                return $tableName;
            }
        }

        return null;
    }
}
