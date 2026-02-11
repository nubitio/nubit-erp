<?php

declare(strict_types=1);

namespace App\Core\Audit;

use App\Core\Context\TenantContext;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
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
            'created_at' => new \DateTimeImmutable('now')->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @param array<string, string> $filters Keys: 'action', 'date_from', 'date_to'
     *
     * @return array{rows: array<int, array<string, mixed>>, total: int}
     * @throws Exception
     */
    public function getAuditLogs(array $filters = [], int $page = 1, int $limit = 50): array
    {
        $connection = $this->getControlPlaneConnection();
        if (!$this->isControlPlaneReady($connection)) {
            return ['rows' => [], 'total' => 0];
        }

        $where = [];
        $params = [];

        if (!empty($filters['action'])) {
            $where[] = 'a.action = ?';
            $params[] = $filters['action'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = 'a.created_at >= ?';
            $params[] = $filters['date_from'] . ' 00:00:00';
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'a.created_at <= ?';
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        $whereClause = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';

        $total = (int) $connection->fetchOne(
            'SELECT COUNT(*) FROM audit_logs a ' . $whereClause,
            $params
        );

        $offset = max(0, ($page - 1) * $limit);

        $sql = 'SELECT a.id, a.action, a.actor, a.metadata, a.created_at, t.name AS tenant_name
                FROM audit_logs a
                LEFT JOIN tenants t ON t.id = a.tenant_id '
            . $whereClause
            . ' ORDER BY a.created_at DESC'
            . ' LIMIT ' . $limit . ' OFFSET ' . $offset;

        $rows = $connection->fetchAllAssociative($sql, $params);

        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * @return list<string>
     * @throws Exception
     */
    public function getDistinctActions(): array
    {
        $connection = $this->getControlPlaneConnection();
        if (!$this->isControlPlaneReady($connection)) {
            return [];
        }

        return $connection->fetchFirstColumn(
            'SELECT DISTINCT action FROM audit_logs ORDER BY action ASC'
        );
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
