<?php

declare(strict_types=1);

namespace App\Purchasing\Command;

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

#[AsCommand(
    name: 'app:purchases:v2:reconcile',
    description: 'Reconcile legacy inbound movements against Purchases V2 goods receipts by day.',
)]
final class PurchasesV2ReconcileCommand extends TenantAwareCommand
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
            'Rolling window in days.',
            30
        );
    }

    #[Override]
    protected function executeTenantCommand(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $days = max(1, (int) ($input->getOption('days') ?? 30));

        try {
            $this->connection->fetchOne('SELECT 1 FROM movimiento LIMIT 1');
            $this->connection->fetchOne('SELECT 1 FROM goods_receipts LIMIT 1');
        } catch (TableNotFoundException $exception) {
            $io->warning('Skipping tenant: required tables for purchases reconcile are not yet migrated.');

            return Command::SUCCESS;
        }

        $from = (new \DateTimeImmutable('today', new \DateTimeZone('America/Lima')))->modify(sprintf('-%d days', $days - 1))->format('Y-m-d');

        $legacyRows = $this->connection->fetchAllAssociative(
            <<<'SQL'
SELECT
    DATE(COALESCE(fecha, date_created)) AS day,
    COUNT(*) AS cnt,
    ROUND(COALESCE(SUM(total), 0), 2) AS total_amount
FROM movimiento
WHERE tipo_id = 1
  AND DATE(COALESCE(fecha, date_created)) >= :from
GROUP BY DATE(COALESCE(fecha, date_created))
ORDER BY DATE(COALESCE(fecha, date_created))
SQL,
            ['from' => $from]
        );

        $v2Rows = $this->connection->fetchAllAssociative(
            <<<'SQL'
SELECT
    DATE(CONVERT_TZ(issued_at, 'UTC', 'America/Lima')) AS day,
    COUNT(*) AS cnt,
    ROUND(COALESCE(SUM(grand_total), 0), 2) AS total_amount
FROM goods_receipts
WHERE DATE(CONVERT_TZ(issued_at, 'UTC', 'America/Lima')) >= :from
GROUP BY DATE(CONVERT_TZ(issued_at, 'UTC', 'America/Lima'))
ORDER BY DATE(CONVERT_TZ(issued_at, 'UTC', 'America/Lima'))
SQL,
            ['from' => $from]
        );

        $legacyMap = $this->toDayMap($legacyRows);
        $v2Map = $this->toDayMap($v2Rows);
        $daysToCompare = array_unique(array_merge(array_keys($legacyMap), array_keys($v2Map)));
        sort($daysToCompare);

        $rows = [];
        $hasMismatch = false;
        foreach ($daysToCompare as $day) {
            $legacy = $legacyMap[$day] ?? ['cnt' => 0, 'total_amount' => 0.0];
            $v2 = $v2Map[$day] ?? ['cnt' => 0, 'total_amount' => 0.0];

            $countDiff = (int) $legacy['cnt'] - (int) $v2['cnt'];
            $amountDiff = round((float) $legacy['total_amount'] - (float) $v2['total_amount'], 2);
            if ($countDiff !== 0 || abs($amountDiff) > 0.01) {
                $hasMismatch = true;
            }

            $rows[] = [
                $day,
                (string) $legacy['cnt'],
                (string) $v2['cnt'],
                (string) $countDiff,
                number_format((float) $legacy['total_amount'], 2, '.', ''),
                number_format((float) $v2['total_amount'], 2, '.', ''),
                number_format($amountDiff, 2, '.', ''),
            ];
        }

        if ($rows === []) {
            $io->note('No purchases found in the selected window.');

            return Command::SUCCESS;
        }

        $io->table(
            ['Day', 'Legacy Cnt', 'V2 Cnt', 'Cnt Diff', 'Legacy Total', 'V2 Total', 'Amount Diff'],
            $rows
        );

        if ($hasMismatch) {
            $io->warning('Purchases V2 reconcile detected mismatches.');

            return Command::FAILURE;
        }

        $io->success('Purchases V2 reconcile passed with no mismatches.');

        return Command::SUCCESS;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     *
     * @return array<string, array{cnt: int, total_amount: float}>
     */
    private function toDayMap(array $rows): array
    {
        $map = [];
        foreach ($rows as $row) {
            $day = (string) ($row['day'] ?? '');
            if ($day === '') {
                continue;
            }

            $map[$day] = [
                'cnt' => (int) ($row['cnt'] ?? 0),
                'total_amount' => (float) ($row['total_amount'] ?? 0.0),
            ];
        }

        return $map;
    }
}
