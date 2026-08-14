<?php

declare(strict_types=1);

namespace App\AccountsPayable\Service;

use App\AccountsPayable\Entity\SupplierBill;
use App\AccountsPayable\Entity\SupplierPayment;
use App\Catalog\Entity\PaymentMethod;
use App\Efact\Domain\Money;
use App\Identity\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

readonly class SupplierPaymentService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {}

    /**
     * Registers a payment against a SupplierBill and recomputes its
     * paidTotal/balanceDue/status. Mirrors how SaleRecord's balanceDue is
     * kept in sync on the sales side, but incrementally (one payment at a
     * time) rather than by replacing the whole payment set on each write,
     * since supplier bills are paid over multiple separate visits rather
     * than in one POS checkout.
     */
    public function registerPayment(
        SupplierBill $bill,
        PaymentMethod $paymentMethod,
        string $amount,
        DateTimeImmutable $paidAt,
        ?string $reference,
        User $createdBy,
    ): SupplierPayment {
        if ($bill->getStatus() === SupplierBill::VOID) {
            throw new UnprocessableEntityHttpException('No se puede registrar un pago sobre una factura anulada.');
        }

        $amountMoney = Money::money($amount);
        if ($amountMoney->isLessThanOrEqualTo(0)) {
            throw new UnprocessableEntityHttpException('El monto del pago debe ser mayor a 0.');
        }

        $balanceDue = Money::money($bill->getBalanceDue());
        if ($amountMoney->isGreaterThan($balanceDue)) {
            throw new UnprocessableEntityHttpException(sprintf(
                'El monto del pago (%s) excede el saldo pendiente (%s).',
                Money::toString($amountMoney),
                Money::toString($balanceDue),
            ));
        }

        $payment = new SupplierPayment()
            ->setSupplierBill($bill)
            ->setPaymentMethod($paymentMethod)
            ->setAmount(Money::toString($amountMoney))
            ->setPaidAt($paidAt)
            ->setReference($reference)
            ->setCreatedBy($createdBy);

        $newPaidTotal = Money::money($bill->getPaidTotal())->plus($amountMoney);
        $newBalanceDue = $balanceDue->minus($amountMoney);

        $bill->setPaidTotal(Money::toString($newPaidTotal));
        $bill->setBalanceDue(Money::toString($newBalanceDue));
        $bill->setStatus($newBalanceDue->isZero() ? SupplierBill::PAID : SupplierBill::PARTIAL);

        $this->entityManager->persist($payment);
        $this->entityManager->flush();

        return $payment;
    }
}
