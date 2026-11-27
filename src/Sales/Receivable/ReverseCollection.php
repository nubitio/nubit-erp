<?php

declare(strict_types=1);

namespace App\Sales\Receivable;

use App\Catalog\Entity\PaymentMethod;
use App\Efact\Domain\Money;
use App\Efact\Observability\OperationLog;
use App\Identity\Entity\User;
use App\Sales\Entity\SaleRecord;
use App\Sales\Repository\SaleRecordRepository;
use App\Sales\Service\CustomerAccountLock;
use App\Sales\State\SalesWriteGateway;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Undoes a registered collection (finance refactoring T1.5). The money goes back out and what it settled is owed
 * again, dated today (D-05): the past is never rewritten, a correction is a new fact. Retrying a reversal that
 * already happened returns it and changes nothing.
 *
 * A collection that left a customer advance (its excess) is not reversible yet: that advance may already have been
 * spent, and unwinding it belongs with the advances work (phase 2).
 */
readonly class ReverseCollection
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SaleRecordRepository $sales,
        private CustomerAccountLock $customerLock,
        private CollectionStore $store,
        private CollectionReversalBooker $booker,
        private SalesWriteGateway $gateway,
        private OperationLog $operationLog,
    ) {}

    public function reverse(string $collectionId, User $by): ReversalResult
    {
        $result = $this->entityManager->wrapInTransaction(fn(): ReversalResult => $this->undo($collectionId, $by));
        if (!$result->alreadyReversed) {
            $this->operationLog->record('sales', 'collection.reversed', $collectionId, [
                'amount' => $result->amount,
                'allocations' => $result->allocationsReversed,
            ]);
        }

        return $result;
    }

    private function undo(string $collectionId, User $by): ReversalResult
    {
        $collection = $this->store->lockCollection($collectionId) ?? throw new NotFoundHttpException(
            'Cobranza no encontrada.',
        );
        if ($collection['status'] === 'reversed') {
            return new ReversalResult(
                $collectionId,
                Money::toString(Money::money((string) $collection['total'])),
                0,
                true,
            );
        }
        if (Money::money((string) $collection['unapplied'])->isPositive()) {
            throw new UnprocessableEntityHttpException(
                'Esta cobranza dejó un saldo a favor; aún no se puede revertir. Aplica o devuelve ese saldo primero.',
            );
        }

        // Same order as every other money flow: the customer's sales, then the customer.
        $sales = [];
        foreach ($this->sales->lockReceivablesForCustomer((int) $collection['customer_id']) as $sale) {
            $sales[(string) $sale->getId()] = $sale;
        }
        $this->customerLock->lock((int) $collection['customer_id']);

        $allocations = $this->store->activeAllocations($collectionId);
        $this->assertReversible($allocations, $sales);

        $now = new DateTimeImmutable();
        $cashSessionId = $this->cashSession($collectionId, $by, (string) ($collection['branch_id'] ?? ''));
        foreach ($allocations as $allocation) {
            $this->store->reverseAllocation(
                $allocation['id'],
                $allocation['installmentId'],
                $allocation['amount'],
                $now,
            );
        }
        $this->booker->book(
            $collection,
            $this->store->legacyApplications($collectionId),
            $this->store->tenders($collectionId),
            $sales,
            $by,
            $cashSessionId,
            $now,
        );
        $this->store->markReversed($collectionId, $now, $by->getId());

        return new ReversalResult(
            $collectionId,
            Money::toString(Money::money((string) $collection['total'])),
            count($allocations),
            false,
        );
    }

    /**
     * @param list<array{id: string, installmentId: string, saleId: string, amount: string}> $allocations
     * @param array<string, SaleRecord> $sales
     */
    private function assertReversible(array $allocations, array $sales): void
    {
        $saleIds = array_values(array_unique(array_column($allocations, 'saleId')));
        foreach ($saleIds as $saleId) {
            $sale = $sales[$saleId] ?? throw new ConflictHttpException(
                'La venta de esta cobranza ya no admite reversión.',
            );
            if ($sale->getStatus() === 'VOIDED') {
                throw new UnprocessableEntityHttpException('Una de las ventas de esta cobranza fue anulada.');
            }
        }
        if ($this->store->saleHasCreditNote($saleIds)) {
            throw new UnprocessableEntityHttpException(
                'Una de las ventas de esta cobranza tiene notas de crédito; resuélvelas primero.',
            );
        }
    }

    /** The reverser's open drawer, when part of the money came in as cash. */
    private function cashSession(string $collectionId, User $by, string $branchId): ?int
    {
        $hasCash = array_filter(
            $this->store->tenders($collectionId),
            static fn(array $t): bool => $t['paymentMethodId'] === PaymentMethod::CASH,
        ) !== [];
        if (!$hasCash) {
            return null;
        }

        $userId = (int) $by->getId();
        $sessionId = $this->gateway->findOpenCashSessionIdForUser($userId, $branchId === '' ? null : $branchId);
        if ($sessionId === null) {
            throw new UnprocessableEntityHttpException('Abre tu caja para devolver el efectivo de esta cobranza.');
        }
        $this->gateway->requireLockedOpenCashSessionForUser($sessionId, $userId);

        return $sessionId;
    }
}
