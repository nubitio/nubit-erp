<?php

declare(strict_types=1);

namespace App\Command;

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
    name: 'app:sales:v2:reconcile',
    description: 'Reconcile legacy movimiento totals against Sales V2 tables by day.',
)]
#[AsCronTask('30 2 * * *', arguments: '--days=7 --tolerance=0.01 --parallel=4')]
final class SalesV2ReconcileCommand extends TenantAwareCommand
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
            'Number of days to reconcile (including today).',
            7
        );
        $this->addOption(
            'tolerance',
            null,
            InputOption::VALUE_OPTIONAL,
            'Absolute amount tolerance for totals diff.',
            '0.01'
        );
    }

    #[Override]
    protected function executeTenantCommand(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $days = max(1, (int) $input->getOption('days'));
        $tolerance = max(0.0, (float) $input->getOption('tolerance'));
        $fromDate = (new \DateTimeImmutable('today'))->modify(sprintf('-%d days', $days - 1))->format('Y-m-d 00:00:00');

        try {
            $legacyRows = $this->connection->fetchAllAssociative(
                <<<'SQL'
SELECT
    DATE(fecha) AS d,
    COUNT(*) AS cnt,
    ROUND(COALESCE(SUM(total), 0), 2) AS total_amount
FROM movimiento
WHERE tipo_id = 2
  AND fecha >= ?
GROUP BY DATE(fecha)
ORDER BY DATE(fecha) ASC
SQL,
                [$fromDate]
            );

            $v2Rows = $this->connection->fetchAllAssociative(
                <<<'SQL'
SELECT
    DATE(issued_at) AS d,
    COUNT(*) AS cnt,
    ROUND(COALESCE(SUM(grand_total), 0), 2) AS total_amount
FROM sales
WHERE issued_at >= ?
GROUP BY DATE(issued_at)
ORDER BY DATE(issued_at) ASC
SQL,
                [$fromDate]
            );
        } catch (TableNotFoundException $exception) {
            $io->warning('Skipping tenant: Sales V2 tables are not yet migrated.');

            return Command::SUCCESS;
        }

        $legacyMap = $this->toDayMap($legacyRows);
        $v2Map = $this->toDayMap($v2Rows);
        $daysToCompare = array_unique(array_merge(array_keys($legacyMap), array_keys($v2Map)));
        sort($daysToCompare);

        if ($daysToCompare === []) {
            $io->warning(sprintf('No rows found for the last %d day(s).', $days));
            return Command::SUCCESS;
        }

        $tableRows = [];
        $mismatches = 0;

        foreach ($daysToCompare as $day) {
            $legacy = $legacyMap[$day] ?? ['cnt' => 0, 'total_amount' => 0.0];
            $v2 = $v2Map[$day] ?? ['cnt' => 0, 'total_amount' => 0.0];

            $countDiff = (int) $legacy['cnt'] - (int) $v2['cnt'];
            $amountDiff = round((float) $legacy['total_amount'] - (float) $v2['total_amount'], 2);
            $ok = $countDiff === 0 && abs($amountDiff) <= $tolerance;

            if (!$ok) {
                ++$mismatches;
            }

            $tableRows[] = [
                $day,
                (string) $legacy['cnt'],
                (string) $v2['cnt'],
                (string) $countDiff,
                number_format((float) $legacy['total_amount'], 2, '.', ''),
                number_format((float) $v2['total_amount'], 2, '.', ''),
                number_format($amountDiff, 2, '.', ''),
                $ok ? 'OK' : 'MISMATCH',
            ];
        }

        $io->table(
            ['Date', 'Legacy Cnt', 'V2 Cnt', 'Cnt Diff', 'Legacy Total', 'V2 Total', 'Total Diff', 'Status'],
            $tableRows
        );

        if ($mismatches > 0) {
            $io->error(sprintf('Sales V2 reconciliation found %d mismatched day(s).', $mismatches));
            return Command::FAILURE;
        }

        $io->success(sprintf(
            'Sales V2 reconciliation OK for %d day(s) with tolerance %.2f.',
            count($daysToCompare),
            $tolerance
        ));

        return Command::SUCCESS;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, array{cnt:int,total_amount:float}>
     */
    private function toDayMap(array $rows): array
    {
        $map = [];
        foreach ($rows as $row) {
            $day = (string) ($row['d'] ?? '');
            if ($day === '') {
                continue;
            }
            $map[$day] = [
                'cnt' => (int) ($row['cnt'] ?? 0),
                'total_amount' => (float) ($row['total_amount'] ?? 0),
            ];
        }

        return $map;
    }
}
