<?php

declare(strict_types=1);

namespace App\Command;

use Override;
use App\Core\Application\Command\TenantAwareCommand;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:populate-branch-id',
    description: 'Populate branch_id columns for existing records (for databases not using migrations)',
)]
class PopulateBranchIdCommand extends TenantAwareCommand
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        parent::configure();
    }

    #[Override]
    protected function executeTenantCommand(
        InputInterface $input,
        OutputInterface $output,
    ): int {
        $io = new SymfonyStyle($input, $output);
        $io->section('Populating branch_id columns');

        $connection = $this->entityManager->getConnection();

        $updates = [
            [
                'name' => 'Sales (movimiento)',
                'query' => '
                    UPDATE movimiento m
                    INNER JOIN almacen a ON a.almacen_id = m.almacen_id
                    SET m.branch_id = a.local_id
                    WHERE m.branch_id IS NULL AND a.local_id IS NOT NULL
                ',
            ],
            [
                'name' => 'Cash Shifts (cash_shift)',
                'query' => '
                    UPDATE cash_shift cs
                    INNER JOIN cash_register cr ON cr.id = cs.cash_register_id
                    SET cs.branch_id = cr.branch_id
                    WHERE cs.branch_id IS NULL AND cr.branch_id IS NOT NULL
                ',
            ],
            [
                'name' => 'Cash Movements (cash_movement)',
                'query' => '
                    UPDATE cash_movement cm
                    INNER JOIN cash_shift cs ON cs.id = cm.cash_shift_id
                    SET cm.branch_id = cs.branch_id
                    WHERE cm.branch_id IS NULL AND cs.branch_id IS NOT NULL
                ',
            ],
        ];

        $totalUpdated = 0;

        foreach ($updates as $update) {
            try {
                $affected = $connection->executeStatement($update['query']);
                $totalUpdated += $affected;

                if ($affected > 0) {
                    $io->success(sprintf('%s: %d records updated', $update['name'], $affected));
                    $this->logger->info(sprintf('%s: %d records updated', $update['name'], $affected));
                } else {
                    $io->info(sprintf('%s: No records to update', $update['name']));
                }
            } catch (\Exception $e) {
                $io->warning(sprintf('%s: Skipped - %s', $update['name'], $e->getMessage()));
                $this->logger->warning(sprintf('%s: Skipped - %s', $update['name'], $e->getMessage()));
            }
        }

        $io->newLine();

        if ($totalUpdated > 0) {
            $io->success(sprintf('Total: %d records updated', $totalUpdated));
        } else {
            $io->info('All branch_id columns are already populated');
        }

        return Command::SUCCESS;
    }
}
