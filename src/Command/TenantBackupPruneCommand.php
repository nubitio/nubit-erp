<?php

declare(strict_types=1);

namespace App\Command;

use App\Core\Service\BinlogBackupService;
use App\Core\Service\TenantBackupService;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\Persistence\ConnectionRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Scheduler\Attribute\AsCronTask;

#[AsCommand(
    name: 'app:tenant:backup:prune',
    description: 'Prune expired tenant backups and binlog backups based on retention policies.',
)]
#[AsCronTask('0 3 * * *')]
class TenantBackupPruneCommand extends Command
{
    public function __construct(
        private readonly TenantBackupService $backupService,
        private readonly BinlogBackupService $binlogBackupService,
        private readonly ConnectionRegistry $connectionRegistry,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be pruned without deleting');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool)$input->getOption('dry-run');

        if ($dryRun) {
            $io->note('Dry-run mode: no backups will be deleted.');
        }

        $conn = $this->getControlPlaneConnection();

        $prunedFull = $this->pruneExpiredFullBackups($conn, $io, $dryRun);
        $prunedStale = $this->pruneStaleBackups($conn, $io, $dryRun);
        $prunedBinlog = $this->pruneBinlogBackups($conn, $io, $dryRun);

        $io->success(sprintf(
            'Pruning complete: %d full backup(s), %d stale backup(s), %d binlog backup(s) %s.',
            $prunedFull,
            $prunedStale,
            $prunedBinlog,
            $dryRun ? 'would be pruned' : 'pruned',
        ));

        return Command::SUCCESS;
    }

    private function pruneExpiredFullBackups(Connection $conn, SymfonyStyle $io, bool $dryRun): int
    {
        $io->section('Pruning expired full backups');

        $tenants = $conn->fetchAllAssociative(
            "SELECT id, name, backup_retention_days FROM tenants WHERE status = 'active' AND backup_retention_days > 0",
        );

        $pruned = 0;

        foreach ($tenants as $tenant) {
            $tenantId = (int)$tenant['id'];
            $retentionDays = (int)$tenant['backup_retention_days'];

            $recentIds = $conn->fetchFirstColumn(
                "SELECT id FROM tenant_backups
                 WHERE tenant_id = ? AND backup_type = 'full' AND status = 'completed'
                 ORDER BY completed_at DESC LIMIT 2",
                [$tenantId],
                [ParameterType::INTEGER],
            );

            $query = "SELECT id FROM tenant_backups
                      WHERE tenant_id = ?
                        AND backup_type = 'full'
                        AND status = 'completed'
                        AND completed_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
            $params = [$tenantId, $retentionDays];
            $types = [ParameterType::INTEGER, ParameterType::INTEGER];

            if ($recentIds !== []) {
                $placeholders = implode(',', array_fill(0, count($recentIds), '?'));
                $query .= " AND id NOT IN ($placeholders)";
                foreach ($recentIds as $rid) {
                    $params[] = (int)$rid;
                    $types[] = ParameterType::INTEGER;
                }
            }

            $expiredIds = $conn->fetchFirstColumn($query, $params, $types);

            foreach ($expiredIds as $backupId) {
                $io->text(sprintf(
                    '  %s backup #%d for tenant "%s" (retention: %d days)',
                    $dryRun ? 'Would prune' : 'Pruning',
                    $backupId,
                    $tenant['name'],
                    $retentionDays,
                ));

                if (!$dryRun) {
                    try {
                        $this->backupService->deleteBackup((int)$backupId);
                    } catch (\Throwable $e) {
                        $this->logger->error('Failed to prune backup #{id}: {error}', [
                            'id' => $backupId,
                            'error' => $e->getMessage(),
                        ]);
                        $io->warning(sprintf('Failed to prune backup #%d: %s', $backupId, $e->getMessage()));
                        continue;
                    }
                }

                $pruned++;
            }
        }

        return $pruned;
    }

    private function pruneStaleBackups(Connection $conn, SymfonyStyle $io, bool $dryRun): int
    {
        $io->section('Pruning stale failed/pending backups (>7 days)');

        $staleIds = $conn->fetchFirstColumn(
            "SELECT id FROM tenant_backups
             WHERE status IN ('failed', 'pending')
               AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)",
        );

        $pruned = 0;

        foreach ($staleIds as $backupId) {
            $io->text(sprintf(
                '  %s stale backup #%d',
                $dryRun ? 'Would prune' : 'Pruning',
                $backupId,
            ));

            if (!$dryRun) {
                try {
                    $this->backupService->deleteBackup((int)$backupId);
                } catch (\Throwable $e) {
                    $this->logger->error('Failed to prune stale backup #{id}: {error}', [
                        'id' => $backupId,
                        'error' => $e->getMessage(),
                    ]);
                    $io->warning(sprintf('Failed to prune stale backup #%d: %s', $backupId, $e->getMessage()));
                    continue;
                }
            }

            $pruned++;
        }

        return $pruned;
    }

    private function pruneBinlogBackups(Connection $conn, SymfonyStyle $io, bool $dryRun): int
    {
        $io->section('Pruning expired binlog backups');

        if (!$conn->createSchemaManager()->tablesExist(['binlog_backups'])) {
            $io->text('  binlog_backups table does not exist, skipping.');

            return 0;
        }

        $maxRetention = $conn->fetchOne(
            "SELECT MAX(backup_retention_days) FROM tenants WHERE status = 'active'",
        );

        $retentionDays = (int)$maxRetention;
        if ($retentionDays <= 0) {
            $retentionDays = 30;
        }

        $expiredIds = $conn->fetchFirstColumn(
            "SELECT id FROM binlog_backups
             WHERE status = 'completed'
               AND completed_at < DATE_SUB(NOW(), INTERVAL ? DAY)",
            [$retentionDays],
            [ParameterType::INTEGER],
        );

        $pruned = 0;

        foreach ($expiredIds as $backupId) {
            $io->text(sprintf(
                '  %s binlog backup #%d (retention: %d days)',
                $dryRun ? 'Would prune' : 'Pruning',
                $backupId,
                $retentionDays,
            ));

            if (!$dryRun) {
                try {
                    $this->binlogBackupService->deleteBinlogBackup((int)$backupId);
                } catch (\Throwable $e) {
                    $this->logger->error('Failed to prune binlog backup #{id}: {error}', [
                        'id' => $backupId,
                        'error' => $e->getMessage(),
                    ]);
                    $io->warning(sprintf('Failed to prune binlog backup #%d: %s', $backupId, $e->getMessage()));
                    continue;
                }
            }

            $pruned++;
        }

        return $pruned;
    }

    private function getControlPlaneConnection(): Connection
    {
        return $this->connectionRegistry->getConnection('control_plane');
    }
}
