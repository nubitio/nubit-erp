<?php

declare(strict_types=1);

namespace App\Core\Audit;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ParameterType;
use Doctrine\Persistence\ConnectionRegistry;
use Psr\Cache\InvalidArgumentException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final readonly class AuditLogRepository
{
    public function __construct(
        private ConnectionRegistry $connectionRegistry,
        private CacheInterface $cache,
    ) {
    }

    /**
     * @param array<string, string> $filters Keys: 'action', 'date_from', 'date_to'
     *
     * @return array{rows: array<int, array<string, mixed>>, total: int}
     *
     * @throws Exception
     * @throws InvalidArgumentException
     */
    public function getAuditLogs(array $filters = [], int $page = 1, int $limit = 50): array
    {
        $connection = $this->getControlPlaneConnection();
        if (!$this->isControlPlaneReady()) {
            return ['rows' => [], 'total' => 0];
        }

        $where = [];
        $params = [];
        $types = [];

        if (!empty($filters['action'])) {
            $where[] = 'a.action = ?';
            $params[] = $filters['action'];
            $types[] = ParameterType::STRING;
        }

        if (!empty($filters['date_from'])) {
            $where[] = 'a.created_at >= ?';
            $params[] = $filters['date_from'] . ' 00:00:00';
            $types[] = ParameterType::STRING;
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'a.created_at <= ?';
            $params[] = $filters['date_to'] . ' 23:59:59';
            $types[] = ParameterType::STRING;
        }

        $whereClause = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';

        $total = (int) $connection->fetchOne(
            'SELECT COUNT(*) FROM audit_logs a ' . $whereClause,
            $params,
            $types,
        );

        $offset = max(0, ($page - 1) * $limit);

        $sql = 'SELECT a.id, a.action, a.actor, a.channel, a.metadata, a.created_at, t.name AS tenant_name
                FROM audit_logs a
                LEFT JOIN tenants t ON t.id = a.tenant_id '
            . $whereClause
            . ' ORDER BY a.created_at DESC'
            . ' LIMIT ? OFFSET ?';

        $params[] = $limit;
        $types[] = ParameterType::INTEGER;
        $params[] = $offset;
        $types[] = ParameterType::INTEGER;

        $rows = $connection->fetchAllAssociative($sql, $params, $types);

        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * @return list<string>
     *
     * @throws Exception
     * @throws InvalidArgumentException
     */
    public function getDistinctActions(): array
    {
        if (!$this->isControlPlaneReady()) {
            return [];
        }

        return $this->getControlPlaneConnection()->fetchFirstColumn(
            'SELECT DISTINCT action FROM audit_logs ORDER BY action ASC',
        );
    }

    /**
     * @throws InvalidArgumentException
     */
    private function isControlPlaneReady(): bool
    {
        return $this->cache->get('audit_control_plane_ready', function (ItemInterface $item): bool {
            $item->expiresAfter(600);

            return $this->getControlPlaneConnection()
                ->createSchemaManager()
                ->tablesExist(['audit_logs']);
        });
    }

    private function getControlPlaneConnection(): Connection
    {
        return $this->connectionRegistry->getConnection('control_plane');
    }
}
