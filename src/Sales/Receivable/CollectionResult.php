<?php

declare(strict_types=1);

namespace App\Sales\Receivable;

use App\Efact\Domain\Finance\Allocation;
use App\Efact\Domain\Finance\AllocationResult;
use App\Efact\Domain\Money;
use App\Sales\Entity\SaleRecord;
use App\Sales\Service\CollectOnAccountPlanner;
use Brick\Math\BigDecimal;

/** What a registered collection did, in the shape the receipt (constancia) needs. */
final readonly class CollectionResult
{
    /**
     * @param list<array{installmentId: string, saleId: string, document: string, amount: string, balanceDueAfter: string}> $allocations
     * @param list<array{tenderId: string, paymentMethodId: int, amount: string}> $tenders
     */
    public function __construct(
        public string $collectionId,
        public int $customerId,
        public string $currency,
        public string $total,
        public string $unapplied,
        public array $allocations,
        public array $tenders,
        public bool $replayed,
    ) {}

    /**
     * @param list<SaleRecord> $allocationSales the sale of each allocation, in order
     * @param list<string> $tenderIds
     */
    public static function booked(
        string $collectionId,
        CollectionRequest $request,
        BigDecimal $total,
        AllocationResult $plan,
        array $allocationSales,
        array $tenderIds,
    ): self {
        return new self(
            collectionId: $collectionId,
            customerId: $request->customerId,
            currency: $request->currency,
            total: Money::toString($total),
            unapplied: Money::toString($plan->unapplied),
            allocations: array_map(
                static fn(Allocation $a, SaleRecord $sale): array => [
                    'installmentId' => $a->itemId,
                    'saleId' => (string) $sale->getId(),
                    'document' => CollectOnAccountPlanner::documentLabel($sale),
                    'amount' => Money::toString($a->amount),
                    'balanceDueAfter' => $sale->getBalanceDue(),
                ],
                $plan->allocations,
                $allocationSales,
            ),
            tenders: array_map(
                static fn(TenderInput $t, string $id): array => [
                    'tenderId' => $id,
                    'paymentMethodId' => (int) $t->method->getId(),
                    'amount' => Money::toString(Money::money($t->amount)),
                ],
                $request->tenders,
                $tenderIds,
            ),
            replayed: false,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'collectionId' => $this->collectionId,
            'customerId' => $this->customerId,
            'currency' => $this->currency,
            'total' => $this->total,
            'unapplied' => $this->unapplied,
            'allocations' => $this->allocations,
            'tenders' => $this->tenders,
            'replayed' => $this->replayed,
        ];
    }
}
