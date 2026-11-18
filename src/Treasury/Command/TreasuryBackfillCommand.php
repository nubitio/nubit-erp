<?php

declare(strict_types=1);

namespace App\Treasury\Command;

use App\Treasury\Service\TreasuryBackfill;
use App\Treasury\Service\TreasuryGaps;
use DateTimeImmutable;
use Exception;
use Nubit\Platform\Symfony\Console\PerTenantCommand;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'treasury:backfill',
    description: 'Record the non-cash POS and supplier payments made since activation that have no Treasury movement. Idempotent.',
)]
final class TreasuryBackfillCommand extends PerTenantCommand
{
    public function __construct(
        private readonly TreasuryBackfill $backfill,
        private readonly TreasuryGaps $gaps,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        parent::configure();
        $this->addOption(
            'from',
            null,
            InputOption::VALUE_REQUIRED,
            'First day to look at (YYYY-MM-DD). Default: the day of the earliest opening balance.',
        )->addOption('dry-run', null, InputOption::VALUE_NONE, 'Only count what would be recorded.');
    }

    #[Override]
    protected function executeTenantCommand(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $fromOption = $input->getOption('from');
            $from = is_string($fromOption) && $fromOption !== ''
                ? new DateTimeImmutable($fromOption)
                : $this->gaps->activationDate();
        } catch (Exception) {
            $io->error('--from must be a date like 2026-11-18.');

            return Command::INVALID;
        }
        if ($from === null) {
            $io->error('No opening balance has been entered yet; pass --from=YYYY-MM-DD explicitly.');

            return Command::INVALID;
        }

        $result = $this->backfill->run($from, (bool) $input->getOption('dry-run'));
        $io->success(sprintf(
            '%s %d payment(s) since %s%s.',
            $input->getOption('dry-run') ? 'Would record' : 'Recorded',
            $input->getOption('dry-run') ? $result['found'] : $result['recorded'],
            $from->format('Y-m-d'),
            $result['suspense'] > 0 ? sprintf(', %d of them in the suspense account', $result['suspense']) : '',
        ));
        if ($result['failed'] !== []) {
            $io->warning(sprintf('%d payment(s) could not be recorded:', count($result['failed'])));
            $io->listing($result['failed']);

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
