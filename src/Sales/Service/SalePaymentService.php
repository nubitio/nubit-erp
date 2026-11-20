<?php

declare(strict_types=1);

namespace App\Sales\Service;

use App\Accounting\Message\EconomicEvent;
use App\Catalog\Entity\PaymentMethod;
use App\Catalog\Enum\SettlementBehavior;
use App\Efact\Domain\Money;
use App\Efact\Observability\OperationLog;
use App\Identity\Entity\User;
use App\Sales\Entity\CustomerPaymentApplication;
use App\Sales\Entity\SalePayment;
use App\Sales\Entity\SaleRecord;
use App\Sales\State\SalesWriteGateway;
use App\Treasury\Domain\MovementSource;
use Brick\Math\BigDecimal;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Messenger\MessageBusInterface;

readonly class SalePaymentService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SalesWriteGateway $salesWriteGateway,
        private MessageBusInterface $messageBus,
        private OperationLog $operationLog,
        private CustomerAccountLedger $customerAccountLedger,
        private ?TreasuryReceipts $treasuryReceipts = null,
    ) {}

    /**
     * Registers a payment against a SaleRecord's outstanding balanceDue and
     * recomputes paidTotal/balanceDue. Mirrors
     * App\AccountsPayable\Service\SupplierPaymentService, but does not touch
     * `status` — on Sales, status tracks the order lifecycle (COMPLETED/
     * VOIDED), not payment completeness, so it's left alone here.
     *
     * Does not go through SalesWriteGateway::write() on purpose — that method
     * calls clearSalePayments() and rebuilds every payment line from the
     * request each time, so it can't be used to append a single payment
     * without resubmitting the sale's entire payment history.
     *
     * This is the single-sale collection entrypoint: it appends the payment row
     * (see {@see appendPayment()}) and then books the money exactly once —
     *  - Paid in cash: writes a CASH_IN cash movement into the collector's open
     *    cash session (category `credit_payment`), so the arqueo/cierre cuadra.
     *    A collection in cash with no open session for the user is rejected.
     *  - Always: dispatches an EconomicEvent so the ledger books
     *    debit Caja/Banco, credit Facturas por cobrar (121) — no revenue line,
     *    that was already recognised when the credit sale was posted.
     *
     * A collection that spreads one payment across several of a customer's
     * sales (#381) calls {@see appendPayment()} per sale and books the money
     * once itself, instead of calling this method in a loop.
     */
    public function registerPayment(
        SaleRecord $sale,
        PaymentMethod $paymentMethod,
        string $amount,
        DateTimeImmutable $paidAt,
        ?string $reference,
        User $createdBy,
        ?string $moneyAccountId = null,
    ): SalePayment {
        // Same order as before the split: reject a bad method or a voided sale
        // before touching the cash drawer, so the error and the (lack of) side
        // effects are unchanged.
        $this->assertCollectable($paymentMethod, $sale);

        $amountMoney = Money::money($amount);

        // Cash collections must land in a real drawer. Resolve the session
        // before persisting so a missing session doesn't leave a dangling
        // sale_payments row with no matching cash movement.
        $isCash = $paymentMethod->getId() === PaymentMethod::CASH;
        $cashSessionId = null;
        if ($isCash) {
            $cashSessionId = $this->salesWriteGateway->findOpenCashSessionIdForUser(
                (int) $createdBy->getId(),
                $sale->getBranch()?->getId(),
            );
            if ($cashSessionId === null) {
                throw new UnprocessableEntityHttpException('Abre tu caja para registrar cobros en efectivo.');
            }
        }

        // One transaction: if Treasury refuses the movement the payment is rolled back with it.
        return $this->entityManager->wrapInTransaction(function () use (
            $sale,
            $paymentMethod,
            $amount,
            $paidAt,
            $reference,
            $createdBy,
            $moneyAccountId,
            $cashSessionId,
            $amountMoney,
        ): SalePayment {
            $payment = $this->appendPayment($sale, $paymentMethod, $amount, $paidAt, $reference);

            $customer = $sale->getCustomer();
            $this->treasuryReceipts?->record(new TreasuryReceipt(
                source: MovementSource::ReceivableCollection,
                sourceId: (string) $payment->getId(),
                amount: Money::toString($amountMoney),
                currency: $payment->getCurrency(),
                paymentMethod: $paymentMethod,
                paidAt: $paidAt,
                userId: $createdBy->getId(),
                branchId: $sale->getBranch()?->getId(),
                customerId: $customer === null ? null : (string) $customer->getId(),
                reference: $reference,
                description: $this->collectionDescription($sale),
                moneyAccountId: $moneyAccountId,
            ));

            $this->bookCollection($payment, $sale, $createdBy, $cashSessionId, $amountMoney, $paidAt);

            $this->recordOnCustomerLedger($sale, $payment, $amountMoney, $paidAt);

            return $payment;
        });
    }

    /**
     * The debt side of a collection, and nothing else: validates the method and
     * amount, appends one `sale_payments` row and recomputes the sale's
     * paidTotal/balanceDue. No cash movement, no ledger event, no operation log
     * — the caller owns those. Split out of {@see registerPayment()} so an
     * on-account collection (#381) can append rows against several sales and
     * still book the money a single time.
     */
    public function appendPayment(
        SaleRecord $sale,
        PaymentMethod $paymentMethod,
        string $amount,
        DateTimeImmutable $paidAt,
        ?string $reference,
    ): SalePayment {
        $this->assertCollectable($paymentMethod, $sale);

        return $this->persistPaymentRow($sale, $paymentMethod, $amount, $paidAt, $reference);
    }

    /**
     * A `sale_payments` row funded by a customer advance being applied to this
     * sale (#382). Same debt-side effect as {@see appendPayment()} — recomputes
     * paidTotal/balanceDue — but the method must carry the ADVANCE settlement
     * behaviour: no money moves now, it moved when the advance was received.
     * The caller ({@see \App\Sales\Service\ApplyAdvanceService}) owns the ledger
     * applications and the Dr 122 / Cr 121 posting.
     */
    public function appendAdvancePayment(
        SaleRecord $sale,
        PaymentMethod $advanceMethod,
        string $amount,
        DateTimeImmutable $paidAt,
        ?string $reference,
    ): SalePayment {
        if ($advanceMethod->getSettlementBehavior() !== SettlementBehavior::ADVANCE) {
            throw new UnprocessableEntityHttpException('El método de aplicación de anticipo no está configurado.');
        }
        if ($sale->getStatus() === 'VOIDED') {
            throw new UnprocessableEntityHttpException('No se puede aplicar un anticipo a una venta anulada.');
        }

        return $this->persistPaymentRow($sale, $advanceMethod, $amount, $paidAt, $reference);
    }

    /**
     * Builds and persists one `sale_payments` row against $sale and recomputes
     * its paidTotal/balanceDue. Validation of the method is the caller's job.
     */
    private function persistPaymentRow(
        SaleRecord $sale,
        PaymentMethod $paymentMethod,
        string $amount,
        DateTimeImmutable $paidAt,
        ?string $reference,
    ): SalePayment {
        $amountMoney = Money::money($amount);
        if ($amountMoney->isLessThanOrEqualTo(0)) {
            throw new UnprocessableEntityHttpException('El monto del cobro debe ser mayor a 0.');
        }

        $balanceDue = Money::money($sale->getBalanceDue());
        if ($amountMoney->isGreaterThan($balanceDue)) {
            throw new UnprocessableEntityHttpException(sprintf(
                'El monto del cobro (%s) excede el saldo pendiente (%s).',
                Money::toString($amountMoney),
                Money::toString($balanceDue),
            ));
        }

        $payment = new SalePayment()
            ->setSaleRecord($sale)
            ->setPaymentMethod($paymentMethod)
            ->setAmount(Money::toString($amountMoney))
            ->setCurrency($sale->getCurrency() !== '' ? $sale->getCurrency() : 'PEN')
            ->setPaidAt(\DateTime::createFromImmutable($paidAt))
            ->setReference($reference)
            ->setCreatedAt(\DateTime::createFromImmutable(new DateTimeImmutable()));

        $newPaidTotal = Money::money($sale->getPaidTotal())->plus($amountMoney);
        $newBalanceDue = $balanceDue->minus($amountMoney);

        $sale->setPaidTotal(Money::toString($newPaidTotal));
        $sale->setBalanceDue(Money::toString($newBalanceDue));
        $sale->addSalePayment($payment);

        $this->entityManager->persist($payment);
        $this->entityManager->flush();

        return $payment;
    }

    /**
     * Books the money behind a just-appended collection: the cash-drawer
     * movement (cash only, into the resolved session) and the EconomicEvent,
     * plus the operation-log line. Kept separate from {@see appendPayment()} so
     * a multi-sale collection books once for the whole amount.
     */
    private function bookCollection(
        SalePayment $payment,
        SaleRecord $sale,
        User $createdBy,
        ?int $cashSessionId,
        BigDecimal $amountMoney,
        DateTimeImmutable $paidAt,
    ): void {
        if ($cashSessionId !== null) {
            $this->salesWriteGateway->writeReceivableCollectionMovement(
                salePaymentId: (int) $payment->getId(),
                saleId: (string) $sale->getId(),
                cashSessionId: $cashSessionId,
                createdBy: (int) $createdBy->getId(),
                amount: Money::toString($amountMoney),
                paymentMethodId: PaymentMethod::CASH,
                createdAt: $paidAt,
                description: $this->collectionDescription($sale),
            );
        }

        $this->messageBus->dispatch(new EconomicEvent(
            source: EconomicEvent::SOURCE_RECEIVABLE_COLLECT,
            sourceId: (string) $payment->getId(),
            kind: $cashSessionId !== null
                ? EconomicEvent::RECEIVABLE_COLLECT_CASH
                : EconomicEvent::RECEIVABLE_COLLECT_BANK,
            occurredOn: $paidAt->format('Y-m-d'),
            description: $this->collectionDescription($sale),
            total: Money::toString($amountMoney),
            currency: $payment->getCurrency(),
        ));

        $this->operationLog->record('sales', 'receivable.collected', $sale->getId(), [
            'sale_payment_id' => (int) $payment->getId(),
            'amount' => Money::toString($amountMoney),
            'currency' => $payment->getCurrency(),
            'to_cash_session' => $cashSessionId,
        ]);
    }

    /**
     * A collection needs a method that represents money actually received, and
     * a sale that is not voided.
     */
    private function assertCollectable(PaymentMethod $paymentMethod, SaleRecord $sale): void
    {
        if (
            !$paymentMethod->getIsActive()
            || $paymentMethod->getSettlementBehavior() !== SettlementBehavior::COLLECTED
        ) {
            throw new UnprocessableEntityHttpException(
                'Seleccione un método de pago activo que represente dinero recibido. Crédito no es un cobro.',
            );
        }

        if ($sale->getStatus() === 'VOIDED') {
            throw new UnprocessableEntityHttpException('No se puede registrar un cobro sobre una venta anulada.');
        }
    }

    /**
     * Mirror a single-sale collection onto the customer's current account
     * (#367): a `payment` ledger entry plus a `customer_payment_applications`
     * row bound to this sale. Only for sales that hit the customer's account
     * with a real customer — an anonymous cash sale never had a balance to
     * track. The on-account flow (#381) writes its own ledger rows and does not
     * go through here.
     */
    private function recordOnCustomerLedger(
        SaleRecord $sale,
        SalePayment $payment,
        BigDecimal $amount,
        DateTimeImmutable $paidAt,
    ): void {
        $customerId = $sale->getCustomer()?->getId();
        if ($customerId === null || !$sale->isReceivableAccount()) {
            return;
        }

        $entry = $this->customerAccountLedger->recordPayment(
            customerId: (int) $customerId,
            occurredOn: $paidAt,
            amount: Money::toString($amount),
            currency: $payment->getCurrency(),
            saleId: (string) $sale->getId(),
            source: 'sale_payment',
            sourceId: (string) $payment->getId(),
            description: $this->collectionDescription($sale),
        );

        $this->entityManager->persist(new CustomerPaymentApplication(
            accountEntry: $entry,
            saleId: (string) $sale->getId(),
            appliedAmount: Money::toString($amount),
            currency: $payment->getCurrency(),
            appliedAt: $paidAt,
            salePaymentId: (int) $payment->getId(),
        ));
        $this->entityManager->flush();
    }

    private function collectionDescription(SaleRecord $sale): string
    {
        $serie = $sale->getSerie() ?? '';
        $numero = $sale->getNumero() ?? '';
        if ($serie !== '' && $numero !== '') {
            return sprintf('Cobro %s-%s', $serie, $numero);
        }

        return 'Cobro de cuenta por cobrar';
    }
}
