<?php

declare(strict_types=1);

namespace App\Tests\Sales\Entity;

use App\Sales\Entity\SaleItem;
use App\Sales\Entity\SaleRecord;
use PHPUnit\Framework\TestCase;

final class SaleEntityTest extends TestCase
{
    // ── SaleRecord defaults ──────────────────────────────────────────────────

    public function testSaleRecordDefaults(): void
    {
        $sale = new SaleRecord();

        self::assertNull($sale->getId());
        self::assertSame('COMPLETED', $sale->getStatus());
        self::assertSame('PEN', $sale->getCurrency());
        self::assertSame('0.00', $sale->getGrandTotal());
        self::assertSame('0.00', $sale->getSubtotal());
        self::assertSame('0.00', $sale->getTaxTotal());
        self::assertFalse($sale->isHasInvoice());
        self::assertNull($sale->getBranch());
        self::assertNull($sale->getCustomer());
        self::assertTrue($sale->getItems()->isEmpty());
    }

    public function testSaleRecordStatusRoundTrip(): void
    {
        $sale = new SaleRecord();
        $sale->setStatus('VOIDED');

        self::assertSame('VOIDED', $sale->getStatus());
    }

    public function testSaleRecordCurrencyRoundTrip(): void
    {
        $sale = new SaleRecord();
        $sale->setCurrency('USD');

        self::assertSame('USD', $sale->getCurrency());
    }

    public function testSaleRecordGrandTotalRoundTrip(): void
    {
        $sale = new SaleRecord();
        $sale->setGrandTotal('150.00');

        self::assertSame('150.00', $sale->getGrandTotal());
    }

    public function testSaleRecordHasInvoiceFluent(): void
    {
        $sale = (new SaleRecord())->setHasInvoice(true);

        self::assertTrue($sale->isHasInvoice());
    }

    public function testSaleRecordExchangeRateDefault(): void
    {
        $sale = new SaleRecord();
        self::assertSame('1.000000', $sale->getExchangeRate());
    }

    public function testSaleRecordSetId(): void
    {
        $sale = new SaleRecord();
        $uuid = 'f47ac10b-58cc-4372-a567-0e02b2c3d479';
        $sale->setId($uuid);

        self::assertSame($uuid, $sale->getId());
    }

    public function testSaleRecordGlosaRoundTrip(): void
    {
        $sale = (new SaleRecord())->setGlosa('Test note');

        self::assertSame('Test note', $sale->getGlosa());
    }

    // ── SaleItem defaults ────────────────────────────────────────────────────

    public function testSaleItemDefaults(): void
    {
        $item = new SaleItem();

        self::assertNull($item->getId());
        self::assertNull($item->getMovimiento());
        self::assertNull($item->getProducto());
    }

    public function testSaleItemCantidadRoundTrip(): void
    {
        $item = new SaleItem();
        $item->setCantidad(3.5);

        self::assertSame(3.5, $item->getCantidad());
    }

    public function testSaleItemPrecioUnitarioRoundTrip(): void
    {
        $item = (new SaleItem())->setPrecioUnitario('25.50');

        self::assertSame('25.50', $item->getPrecioUnitario());
    }

    public function testSaleItemPrecioTotalRoundTrip(): void
    {
        $item = (new SaleItem())->setPrecioTotal('76.50');

        self::assertSame('76.50', $item->getPrecioTotal());
    }

    public function testSaleItemLinksToSaleRecord(): void
    {
        $sale = new SaleRecord();
        $item = new SaleItem();
        $item->setMovimiento($sale);

        self::assertSame($sale, $item->getMovimiento());
    }
}
