<?php

declare(strict_types=1);

namespace App\Treasury\Command;

use App\Treasury\Service\PositionQuery;
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
    name: 'treasury:verify',
    description: 'Check that every non-cash POS and supplier payment since activation has its Treasury movement, and report suspense money. Exits non-zero on a gap.',
)]
final class TreasuryVerifyCommand extends PerTenantCommand
{
    public function __construct(
        private readonly TreasuryGaps $gaps,
        private readonly PositionQuery $position,
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
        );
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
            $io->note(
                'No opening balance has been entered yet, so Treasury has not started counting: nothing to verify.',
            );

            return Command::SUCCESS;
        }

        $status = Command::SUCCESS;
        $missing = $this->gaps->missing($from);
        if ($missing === []) {
            $io->success(sprintf(
                'Every non-cash POS and supplier payment since %s has its movement.',
                $from->format('Y-m-d'),
            ));
        } else {
            $io->error(sprintf(
                '%d payment(s) since %s have no Treasury movement. Run treasury:backfill.',
                count($missing),
                $from->format('Y-m-d'),
            ));
            $io->listing(array_map(
                static fn($m): string => sprintf(
                    '%s %s (%s %s)',
                    $m->source->value,
                    $m->sourceId,
                    $m->amount,
                    $m->currency,
                ),
                array_slice($missing, 0, 20),
            ));
            $status = Command::FAILURE;
        }

        foreach ($this->position->accounts() as $row) {
            if ($row['account']->kind->value === 'suspense' && (float) $row['balance'] !== 0.0) {
                $io->warning(sprintf(
                    '%s holds %s %s: assign the payment methods and reclassify it.',
                    $row['account']->name,
                    $row['balance'],
                    $row['account']->currency,
                ));
            }
        }

        return $status;
    }
}
