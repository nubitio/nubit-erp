<?php

declare(strict_types=1);

namespace App\Tests\Purchasing\Service;

use App\Purchasing\Entity\Purchase;
use App\Purchasing\Service\PurchaseSupplierDocumentValidator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class PurchaseSupplierDocumentValidatorTest extends TestCase
{
    public function testRequiresSupplierDocumentFields(): void
    {
        $purchase = new Purchase();

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('tipo de comprobante');

        new PurchaseSupplierDocumentValidator()->assertRequiredForCreate($purchase);
    }

    public function testAcceptsCompleteSupplierDocument(): void
    {
        $purchase = new Purchase()
            ->setSupplierDocType('01')
            ->setSupplierDocSeries('F001')
            ->setSupplierDocNumber('12345');

        new PurchaseSupplierDocumentValidator()->assertRequiredForCreate($purchase);

        self::assertSame('01', $purchase->getSupplierDocType());
    }
}
