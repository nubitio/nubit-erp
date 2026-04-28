<?php

declare(strict_types=1);

namespace App\Purchasing\Command;

use App\Core\Application\Command\TenantAwareCommand;
use Doctrine\DBAL\Connection;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:purchases:menu:migrate',
    description: 'Migrate legacy inbound menu entries to purchases route for existing tenants.',
)]
final class PurchasesMenuMigrateCommand extends TenantAwareCommand
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
            'dry-run',
            null,
            InputOption::VALUE_NONE,
            'Only report rows that would be updated.'
        );
    }

    #[Override]
    protected function executeTenantCommand(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        $count = (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM menu WHERE route = 'inbound'"
        );

        if ($dryRun) {
            $io->note(sprintf('Rows to update: %d', $count));

            return Command::SUCCESS;
        }

        if ($count === 0) {
            $io->note('No legacy inbound menu rows found.');

            return Command::SUCCESS;
        }

        $updated = $this->connection->executeStatement(
            <<<'SQL'
UPDATE menu
SET route = 'purchases',
    key_name = CASE WHEN key_name = 'Ingresos' THEN 'Compras' ELSE key_name END
WHERE route = 'inbound'
SQL
        );

        $io->success(sprintf('Menu migration completed (updated=%d).', $updated));

        return Command::SUCCESS;
    }
}
