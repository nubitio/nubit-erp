<?php

declare(strict_types=1);

namespace App\Sales\Command;

use App\Sales\Receivable\ReceivablesBackfill;
use Nubit\Platform\Symfony\Console\PerTenantCommand;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'receivables:backfill',
    description: 'Open the receivable of every credit sale that still owes money and has none. Idempotent; --dry-run only counts.',
)]
final class ReceivablesBackfillCommand extends PerTenantCommand
{
    public function __construct(
        private readonly ReceivablesBackfill $backfill,
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
            'Count the sales that would be opened and change nothing.',
        );
    }

    #[Override]
    protected function executeTenantCommand(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        $result = $this->backfill->run($dryRun);

        $io->success(
            $dryRun
                ? sprintf('%d sale(s) would get a receivable. Nothing was changed.', $result['candidates'])
                : sprintf(
                    '%d candidate(s): %d opened, %d skipped. Run finance:audit to verify.',
                    $result['candidates'],
                    $result['opened'],
                    $result['skipped'],
                ),
        );

        return Command::SUCCESS;
    }
}
