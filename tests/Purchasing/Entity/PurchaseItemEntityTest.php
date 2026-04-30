<?php

declare(strict_types=1);

namespace App\Tests\Purchasing\Entity;

use App\Catalog\Entity\Product;
use App\Purchasing\Entity\Purchase;
use App\Purchasing\Entity\PurchaseItem;
use DateTime;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for PurchaseItem entity — setters, defaults, back-reference.
 */
final class PurchaseItemEntityTest extends TestCase
{
    // ── Defaults ──────────────────────────────────────────────────────────────

    public function testDefaultValues(): void
    {
        $item = new PurchaseItem();

        self::assertNull($item->getId());
        self::assertNull($item->getPurchase());
        self::assertNull($item->getProduct());
        self::assertSame(0, $item->getLineNo());
        self::assertSame('', $item->getNameSnapshot());
        self::assertSame('0.000', (string) $item->getQty());
        self::assertSame('0.0000', $item->getUnitCost());
        self::assertSame('0.00', $item->getGrossAmount());
        self::assertSame('0.00', $item->getTaxAmount());
        self::assertSame('0.00', $item->getTotalAmount());
        self::assertSame('SYNCED', $item->getSyncStatus());
        self::assertSame(1, $item->getVersionNo());
        self::assertNull($item->getSkuSnapshot());
        self::assertNull($item->getVariantId());
        self::assertNull($item->getUomSnapshot());
        self::assertNull($item->getCreatedAt());
        self::assertNull($item->getLocalUuid());
        self::assertNull($item->getIdempotencyKey());
        self::assertNull($item->getDeviceId());
    }

    // ── Setters / getters ─────────────────────────────────────────────────────

    public function testPurchaseBackReference(): void
    {
        $purchase = new Purchase();
        $item = new PurchaseItem();

        $result = $item->setPurchase($purchase);

        self::assertSame($purchase, $item->getPurchase());
        self::assertSame($item, $result); // fluent
    }

    public function testProductSetterAndGetter(): void
    {
        $product = new Product();
        $item = new PurchaseItem();
        $item->setProduct($product);

        self::assertSame($product, $item->getProduct());
    }

    public function testLineNoSetterAndGetter(): void
    {
        $item = new PurchaseItem();
        $item->setLineNo(3);

        self::assertSame(3, $item->getLineNo());
    }

    public function testSnapshotFields(): void
    {
        $item = new PurchaseItem();
        $item->setNameSnapshot('Arroz extra');
        $item->setSkuSnapshot('SKU-001');
        $item->setUomSnapshot('KG');
        $item->setVariantId('variant-42');

        self::assertSame('Arroz extra', $item->getNameSnapshot());
        self::assertSame('SKU-001', $item->getSkuSnapshot());
        self::assertSame('KG', $item->getUomSnapshot());
        self::assertSame('variant-42', $item->getVariantId());
    }

    public function testQtyAcceptsStringIntAndFloat(): void
    {
        $item = new PurchaseItem();

        $item->setQty('3.500');
        self::assertSame('3.500', $item->getQty());

        $item->setQty(5);
        self::assertSame('5', (string) $item->getQty());

        $item->setQty(2.5);
        self::assertEqualsWithDelta(2.5, (float) $item->getQty(), 0.0001);
    }

    public function testAmountFields(): void
    {
        $item = new PurchaseItem();
        $item->setUnitCost('25.5000');
        $item->setGrossAmount('76.50');
        $item->setTaxAmount('13.77');
        $item->setTotalAmount('90.27');

        self::assertSame('25.5000', $item->getUnitCost());
        self::assertSame('76.50', $item->getGrossAmount());
        self::assertSame('13.77', $item->getTaxAmount());
        self::assertSame('90.27', $item->getTotalAmount());
    }

    public function testSyncAndVersionFields(): void
    {
        $item = new PurchaseItem();
        $now = new DateTime();
        $item->setSyncStatus('PENDING');
        $item->setSyncedAt($now);
        $item->setVersionNo(4);
        $item->setLocalUuid('lu-1');
        $item->setIdempotencyKey('ik-1');
        $item->setDeviceId('dev-1');

        self::assertSame('PENDING', $item->getSyncStatus());
        self::assertSame($now, $item->getSyncedAt());
        self::assertSame(4, $item->getVersionNo());
        self::assertSame('lu-1', $item->getLocalUuid());
        self::assertSame('ik-1', $item->getIdempotencyKey());
        self::assertSame('dev-1', $item->getDeviceId());
    }

    public function testLegacyIdField(): void
    {
        $item = new PurchaseItem();
        $item->setLegacyMovimientoDetalleId(999);

        self::assertSame(999, $item->getLegacyMovimientoDetalleId());
    }

    public function testDateFields(): void
    {
        $item = new PurchaseItem();
        $created = new DateTime('2026-01-01');
        $updated = new DateTime('2026-04-30');
        $lastMod = new DateTime('2026-04-30 12:00:00');

        $item->setCreatedAt($created);
        $item->setUpdatedAt($updated);
        $item->setLastModifiedAt($lastMod);

        self::assertSame($created, $item->getCreatedAt());
        self::assertSame($updated, $item->getUpdatedAt());
        self::assertSame($lastMod, $item->getLastModifiedAt());
    }

    // ── setTimestampsOnPersist ─────────────────────────────────────────────────

    public function testSetTimestampsOnPersistSetsNullDates(): void
    {
        $item = new PurchaseItem();
        $before = new DateTime();
        $item->setTimestampsOnPersist();
        $after = new DateTime();

        self::assertNotNull($item->getCreatedAt());
        self::assertGreaterThanOrEqual($before, $item->getCreatedAt());
        self::assertLessThanOrEqual($after, $item->getCreatedAt());
    }

    public function testSetTimestampsOnPersistDoesNotOverwriteExistingDate(): void
    {
        $item = new PurchaseItem();
        $existing = new DateTime('2025-06-01');
        $item->setCreatedAt($existing);
        $item->setTimestampsOnPersist();

        self::assertSame($existing, $item->getCreatedAt());
    }

    public function testSetTimestampsOnUpdateSetsUpdatedAt(): void
    {
        $item = new PurchaseItem();
        $before = new DateTime();
        $item->setTimestampsOnUpdate();
        $after = new DateTime();

        self::assertNotNull($item->getUpdatedAt());
        self::assertGreaterThanOrEqual($before, $item->getUpdatedAt());
        self::assertLessThanOrEqual($after, $item->getUpdatedAt());
    }
}
