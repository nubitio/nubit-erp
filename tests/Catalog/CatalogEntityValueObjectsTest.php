<?php

declare(strict_types=1);

namespace App\Tests\Catalog;

use App\Catalog\Entity\Category;
use App\Catalog\Entity\PaymentMethod;
use App\Catalog\Entity\Product;
use App\Catalog\Entity\ProductBomItem;
use App\Catalog\Entity\UnitOfMeasure;
use App\Catalog\Entity\VoucherType;
use App\Inventory\Core\Enum\InventoryBehavior;
use PHPUnit\Framework\TestCase;

/**
 * Entity value-object tests for the Catalog bounded context.
 * No DB / Doctrine kernel required.
 */
final class CatalogEntityValueObjectsTest extends TestCase
{
    // ── PaymentMethod ─────────────────────────────────────────────────────────

    public function testPaymentMethodCashConstant(): void
    {
        self::assertSame(1, PaymentMethod::CASH);
    }

    public function testPaymentMethodSettersAndGetters(): void
    {
        $pm = new PaymentMethod();
        $pm->setName('Transferencia');

        self::assertSame('Transferencia', $pm->getName());
        self::assertNull($pm->getId()); // not persisted
    }

    // ── VoucherType ───────────────────────────────────────────────────────────

    public function testVoucherTypeSettersAndGetters(): void
    {
        $vt = new VoucherType();
        $vt->setCode('01')->setName('Factura Electrónica');

        self::assertSame('01', $vt->getCode());
        self::assertSame('Factura Electrónica', $vt->getName());
    }

    public function testVoucherTypeFluentApi(): void
    {
        $vt = new VoucherType();
        self::assertSame($vt, $vt->setCode('03'));
        self::assertSame($vt, $vt->setName('Boleta'));
    }

    // ── Category ─────────────────────────────────────────────────────────────

    public function testCategoryGettersAndSetters(): void
    {
        $cat = new Category();
        $cat->setName('Bebidas');

        self::assertSame('Bebidas', $cat->getName());
        self::assertNull($cat->getId());
    }

    // ── UnitOfMeasure ─────────────────────────────────────────────────────────

    public function testUnitOfMeasureGettersAndSetters(): void
    {
        $uom = new UnitOfMeasure();
        $uom->setName('Kilogramo');

        self::assertSame('Kilogramo', $uom->getName());
    }

    // ── ProductBomItem ────────────────────────────────────────────────────────

    private function makeBomItem(string $componentQty = '2.000000', string $wastage = '1.000000'): ProductBomItem
    {
        $parent = new Product();
        $parent->setInventoryBehavior(InventoryBehavior::COMPOSITE);

        $component = new Product();
        $component->setInventoryBehavior(InventoryBehavior::TRACKED);

        $item = new ProductBomItem();
        $item->setParentProduct($parent);
        $item->setComponentProduct($component);
        $item->setComponentQtyBase($componentQty);
        $item->setWastageFactor($wastage);

        return $item;
    }

    public function testBomItemEffectiveQtyWithWastage(): void
    {
        // 2 base units × 1.1 wastage × 3 parent qty = 6.6
        $item = $this->makeBomItem('2.000000', '1.100000');
        $result = $item->effectiveQty('3.000000');

        self::assertEqualsWithDelta(6.6, (float) $result, 0.0001);
    }

    public function testBomItemEffectiveQtyNoWastage(): void
    {
        // 1 base unit × 1.0 wastage × 5 = 5
        $item = $this->makeBomItem('1.000000', '1.000000');
        $result = $item->effectiveQty('5.000000');

        self::assertEqualsWithDelta(5.0, (float) $result, 0.0001);
    }

    public function testBomItemIsNotOptionalByDefault(): void
    {
        $item = $this->makeBomItem();
        self::assertFalse($item->isOptional());
    }

    public function testBomItemSetOptional(): void
    {
        $item = $this->makeBomItem();
        $item->setIsOptional(true);
        self::assertTrue($item->isOptional());
    }

    public function testBomItemParentAndComponentProduct(): void
    {
        $item = $this->makeBomItem();

        self::assertInstanceOf(Product::class, $item->getParentProduct());
        self::assertInstanceOf(Product::class, $item->getComponentProduct());
        self::assertSame('COMPOSITE', $item->getParentProduct()->getInventoryBehavior());
        self::assertSame('TRACKED', $item->getComponentProduct()->getInventoryBehavior());
    }

    public function testBomItemGettersAndSetters(): void
    {
        $item = $this->makeBomItem('3.500000', '1.050000');

        self::assertSame('3.500000', $item->getComponentQtyBase());
        self::assertSame('1.050000', $item->getWastageFactor());
        self::assertNull($item->getId()); // not persisted
    }
}
