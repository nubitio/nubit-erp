<?php

declare(strict_types=1);

namespace App\Sales\Command;

use App\AccountsPayable\Audit\PayablesAudit;
use App\Sales\Audit\ReceivablesAudit;
use Nubit\Platform\Symfony\Console\PerTenantCommand;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'finance:audit',
    description: 'Read-only consistency check of receivables and payables (balances, ledger, applications). Exits non-zero when a rule is violated.',
)]
final class FinanceAuditCommand extends PerTenantCommand
{
    public function __construct(
        private readonly ReceivablesAudit $receivables,
        private readonly PayablesAudit $payables,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function executeTenantCommand(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $violations = 0;

        foreach ([...$this->receivables->run(), ...$this->payables->run()] as $id => $result) {
            if ($result['count'] === 0) {
                $io->writeln(sprintf('<info>OK</info>   %s  %s', $id, $result['description']));

                continue;
            }

            ++$violations;
            $io->writeln(sprintf('<error>FAIL</error> %s  %s (%d)', $id, $result['description'], $result['count']));
            foreach ($result['sample'] as $row) {
                $io->writeln('       ' . json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            }
        }

        if ($violations > 0) {
            $io->error(sprintf('%d rule(s) violated. Nothing was changed.', $violations));

            return Command::FAILURE;
        }

        $io->success('All receivable and payable rules hold.');

        return Command::SUCCESS;
    }
}
