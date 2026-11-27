<?php

declare(strict_types=1);

namespace App\AccountsPayable\Service;

use App\Accounting\Message\EconomicEvent;
use App\AccountsPayable\Entity\SupplierBill;
use App\AccountsPayable\Entity\SupplierPayment;
use App\Catalog\Entity\PaymentMethod;
use App\Efact\Domain\Money;
use App\Identity\Entity\User;
use App\Treasury\Domain\Direction;
use App\Treasury\Domain\MovementKey;
use App\Treasury\Domain\MovementSource;
use App\Treasury\Domain\Party;
use App\Treasury\Domain\TreasuryException;
use App\Treasury\Port\MoneyMovementRequest;
use App\Treasury\Port\RecordsMoneyMovement;
use Brick\Math\BigDecimal;
use DateTimeImmutable;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Messenger\MessageBusInterface;

readonly class SupplierPaymentService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ?MessageBusInterface $messageBus = null,
        private ?RecordsMoneyMovement $treasury = null,
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
        ?string $moneyAccountId = null,
        ?string $idempotencyKey = null,
    ): SupplierPayment {
        if ($bill->getStatus() === SupplierBill::VOID) {
            throw new UnprocessableEntityHttpException('No se puede registrar un pago sobre una factura anulada.');
        }

        $amountMoney = Money::money($amount);
        if ($amountMoney->isLessThanOrEqualTo(0)) {
            throw new UnprocessableEntityHttpException('El monto del pago debe ser mayor a 0.');
        }

        return $this->entityManager->wrapInTransaction(function () use (
            $bill,
            $paymentMethod,
            $amountMoney,
            $paidAt,
            $reference,
            $createdBy,
            $moneyAccountId,
            $idempotencyKey,
        ): SupplierPayment {
            // Lock the bill and re-read it: the balance checked here is the one a concurrent payment
            // left, not the snapshot loaded before the transaction (G-01).
            $this->entityManager->refresh($bill, LockMode::PESSIMISTIC_WRITE);
            $replayed = SupplierPaymentIdempotency::existing(
                $this->entityManager,
                $idempotencyKey,
                $bill,
                $amountMoney,
            );
            if ($replayed instanceof SupplierPayment) {
                return $replayed;
            }
            if ($bill->getStatus() === SupplierBill::VOID) {
                throw new UnprocessableEntityHttpException('No se puede registrar un pago sobre una factura anulada.');
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
                ->setIdempotencyKey($idempotencyKey)
                ->setCreatedBy($createdBy);

            $newPaidTotal = Money::money($bill->getPaidTotal())->plus($amountMoney);
            $newBalanceDue = $balanceDue->minus($amountMoney);

            $bill->setPaidTotal(Money::toString($newPaidTotal));
            $bill->setBalanceDue(Money::toString($newBalanceDue));
            $bill->setStatus($newBalanceDue->isZero() ? SupplierBill::PAID : SupplierBill::PARTIAL);

            $this->entityManager->persist($payment);
            $this->entityManager->flush();
            $this->recordInTreasury(
                $bill,
                $payment,
                $paymentMethod,
                $amountMoney,
                $paidAt,
                $reference,
                $createdBy,
                $moneyAccountId,
            );

            $this->messageBus?->dispatch(new EconomicEvent(
                source: EconomicEvent::SOURCE_PAYABLE_PAYMENT,
                sourceId: (string) $payment->getId(),
                kind: $paymentMethod->getId() === PaymentMethod::CASH
                    ? EconomicEvent::PAYABLE_PAYMENT_CASH
                    : EconomicEvent::PAYABLE_PAYMENT_BANK,
                occurredOn: $paidAt->format('Y-m-d'),
                description: 'Pago de factura de proveedor',
                total: Money::toString($amountMoney),
                currency: $bill->getCurrency(),
            ));

            return $payment;
        });
    }

    /**
     * Reverses a previously registered payment and restores the bill's
     * paidTotal/balanceDue/status to what they were before it. The payment
     * row is retained and marked as reversed so the operational audit trail
     * remains append-only, in line with the accounting journal.
     * Used to correct a misposted payment without having to void the
     * whole bill (voiding is blocked once any payment exists).
     */
    public function reversePayment(SupplierBill $bill, SupplierPayment $payment, ?int $userId = null): void
    {
        if ($payment->getSupplierBill()->getId() !== $bill->getId()) {
            throw new UnprocessableEntityHttpException('El pago no pertenece a esta factura.');
        }

        if ($bill->getStatus() === SupplierBill::VOID) {
            throw new UnprocessableEntityHttpException('No se puede reversar un pago de una factura anulada.');
        }

        if ($payment->isReversed()) {
            throw new UnprocessableEntityHttpException('Este pago ya fue reversado.');
        }

        $this->entityManager->wrapInTransaction(function () use ($bill, $payment, $userId): void {
            // Lock and re-read both rows so two reversals of one payment cannot both pass (G-01).
            $this->entityManager->refresh($bill, LockMode::PESSIMISTIC_WRITE);
            $this->entityManager->refresh($payment);
            if ($payment->isReversed()) {
                throw new UnprocessableEntityHttpException('Este pago ya fue reversado.');
            }

            $amountMoney = Money::money($payment->getAmount());
            $newPaidTotal = Money::money($bill->getPaidTotal())->minus($amountMoney);
            $newBalanceDue = Money::money($bill->getBalanceDue())->plus($amountMoney);

            $bill->setPaidTotal(Money::toString($newPaidTotal));
            $bill->setBalanceDue(Money::toString($newBalanceDue));
            $bill->setStatus($newPaidTotal->isZero() ? SupplierBill::PENDING : SupplierBill::PARTIAL);

            $paymentMethod = $payment->getPaymentMethod();
            $paymentId = (string) $payment->getId();
            $payment->setReversedAt(new DateTimeImmutable());
            $this->entityManager->flush();
            $this->reverseInTreasury($payment, $userId);

            $this->messageBus?->dispatch(new EconomicEvent(
                source: EconomicEvent::SOURCE_PAYABLE_PAYMENT_REVERSAL,
                sourceId: $paymentId,
                kind: $paymentMethod->getId() === PaymentMethod::CASH
                    ? EconomicEvent::PAYABLE_PAYMENT_REVERSE_CASH
                    : EconomicEvent::PAYABLE_PAYMENT_REVERSE_BANK,
                occurredOn: new DateTimeImmutable('today')->format('Y-m-d'),
                description: 'Reversión de pago de factura de proveedor',
                total: Money::toString($amountMoney),
                currency: $bill->getCurrency(),
            ));
        });
    }

    /**
     * Puts the payment into Treasury, in the same transaction: cash leaves the user's open drawer (so the
     * arqueo sees it), anything else leaves the chosen or mapped bank or wallet account. A no-op when Treasury
     * is off for the tenant.
     */
    private function recordInTreasury(
        SupplierBill $bill,
        SupplierPayment $payment,
        PaymentMethod $paymentMethod,
        BigDecimal $amount,
        DateTimeImmutable $paidAt,
        ?string $reference,
        User $user,
        ?string $moneyAccountId,
    ): void {
        if ($this->treasury === null) {
            return;
        }

        try {
            $this->treasury->record(new MoneyMovementRequest(
                key: new MovementKey(MovementSource::SupplierPayment, (string) $payment->getId()),
                direction: Direction::Out,
                amount: Money::toString($amount),
                currency: $bill->getCurrency(),
                branchId: (string) $bill->getBranch()->getId(),
                occurredAt: $paidAt,
                isCash: $paymentMethod->getId() === PaymentMethod::CASH,
                paymentMethodId: $paymentMethod->getId(),
                userId: $user->getId(),
                party: new Party('supplier', (string) $bill->getSupplier()->getId()),
                reference: $reference,
                description: trim(sprintf('Pago factura %s-%s', $bill->getSeries() ?? '', $bill->getNumber() ?? '')),
                accountId: $moneyAccountId,
            ));
        } catch (TreasuryException $e) {
            throw new UnprocessableEntityHttpException($e->getMessage(), $e);
        }
    }

    private function reverseInTreasury(SupplierPayment $payment, ?int $userId): void
    {
        if ($this->treasury === null) {
            return;
        }

        try {
            $this->treasury->reverse(
                new MovementKey(MovementSource::SupplierPayment, (string) $payment->getId()),
                new MovementKey(MovementSource::SupplierPaymentReversal, (string) $payment->getId()),
                new DateTimeImmutable(),
                $userId,
                'Reversión de pago a proveedor',
            );
        } catch (TreasuryException $e) {
            throw new UnprocessableEntityHttpException($e->getMessage(), $e);
        }
    }
}
