<?php

declare(strict_types=1);

namespace App\Tests\AccountsPayable\State;

use ApiPlatform\Metadata\Delete;
use App\AccountsPayable\Entity\SupplierBill;
use App\AccountsPayable\State\SupplierBillVoidProcessor;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class SupplierBillVoidProcessorTest extends TestCase
{
    public function testVoidsABillWithNoPaymentsApplied(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(static::once())->method('flush');

        $bill = new SupplierBill()
            ->setAmount('100.00')
            ->setPaidTotal('0.00')
            ->setBalanceDue('100.00');

        $result = new SupplierBillVoidProcessor($em)->process($bill, new Delete(), [], []);

        static::assertSame(SupplierBill::VOID, $bill->getStatus());
        /** @phpstan-ignore staticMethod.alreadyNarrowedType */
        static::assertNull($result);
    }

    public function testRejectsAnAlreadyVoidedBill(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(static::never())->method('flush');

        $bill = new SupplierBill()
            ->setAmount('100.00')
            ->setPaidTotal('0.00')
            ->setBalanceDue('100.00');
        $bill->setStatus(SupplierBill::VOID);

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('Esta factura ya fue anulada.');

        new SupplierBillVoidProcessor($em)->process($bill, new Delete(), [], []);
    }

    public function testRejectsABillWithPaymentsApplied(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(static::never())->method('flush');

        $bill = new SupplierBill()
            ->setAmount('100.00')
            ->setPaidTotal('40.00')
            ->setBalanceDue('60.00');
        $bill->setStatus(SupplierBill::PARTIAL);

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('No se puede anular una factura con pagos aplicados. Reversa los pagos primero.');

        new SupplierBillVoidProcessor($em)->process($bill, new Delete(), [], []);
    }
}
