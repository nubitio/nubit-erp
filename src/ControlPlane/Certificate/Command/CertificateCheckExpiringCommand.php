<?php

declare(strict_types=1);

namespace App\ControlPlane\Certificate\Command;

use App\ControlPlane\Certificate\CertificateAlertServiceInterface;
use App\ControlPlane\Certificate\CertificateLifecycleServiceInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:control-plane:certificate:check-expiring', description: 'Refresh and list expiring tenant SUNAT certificates.')]
final class CertificateCheckExpiringCommand extends Command
{
    public function __construct(
        private readonly CertificateLifecycleServiceInterface $certificates,
        private readonly CertificateAlertServiceInterface $alerts,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('days', null, InputOption::VALUE_OPTIONAL, 'Expiration window in days', '30')
            ->addOption('notify', null, InputOption::VALUE_NONE, 'Send email and in-app/Mercure alerts to tenant admins');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $certificates = $this->certificates->refreshExpiringStatuses((int)$input->getOption('days'));
        $io->table(['Tenant', 'RUC', 'Status', 'Expires'], array_map(static fn ($cert): array => [
            $cert->tenantName,
            $cert->ruc,
            $cert->status->value,
            $cert->expiresAt->format('Y-m-d'),
        ], $certificates));

        if ($input->getOption('notify')) {
            $sent = $this->alerts->sendExpiringAlerts((int)$input->getOption('days'));
            $io->success(sprintf('Certificate alerts sent for %d certificate(s).', $sent));
        }

        return Command::SUCCESS;
    }
}
