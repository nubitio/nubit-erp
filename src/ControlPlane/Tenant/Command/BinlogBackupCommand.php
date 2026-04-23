<?php

declare(strict_types=1);

namespace App\ControlPlane\Tenant\Command;

use App\Core\Service\BinlogBackupService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Scheduler\Attribute\AsCronTask;

#[AsCommand(
    name: 'app:binlog:backup',
    description: 'Backup MariaDB binary logs for point-in-time recovery.',
)]
#[AsCronTask('*/15 * * * *')]
class BinlogBackupCommand extends Command
{
    public function __construct(
        private readonly BinlogBackupService $binlogBackupService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $result = $this->binlogBackupService->backupBinlogs();
        } catch (\Throwable $e) {
            $io->error('Binlog backup failed: ' . $e->getMessage());

            return Command::FAILURE;
        }

        if ($result['skipped']) {
            $io->info($result['message']);
        } else {
            $io->success($result['message']);
        }

        return Command::SUCCESS;
    }
}
