<?php

declare(strict_types=1);

namespace App\ControlPlane\Tenant\Command;

use App\ControlPlane\Backup\BinlogBackupService;
use App\ControlPlane\Tenant\Service\TenantBackupService;
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
        $dryRun = (bool) $input->getOption('dry-run');

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
            $tenantId = (int) $tenant['id'];
            $retentionDays = (int) $tenant['backup_retention_days'];
            $cutoff = (new \DateTimeImmutable(sprintf('-%d days', $retentionDays)))->format('Y-m-d H:i:s');

            $expired = $conn->fetchAllAssociative(
                'SELECT id, filename, storage_type FROM tenant_backups WHERE tenant_id = ? AND created_at < ? AND status = ?',
                [$tenantId, $cutoff, 'completed'],
                [ParameterType::INTEGER, ParameterType::STRING, ParameterType::STRING],
            );

            foreach ($expired as $backup) {
                $io->writeln(sprintf(
                    '  [%s] %s (%s)',
                    $tenant['name'],
                    $backup['filename'],
                    $backup['storage_type'],
                ));

                if (!$dryRun) {
                    try {
                        $this->backupService->deleteBackup((int) $backup['id']);
                        ++$pruned;
                    } catch (\Throwable $e) {
                        $this->logger->warning('Failed to prune backup', [
                            'backup_id' => $backup['id'],
                            'exception' => $e->getMessage(),
                        ]);
                    }
                } else {
                    ++$pruned;
                }
            }
        }

        return $pruned;
    }

    private function pruneStaleBackups(Connection $conn, SymfonyStyle $io, bool $dryRun): int
    {
        $io->section('Pruning stale (failed/pending) backups older than 7 days');

        $cutoff = (new \DateTimeImmutable('-7 days'))->format('Y-m-d H:i:s');

        $stale = $conn->fetchAllAssociative(
            "SELECT id, filename FROM tenant_backups WHERE status IN ('failed', 'pending') AND created_at < ?",
            [$cutoff],
        );

        $pruned = 0;

        foreach ($stale as $backup) {
            $io->writeln(sprintf('  Stale backup: %s', $backup['filename']));

            if (!$dryRun) {
                try {
                    $this->backupService->deleteBackup((int) $backup['id']);
                    ++$pruned;
                } catch (\Throwable $e) {
                    $this->logger->warning('Failed to prune stale backup', [
                        'backup_id' => $backup['id'],
                        'exception' => $e->getMessage(),
                    ]);
                }
            } else {
                ++$pruned;
            }
        }

        return $pruned;
    }

    private function pruneBinlogBackups(Connection $conn, SymfonyStyle $io, bool $dryRun): int
    {
        $io->section('Pruning expired binlog backups');

        $retentionDays = (int) ($conn->fetchOne(
            "SELECT value FROM control_plane_settings WHERE key = 'binlog_retention_days'",
        ) ?: 7);

        $cutoff = (new \DateTimeImmutable(sprintf('-%d days', $retentionDays)))->format('Y-m-d H:i:s');

        $expired = $conn->fetchAllAssociative(
            'SELECT id, filename FROM binlog_backups WHERE created_at < ?',
            [$cutoff],
        );

        $pruned = 0;

        foreach ($expired as $backup) {
            $io->writeln(sprintf('  Binlog: %s', $backup['filename']));

            if (!$dryRun) {
                try {
                    $this->binlogBackupService->deleteBinlogBackup((int) $backup['id']);
                    ++$pruned;
                } catch (\Throwable $e) {
                    $this->logger->warning('Failed to prune binlog backup', [
                        'backup_id' => $backup['id'],
                        'exception' => $e->getMessage(),
                    ]);
                }
            } else {
                ++$pruned;
            }
        }

        return $pruned;
    }

    private function getControlPlaneConnection(): Connection
    {
        return $this->connectionRegistry->getConnection('control_plane');
    }
}
