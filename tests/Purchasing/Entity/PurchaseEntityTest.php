<?php

declare(strict_types=1);

namespace App\Tests\Purchasing\Entity;

use App\Inventory\Entity\Warehouse;
use App\Organization\Entity\Branch;
use App\Purchasing\Entity\Purchase;
use App\Purchasing\Entity\PurchaseItem;
use DateTime;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the Purchase entity (setters, defaults, collection helpers).
 * Does NOT require a database — exercises the domain object in isolation.
 */
final class PurchaseEntityTest extends TestCase
{
    // ── Default values ────────────────────────────────────────────────────────

    public function testDefaultValues(): void
    {
        $p = new Purchase();

        self::assertNull($p->getId());
        self::assertSame('POSTED', $p->getStatus());
        self::assertSame('PEN', $p->getCurrency());
        self::assertSame('1.000000', $p->getExchangeRate());
        self::assertSame('0.00', $p->getSubtotal());
        self::assertSame('0.00', $p->getTaxTotal());
        self::assertSame('0.00', $p->getGrandTotal());
        self::assertSame('SYNCED', $p->getSyncStatus());
        self::assertSame(1, $p->getVersionNo());
        self::assertNull($p->getBranch());
        self::assertNull($p->getWarehouse());
        self::assertNull($p->getSupplier());
        self::assertNull($p->getBuyerUser());
        self::assertNull($p->getNote());
        self::assertNull($p->getIssuedAt());
        self::assertNull($p->getCreatedAt());
        self::assertNull($p->getDeletedAt());
        self::assertCount(0, $p->getItems());
    }

    // ── Setters / getters ─────────────────────────────────────────────────────

    public function testIdSetterAndGetter(): void
    {
        $p = new Purchase();
        $result = $p->setId('uuid-1234');

        self::assertSame('uuid-1234', $p->getId());
        self::assertSame($p, $result); // fluent
    }

    public function testStatusSetterAndGetter(): void
    {
        $p = new Purchase();
        $p->setStatus('CANCELLED');

        self::assertSame('CANCELLED', $p->getStatus());
    }

    public function testCurrencyAndExchangeRate(): void
    {
        $p = new Purchase();
        $p->setCurrency('USD');
        $p->setExchangeRate('3.750000');

        self::assertSame('USD', $p->getCurrency());
        self::assertSame('3.750000', $p->getExchangeRate());
    }

    public function testAmountFields(): void
    {
        $p = new Purchase();
        $p->setSubtotal('100.00');
        $p->setTaxTotal('18.00');
        $p->setGrandTotal('118.00');

        self::assertSame('100.00', $p->getSubtotal());
        self::assertSame('18.00', $p->getTaxTotal());
        self::assertSame('118.00', $p->getGrandTotal());
    }

    public function testBranchAndWarehouse(): void
    {
        $branch = new Branch();
        $warehouse = new Warehouse();

        $p = new Purchase();
        $p->setBranch($branch);
        $p->setWarehouse($warehouse);

        self::assertSame($branch, $p->getBranch());
        self::assertSame($warehouse, $p->getWarehouse());
    }

    public function testNoteAndSyncKeys(): void
    {
        $p = new Purchase();
        $p->setNote('Compra urgente');
        $p->setLocalUuid('local-uuid-1');
        $p->setIdempotencyKey('idem-key');
        $p->setDeviceId('device-1');

        self::assertSame('Compra urgente', $p->getNote());
        self::assertSame('local-uuid-1', $p->getLocalUuid());
        self::assertSame('idem-key', $p->getIdempotencyKey());
        self::assertSame('device-1', $p->getDeviceId());
    }

    public function testSyncFields(): void
    {
        $p = new Purchase();
        $now = new DateTime();
        $p->setSyncStatus('PENDING');
        $p->setSyncedAt($now);
        $p->setVersionNo(3);

        self::assertSame('PENDING', $p->getSyncStatus());
        self::assertSame($now, $p->getSyncedAt());
        self::assertSame(3, $p->getVersionNo());
    }

    public function testDateFields(): void
    {
        $p = new Purchase();
        $issued = new DateTime('2026-04-01');
        $created = new DateTime('2026-04-01 08:00:00');
        $updated = new DateTime('2026-04-01 09:00:00');
        $deleted = new DateTime('2026-04-30');
        $lastMod = new DateTime('2026-04-30 10:00:00');

        $p->setIssuedAt($issued);
        $p->setCreatedAt($created);
        $p->setUpdatedAt($updated);
        $p->setDeletedAt($deleted);
        $p->setLastModifiedAt($lastMod);

        self::assertSame($issued, $p->getIssuedAt());
        self::assertSame($created, $p->getCreatedAt());
        self::assertSame($updated, $p->getUpdatedAt());
        self::assertSame($deleted, $p->getDeletedAt());
        self::assertSame($lastMod, $p->getLastModifiedAt());
    }

    // ── Items collection ──────────────────────────────────────────────────────

    public function testAddItemAndGetItems(): void
    {
        $p = new Purchase();
        $item = new PurchaseItem();

        $result = $p->addItem($item);

        self::assertSame($p, $result); // fluent
        self::assertCount(1, $p->getItems());
        self::assertTrue($p->getItems()->contains($item));
        // addItem must set back-reference
        self::assertSame($p, $item->getPurchase());
    }

    public function testRemoveItem(): void
    {
        $p = new Purchase();
        $item = new PurchaseItem();
        $p->addItem($item);

        $result = $p->removeItem($item);

        self::assertSame($p, $result); // fluent
        self::assertCount(0, $p->getItems());
    }

    public function testAddSameItemTwiceDoesNotDuplicate(): void
    {
        $p = new Purchase();
        $item = new PurchaseItem();

        $p->addItem($item);
        $p->addItem($item); // duplicate

        self::assertCount(1, $p->getItems());
    }

    // ── setTimestampsOnPersist ─────────────────────────────────────────────────

    public function testSetTimestampsOnPersistSetsNullDates(): void
    {
        $p = new Purchase();
        $before = new DateTime();
        $p->setTimestampsOnPersist();
        $after = new DateTime();

        self::assertNotNull($p->getCreatedAt());
        self::assertNotNull($p->getIssuedAt());
        self::assertNotNull($p->getLastModifiedAt());
        self::assertGreaterThanOrEqual($before, $p->getCreatedAt());
        self::assertLessThanOrEqual($after, $p->getCreatedAt());
    }

    public function testSetTimestampsOnPersistDoesNotOverwriteExistingDates(): void
    {
        $p = new Purchase();
        $existing = new DateTime('2025-01-01');
        $p->setCreatedAt($existing);
        $p->setIssuedAt($existing);

        $p->setTimestampsOnPersist();

        self::assertSame($existing, $p->getCreatedAt());
        self::assertSame($existing, $p->getIssuedAt());
    }
}
