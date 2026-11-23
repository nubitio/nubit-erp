<?php

declare(strict_types=1);

namespace App\Purchasing\Command;

use App\Purchasing\Service\PurchaseSummaryRebuilder;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Nubit\Platform\Symfony\Console\PerTenantCommand;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Scheduler\Attribute\AsCronTask;

#[AsCommand(
    name: 'app:purchases:v2:rebuild-daily-summaries',
    description: 'Rebuild Purchases V2 daily summary tables for a date range.',
)]
#[AsCronTask('20 * * * *', arguments: '--days=2 --parallel=4')]
final class PurchasesV2RebuildDailySummariesCommand extends PerTenantCommand
{
    public function __construct(
        private readonly Connection $connection,
        private readonly PurchaseSummaryRebuilder $rebuilder,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        parent::configure();

        $this->addOption('from', null, InputOption::VALUE_OPTIONAL, 'Start date (Y-m-d). Defaults to 30 days ago.');
        $this->addOption('to', null, InputOption::VALUE_OPTIONAL, 'End date (Y-m-d). Defaults to today.');
        $this->addOption(
            'days',
            null,
            InputOption::VALUE_OPTIONAL,
            'Rolling window in days when --from/--to are not provided.',
            30,
        );
    }

    #[Override]
    protected function executeTenantCommand(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $toRaw = (string) ($input->getOption('to') ?? '');
            $fromRaw = (string) ($input->getOption('from') ?? '');
            $days = max(1, (int) ($input->getOption('days') ?? 30));

            $toDate = $this->parseDate($toRaw, new \DateTimeImmutable('today', new \DateTimeZone('America/Lima')));
            $fromDefault = $toDate->modify(sprintf('-%d days', $days - 1));
            $fromDate = $this->parseDate($fromRaw, $fromDefault);
        } catch (\InvalidArgumentException $exception) {
            $io->error($exception->getMessage());
            return Command::FAILURE;
        }

        if ($fromDate > $toDate) {
            $io->error('Invalid range: --from cannot be after --to.');
            return Command::FAILURE;
        }

        $from = $fromDate->format('Y-m-d');
        $to = $toDate->format('Y-m-d');

        try {
            $this->connection->beginTransaction();

            $this->rebuilder->rebuild($from, $to);

            $this->connection->commit();
        } catch (TableNotFoundException) {
            $this->connection->rollBack();
            $io->warning('Skipping tenant: purchases summary tables are not yet migrated.');

            return Command::SUCCESS;
        } catch (\Throwable $throwable) {
            $this->connection->rollBack();
            throw $throwable;
        }

        $io->success(sprintf('Purchases daily summaries rebuilt for range %s to %s.', $from, $to));

        return Command::SUCCESS;
    }

    private function parseDate(string $raw, \DateTimeImmutable $default): \DateTimeImmutable
    {
        $value = trim($raw);
        if ($value === '') {
            return $default;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);
        if (!$date instanceof \DateTimeImmutable) {
            throw new \InvalidArgumentException(sprintf('Invalid date format: %s (expected Y-m-d).', $value));
        }

        return $date;
    }
}
