<?php

declare(strict_types=1);

namespace App\BankReconciliation\Service;

use App\AccountsPayable\Entity\SupplierPayment;
use App\BankReconciliation\Dto\BankStatementLineSuggestionDto;
use App\BankReconciliation\Entity\BankReconciliationMatch;
use App\BankReconciliation\Entity\BankStatementLine;
use App\CashRegister\Entity\CashMovement;
use App\CashRegister\Enum\CashMovementType;
use App\Efact\Domain\Money;
use App\Sales\Entity\SalePayment;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Read-only suggestion/query logic for reconciling a BankStatementLine
 * against the three existing sources of truth (SupplierPayment,
 * SalePayment, CashMovement). Never writes — confirming a match is
 * BankReconciliationMatchService's job.
 */
final readonly class BankReconciliationMatchingService
{
    private const int DATE_WINDOW_DAYS = 3;

    /**
     * Caps the right-hand pane's per-source-type row count. Without this,
     * a tenant with a long sales history returns every unmatched movement
     * ever recorded in one response, which both bloats the payload and (on
     * the frontend) inflates the pane's height enough to break the
     * two-pane layout (align-items: stretch matches the other pane to it).
     */
    private const int UNRECONCILED_LIST_LIMIT = 100;

    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    /**
     * Cascading suggestion tiers:
     *  - EXACT:       same amount, same date, and (if the line has a
     *                  reference) the reference appears in the candidate's
     *                  own reference/description.
     *  - APPROXIMATE: same amount, date within ±3 days.
     * A negative line amount (cargo/salida) only matches outflow sources
     * (SupplierPayment, CashMovement EXPENSE); a positive line amount
     * (abono/entrada) only matches inflow sources (SalePayment,
     * CashMovement INCOME).
     *
     * @return list<BankStatementLineSuggestionDto>
     */
    public function suggestionsFor(BankStatementLine $line): array
    {
        $lineAmount = Money::money($line->getAmount());
        $absAmount = $lineAmount->abs();
        $isOutgoing = $lineAmount->isNegative();

        $from = $line->getTransactionDate()->modify('-' . self::DATE_WINDOW_DAYS . ' days');
        $to = $line->getTransactionDate()->modify('+' . self::DATE_WINDOW_DAYS . ' days');

        $candidates = $isOutgoing
            ? [
                ...$this->supplierPaymentCandidates($absAmount, $from, $to),
                ...$this->cashMovementCandidates($absAmount, $from, $to, CashMovementType::EXPENSE),
            ]
            : [
                ...$this->salePaymentCandidates($absAmount, $from, $to),
                ...$this->cashMovementCandidates($absAmount, $from, $to, CashMovementType::INCOME),
            ];

        $suggestions = [];
        foreach ($candidates as $candidate) {
            $diffDays = (int) $line->getTransactionDate()->diff($candidate['date'])->days;
            $referenceMatches =
                $line->getReference() !== null
                && $line->getReference() !== ''
                && str_contains(mb_strtolower($candidate['label']), mb_strtolower($line->getReference()));

            $confidence =
                $diffDays === 0 && ($line->getReference() === null || $referenceMatches) ? 'EXACT' : 'APPROXIMATE';

            $suggestions[] = new BankStatementLineSuggestionDto(
                sourceType: $candidate['sourceType'],
                sourceId: $candidate['sourceId'],
                amount: Money::toString($absAmount),
                date: $candidate['date']->format('Y-m-d'),
                label: $candidate['label'],
                confidence: $confidence,
            );
        }

        usort(
            $suggestions,
            static fn(BankStatementLineSuggestionDto $a, BankStatementLineSuggestionDto $b): int => (
                ($a->confidence === 'EXACT' ? 0 : 1) <=> ($b->confidence === 'EXACT' ? 0 : 1)
            ),
        );

        return $suggestions;
    }

    /** @return list<SupplierPayment> */
    public function unreconciledSupplierPayments(?DateTimeImmutable $from, ?DateTimeImmutable $to): array
    {
        $qb = $this->em
            ->createQueryBuilder()
            ->select('sp')
            ->from(SupplierPayment::class, 'sp')
            ->leftJoin(BankReconciliationMatch::class, 'm', 'WITH', 'm.supplierPayment = sp')
            ->andWhere('m.id IS NULL');

        if ($from !== null) {
            $qb->andWhere('sp.paidAt >= :from')->setParameter('from', $from);
        }
        if ($to !== null) {
            $qb->andWhere('sp.paidAt <= :to')->setParameter('to', $to);
        }

        return $qb->orderBy('sp.paidAt', 'DESC')->setMaxResults(self::UNRECONCILED_LIST_LIMIT)->getQuery()->getResult();
    }

    /** @return list<SalePayment> */
    public function unreconciledSalePayments(?DateTimeImmutable $from, ?DateTimeImmutable $to): array
    {
        $qb = $this->em
            ->createQueryBuilder()
            ->select('sp')
            ->from(SalePayment::class, 'sp')
            ->leftJoin(BankReconciliationMatch::class, 'm', 'WITH', 'm.salePayment = sp')
            ->andWhere('m.id IS NULL')
            ->andWhere('sp.paidAt IS NOT NULL');

        if ($from !== null) {
            $qb->andWhere('sp.paidAt >= :from')->setParameter('from', $from);
        }
        if ($to !== null) {
            $qb->andWhere('sp.paidAt <= :to')->setParameter('to', $to);
        }

        return $qb->orderBy('sp.paidAt', 'DESC')->setMaxResults(self::UNRECONCILED_LIST_LIMIT)->getQuery()->getResult();
    }

    /** @return list<CashMovement> */
    public function unreconciledCashMovements(?DateTimeImmutable $from, ?DateTimeImmutable $to): array
    {
        $qb = $this->em
            ->createQueryBuilder()
            ->select('cm')
            ->from(CashMovement::class, 'cm')
            ->leftJoin(BankReconciliationMatch::class, 'm', 'WITH', 'm.cashMovement = cm')
            ->andWhere('m.id IS NULL');

        if ($from !== null) {
            $qb->andWhere('cm.createdAt >= :from')->setParameter('from', $from);
        }
        if ($to !== null) {
            $qb->andWhere('cm.createdAt <= :to')->setParameter('to', $to);
        }

        return $qb
            ->orderBy('cm.createdAt', 'DESC')
            ->setMaxResults(self::UNRECONCILED_LIST_LIMIT)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<array{sourceType: string, sourceId: int|string, date: DateTimeImmutable, label: string}>
     */
    private function supplierPaymentCandidates(
        \Brick\Math\BigDecimal $absAmount,
        DateTimeImmutable $from,
        DateTimeImmutable $to,
    ): array {
        $payments = $this->em
            ->createQueryBuilder()
            ->select('sp')
            ->from(SupplierPayment::class, 'sp')
            ->leftJoin(BankReconciliationMatch::class, 'm', 'WITH', 'm.supplierPayment = sp')
            ->andWhere('m.id IS NULL')
            ->andWhere('sp.amount = :amount')
            ->andWhere('sp.paidAt BETWEEN :from AND :to')
            ->setParameter('amount', Money::toString($absAmount))
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getResult();

        return array_map(static fn(SupplierPayment $p): array => [
            'sourceType' => 'supplier_payment',
            'sourceId' => $p->getId(),
            'date' => DateTimeImmutable::createFromInterface($p->getPaidAt()),
            'label' => (string) $p->getReference(),
        ], $payments);
    }

    /**
     * @return list<array{sourceType: string, sourceId: int|string, date: DateTimeImmutable, label: string}>
     */
    private function salePaymentCandidates(
        \Brick\Math\BigDecimal $absAmount,
        DateTimeImmutable $from,
        DateTimeImmutable $to,
    ): array {
        $payments = $this->em
            ->createQueryBuilder()
            ->select('sp')
            ->from(SalePayment::class, 'sp')
            ->leftJoin(BankReconciliationMatch::class, 'm', 'WITH', 'm.salePayment = sp')
            ->andWhere('m.id IS NULL')
            ->andWhere('sp.amount = :amount')
            ->andWhere('sp.paidAt BETWEEN :from AND :to')
            ->setParameter('amount', Money::toString($absAmount))
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getResult();

        return array_map(static fn(SalePayment $p): array => [
            'sourceType' => 'sale_payment',
            'sourceId' => $p->getId(),
            'date' => DateTimeImmutable::createFromInterface($p->getPaidAt()),
            'label' => 'Cobro venta #' . $p->getSaleRecord()->getId(),
        ], $payments);
    }

    /**
     * @return list<array{sourceType: string, sourceId: int|string, date: DateTimeImmutable, label: string}>
     */
    private function cashMovementCandidates(
        \Brick\Math\BigDecimal $absAmount,
        DateTimeImmutable $from,
        DateTimeImmutable $to,
        CashMovementType $type,
    ): array {
        $movements = $this->em
            ->createQueryBuilder()
            ->select('cm')
            ->from(CashMovement::class, 'cm')
            ->leftJoin(BankReconciliationMatch::class, 'm', 'WITH', 'm.cashMovement = cm')
            ->andWhere('m.id IS NULL')
            ->andWhere('cm.amount = :amount')
            ->andWhere('cm.type = :type')
            ->andWhere('cm.createdAt BETWEEN :from AND :to')
            ->setParameter('amount', Money::toString($absAmount))
            ->setParameter('type', $type)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getResult();

        return array_map(static fn(CashMovement $cm): array => [
            'sourceType' => 'cash_movement',
            'sourceId' => $cm->getId(),
            'date' => $cm->getCreatedAt(),
            'label' => $cm->getDescription(),
        ], $movements);
    }
}
