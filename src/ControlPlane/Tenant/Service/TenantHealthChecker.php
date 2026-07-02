<?php

declare(strict_types=1);

namespace App\ControlPlane\Tenant\Service;

use App\Efact\Doctrine\DBAL\TenantConnection;
use Doctrine\Persistence\ConnectionRegistry;
use Psr\Log\LoggerInterface;

final readonly class TenantHealthChecker
{
    public function __construct(
        private ConnectionRegistry $connectionRegistry,
        private TenantManager $tenantManager,
        private LoggerInterface $logger,
    ) {}

    /**
     * @return array{healthy: bool, tenant: string, checks: array<string, array{ok: bool, message: string}>}
     */
    public function check(string $tenantName): array
    {
        $checks = [];

        $checks['control_plane'] = $this->checkControlPlaneEntry($tenantName);
        $checks['connection'] = $this->checkConnection($tenantName);

        if ($checks['connection']['ok']) {
            $checks['schema'] = $this->checkSchema($tenantName);
        }

        $healthy = array_reduce($checks, static fn(bool $carry, array $check): bool => $carry && $check['ok'], true);

        return [
            'healthy' => $healthy,
            'tenant' => $tenantName,
            'checks' => $checks,
        ];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    private function checkControlPlaneEntry(string $tenantName): array
    {
        try {
            $tenant = $this->tenantManager->getTenantByName($tenantName);
            if ($tenant === null) {
                return ['ok' => false, 'message' => 'Tenant not found in control plane'];
            }

            return ['ok' => true, 'message' => 'Registered in control plane'];
        } catch (\Throwable $e) {
            $this->logger->warning('Health check: control plane lookup failed', [
                'tenant' => $tenantName,
                'exception' => $e,
            ]);

            return ['ok' => false, 'message' => 'Control plane lookup failed'];
        }
    }

    /**
     * @return array{ok: bool, message: string}
     */
    private function checkConnection(string $tenantName): array
    {
        try {
            /** @var TenantConnection $connection */
            $connection = $this->connectionRegistry->getConnection('tenant');
            $tenant = $this->tenantManager->getTenantByName($tenantName);
            if ($tenant === null) {
                return ['ok' => false, 'message' => 'Tenant not found'];
            }

            $connection->switchTenantParams($tenant);
            $connection->executeQuery('SELECT 1');

            return ['ok' => true, 'message' => 'Database connection successful'];
        } catch (\Throwable $e) {
            $this->logger->warning('Health check: connection failed', [
                'tenant' => $tenantName,
                'exception' => $e,
            ]);

            return ['ok' => false, 'message' => 'Database connection failed'];
        }
    }

    /**
     * @return array{ok: bool, message: string}
     */
    private function checkSchema(string $tenantName): array
    {
        try {
            /** @var TenantConnection $connection */
            $connection = $this->connectionRegistry->getConnection('tenant');
            $schemaManager = $connection->createSchemaManager();

            $requiredTables = ['user', 'tenant_settings', 'branch'];
            $existingTables = $schemaManager->listTableNames();
            $missing = array_diff($requiredTables, $existingTables);

            if ($missing !== []) {
                return [
                    'ok' => false,
                    'message' => 'Missing tables: ' . implode(', ', $missing),
                ];
            }

            return ['ok' => true, 'message' => 'Schema verified'];
        } catch (\Throwable $e) {
            $this->logger->warning('Health check: schema verification failed', [
                'tenant' => $tenantName,
                'exception' => $e,
            ]);

            return ['ok' => false, 'message' => 'Schema verification failed'];
        }
    }
}
