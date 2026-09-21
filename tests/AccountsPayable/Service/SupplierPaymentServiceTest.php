<?php

declare(strict_types=1);

namespace App\Tests\AccountsPayable\Service;

use App\AccountsPayable\Entity\SupplierBill;
use App\AccountsPayable\Entity\SupplierPayment;
use App\AccountsPayable\Service\SupplierPaymentService;
use App\Catalog\Entity\PaymentMethod;
use App\Identity\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

#[AllowMockObjectsWithoutExpectations]
final class SupplierPaymentServiceTest extends TestCase
{
    private function pendingBill(
        string $amount = '100.00',
        string $paidTotal = '0.00',
        string $balanceDue = '100.00',
    ): SupplierBill {
        $bill = new SupplierBill()
            ->setAmount($amount)
            ->setPaidTotal($paidTotal)
            ->setBalanceDue($balanceDue);
        new \ReflectionProperty(SupplierBill::class, 'id')->setValue($bill, 'bill-1');

        return $bill;
    }

    private function service(EntityManagerInterface&MockObject $em): SupplierPaymentService
    {
        $em->method('wrapInTransaction')->willReturnCallback(static fn(callable $callback): mixed => $callback());

        return new SupplierPaymentService($em);
    }

    public function testRegisterPaymentRejectsPaymentOnVoidedBill(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(static::never())->method('flush');

        $bill = $this->pendingBill();
        $bill->setStatus(SupplierBill::VOID);

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('No se puede registrar un pago sobre una factura anulada.');

        $this->service($em)->registerPayment(
            $bill,
            new PaymentMethod(),
            '10.00',
            new DateTimeImmutable(),
            null,
            new User(),
        );
    }

    public function testRegisterPaymentRejectsAmountAboveBalance(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(static::never())->method('flush');

        $bill = $this->pendingBill();

        $this->expectException(UnprocessableEntityHttpException::class);

        $this->service($em)->registerPayment(
            $bill,
            new PaymentMethod(),
            '100.01',
            new DateTimeImmutable(),
            null,
            new User(),
        );
    }

    public function testRegisterPaymentMovesBalanceToPartial(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(static::once())->method('persist');
        $em->expects(static::once())->method('flush');

        $bill = $this->pendingBill();
        $payment = $this->service($em)->registerPayment(
            $bill,
            new PaymentMethod(),
            '40.00',
            new DateTimeImmutable(),
            'OP-1',
            new User(),
        );

        static::assertSame('40.00', $bill->getPaidTotal());
        static::assertSame('60.00', $bill->getBalanceDue());
        static::assertSame(SupplierBill::PARTIAL, $bill->getStatus());
        static::assertSame('40.00', $payment->getAmount());
    }

    public function testRegisterPaymentMovesBalanceToPaidWhenFullySettled(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);

        $bill = $this->pendingBill();
        $this->service($em)->registerPayment(
            $bill,
            new PaymentMethod(),
            '100.00',
            new DateTimeImmutable(),
            null,
            new User(),
        );

        static::assertSame('0.00', $bill->getBalanceDue());
        static::assertSame(SupplierBill::PAID, $bill->getStatus());
    }

    private function paymentOn(SupplierBill $bill, string $amount): SupplierPayment
    {
        return new SupplierPayment()
            ->setSupplierBill($bill)
            ->setPaymentMethod(new PaymentMethod())
            ->setAmount($amount)
            ->setPaidAt(new DateTimeImmutable());
    }

    public function testReversePaymentRestoresBalanceAndStatus(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(static::once())->method('flush');

        $bill = $this->pendingBill(paidTotal: '40.00', balanceDue: '60.00');
        $bill->setStatus(SupplierBill::PARTIAL);
        $payment = $this->paymentOn($bill, '40.00');

        $this->service($em)->reversePayment($bill, $payment);

        static::assertSame('0.00', $bill->getPaidTotal());
        static::assertSame('100.00', $bill->getBalanceDue());
        static::assertSame(SupplierBill::PENDING, $bill->getStatus());
        static::assertTrue($payment->isReversed());
    }

    public function testReversePaymentLeavesBillPartialWhenOtherPaymentsRemain(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);

        $bill = $this->pendingBill(paidTotal: '70.00', balanceDue: '30.00');
        $bill->setStatus(SupplierBill::PARTIAL);
        $payment = $this->paymentOn($bill, '30.00');

        $this->service($em)->reversePayment($bill, $payment);

        static::assertSame('40.00', $bill->getPaidTotal());
        static::assertSame('60.00', $bill->getBalanceDue());
        static::assertSame(SupplierBill::PARTIAL, $bill->getStatus());
        static::assertTrue($payment->isReversed());
    }

    public function testReversePaymentRejectsPaymentFromAnotherBill(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(static::never())->method('flush');

        $bill = $this->pendingBill();
        $otherBill = $this->pendingBill();
        new \ReflectionProperty(SupplierBill::class, 'id')->setValue($otherBill, 'bill-2');
        $payment = $this->paymentOn($otherBill, '10.00');

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('El pago no pertenece a esta factura.');

        $this->service($em)->reversePayment($bill, $payment);
    }

    public function testReversePaymentRejectsOnVoidedBill(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(static::never())->method('flush');

        $bill = $this->pendingBill();
        $bill->setStatus(SupplierBill::VOID);
        $payment = $this->paymentOn($bill, '10.00');

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('No se puede reversar un pago de una factura anulada.');

        $this->service($em)->reversePayment($bill, $payment);
    }

    public function testReversePaymentRejectsTheSamePaymentTwice(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(static::never())->method('flush');

        $bill = $this->pendingBill(paidTotal: '40.00', balanceDue: '60.00');
        $payment = $this->paymentOn($bill, '40.00')->setReversedAt(new DateTimeImmutable());

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('Este pago ya fue reversado.');

        $this->service($em)->reversePayment($bill, $payment);
    }
}
