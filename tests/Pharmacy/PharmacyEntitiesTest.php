<?php

declare(strict_types=1);

namespace App\Tests\Pharmacy;

use App\Pharmacy\Entity\PharmacyBatch;
use App\Pharmacy\Entity\PharmacyProduct;
use App\Pharmacy\Enum\ExpiryControl;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class PharmacyEntitiesTest extends TestCase
{
    public function testPharmacyProductDefaultsAndMutators(): void
    {
        $product = new PharmacyProduct(42);

        self::assertNull($product->getId());
        self::assertSame(42, $product->getProductId());
        self::assertFalse($product->requiresPrescription());
        self::assertFalse($product->isControlled());
        self::assertSame(ExpiryControl::None, $product->getExpiryControl());
        self::assertEquals($product->getCreatedAt(), $product->getUpdatedAt());

        $product
            ->setActiveIngredient('paracetamol')
            ->setSanitaryRegistration('EN-12345')
            ->setRequiresPrescription(true)
            ->setControlled(true)
            ->setExpiryControl(ExpiryControl::Block);

        self::assertSame('paracetamol', $product->getActiveIngredient());
        self::assertSame('EN-12345', $product->getSanitaryRegistration());
        self::assertTrue($product->requiresPrescription());
        self::assertTrue($product->isControlled());
        self::assertSame(ExpiryControl::Block, $product->getExpiryControl());
        self::assertGreaterThanOrEqual($product->getCreatedAt(), $product->getUpdatedAt());
    }

    public function testPharmacyBatchExpiryCheck(): void
    {
        $batch = new PharmacyBatch(7, 'L-2401', new DateTimeImmutable('2026-01-31'));
        $batch->setManufacturer('ACME Labs');

        self::assertSame(7, $batch->getProductId());
        self::assertSame('L-2401', $batch->getBatchNumber());
        self::assertSame('ACME Labs', $batch->getManufacturer());
        self::assertTrue($batch->isExpiredOn(new DateTimeImmutable('2026-02-01 09:00')));
        self::assertFalse($batch->isExpiredOn(new DateTimeImmutable('2026-01-31 23:59')));
    }
}
