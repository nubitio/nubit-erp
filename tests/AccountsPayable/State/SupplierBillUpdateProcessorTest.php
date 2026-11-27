<?php

declare(strict_types=1);

namespace App\Tests\AccountsPayable\State;

use App\AccountsPayable\Entity\SupplierBill;
use App\AccountsPayable\State\SupplierBillUpdateProcessor;
use App\Contacts\Entity\Supplier;
use App\Organization\Entity\Branch;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/** G-12: the debt a bill carries is frozen once booked; only descriptive fields stay editable. */
final class SupplierBillUpdateProcessorTest extends TestCase
{
    public function testDescriptiveFieldsAndDueDateRemainEditable(): void
    {
        $before = $this->bill('100.00');
        $after = $this->bill('100.0');
        $after->setNote('Pagar el viernes')->setDueDate(new \DateTimeImmutable('2026-12-31'));

        SupplierBillUpdateProcessor::assertEditable($before, $after);

        $this->expectNotToPerformAssertions();
    }

    public function testChangingTheAmountIsRejected(): void
    {
        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('El importe no se puede cambiar');

        SupplierBillUpdateProcessor::assertEditable($this->bill('100.00'), $this->bill('90.00'));
    }

    public function testChangingTheCurrencyIsRejected(): void
    {
        $after = $this->bill('100.00')->setCurrency('USD');

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('La moneda no se puede cambiar');

        SupplierBillUpdateProcessor::assertEditable($this->bill('100.00'), $after);
    }

    public function testChangingTheSupplierIsRejected(): void
    {
        $after = $this->bill('100.00', supplierId: 's-2');

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('El proveedor no se puede cambiar');

        SupplierBillUpdateProcessor::assertEditable($this->bill('100.00'), $after);
    }

    public function testAVoidedBillIsFrozen(): void
    {
        $before = $this->bill('100.00');
        $before->setStatus(SupplierBill::VOID);

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('anulada');

        SupplierBillUpdateProcessor::assertEditable($before, $this->bill('100.00'));
    }

    private function bill(string $amount, string $supplierId = 's-1'): SupplierBill
    {
        $supplier = $this->createStub(Supplier::class);
        $supplier->method('getId')->willReturn($supplierId);
        $branch = $this->createStub(Branch::class);
        $branch->method('getId')->willReturn('b-1');

        return new SupplierBill()
            ->setSupplier($supplier)
            ->setBranch($branch)
            ->setAmount($amount)
            ->setBalanceDue($amount);
    }
}
