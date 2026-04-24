<?php

declare(strict_types=1);

namespace App\ControlPlane\Certificate\Command;

use App\ControlPlane\Certificate\CertificateLifecycleService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:control-plane:certificate:list', description: 'List tenant SUNAT certificates.')]
final class CertificateListCommand extends Command
{
    public function __construct(private readonly CertificateLifecycleService $certificates)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('tenant', InputArgument::REQUIRED, 'Tenant name');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        try {
            $rows = array_map(static fn ($cert): array => [
                $cert->id,
                $cert->ruc,
                $cert->status->value,
                $cert->expiresAt->format('Y-m-d'),
                substr($cert->fingerprintSha256, 0, 12),
            ], $this->certificates->list((string)$input->getArgument('tenant')));
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->table(['ID', 'RUC', 'Status', 'Expires', 'Fingerprint'], $rows);

        return Command::SUCCESS;
    }
}
