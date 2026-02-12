<?php

declare(strict_types=1);

namespace App\Command;

use App\Core\Service\TenantBackupService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:tenant:backup',
    description: 'Create a mariadb-dump backup of a tenant database.',
)]
class TenantBackupCommand extends Command
{
    public function __construct(
        private readonly TenantBackupService $backupService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('tenant', InputArgument::REQUIRED, 'Tenant name to backup')
            ->addOption('upload-s3', null, InputOption::VALUE_NEGATABLE, 'Upload backup to S3', true)
            ->addOption('output-dir', 'o', InputOption::VALUE_OPTIONAL, 'Output directory (local-only backups)', 'var/backups');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $tenantName = trim((string) $input->getArgument('tenant'));
        $uploadToS3 = (bool) $input->getOption('upload-s3');

        if ($tenantName === '') {
            $io->error('Tenant name is required.');

            return Command::FAILURE;
        }

        $io->section(sprintf('Backing up tenant "%s"', $tenantName));

        try {
            $result = $this->backupService->backup($tenantName, $uploadToS3);
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $sizeMb = round($result['size_bytes'] / 1024 / 1024, 2);
        $io->success(sprintf(
            'Backup #%d saved: %s (%s MB, %s)',
            $result['id'],
            $result['filename'],
            $sizeMb,
            $result['storage_type'],
        ));

        return Command::SUCCESS;
    }
}
