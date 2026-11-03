<?php

declare(strict_types=1);

namespace App\Sales\Command;

use App\Sales\Repository\CollectionReminderRepository;
use DateTimeImmutable;
use DateTimeZone;
use Nubit\Platform\Symfony\Console\PerTenantCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Enforces the twelve-month retention of collection reminders, which store customer
 * phones, e-mails and message text.
 *
 * Schedule: daily via Symfony Scheduler (see Schedule.php).
 *
 * Usage:
 *   php bin/console app:receivables:purge-reminders              # all tenants, 12 months
 *   php bin/console app:receivables:purge-reminders --months=6   # custom retention
 */
#[AsCommand(
    name: 'app:receivables:purge-reminders',
    description: 'Delete collection reminders older than the retention period (default 12 months)',
)]
final class PurgeCollectionRemindersCommand extends PerTenantCommand
{
    private const int DEFAULT_RETENTION_MONTHS = 12;

    public function __construct(
        private readonly CollectionReminderRepository $reminders,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        parent::configure();
        $this->addOption(
            'months',
            null,
            InputOption::VALUE_REQUIRED,
            'Retention in months',
            (string) self::DEFAULT_RETENTION_MONTHS,
        );
    }

    protected function executeTenantCommand(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $months = (int) $input->getOption('months');
        if ($months < 1) {
            $io->error('--months must be at least 1.');

            return self::FAILURE;
        }

        $cutoff = new DateTimeImmutable('now', new DateTimeZone('America/Lima'))->modify(sprintf(
            '-%d months',
            $months,
        ));
        $deleted = $this->reminders->deleteOlderThan($cutoff);
        $io->text(sprintf(
            '[%s] Deleted %d reminder(s) older than %s.',
            (string) ($this->currentTenantName ?? 'default'),
            $deleted,
            $cutoff->format('Y-m-d'),
        ));

        return self::SUCCESS;
    }
}
