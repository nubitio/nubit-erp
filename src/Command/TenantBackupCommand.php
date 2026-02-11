<?php

declare(strict_types=1);

namespace App\Command;

use App\Core\Service\TenantManager;
use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ConnectionRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

#[AsCommand(
    name: 'app:tenant:backup',
    description: 'Create a mysqldump backup of a tenant database.',
)]
class TenantBackupCommand extends Command
{
    public function __construct(
        private readonly TenantManager $tenantManager,
        private readonly ConnectionRegistry $connectionRegistry,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('tenant', InputArgument::REQUIRED, 'Tenant name to backup')
            ->addOption('output-dir', 'o', InputOption::VALUE_OPTIONAL, 'Output directory', 'var/backups');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $tenantName = trim((string)$input->getArgument('tenant'));
        $outputDir = rtrim(trim((string)$input->getOption('output-dir')), '/');

        if ($tenantName === '') {
            $io->error('Tenant name is required.');

            return Command::FAILURE;
        }

        $tenant = $this->tenantManager->getTenantByName($tenantName);
        if ($tenant === null) {
            $io->error(sprintf('Tenant "%s" not found.', $tenantName));

            return Command::FAILURE;
        }

        $dbName = (string)($tenant['connection'] ?? $tenantName);

        $params = $this->getConnectionParams();
        $host = (string)($params['host'] ?? 'localhost');
        $port = (string)($params['port'] ?? '3306');
        $user = (string)($params['user'] ?? 'root');
        $password = (string)($params['password'] ?? '');

        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0o750, true);
        }

        $timestamp = date('Ymd_His');
        $filename = sprintf('%s/%s_%s.sql.gz', $outputDir, $dbName, $timestamp);

        $io->section(sprintf('Backing up tenant "%s" (database: %s)', $tenantName, $dbName));

        $dumpCmd = [
            'mysqldump',
            '--host=' . $host,
            '--port=' . $port,
            '--user=' . $user,
            '--single-transaction',
            '--routines',
            '--triggers',
            '--quick',
            $dbName,
        ];

        if ($password !== '') {
            $dumpCmd[] = '--password=' . $password;
        }

        $process = Process::fromShellCommandline(
            implode(' ', array_map('escapeshellarg', $dumpCmd)) . ' | gzip > ' . escapeshellarg($filename)
        );
        $process->setTimeout(null);

        $io->writeln('Running mysqldump...');
        $process->run(function (string $type, string $buffer) use ($io): void {
            if ($type === Process::ERR) {
                // mysqldump writes warnings to stderr, only show non-empty
                $trimmed = trim($buffer);
                if ($trimmed !== '') {
                    $io->note($trimmed);
                }
            }
        });

        if (!$process->isSuccessful()) {
            $io->error('Backup failed: ' . $process->getErrorOutput());

            return Command::FAILURE;
        }

        if (!file_exists($filename) || filesize($filename) === 0) {
            $io->error('Backup file is empty or was not created.');

            return Command::FAILURE;
        }

        $sizeBytes = filesize($filename);
        $sizeMb = round($sizeBytes / 1024 / 1024, 2);

        $io->success(sprintf('Backup saved: %s (%s MB)', $filename, $sizeMb));

        return Command::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function getConnectionParams(): array
    {
        $url = $_ENV['PROVISIONING_DATABASE_URL']
            ?? $_SERVER['PROVISIONING_DATABASE_URL']
            ?? getenv('PROVISIONING_DATABASE_URL')
            ?: '';

        if (is_string($url) && $url !== '') {
            $parts = parse_url($url);
            if ($parts !== false) {
                return [
                    'host' => $parts['host'] ?? 'localhost',
                    'port' => $parts['port'] ?? 3306,
                    'user' => isset($parts['user']) ? urldecode($parts['user']) : 'root',
                    'password' => isset($parts['pass']) ? urldecode($parts['pass']) : '',
                ];
            }
        }

        /** @var Connection $connection */
        $connection = $this->connectionRegistry->getConnection('tenant');
        $params = $connection->getParams();

        return [
            'host' => $params['host'] ?? 'localhost',
            'port' => $params['port'] ?? 3306,
            'user' => $params['user'] ?? 'root',
            'password' => $params['password'] ?? '',
        ];
    }
}
