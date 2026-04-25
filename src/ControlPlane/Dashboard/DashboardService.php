<?php

declare(strict_types=1);

namespace App\ControlPlane\Dashboard;

use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ConnectionRegistry;
use Psr\Log\LoggerInterface;

final readonly class DashboardService
{
    public function __construct(
        private ConnectionRegistry $connectionRegistry,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @return array{total: int, active: int, suspended: int, deleted: int}
     */
    public function getTenantStats(): array
    {
        try {
            $conn = $this->getConnection();

            $total = (int) $conn->fetchOne('SELECT COUNT(*) FROM tenants');
            $active = (int) $conn->fetchOne("SELECT COUNT(*) FROM tenants WHERE status = 'active'");
            $suspended = (int) $conn->fetchOne("SELECT COUNT(*) FROM tenants WHERE status = 'suspended'");
            $deleted = (int) $conn->fetchOne("SELECT COUNT(*) FROM tenants WHERE deleted_at IS NOT NULL");

            return compact('total', 'active', 'suspended', 'deleted');
        } catch (\Throwable $e) {
            $this->logger->warning('Dashboard: failed to load tenant stats', ['exception' => $e->getMessage()]);

            return ['total' => 0, 'active' => 0, 'suspended' => 0, 'deleted' => 0];
        }
    }

    /**
     * @return array{total_plans: int, tenants_without_plan: int}
     */
    public function getPlanStats(): array
    {
        try {
            $conn = $this->getConnection();

            $totalPlans = (int) $conn->fetchOne('SELECT COUNT(*) FROM plans');
            $tenantsWithoutPlan = (int) $conn->fetchOne(
                "SELECT COUNT(*) FROM tenants WHERE plan IS NULL OR plan = ''",
            );

            return [
                'total_plans' => $totalPlans,
                'tenants_without_plan' => $tenantsWithoutPlan,
            ];
        } catch (\Throwable $e) {
            $this->logger->warning('Dashboard: failed to load plan stats', ['exception' => $e->getMessage()]);

            return ['total_plans' => 0, 'tenants_without_plan' => 0];
        }
    }

    /**
     * @return array<int, array{action: string, count: int}>
     */
    public function getRecentAuditSummary(int $hours = 24): array
    {
        try {
            $conn = $this->getConnection();

            if (!$conn->createSchemaManager()->tablesExist(['audit_logs'])) {
                return [];
            }

            $since = (new \DateTimeImmutable(sprintf('-%d hours', $hours)))->format('Y-m-d H:i:s');

            return $conn->fetchAllAssociative(
                'SELECT action, COUNT(*) as count
                 FROM audit_logs
                 WHERE created_at >= ?
                 GROUP BY action
                 ORDER BY count DESC
                 LIMIT 20',
                [$since],
            );
        } catch (\Throwable $e) {
            $this->logger->warning('Dashboard: failed to load audit summary', ['exception' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * @return array{total_backups: int, last_backup_at: ?string, total_size_bytes: int, failed_count: int}
     */
    public function getBackupStats(): array
    {
        try {
            $conn = $this->getConnection();

            if (!$conn->createSchemaManager()->tablesExist(['tenant_backups'])) {
                return ['total_backups' => 0, 'last_backup_at' => null, 'total_size_bytes' => 0, 'failed_count' => 0];
            }

            $totalBackups = (int) $conn->fetchOne("SELECT COUNT(*) FROM tenant_backups WHERE status = 'completed'");
            $lastBackupAt = $conn->fetchOne(
                "SELECT MAX(completed_at) FROM tenant_backups WHERE status = 'completed'",
            );
            $totalSizeBytes = (int) $conn->fetchOne(
                "SELECT COALESCE(SUM(size_bytes), 0) FROM tenant_backups WHERE status = 'completed'",
            );
            $failedCount = (int) $conn->fetchOne("SELECT COUNT(*) FROM tenant_backups WHERE status = 'failed'");

            return [
                'total_backups' => $totalBackups,
                'last_backup_at' => $lastBackupAt !== false && $lastBackupAt !== null ? (string) $lastBackupAt : null,
                'total_size_bytes' => $totalSizeBytes,
                'failed_count' => $failedCount,
            ];
        } catch (\Throwable $e) {
            $this->logger->warning('Dashboard: failed to load backup stats', ['exception' => $e->getMessage()]);

            return ['total_backups' => 0, 'last_backup_at' => null, 'total_size_bytes' => 0, 'failed_count' => 0];
        }
    }

    private function getConnection(): Connection
    {
        return $this->connectionRegistry->getConnection('control_plane');
    }
}
