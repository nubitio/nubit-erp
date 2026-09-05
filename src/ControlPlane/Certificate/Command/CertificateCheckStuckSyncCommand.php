<?php

declare(strict_types=1);

namespace App\ControlPlane\Certificate\Command;

use App\ControlPlane\Certificate\CertificateLifecycleServiceInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Monitoring command for SUNAT certificates stuck in a non-"synced" Lycet
 * status (upsert_pending, upsert_failed, revocation_pending,
 * revocation_failed). CertificateLifecycleService::upsert()/revoke() swallow
 * Lycet failures and record them via markLycetSyncFailed() instead of
 * throwing, so the tenant-facing HTTP response never surfaces this — nothing
 * previously watched for a certificate staying stuck.
 *
 * Automatic re-submission isn't safe here: retrying an upsert needs the
 * original PEM certificate and SOL password, which aren't persisted (see
 * CertificateRetrySyncCommand, the existing manual per-tenant retry tool).
 * This command's job is to surface the problem — via logs and a non-zero
 * exit code a cron/monitoring wrapper can alert on — so an operator runs
 * that manual retry with fresh credentials.
 */
#[AsCommand(
    name: 'app:control-plane:certificate:check-stuck-sync',
    description: 'List and log SUNAT certificates stuck in a non-synced Lycet status.',
)]
final class CertificateCheckStuckSyncCommand extends Command
{
    public function __construct(
        private readonly CertificateLifecycleServiceInterface $certificates,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'minutes',
            null,
            InputOption::VALUE_OPTIONAL,
            'How long a certificate must have been stuck before it is reported',
            '30',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $minutesOption = $input->getOption('minutes');

        if (!is_string($minutesOption) || !ctype_digit($minutesOption) || (int) $minutesOption < 1) {
            $io->error('--minutes must be a positive integer.');

            return Command::INVALID;
        }

        $minutesThreshold = (int) $minutesOption;

        $stuckCertificates = $this->certificates->findStuckLycetSyncs($minutesThreshold);

        if ($stuckCertificates === []) {
            $io->success(sprintf(
                'No certificates stuck in a non-synced Lycet status for %d+ minutes.',
                $minutesThreshold,
            ));

            return Command::SUCCESS;
        }

        $io->table(['Tenant', 'RUC', 'Lycet status', 'Last sync attempt', 'Error'], array_map(
            static fn($cert): array => [
                $cert->tenantName,
                $cert->ruc,
                $cert->lycetSyncStatus,
                $cert->lycetSyncUpdatedAt?->format('Y-m-d H:i:s') ?? '—',
                $cert->lycetSyncError ?? '—',
            ],
            $stuckCertificates,
        ));

        foreach ($stuckCertificates as $cert) {
            $this->logger->warning('Tenant certificate stuck in non-synced Lycet status', [
                'tenantName' => $cert->tenantName,
                'ruc' => $cert->ruc,
                'certificateId' => $cert->id,
                'lycetSyncStatus' => $cert->lycetSyncStatus,
                'lycetSyncError' => $cert->lycetSyncError,
                'lycetSyncUpdatedAt' => $cert->lycetSyncUpdatedAt?->format(DATE_ATOM),
            ]);
        }

        $io->warning(sprintf(
            '%d certificate(s) stuck for %d+ minutes. Retry with app:control-plane:certificate:retry-sync using fresh credentials.',
            count($stuckCertificates),
            $minutesThreshold,
        ));

        return Command::FAILURE;
    }
}
