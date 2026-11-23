<?php

declare(strict_types=1);

namespace App\Setup\Demo\Minimarket;

use App\CashRegister\Entity\CashSession;
use App\Sales\Entity\SaleRecord;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Turns the story into records, one cashier turn at a time: the cash session, its sales with their
 * fiscal documents and postings, and finally the closing count.
 */
final class MinimarketSalesWriter
{
    /** @var array<string, int> units that left the shelf for good, by product code */
    private array $sold = [];

    /** @var array{sales: int, voided: int, creditNotes: int, shifts: int} */
    private array $stats = ['sales' => 0, 'voided' => 0, 'creditNotes' => 0, 'shifts' => 0];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SaleRecordFactory $sales,
        private readonly FiscalDocuments $documents,
        private readonly SalePostings $postings,
    ) {}

    /**
     * @param list<ShiftPlan> $shifts
     *
     * @return array{stats: array{sales: int, voided: int, creditNotes: int, shifts: int}, sold: array<string, int>}
     */
    public function write(array $shifts, SeedContext $context): array
    {
        foreach ($shifts as $shift) {
            $this->writeShift($shift, $context);
            ++$this->stats['shifts'];
        }

        $this->stats['creditNotes'] = $this->documents->creditNotesIssued();

        return ['stats' => $this->stats, 'sold' => $this->sold];
    }

    private function writeShift(ShiftPlan $shift, SeedContext $context): void
    {
        $user = $context->cashiers[$shift->cashier];
        $userId = (int) $user->getId();
        $session = new CashSession()
            ->setCashRegister($context->cashRegister)
            ->setBranch($context->branch)
            ->setOpenedBy($user)
            ->setStatus('OPEN')
            ->setOpeningAmount(Clock::money($shift->openingAmount))
            ->setOpenedAt(Clock::utcImmutable($shift->opensAt))
            ->setCreatedAt(Clock::utcImmutable($shift->opensAt));
        $this->entityManager->persist($session);
        $this->entityManager->flush();
        $sessionId = (int) $session->getId();
        $this->postings->movement(
            $sessionId,
            'OPENING',
            null,
            Clock::money($shift->openingAmount),
            'Apertura de caja',
            $shift->opensAt,
            $userId,
        );

        $written = [];
        foreach ($shift->sales as $plan) {
            [$series, $number] = $this->documents->nextNumber($plan);
            [$sale, $amounts] = $this->sales->build($plan, $session, $context, $series, $number);
            $this->documents->issue($sale, $plan, $amounts, $context, $series, $number);
            $this->tally($plan);
            $written[] = [$sale, $plan];
        }
        $this->entityManager->flush();

        $cashIn = $this->postAll($written, $sessionId, $context);
        $cashOut = 0.0;
        foreach ($shift->outflows as $outflow) {
            $this->postings->movement(
                $sessionId,
                'CASH_OUT',
                $outflow['category'],
                Clock::money($outflow['amount']),
                $outflow['description'],
                $outflow['at'],
                $userId,
                paymentMethodId: $context->paymentMethods['Efectivo']->getId(),
            );
            $cashOut += $outflow['amount'];
        }

        if ($shift->closesAt !== null && $shift->difference !== null) {
            $this->close($session, $shift, $shift->openingAmount + $cashIn - $cashOut);
        }
    }

    /**
     * @param list<array{0: SaleRecord, 1: SalePlan}> $written
     *
     * @return float the cash that stayed in the drawer
     */
    private function postAll(array $written, int $sessionId, SeedContext $context): float
    {
        $cashIn = 0.0;
        foreach ($written as [$sale, $plan]) {
            $this->postings->post($sale, $plan, $sessionId, $context);
            $cashIn += $plan->keepsCash() ? (float) $sale->getGrandTotal() : 0.0;
        }

        return $cashIn;
    }

    private function close(CashSession $session, ShiftPlan $shift, float $expected): void
    {
        $closedAt = Clock::utcImmutable($shift->closesAt ?? $shift->opensAt);
        $session
            ->setStatus('CLOSED')
            ->setClosedBy($session->getOpenedBy())
            ->setClosedAt($closedAt)
            ->setExpectedAmount(Clock::money($expected))
            ->setCountedAmount(Clock::money($expected + (float) $shift->difference))
            ->setDifferenceAmount(Clock::money((float) $shift->difference))
            ->setCloseNote($shift->closeNote)
            ->setUpdatedAt($closedAt);
        $this->entityManager->flush();
    }

    private function tally(SalePlan $plan): void
    {
        ++$this->stats['sales'];
        if ($plan->outcome !== SalePlan::NORMAL) {
            ++$this->stats['voided'];

            return;
        }
        foreach ($plan->lines as $line) {
            $this->sold[$line['code']] = ($this->sold[$line['code']] ?? 0) + $line['qty'];
        }
    }
}
