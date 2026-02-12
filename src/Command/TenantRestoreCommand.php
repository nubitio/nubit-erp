<?php

declare(strict_types=1);

namespace App\Command;

use App\Core\Service\TenantBackupService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:tenant:restore',
    description: 'Restore a tenant database from a backup.',
)]
class TenantRestoreCommand extends Command
{
    public function __construct(
        private readonly TenantBackupService $backupService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('backup-id', InputArgument::REQUIRED, 'Backup ID to restore from');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $backupId = (int) $input->getArgument('backup-id');

        if ($backupId <= 0) {
            $io->error('Invalid backup ID.');

            return Command::FAILURE;
        }

        if (!$io->confirm(sprintf('Restore tenant from backup #%d? This will overwrite the current database.', $backupId), false)) {
            $io->note('Restore cancelled.');

            return Command::SUCCESS;
        }

        try {
            $this->backupService->restore($backupId);
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf('Tenant restored from backup #%d.', $backupId));

        return Command::SUCCESS;
    }
}
