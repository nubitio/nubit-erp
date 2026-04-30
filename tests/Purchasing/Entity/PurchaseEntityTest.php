<?php

declare(strict_types=1);

namespace App\Tests\Purchasing\Entity;

use App\Purchasing\Entity\Purchase;
use App\Purchasing\Entity\PurchaseItem;
use PHPUnit\Framework\TestCase;

final class PurchaseEntityTest extends TestCase
{
    // ── Purchase defaults ────────────────────────────────────────────────────

    public function testPurchaseDefaults(): void
    {
        $p = new Purchase();

        self::assertNull($p->getId());
        self::assertSame('POSTED', $p->getStatus());
        self::assertSame('PEN', $p->getCurrency());
        self::assertSame('0.00', $p->getSubtotal());
        self::assertNull($p->getBranch());
        self::assertNull($p->getWarehouse());
        self::assertNull($p->getSupplier());
        self::assertTrue($p->getItems()->isEmpty());
    }

    public function testPurchaseStatusRoundTrip(): void
    {
        $p = (new Purchase())->setStatus('DRAFT');
        self::assertSame('DRAFT', $p->getStatus());
    }

    public function testPurchaseCurrencyRoundTrip(): void
    {
        $p = (new Purchase())->setCurrency('USD');
        self::assertSame('USD', $p->getCurrency());
    }

    public function testPurchaseTotalRoundTrip(): void
    {
        $p = new Purchase();
        $p->setGrandTotal('350.00');

        self::assertSame('350.00', $p->getGrandTotal());
    }

    public function testPurchaseExchangeRateDefault(): void
    {
        self::assertSame('1.000000', (new Purchase())->getExchangeRate());
    }

    public function testPurchaseIdFluent(): void
    {
        $uuid = 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11';
        $p    = (new Purchase())->setId($uuid);

        self::assertSame($uuid, $p->getId());
    }

    // ── PurchaseItem defaults ─────────────────────────────────────────────────

    public function testPurchaseItemDefaults(): void
    {
        $item = new PurchaseItem();

        self::assertNull($item->getId());
        self::assertNull($item->getPurchase());
        self::assertNull($item->getProduct());
    }

    public function testPurchaseItemLineNoRoundTrip(): void
    {
        $item = new PurchaseItem();
        $item->setLineNo(3);

        self::assertSame(3, $item->getLineNo());
    }

    public function testPurchaseItemLinksToPurchase(): void
    {
        $purchase = new Purchase();
        $item     = (new PurchaseItem())->setPurchase($purchase);

        self::assertSame($purchase, $item->getPurchase());
    }
}
