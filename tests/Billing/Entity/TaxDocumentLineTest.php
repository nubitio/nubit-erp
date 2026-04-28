<?php

declare(strict_types=1);

namespace App\Tests\Billing\Entity;

use App\Billing\Entity\TaxDocument;
use App\Billing\Entity\TaxDocumentLine;
use PHPUnit\Framework\TestCase;

final class TaxDocumentLineTest extends TestCase
{
    public function testTaxDocumentLineAccessors(): void
    {
        $taxDocument = (new TaxDocument())
            ->setDocumentType('01')
            ->setSeries('F001')
            ->setNumber('12');
        $createdAt = new \DateTimeImmutable('2026-02-10 12:00:00');

        $line = new TaxDocumentLine();
        $line->setTaxDocument($taxDocument)
            ->setLineNumber(1)
            ->setProductId('P1')
            ->setProductCode('PRD-1')
            ->setProductName('Producto')
            ->setProductUom('NIU')
            ->setQuantity('2.000000')
            ->setUnitPrice('10.000000')
            ->setUnitValue('8.474576')
            ->setDiscountAmount('0.00')
            ->setTaxedAmount('16.95')
            ->setTaxAmount('3.05')
            ->setTaxPercentage('18.00')
            ->setTaxType('10')
            ->setTotal('20.00')
            ->setCreatedAt($createdAt);

        self::assertSame($taxDocument, $line->getTaxDocument());
        self::assertSame(1, $line->getLineNumber());
        self::assertSame('P1', $line->getProductId());
        self::assertSame('PRD-1', $line->getProductCode());
        self::assertSame('Producto', $line->getProductName());
        self::assertSame('NIU', $line->getProductUom());
        self::assertSame('2.000000', $line->getQuantity());
        self::assertSame('10.000000', $line->getUnitPrice());
        self::assertSame('8.474576', $line->getUnitValue());
        self::assertSame('0.00', $line->getDiscountAmount());
        self::assertSame('16.95', $line->getTaxedAmount());
        self::assertSame('3.05', $line->getTaxAmount());
        self::assertSame('18.00', $line->getTaxPercentage());
        self::assertSame('10', $line->getTaxType());
        self::assertSame('20.00', $line->getTotal());
        self::assertSame($createdAt, $line->getCreatedAt());
    }
}
