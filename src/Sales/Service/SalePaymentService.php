<?php

declare(strict_types=1);

namespace App\Sales\Service;

use App\Catalog\Entity\PaymentMethod;
use App\Efact\Domain\Money;
use App\Identity\Entity\User;
use App\Sales\Entity\SalePayment;
use App\Sales\Entity\SaleRecord;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

readonly class SalePaymentService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
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
     */
    public function registerPayment(
        SaleRecord $sale,
        PaymentMethod $paymentMethod,
        string $amount,
        DateTimeImmutable $paidAt,
        ?string $reference,
        User $createdBy,
    ): SalePayment {
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

        $payment = new SalePayment()
            ->setSaleRecord($sale)
            ->setPaymentMethod($paymentMethod)
            ->setAmount(Money::toString($amountMoney))
            ->setCurrency($sale->getCurrency() !== '' ? $sale->getCurrency() : 'PEN')
            ->setPaidAt(\DateTime::createFromImmutable($paidAt))
            ->setCreatedAt(\DateTime::createFromImmutable(new DateTimeImmutable()));

        // $reference is accepted for API/UI symmetry with SupplierPayment but
        // has nowhere to land: sale_payments has no reference/note column
        // (unlike supplier_payment). Not persisted; would need a migration to
        // add one if this turns out to matter in practice.

        $newPaidTotal = Money::money($sale->getPaidTotal())->plus($amountMoney);
        $newBalanceDue = $balanceDue->minus($amountMoney);

        $sale->setPaidTotal(Money::toString($newPaidTotal));
        $sale->setBalanceDue(Money::toString($newBalanceDue));
        $sale->addSalePayment($payment);

        $this->entityManager->persist($payment);
        $this->entityManager->flush();

        return $payment;
    }
}
