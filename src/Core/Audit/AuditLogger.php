<?php

declare(strict_types=1);

namespace App\Core\Audit;

use App\Core\Context\TenantContext;
use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ConnectionRegistry;
use RuntimeException;

final readonly class AuditLogger
{
    public function __construct(
        private ConnectionRegistry $connectionRegistry,
        private TenantContext $tenantContext,
    ) {
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function log(
        string $action,
        array $metadata = [],
        ?int $tenantId = null,
        ?string $actor = null,
    ): void {
        $connection = $this->getControlPlaneConnection();
        if (!$this->isControlPlaneReady($connection)) {
            throw new RuntimeException('Control plane database not ready for audit logging.');
        }

        $tenantId = $tenantId ?? $this->tenantContext->getTenantId();
        $requestId = $this->tenantContext->getRequestId();

        $connection->insert('audit_logs', [
            'tenant_id' => $tenantId,
            'action' => $action,
            'actor' => $actor,
            'request_id' => $requestId,
            'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
            'created_at' => (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
        ]);
    }

    private function getControlPlaneConnection(): Connection
    {
        return $this->connectionRegistry->getConnection('control_plane');
    }

    private function isControlPlaneReady(Connection $connection): bool
    {
        return $connection->createSchemaManager()->tablesExist(['audit_logs']);
    }
}
