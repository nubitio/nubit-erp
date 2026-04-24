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

#[AsCommand(name: 'app:control-plane:certificate:revoke', description: 'Revoke tenant SUNAT certificate metadata and remove it from lycet.')]
final class CertificateRevokeCommand extends Command
{
    public function __construct(private readonly CertificateLifecycleServiceInterface $certificates)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('tenant', InputArgument::REQUIRED, 'Tenant name')
            ->addArgument('ruc', InputArgument::REQUIRED, 'Company RUC')
            ->addOption('reason', null, InputOption::VALUE_REQUIRED, 'Revocation reason')
            ->addOption('revoked-by', null, InputOption::VALUE_OPTIONAL, 'Operator identifier', 'cli');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $reason = trim((string)($input->getOption('reason') ?? ''));
        if ($reason === '') {
            $io->error('--reason is required.');

            return Command::FAILURE;
        }

        try {
            $this->certificates->revoke(
                (string)$input->getArgument('tenant'),
                (string)$input->getArgument('ruc'),
                (string)$input->getOption('revoked-by'),
                $reason,
            );
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->success('Certificate revoked.');

        return Command::SUCCESS;
    }
}
