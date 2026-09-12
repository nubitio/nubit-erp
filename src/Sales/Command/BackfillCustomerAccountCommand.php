<?php

declare(strict_types=1);

namespace App\Sales\Command;

use App\Sales\Service\CustomerAccountBackfiller;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Nubit\Platform\Symfony\Console\PerTenantCommand;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Backfills the customer current account (#367 / #384) from historical credit
 * sales and their payments. Run once per tenant at rollout, before much new
 * activity — customers that already have ledger entries are skipped.
 */
#[AsCommand(
    name: 'app:sales:backfill-customer-account',
    description: 'Rebuild customer_account_entries from historical credit sales and sale_payments.',
)]
final class BackfillCustomerAccountCommand extends PerTenantCommand
{
    public function __construct(
        private readonly CustomerAccountBackfiller $backfiller,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        parent::configure();
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report what would be written without inserting.');
    }

    #[Override]
    protected function executeTenantCommand(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run') === true;

        try {
            $summary = $dryRun ? $this->backfiller->dryRun() : $this->backfiller->run();
        } catch (TableNotFoundException) {
            $io->warning('Skipping tenant: customer-account or Sales tables are not migrated yet.');

            return Command::SUCCESS;
        }

        $io->definitionList(
            ['Customers backfilled' => (string) $summary['customers']],
            ['Charges' => (string) $summary['charges']],
            ['Payments' => (string) $summary['payments']],
            [
                'Skipped (already have entries)' => $summary['skipped'] === []
                    ? '0'
                    : sprintf('%d — ids: %s', count($summary['skipped']), implode(', ', $summary['skipped'])),
            ],
        );

        $io->success($dryRun ? 'Dry run complete — nothing written.' : 'Backfill complete.');

        return Command::SUCCESS;
    }
}
