<?php

declare(strict_types=1);

namespace App\Payroll\Command;

use App\Accounting\Service\DefaultPostingService;
use App\Catalog\Entity\PaymentMethod;
use App\Payroll\Entity\PayrollRun;
use App\Payroll\Repository\PayrollRunRepository;
use App\Payroll\Service\PayrollAccountingService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

/**
 * Posts the accrual of runs approved (and the settlement of runs paid) before
 * payroll started posting to the general ledger. Safe to re-run: the journal is
 * keyed by source and source id, so existing entries are returned untouched.
 *
 * The payment method of a historical run was never recorded; every settlement
 * defaults to banco unless --cash is given.
 */
#[AsCommand(
    name: 'app:payroll:ledger-backfill',
    description: 'Post historical approved and paid payroll runs to the general ledger.',
)]
final class PayrollLedgerBackfillCommand extends Command
{
    public function __construct(
        private readonly PayrollRunRepository $runs,
        private readonly PayrollAccountingService $accounting,
        private readonly DefaultPostingService $postingService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'dry-run',
            null,
            InputOption::VALUE_NONE,
            'List what would be posted without writing entries.',
        )->addOption(
            'cash',
            null,
            InputOption::VALUE_NONE,
            'Settle paid runs against caja (101) instead of banco (1041).',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $method = $input->getOption('cash') ? PaymentMethod::CASH : null;

        $posted = 0;
        $failed = 0;

        $runs = $this->runs->findBy(['status' => [PayrollRun::APPROVED, PayrollRun::PAID]], ['period' => 'ASC']);
        foreach ($runs as $run) {
            $events = [$this->accounting->accrualEvent($run)];
            if ($run->getStatus() === PayrollRun::PAID) {
                $events[] = $this->accounting->paymentEvent($run, $method);
            }

            foreach ($events as $event) {
                if ($dryRun) {
                    $io->writeln(sprintf(
                        '%s %s  %s  total %s',
                        $event->kind,
                        $event->occurredOn,
                        $event->description,
                        $event->total,
                    ));
                    ++$posted;

                    continue;
                }

                try {
                    $this->postingService->post($event);
                    ++$posted;
                } catch (Throwable $exception) {
                    ++$failed;
                    $io->warning(sprintf('%s %s: %s', $event->source, $event->sourceId, $exception->getMessage()));
                }
            }
        }

        $io->success(sprintf(
            '%s: %d eventos de planilla%s.',
            $dryRun ? 'Simulación' : 'Procesados',
            $posted,
            $failed > 0 ? sprintf(', %d con error', $failed) : '',
        ));

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
