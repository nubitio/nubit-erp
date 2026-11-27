<?php

declare(strict_types=1);

namespace App\Sales\Receivable;

use App\Accounting\Message\EconomicEvent;
use App\Catalog\Entity\PaymentMethod;
use App\Efact\Domain\Money;
use App\Identity\Entity\User;
use App\Sales\Domain\CustomerAccountEntryType;
use App\Sales\Entity\SalePayment;
use App\Sales\Entity\SaleRecord;
use App\Sales\Service\CustomerAccountLedger;
use App\Sales\State\SalesWriteGateway;
use App\Treasury\Domain\MovementKey;
use App\Treasury\Domain\MovementSource;
use App\Treasury\Domain\TreasuryException;
use App\Treasury\Port\RecordsMoneyMovement;
use Brick\Math\BigDecimal;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * The effects of reversing a collection outside the new tables, the mirror of {@see CollectionBooker}.
 *
 * Nothing is deleted: each `sale_payments` row it created gets an opposite row, the ledger gets a debit
 * entry, Treasury gets correcting movements, and the drawer gets a REFUND_OUT in the reverser's open session.
 * Called inside the reversal's transaction.
 */
readonly class CollectionReversalBooker
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CustomerAccountLedger $ledger,
        private SalesWriteGateway $gateway,
        private RecordsMoneyMovement $treasury,
        private MessageBusInterface $messageBus,
    ) {}

    /**
     * @param array<string, mixed> $collection the sales_collection row
     * @param list<array{saleId: string, salePaymentId: int, paymentMethodId: int, amount: string}> $applications
     * @param list<array{id: string, paymentMethodId: int, amount: string}> $tenders
     * @param array<string, SaleRecord> $sales by id
     */
    public function book(
        array $collection,
        array $applications,
        array $tenders,
        array $sales,
        User $by,
        ?int $cashSessionId,
        DateTimeImmutable $now,
    ): void {
        $collectionId = (string) $collection['id'];
        $currency = (string) $collection['currency'];
        $description = sprintf('Reversión de cobranza cliente #%d', (int) $collection['customer_id']);

        $applied = BigDecimal::zero();
        foreach ($applications as $application) {
            $this->reverseOnSale($sales[$application['saleId']], $application, $currency, $now);
            $applied = $applied->plus(Money::money($application['amount']));
        }
        if ($applied->isPositive()) {
            $this->ledger->record(
                customerId: (int) $collection['customer_id'],
                entryType: CustomerAccountEntryType::PAYMENT_REVERSAL,
                occurredOn: $now,
                debit: Money::toString($applied),
                credit: '0.00',
                currency: $currency,
                source: 'collection_reversal',
                sourceId: $collectionId,
                description: $description,
            );
        }

        foreach ($tenders as $tender) {
            $this->reverseTender($collectionId, $tender, $currency, $by, $cashSessionId, $now, $description);
        }
        $this->entityManager->flush();
    }

    /** @param array{saleId: string, salePaymentId: int, paymentMethodId: int, amount: string} $application */
    private function reverseOnSale(SaleRecord $sale, array $application, string $currency, DateTimeImmutable $now): void
    {
        $amount = Money::money($application['amount']);
        $method = $this->entityManager->getReference(PaymentMethod::class, $application['paymentMethodId']);
        if (!$method instanceof PaymentMethod) {
            throw new UnprocessableEntityHttpException('El método de pago de la cobranza ya no existe.');
        }

        $payment = new SalePayment()
            ->setSaleRecord($sale)
            ->setPaymentMethod($method)
            ->setAmount(Money::toString($amount->negated()))
            ->setCurrency($currency)
            ->setPaidAt(\DateTime::createFromImmutable($now))
            ->setReference(sprintf('Reversión del pago #%d', $application['salePaymentId']))
            ->setCreatedAt(\DateTime::createFromImmutable($now));
        $sale->setPaidTotal(Money::toString(Money::money($sale->getPaidTotal())->minus($amount)));
        $sale->setBalanceDue(Money::toString(Money::money($sale->getBalanceDue())->plus($amount)));
        $sale->addSalePayment($payment);
        $this->entityManager->persist($payment);
    }

    /** @param array{id: string, paymentMethodId: int, amount: string} $tender */
    private function reverseTender(
        string $collectionId,
        array $tender,
        string $currency,
        User $by,
        ?int $cashSessionId,
        DateTimeImmutable $now,
        string $description,
    ): void {
        $isCash = $tender['paymentMethodId'] === PaymentMethod::CASH;
        if ($isCash && $cashSessionId !== null) {
            $this->gateway->writeCollectionReversalMovement(
                collectionId: $collectionId,
                cashSessionId: $cashSessionId,
                createdBy: (int) $by->getId(),
                amount: $tender['amount'],
                paymentMethodId: PaymentMethod::CASH,
                createdAt: $now,
                description: $description,
            );
        }
        if (!$isCash) {
            try {
                $this->treasury->reverse(
                    new MovementKey(MovementSource::ReceivableCollection, $tender['id']),
                    new MovementKey(MovementSource::ReceivableCollectionReversal, $tender['id']),
                    $now,
                    $by->getId(),
                    $description,
                );
            } catch (TreasuryException $e) {
                throw new UnprocessableEntityHttpException($e->getMessage(), $e);
            }
        }

        $this->messageBus->dispatch(new EconomicEvent(
            source: EconomicEvent::SOURCE_RECEIVABLE_COLLECT_REVERSAL,
            sourceId: $tender['id'],
            kind: $isCash
                ? EconomicEvent::RECEIVABLE_COLLECT_REVERSE_CASH
                : EconomicEvent::RECEIVABLE_COLLECT_REVERSE_BANK,
            occurredOn: $now->format('Y-m-d'),
            description: $description,
            total: Money::toString(Money::money($tender['amount'])),
            currency: $currency,
        ));
    }
}
