<?php

declare(strict_types=1);

namespace App\Sales\Receivable;

use App\Efact\Domain\Finance\AllocationPlanner;
use App\Efact\Domain\Finance\AllocationResult;
use App\Efact\Domain\Finance\ManualAllocationPlanner;
use App\Efact\Domain\Finance\OpenItem;
use App\Efact\Domain\Money;
use App\Efact\Observability\OperationLog;
use App\Sales\Entity\SaleRecord;
use App\Sales\Repository\SaleRecordRepository;
use App\Sales\Service\CollectionPlanGuard;
use App\Sales\Service\CustomerAccountLock;
use App\Sales\State\SalesWriteGateway;
use Brick\Math\BigDecimal;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * Registers money received from a customer (finance refactoring T1.4). A collection is not a sale and not an
 * invoice payment: it is the money, however many ways it arrived (tenders), and an allocation of it to the
 * instalments it settles. Whatever settles nothing stays as the customer's advance.
 *
 * One transaction: locks the customer's open sales and instalments, plans the split (oldest due date first,
 * or the user's own), writes the new tables, and books the same facts in the legacy structures. The key makes a
 * retry return the first result.
 */
readonly class RegisterCollection
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SaleRecordRepository $sales,
        private CustomerAccountLock $customerLock,
        private CollectionStore $store,
        private OpenInstallmentLoader $loader,
        private AllocationPlanner $planner,
        private ManualAllocationPlanner $manualPlanner,
        private CollectionSlicer $slicer,
        private CollectionBooker $booker,
        private SalesWriteGateway $gateway,
        private OperationLog $operationLog,
    ) {}

    public function register(CollectionRequest $request): CollectionResult
    {
        CollectionGuard::assertTenders($request);

        $replayed = $this->store->resultFor($request->idempotencyKey, $request->customerId);
        if ($replayed instanceof CollectionResult) {
            return $replayed;
        }

        $result = $this->entityManager->wrapInTransaction(fn(): CollectionResult => $this->book($request));
        $this->operationLog->record('sales', 'collection.registered', $result->collectionId, [
            'customer_id' => $request->customerId,
            'amount' => $result->total,
            'currency' => $request->currency,
            'tenders' => count($result->tenders),
            'allocations' => count($result->allocations),
            'unapplied' => $result->unapplied,
        ]);

        return $result;
    }

    private function book(CollectionRequest $request): CollectionResult
    {
        // Same lock order as the legacy collection: the customer's sales first, then the customer.
        $bySale = [];
        foreach ($this->sales->lockOpenReceivablesForCustomer($request->customerId) as $sale) {
            if (CollectionPlanGuard::saleCurrency($sale) === $request->currency) {
                $bySale[(string) $sale->getId()] = $sale;
            }
        }
        $this->customerLock->lock($request->customerId);

        // A concurrent request with the same key may have booked it while this one waited.
        $replayed = $this->store->resultFor($request->idempotencyKey, $request->customerId);
        if ($replayed instanceof CollectionResult) {
            return $replayed;
        }

        $items = $this->loader->load($bySale);
        $total = CollectionGuard::total($request);
        $plan = $this->plan($request, $items, $total);

        $saleOf = [];
        foreach ($items as $row) {
            $saleOf[$row['item']->id] = $row['saleId'];
        }
        $allocationSales = array_map(static fn($a): SaleRecord => $bySale[$saleOf[$a->itemId]], $plan->allocations);
        $first = $allocationSales[0] ?? array_values($bySale)[0] ?? null;
        $branchId = $first?->getBranch()?->getId();
        $cashSessionId = $this->cashSession($request, $branchId);

        $collectionId = Uuid::v7()->toRfc4122();
        $tenderIds = $this->store->record(
            $collectionId,
            $request,
            $branchId,
            Money::toString($total),
            Money::toString($plan->unapplied),
            $plan->allocations,
        );
        $slices = $this->slicer->slice(
            array_map(static fn(TenderInput $t): BigDecimal => Money::money($t->amount), $request->tenders),
            array_map(static fn($a): BigDecimal => $a->amount, $plan->allocations),
        );
        $this->booker->book($request, $collectionId, $tenderIds, $allocationSales, $slices, $cashSessionId, $branchId);

        return CollectionResult::booked($collectionId, $request, $total, $plan, $allocationSales, $tenderIds);
    }

    /** @param list<array{item: OpenItem, saleId: string}> $items */
    private function plan(CollectionRequest $request, array $items, BigDecimal $total): AllocationResult
    {
        try {
            return $request->allocations === []
                ? $this->planner->plan(array_column($items, 'item'), $total)
                : $this->manualPlanner->plan(
                    array_column($items, 'item'),
                    $total,
                    CollectionGuard::resolveLines($request, $items),
                );
        } catch (InvalidArgumentException $e) {
            throw new UnprocessableEntityHttpException($e->getMessage(), $e);
        }
    }

    private function cashSession(CollectionRequest $request, ?string $branchId): ?int
    {
        if (!CollectionGuard::hasCash($request)) {
            return null;
        }

        $userId = (int) $request->createdBy->getId();
        $sessionId = $this->gateway->findOpenCashSessionIdForUser($userId, $branchId);
        if ($sessionId === null) {
            throw new UnprocessableEntityHttpException('Abre tu caja para registrar cobros en efectivo.');
        }
        $this->gateway->requireLockedOpenCashSessionForUser($sessionId, $userId);

        return $sessionId;
    }
}
