<?php

declare(strict_types=1);

namespace App\Sales\Service;

use App\Accounting\Message\EconomicEvent;
use App\Catalog\Entity\PaymentMethod;
use App\Catalog\Enum\SettlementBehavior;
use App\Efact\Domain\Money;
use App\Identity\Entity\User;
use App\Sales\Entity\SalePayment;
use App\Sales\Entity\SaleRecord;
use App\Sales\State\SalesWriteGateway;
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
     * Side effects beyond the sale_payments row:
     *  - Paid in cash: writes a CASH_IN cash movement into the collector's open
     *    cash session (category `credit_payment`), so the arqueo/cierre cuadra.
     *    A collection in cash with no open session for the user is rejected.
     *  - Always: dispatches an EconomicEvent so the ledger books
     *    debit Caja/Banco, credit Facturas por cobrar (121) — no revenue line,
     *    that was already recognised when the credit sale was posted.
     */
    public function registerPayment(
        SaleRecord $sale,
        PaymentMethod $paymentMethod,
        string $amount,
        DateTimeImmutable $paidAt,
        ?string $reference,
        User $createdBy,
    ): SalePayment {
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

        // Cash collections must land in a real drawer. Resolve the session
        // before persisting so a missing session doesn't leave a dangling
        // sale_payments row with no matching cash movement.
        $isCash = $paymentMethod->getId() === PaymentMethod::CASH;
        $cashSessionId = null;
        if ($isCash) {
            $cashSessionId = $this->salesWriteGateway->findOpenCashSessionIdForUser((int) $createdBy->getId());
            if ($cashSessionId === null) {
                throw new UnprocessableEntityHttpException('Abre tu caja para registrar cobros en efectivo.');
            }
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
            kind: $isCash ? EconomicEvent::RECEIVABLE_COLLECT_CASH : EconomicEvent::RECEIVABLE_COLLECT_BANK,
            occurredOn: $paidAt->format('Y-m-d'),
            description: $this->collectionDescription($sale),
            total: Money::toString($amountMoney),
            currency: $payment->getCurrency(),
        ));

        return $payment;
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
