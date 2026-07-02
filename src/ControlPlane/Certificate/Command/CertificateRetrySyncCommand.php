<?php

declare(strict_types=1);

namespace App\ControlPlane\Certificate\Command;

use App\ControlPlane\Certificate\CertificateLifecycleServiceInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:control-plane:certificate:retry-sync',
    description: 'Retry a pending or failed Lycet certificate operation.',
)]
final class CertificateRetrySyncCommand extends Command
{
    public function __construct(
        private readonly CertificateLifecycleServiceInterface $certificates,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('tenant', InputArgument::REQUIRED, 'Tenant name')
            ->addArgument('ruc', InputArgument::REQUIRED, 'Company RUC')
            ->addOption('operation', null, InputOption::VALUE_REQUIRED, 'Operation to retry: upsert or revoke')
            ->addOption('sol-user', null, InputOption::VALUE_OPTIONAL, 'SOL user required for upsert retry')
            ->addOption(
                'sol-pass-env',
                null,
                InputOption::VALUE_OPTIONAL,
                'Environment variable containing the SOL password for upsert retry',
                'SOL_PASS',
            )
            ->addOption(
                'cert-file',
                null,
                InputOption::VALUE_OPTIONAL,
                'PEM certificate path required for upsert retry',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $operation = (string) ($input->getOption('operation') ?? '');

        try {
            if ($operation === 'revoke') {
                $this->certificates->retryRevocation(
                    (string) $input->getArgument('tenant'),
                    (string) $input->getArgument('ruc'),
                );
                $io->success('Certificate revocation synchronized with Lycet.');

                return Command::SUCCESS;
            }

            if ($operation === 'upsert') {
                $certFile = (string) ($input->getOption('cert-file') ?? '');
                if ($certFile === '' || !file_exists($certFile)) {
                    $io->error('Valid --cert-file is required for upsert retry.');

                    return Command::FAILURE;
                }

                $this->certificates->retryUpsertSync(
                    (string) $input->getArgument('tenant'),
                    (string) $input->getArgument('ruc'),
                    (string) ($input->getOption('sol-user') ?? ''),
                    $this->readSolPassword((string) ($input->getOption('sol-pass-env') ?? 'SOL_PASS')),
                    (string) file_get_contents($certFile),
                );
                $io->success('Certificate upsert synchronized with Lycet.');

                return Command::SUCCESS;
            }
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->error('--operation must be either upsert or revoke.');

        return Command::FAILURE;
    }

    private function readSolPassword(string $envName): string
    {
        $envName = trim($envName);
        if ($envName === '') {
            $envName = 'SOL_PASS';
        }

        $value = $_ENV[$envName] ?? $_SERVER[$envName] ?? getenv($envName);

        return is_string($value) ? $value : '';
    }
}
