<?php

declare(strict_types=1);

namespace App\ControlPlane\Tenant\Command;

use App\ControlPlane\Tenant\Service\TenantBackupService;
use Doctrine\Persistence\ConnectionRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Scheduler\Attribute\AsCronTask;

#[AsCommand(
    name: 'app:tenant:backup:schedule',
    description: 'Run scheduled backups for tenants based on their backup_frequency setting.',
)]
#[AsCronTask('0 */1 * * *')]
final class TenantBackupScheduleCommand extends Command
{
    public function __construct(
        private readonly TenantBackupService $backupService,
        private readonly ConnectionRegistry $connectionRegistry,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $conn = $this->connectionRegistry->getConnection('control_plane');
        $tenants = $conn->fetchAllAssociative(
            "SELECT id, name, backup_frequency FROM tenants WHERE status = 'active' AND backup_frequency > 0",
        );

        if ($tenants === []) {
            $io->note('No tenants with backup enabled.');

            return Command::SUCCESS;
        }

        $backed = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($tenants as $tenant) {
            $tenantName = (string) $tenant['name'];
            $frequency = (int) $tenant['backup_frequency'];

            if (!$this->needsBackup((int) $tenant['id'], $frequency)) {
                $skipped++;
                continue;
            }

            try {
                $this->backupService->backup($tenantName, true);
                $backed++;
                $io->writeln(sprintf('  Backed up: %s', $tenantName));
            } catch (\Throwable $e) {
                $failed++;
                $this->logger->error('Scheduled backup failed', [
                    'tenant' => $tenantName,
                    'exception' => $e->getMessage(),
                ]);
                $io->warning(sprintf('  Failed: %s - %s', $tenantName, $e->getMessage()));
            }
        }

        $io->success(sprintf('Done. Backed up: %d, Skipped: %d, Failed: %d', $backed, $skipped, $failed));

        return Command::SUCCESS;
    }

    private function needsBackup(int $tenantId, int $frequencyHours): bool
    {
        $conn = $this->connectionRegistry->getConnection('control_plane');

        $lastBackup = $conn->fetchOne("SELECT created_at FROM tenant_backups WHERE tenant_id = ? AND status = 'completed' ORDER BY created_at DESC LIMIT 1", [
            $tenantId,
        ]);

        if ($lastBackup === false || $lastBackup === null) {
            return true;
        }

        $lastBackupTime = new \DateTimeImmutable((string) $lastBackup);
        $nextBackupTime = $lastBackupTime->modify(sprintf('+%d hours', $frequencyHours));

        return new \DateTimeImmutable() >= $nextBackupTime;
    }
}
