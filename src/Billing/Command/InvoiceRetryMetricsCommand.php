<?php

declare(strict_types=1);

namespace App\Billing\Command;

use Nubit\Platform\Symfony\Console\PerTenantCommand;
use App\Efact\Service\SettingsService;
use App\Efact\Config\EfactConstants as Constants;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'invoice:retry:metrics',
    description: 'Show SUNAT send/retry metrics for the current tenant',
)]
class InvoiceRetryMetricsCommand extends PerTenantCommand
{
    public function __construct(
        private readonly \Doctrine\DBAL\Connection $connection,
        private readonly SettingsService $settings,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        parent::configure();
        $this->addOption('hours', null, InputOption::VALUE_REQUIRED, 'Window in hours', '24');
    }

    protected function executeTenantCommand(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $hours = max(1, (int)$input->getOption('hours'));

        $metrics = $this->connection->fetchAssociative(
            "
            SELECT
              COUNT(CASE WHEN event_type = 'sunat_send' THEN 1 END) AS send_attempts,
              COUNT(CASE WHEN event_type = 'sunat_send' AND status = 'accepted' THEN 1 END) AS send_accepted,
              COUNT(CASE WHEN event_type = 'sunat_send' AND status = 'error' THEN 1 END) AS send_errors,
              COUNT(CASE WHEN event_type = 'sunat_send' AND status = 'pending' THEN 1 END) AS send_pending,
              COUNT(CASE
                WHEN event_type = 'sunat_send'
                  AND payload_json LIKE '%\"recovered_by_consult\":true%'
                THEN 1 END) AS recovered_by_consult,
              COUNT(CASE WHEN event_type = 'sunat_retry_skip' AND code = 'backoff' THEN 1 END) AS skipped_backoff,
              COUNT(CASE
                WHEN event_type = 'sunat_retry_skip' AND code = 'max_attempts'
                THEN 1 END) AS skipped_max_attempts,
              COUNT(CASE WHEN event_type = 'sunat_retry_reset' THEN 1 END) AS retry_resets
            FROM tax_document_event
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL :hours HOUR)
            ",
            ['hours' => $hours],
            ['hours' => \Doctrine\DBAL\ParameterType::INTEGER]
        );

        $blockedDocs = (int)$this->connection->fetchOne(
            "
            SELECT COUNT(DISTINCT tax_document_id)
            FROM tax_document_event
            WHERE event_type = 'sunat_retry_skip'
              AND code = 'max_attempts'
              AND created_at >= DATE_SUB(NOW(), INTERVAL :hours HOUR)
            ",
            ['hours' => $hours],
            ['hours' => \Doctrine\DBAL\ParameterType::INTEGER]
        );

        $sunatSendEnabled = filter_var(
            (string)$this->settings->get(Constants::SUNAT_SEND_ENABLED, 'true'),
            FILTER_VALIDATE_BOOLEAN
        );
        $rejectionStreak = (int)$this->settings->get(Constants::SUNAT_REJECTION_STREAK, '0');
        $rejectionThreshold = (int)$this->settings->get(Constants::SUNAT_REJECTION_THRESHOLD, '3');

        $io->title('SUNAT Retry Metrics');
        $io->text(sprintf('Window: last %d hour(s)', $hours));
        $io->text(sprintf(
            'Circuit breaker: %s | streak=%d | threshold=%d',
            $sunatSendEnabled ? 'enabled' : 'paused',
            $rejectionStreak,
            max(1, $rejectionThreshold)
        ));

        $io->table(
            ['Metric', 'Value'],
            [
                ['send_attempts', (string)(int)($metrics['send_attempts'] ?? 0)],
                ['send_accepted', (string)(int)($metrics['send_accepted'] ?? 0)],
                ['send_errors', (string)(int)($metrics['send_errors'] ?? 0)],
                ['send_pending', (string)(int)($metrics['send_pending'] ?? 0)],
                ['recovered_by_consult', (string)(int)($metrics['recovered_by_consult'] ?? 0)],
                ['retry_skipped_backoff', (string)(int)($metrics['skipped_backoff'] ?? 0)],
                ['retry_skipped_max_attempts', (string)(int)($metrics['skipped_max_attempts'] ?? 0)],
                ['docs_blocked_by_max_attempts', (string)$blockedDocs],
                ['manual_retry_resets', (string)(int)($metrics['retry_resets'] ?? 0)],
            ]
        );

        return Command::SUCCESS;
    }
}
