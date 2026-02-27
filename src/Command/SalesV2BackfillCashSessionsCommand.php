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
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Scheduler\Attribute\AsCronTask;

#[AsCommand(
    name: 'app:sales:v2:backfill-cash-sessions',
    description: 'Backfill cash_sessions from legacy cash_shift.',
)]
#[AsCronTask('5 * * * *', arguments: '--parallel=4')]
final class SalesV2BackfillCashSessionsCommand extends TenantAwareCommand
{
    public function __construct(
        private readonly Connection $connection,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function executeTenantCommand(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $affected = $this->connection->executeStatement(
                <<<'SQL'
INSERT INTO cash_sessions (
    legacy_cash_shift_id,
    cash_register_id,
    branch_id,
    opened_by,
    closed_by,
    status,
    opening_amount,
    expected_amount,
    counted_amount,
    difference_amount,
    opened_at,
    closed_at,
    close_note,
    created_at,
    updated_at
)
SELECT
    cs.id AS legacy_cash_shift_id,
    cs.cash_register_id,
    COALESCE(cs.branch_id, cr.branch_id) AS branch_id,
    cs.opened_by,
    cs.closed_by,
    CASE
        WHEN LOWER(cs.status) = 'open' THEN 'OPEN'
        WHEN LOWER(cs.status) = 'closed' THEN 'CLOSED'
        ELSE 'RECONCILING'
    END AS status,
    COALESCE(cs.opening_balance, 0) AS opening_amount,
    cs.expected_amount,
    cs.counted_amount,
    cs.difference AS difference_amount,
    cs.opened_at,
    cs.closed_at,
    cs.note AS close_note,
    COALESCE(cs.opened_at, NOW()) AS created_at,
    NOW() AS updated_at
FROM cash_shift cs
INNER JOIN cash_register cr ON cr.id = cs.cash_register_id
ON DUPLICATE KEY UPDATE
    cash_register_id = VALUES(cash_register_id),
    branch_id = VALUES(branch_id),
    opened_by = VALUES(opened_by),
    closed_by = VALUES(closed_by),
    status = VALUES(status),
    opening_amount = VALUES(opening_amount),
    expected_amount = VALUES(expected_amount),
    counted_amount = VALUES(counted_amount),
    difference_amount = VALUES(difference_amount),
    opened_at = VALUES(opened_at),
    closed_at = VALUES(closed_at),
    close_note = VALUES(close_note),
    updated_at = NOW()
SQL
            );
        } catch (TableNotFoundException $exception) {
            $io->warning('Skipping tenant: cash_shift/cash_sessions tables are not yet migrated.');

            return Command::SUCCESS;
        }

        $io->success(sprintf('cash_sessions backfill completed (%d affected rows).', $affected));

        return Command::SUCCESS;
    }
}
