<?php

declare(strict_types=1);

namespace App\Sales\Command;

use App\Core\Application\Command\TenantAwareCommand;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Scheduler\Attribute\AsCronTask;

#[AsCommand(
    name: 'app:sales:rebuild-daily-summaries',
    description: 'Rebuild branch_daily_summary for the last N days from the sales table.',
)]
#[AsCronTask('*/30 * * * *', arguments: '--days=2')]
final class SalesV2RebuildDailySummariesCommand extends TenantAwareCommand
{
    public function __construct(
        private readonly Connection $connection,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        parent::configure();

        $this->addOption(
            'days',
            null,
            InputOption::VALUE_OPTIONAL,
            'Number of rolling days to rebuild (counting back from today).',
            2
        );
    }

    #[Override]
    protected function executeTenantCommand(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $days = max(1, (int) ($input->getOption('days') ?? 2));

        $today = new \DateTimeImmutable('today', new \DateTimeZone('America/Lima'));
        $toDate = $today;
        $fromDate = $today->modify(sprintf('-%d days', $days - 1));

        $from = $fromDate->format('Y-m-d');
        $to = $toDate->format('Y-m-d');

        try {
            $this->connection->fetchOne('SELECT 1 FROM branch_daily_summary LIMIT 1');
            $this->connection->fetchOne('SELECT 1 FROM sales LIMIT 1');
        } catch (TableNotFoundException) {
            $io->warning('Skipping tenant: branch_daily_summary or sales table not yet migrated.');

            return Command::SUCCESS;
        }

        try {
            $this->connection->beginTransaction();

            $this->connection->executeStatement(
                'DELETE FROM branch_daily_summary WHERE summary_date BETWEEN :from AND :to',
                ['from' => $from, 'to' => $to]
            );

            $this->connection->executeStatement(
                $this->rebuildSql(),
                ['from' => $from, 'to' => $to]
            );

            $this->connection->commit();
        } catch (\Throwable $throwable) {
            $this->connection->rollBack();
            throw $throwable;
        }

        $io->success(sprintf('branch_daily_summary rebuilt for %s to %s.', $from, $to));

        return Command::SUCCESS;
    }

    private function rebuildSql(): string
    {
        return <<<'SQL'
INSERT INTO branch_daily_summary
    (summary_date, branch_id, sales_count, gross_sales, discount_total, tax_total, net_sales, refunds_total)
SELECT
    DATE(CONVERT_TZ(issued_at, '+00:00', 'America/Lima'))     AS summary_date,
    branch_id,
    COUNT(*)                                                   AS sales_count,
    COALESCE(SUM(grand_total), 0)                              AS gross_sales,
    COALESCE(SUM(discount_total), 0)                           AS discount_total,
    COALESCE(SUM(tax_total), 0)                                AS tax_total,
    COALESCE(SUM(grand_total - tax_total), 0)                  AS net_sales,
    0                                                          AS refunds_total
FROM sales
WHERE DATE(CONVERT_TZ(issued_at, '+00:00', 'America/Lima')) BETWEEN :from AND :to
  AND status != 'VOIDED'
GROUP BY DATE(CONVERT_TZ(issued_at, '+00:00', 'America/Lima')), branch_id
SQL;
    }
}
