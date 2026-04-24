<?php

declare(strict_types=1);

namespace App\ControlPlane\Certificate\Command;

use App\ControlPlane\Certificate\CertificateLifecycleService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:control-plane:certificate:upsert', description: 'Upload or renew a tenant SUNAT certificate.')]
final class CertificateUpsertCommand extends Command
{
    public function __construct(private readonly CertificateLifecycleService $certificates)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('tenant', InputArgument::REQUIRED, 'Tenant name')
            ->addArgument('ruc', InputArgument::REQUIRED, 'Company RUC')
            ->addOption('sol-user', null, InputOption::VALUE_REQUIRED, 'SOL user')
            ->addOption('sol-pass', null, InputOption::VALUE_REQUIRED, 'SOL password')
            ->addOption('cert-file', null, InputOption::VALUE_REQUIRED, 'Path to PEM certificate')
            ->addOption('uploaded-by', null, InputOption::VALUE_OPTIONAL, 'Operator identifier');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $certFile = (string)($input->getOption('cert-file') ?? '');
        if ($certFile === '' || !file_exists($certFile)) {
            $io->error('Valid --cert-file is required.');

            return Command::FAILURE;
        }

        try {
            $certificate = $this->certificates->upsert(
                (string)$input->getArgument('tenant'),
                (string)$input->getArgument('ruc'),
                (string)($input->getOption('sol-user') ?? ''),
                (string)($input->getOption('sol-pass') ?? ''),
                (string)file_get_contents($certFile),
                $input->getOption('uploaded-by') !== null ? (string)$input->getOption('uploaded-by') : 'cli',
            );
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf(
            'Certificate %d registered for %s; status=%s expires=%s',
            $certificate->id,
            $certificate->ruc,
            $certificate->status->value,
            $certificate->expiresAt->format('Y-m-d')
        ));

        return Command::SUCCESS;
    }
}
