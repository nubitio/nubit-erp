<?php

declare(strict_types=1);

namespace App\Sales\Receivable;

use App\Accounting\Message\EconomicEvent;
use App\Catalog\Entity\PaymentMethod;
use App\Efact\Domain\Money;
use App\Sales\Entity\CustomerPaymentApplication;
use App\Sales\Entity\SaleRecord;
use App\Sales\Service\CustomerAccountLedger;
use App\Sales\Service\SalePaymentService;
use App\Sales\Service\TreasuryReceipt;
use App\Sales\Service\TreasuryReceipts;
use App\Sales\State\SalesWriteGateway;
use App\Treasury\Domain\MovementSource;
use Brick\Math\BigDecimal;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * The effects a collection has outside the new tables, so the legacy world stays consistent while both exist:
 * the sale's paid/balance (one `sale_payments` row per slice), the customer ledger, the drawer or Treasury
 * movement once per tender, and the accounting events. Always called inside the collection's transaction.
 */
readonly class CollectionBooker
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SalePaymentService $salePayments,
        private CustomerAccountLedger $ledger,
        private SalesWriteGateway $gateway,
        private TreasuryReceipts $treasuryReceipts,
        private MessageBusInterface $messageBus,
    ) {}

    /**
     * @param list<string> $tenderIds ids of the tenders, same order as the request
     * @param list<SaleRecord> $allocationSales the sale of each allocation, same order as the allocations
     * @param list<CollectionSlice> $slices
     */
    public function book(
        CollectionRequest $request,
        string $collectionId,
        array $tenderIds,
        array $allocationSales,
        array $slices,
        ?int $cashSessionId,
        ?string $branchId,
    ): void {
        $description = sprintf('Cobranza cliente #%d', $request->customerId);
        $applied = $this->total($slices, static fn(CollectionSlice $s): bool => $s->allocationIndex !== null);
        $excess = $this->total($slices, static fn(CollectionSlice $s): bool => $s->allocationIndex === null);

        if ($applied->isPositive()) {
            $this->bookApplied($request, $collectionId, $allocationSales, $slices, $applied, $description);
        }
        if ($excess->isPositive()) {
            $this->ledger->recordAdvanceReceived(
                customerId: $request->customerId,
                occurredOn: $request->receivedOn,
                amount: Money::toString($excess),
                currency: $request->currency,
                source: 'collection_excess',
                sourceId: $collectionId,
                description: $description . ' (saldo a favor)',
                branchId: $branchId,
            );
        }

        foreach ($request->tenders as $index => $tender) {
            $this->bookTender(
                $request,
                $collectionId,
                $tenderIds[$index],
                $tender,
                $slices,
                $index,
                $cashSessionId,
                $branchId,
                $description,
            );
        }
        $this->entityManager->flush();
    }

    /**
     * @param list<SaleRecord> $allocationSales
     * @param list<CollectionSlice> $slices
     */
    private function bookApplied(
        CollectionRequest $request,
        string $collectionId,
        array $allocationSales,
        array $slices,
        BigDecimal $applied,
        string $description,
    ): void {
        $entry = $this->ledger->recordPayment(
            customerId: $request->customerId,
            occurredOn: $request->receivedOn,
            amount: Money::toString($applied),
            currency: $request->currency,
            source: 'collection',
            sourceId: $collectionId,
            description: $description,
        );

        foreach ($slices as $slice) {
            if ($slice->allocationIndex === null) {
                continue;
            }
            $sale = $allocationSales[$slice->allocationIndex];
            $tender = $request->tenders[$slice->tenderIndex];
            $amount = Money::toString($slice->amount);
            $payment = $this->salePayments->appendPayment(
                $sale,
                $tender->method,
                $amount,
                $request->receivedOn,
                $tender->reference,
            );
            $this->entityManager->persist(new CustomerPaymentApplication(
                accountEntry: $entry,
                saleId: (string) $sale->getId(),
                appliedAmount: $amount,
                currency: $request->currency,
                appliedAt: $request->receivedOn,
                salePaymentId: (int) $payment->getId(),
            ));
        }
    }

    /** @param list<CollectionSlice> $slices */
    private function bookTender(
        CollectionRequest $request,
        string $collectionId,
        string $tenderId,
        TenderInput $tender,
        array $slices,
        int $index,
        ?int $cashSessionId,
        ?string $branchId,
        string $description,
    ): void {
        $isCash = $tender->method->getId() === PaymentMethod::CASH;
        $amount = Money::toString(Money::money($tender->amount));

        if ($isCash && $cashSessionId !== null) {
            $this->gateway->writeOnAccountCollectionMovement(
                collectionId: $collectionId,
                cashSessionId: $cashSessionId,
                createdBy: (int) $request->createdBy->getId(),
                amount: $amount,
                paymentMethodId: PaymentMethod::CASH,
                createdAt: $request->receivedOn,
                description: $description,
            );
        }
        $this->treasuryReceipts->record(new TreasuryReceipt(
            source: MovementSource::ReceivableCollection,
            sourceId: $tenderId,
            amount: $amount,
            currency: $request->currency,
            paymentMethod: $tender->method,
            paidAt: $request->receivedOn,
            userId: $request->createdBy->getId(),
            branchId: $branchId,
            customerId: (string) $request->customerId,
            reference: $tender->reference,
            description: $description,
            moneyAccountId: $tender->moneyAccountId,
        ));

        $mine = static fn(CollectionSlice $s): bool => $s->tenderIndex === $index;
        $this->dispatch(
            $request,
            $tenderId,
            $description,
            EconomicEvent::SOURCE_RECEIVABLE_COLLECT,
            $isCash ? EconomicEvent::RECEIVABLE_COLLECT_CASH : EconomicEvent::RECEIVABLE_COLLECT_BANK,
            $this->total($slices, static fn(CollectionSlice $s): bool => $mine($s) && $s->allocationIndex !== null),
        );
        $this->dispatch(
            $request,
            $tenderId,
            $description,
            EconomicEvent::SOURCE_CUSTOMER_ADVANCE,
            $isCash ? EconomicEvent::CUSTOMER_ADVANCE_RECEIVED_CASH : EconomicEvent::CUSTOMER_ADVANCE_RECEIVED_BANK,
            $this->total($slices, static fn(CollectionSlice $s): bool => $mine($s) && $s->allocationIndex === null),
        );
    }

    private function dispatch(
        CollectionRequest $request,
        string $tenderId,
        string $description,
        string $source,
        string $kind,
        BigDecimal $amount,
    ): void {
        if (!$amount->isPositive()) {
            return;
        }

        $this->messageBus->dispatch(new EconomicEvent(
            source: $source,
            sourceId: $tenderId,
            kind: $kind,
            occurredOn: $request->receivedOn->format('Y-m-d'),
            description: $description,
            total: Money::toString($amount),
            currency: $request->currency,
        ));
    }

    /**
     * @param list<CollectionSlice> $slices
     * @param callable(CollectionSlice): bool $filter
     */
    private function total(array $slices, callable $filter): BigDecimal
    {
        return array_reduce(
            $slices,
            static fn(BigDecimal $carry, CollectionSlice $s): BigDecimal => $filter($s)
                ? $carry->plus($s->amount)
                : $carry,
            BigDecimal::zero(),
        );
    }
}
