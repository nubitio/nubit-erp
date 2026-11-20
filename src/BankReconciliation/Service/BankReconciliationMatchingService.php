<?php

declare(strict_types=1);

namespace App\BankReconciliation\Service;

use App\AccountsPayable\Entity\SupplierPayment;
use App\BankReconciliation\Dto\BankStatementLineSuggestionDto;
use App\BankReconciliation\Entity\BankReconciliationMatch;
use App\BankReconciliation\Entity\BankStatementLine;
use App\CashRegister\Entity\CashMovement;
use App\CashRegister\Enum\CashMovementType;
use App\CashRegister\Service\CashMovementHydrator;
use App\Efact\Domain\Money;
use App\Sales\Entity\SalePayment;
use App\Sales\Entity\SaleRecord;
use Brick\Math\BigDecimal;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityNotFoundException;

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
        private Connection $connection,
        private CashMovementHydrator $cashMovementHydrator,
        private ?TreasuryReconciliationSource $treasury = null,
    ) {}

    /**
     * Human-friendly label for a sale in reconciliation lists. The customer
     * name is what a user actually recognizes a bank movement by; a
     * truncated id is only a fallback identifier, since the full UUID
     * primary key is meaningless to skim in a match list.
     *
     * $sale is frequently an unitialized proxy here (it's only ever touched
     * for its id/customer, never queried directly), and the underlying row
     * can be gone if the sale was hard-deleted after the payment was made —
     * initializing the proxy then throws instead of returning null.
     */
    public function formatSaleLabel(SaleRecord $sale): string
    {
        $shortId = '#' . substr($sale->getId() ?? '', offset: 0, length: 8);

        try {
            $customerName = $sale->getCustomer()?->getBusinessName();
        } catch (EntityNotFoundException) {
            $customerName = null;
        }

        return 'Cobro venta ' . $shortId . ($customerName !== null ? ' — ' . $customerName : '');
    }

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

        $candidates =
            $this->treasuryCandidates($line, $lineAmount, $from, $to)
            ?? (
                $isOutgoing
                    ? [
                        ...$this->supplierPaymentCandidates($absAmount, $from, $to),
                        ...$this->cashMovementCandidates($absAmount, $from, $to, CashMovementType::EXPENSE),
                    ]
                    : [
                        ...$this->salePaymentCandidates($absAmount, $from, $to),
                        ...$this->cashMovementCandidates($absAmount, $from, $to, CashMovementType::INCOME),
                    ]
            );

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

    /**
     * The candidates from the line's own Treasury account, or null when the old three sources must be used
     * (Treasury off, or this bank account has no Treasury account yet).
     *
     * @return list<array{sourceType: string, sourceId: string, date: DateTimeImmutable, label: string}>|null
     */
    private function treasuryCandidates(
        BankStatementLine $line,
        BigDecimal $lineAmount,
        DateTimeImmutable $from,
        DateTimeImmutable $to,
    ): ?array {
        $bankAccountId = $line->getBankAccount()->getId();
        if ($this->treasury === null || $bankAccountId === null || !$this->treasury->activeFor($bankAccountId)) {
            return null;
        }

        return array_map(
            static fn(array $row): array => [
                'sourceType' => 'treasury_movement',
                'sourceId' => $row['id'],
                'date' => $row['date'],
                'label' => $row['label'],
            ],
            $this->treasury->candidates($bankAccountId, $lineAmount, $from, $to),
        );
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

    /**
     * Reads from the v2 `cash_movements` table via raw DBAL, like
     * CashMovementItemProvider/CashMovementCollectionProvider — the ORM
     * entity `CashMovement` maps to the frozen legacy `cash_movement`
     * (singular) table, which nothing has written to since the CashRegister
     * v2 cutover (see ADR-004's amendment). Querying it here would silently
     * return zero/stale candidates instead of any real cash movement.
     *
     * @throws Exception
     * @return list<CashMovement>
     */
    public function unreconciledCashMovements(?DateTimeImmutable $from, ?DateTimeImmutable $to): array
    {
        $conditions = ['m.id IS NULL'];
        $params = [];
        $types = [];

        if ($from !== null) {
            $conditions[] = 'cm.created_at >= ?';
            $params[] = $from->format('Y-m-d H:i:s');
            $types[] = ParameterType::STRING;
        }
        if ($to !== null) {
            $conditions[] = 'cm.created_at <= ?';
            $params[] = $to->format('Y-m-d H:i:s');
            $types[] = ParameterType::STRING;
        }

        $params[] = self::UNRECONCILED_LIST_LIMIT;
        $types[] = ParameterType::INTEGER;

        $rows = $this->connection->fetchAllAssociative(
            sprintf('SELECT cm.id, cm.cash_session_id, cm.movement_type, cm.category_id, cm.amount, cm.description, cm.created_at
                 FROM cash_movements cm
                 LEFT JOIN bank_reconciliation_match m ON m.cash_movement_id = cm.id
                 WHERE %s
                 ORDER BY cm.created_at DESC
                 LIMIT ?', implode(' AND ', $conditions)),
            $params,
            $types,
        );

        return array_map($this->cashMovementHydrator->fromRow(...), $rows);
    }

    /**
     * @return list<array{sourceType: string, sourceId: int|string, date: DateTimeImmutable, label: string}>
     */
    private function supplierPaymentCandidates(
        \Brick\Math\BigDecimal $absAmount,
        DateTimeImmutable $from,
        DateTimeImmutable $to,
    ): array {
        /** @var list<SupplierPayment> $payments */
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

        $candidates = [];
        foreach ($payments as $payment) {
            if (($id = $payment->getId()) !== null) {
                $candidates[] = [
                    'sourceType' => 'supplier_payment',
                    'sourceId' => $id,
                    'date' => DateTimeImmutable::createFromInterface($payment->getPaidAt()),
                    'label' => (string) $payment->getReference(),
                ];
            }
        }

        return $candidates;
    }

    /**
     * @return list<array{sourceType: string, sourceId: int|string, date: DateTimeImmutable, label: string}>
     */
    private function salePaymentCandidates(
        \Brick\Math\BigDecimal $absAmount,
        DateTimeImmutable $from,
        DateTimeImmutable $to,
    ): array {
        /** @var list<SalePayment> $payments */
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

        $candidates = [];
        foreach ($payments as $payment) {
            if (($id = $payment->getId()) !== null) {
                $candidates[] = [
                    'sourceType' => 'sale_payment',
                    'sourceId' => $id,
                    'date' => DateTimeImmutable::createFromInterface($payment->getPaidAt()),
                    'label' => $this->formatSaleLabel($payment->getSaleRecord()),
                ];
            }
        }

        return $candidates;
    }

    /**
     * Same v2-table caveat as unreconciledCashMovements() above — reads
     * `cash_movements` via raw DBAL rather than the ORM `CashMovement`
     * entity, which maps to the frozen legacy `cash_movement` table.
     *
     * @throws Exception
     * @return list<array{sourceType: string, sourceId: int|string, date: DateTimeImmutable, label: string}>
     */
    private function cashMovementCandidates(
        \Brick\Math\BigDecimal $absAmount,
        DateTimeImmutable $from,
        DateTimeImmutable $to,
        CashMovementType $type,
    ): array {
        $movementType = $type === CashMovementType::EXPENSE ? 'CASH_OUT' : 'CASH_IN';

        $rows = $this->connection->fetchAllAssociative('SELECT cm.id, cm.cash_session_id, cm.movement_type, cm.category_id, cm.amount, cm.description, cm.created_at
             FROM cash_movements cm
             LEFT JOIN bank_reconciliation_match m ON m.cash_movement_id = cm.id
             WHERE m.id IS NULL
               AND cm.amount = ?
               AND cm.movement_type = ?
               AND cm.created_at BETWEEN ? AND ?', [
            Money::toString($absAmount),
            $movementType,
            $from->format('Y-m-d H:i:s'),
            $to->format('Y-m-d H:i:s'),
        ]);

        return array_map(function (array $row): array {
            $movement = $this->cashMovementHydrator->fromRow($row);

            return [
                'sourceType' => 'cash_movement',
                'sourceId' => $movement->getId(),
                'date' => $movement->getCreatedAt(),
                'label' => $movement->getDescription(),
            ];
        }, $rows);
    }
}
