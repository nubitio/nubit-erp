<?php

namespace App\Cashbox\Application\Command;

use App\Cashbox\Infrastructure\CashboxEntity;
use App\CashboxDetail\Infrastructure\CashboxDetailEntity;
use App\Core\Application\Command\TenantAwareCommand;
use App\Core\Infrastructure\Service\TenantConnectionSwitcher;
use App\Core\Infrastructure\Service\TenantManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'cashbox:populate-balance',
    description: 'Populate cashbox balance',
)]
class PopulateCashboxBalanceCommand extends TenantAwareCommand
{
    public function __construct(
        private readonly TenantManager $tenantManager,
        private readonly TenantConnectionSwitcher $tenantConnectionSwitcher,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct($this->tenantManager, $this->tenantConnectionSwitcher);
    }

    protected function configure(): void
    {
        parent::configure();
        $this->setHelp('This command allows you to populate cashbox balance');
    }

    protected function executeTenantCommand(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $batchSize = 50;
        $processed = 0;
        $io->title('Populating cashbox balance');
        $cashboxIds = $this->entityManager->createQueryBuilder()
            ->select('c.caja_id')
            ->from(CashboxEntity::class, 'c')
            ->getQuery()
            ->getArrayResult();
        $io->progressStart(count($cashboxIds));
        foreach ($cashboxIds as $cashboxId) {
            $cashbox = $this->entityManager->find(CashboxEntity::class, $cashboxId);
            $cashboxDetails = $this->entityManager->createQueryBuilder()
                ->select('cd')
                ->from(CashboxDetailEntity::class, 'cd')
                ->where('cd.cashbox = :cashbox')
                ->setParameter('cashbox', $cashbox)
                ->getQuery()
                ->getResult();
            $totalIncome = 0;
            $totalOutcome = 0;
            foreach ($cashboxDetails as $cashboxDetail) {
                if (1 === $cashboxDetail->tipo_id) {
                    $totalIncome += $cashboxDetail->monto;
                } else {
                    $totalOutcome += $cashboxDetail->monto;
                }
            }
            // $cashbox->balance = $totalIncome - $totalOutcome;
            $io->progressAdvance();
            if (($processed % $batchSize) === 0) {
                $this->entityManager->flush();
                $this->entityManager->clear();
            }
            ++$processed;
        }
        $this->entityManager->flush();
        $this->entityManager->clear();
        $io->progressFinish();

        return Command::SUCCESS;
    }
}
