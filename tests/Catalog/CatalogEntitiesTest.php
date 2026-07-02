<?php

declare(strict_types=1);

namespace App\Tests\Catalog;

use App\Catalog\Entity\Category;
use App\Catalog\Entity\PaymentMethod;
use App\Catalog\Entity\Product;
use App\Catalog\Entity\ProductBomItem;
use App\Catalog\Entity\ProductUnit;
use App\Catalog\Entity\SunatCatalog;
use App\Catalog\Entity\UnitOfMeasure;
use App\Catalog\Entity\VoucherType;
use PHPUnit\Framework\TestCase;

/**
 * Entity tests for Catalog bounded context (entities not yet covered).
 */
final class CatalogEntitiesTest extends TestCase
{
    // ── Category ──────────────────────────────────────────────────────────────

    public function testCategorySettersAndGetters(): void
    {
        $cat = new Category();
        $cat->setName('Bebidas');

        self::assertSame('Bebidas', $cat->getName());
        self::assertNull($cat->getId());
        self::assertNull($cat->getParent());
        self::assertCount(0, $cat->getChildren());
    }

    public function testCategoryParentChild(): void
    {
        $parent = new Category();
        $parent->setName('Alimentos');

        $child = new Category();
        $child->setName('Bebidas');
        $child->setParent($parent);
        $parent->addChild($child);

        self::assertSame($parent, $child->getParent());
        self::assertCount(1, $parent->getChildren());
        self::assertTrue($parent->getChildren()->contains($child));
    }

    public function testCategoryRemoveChild(): void
    {
        $parent = new Category();
        $child = new Category();
        $child->setParent($parent);
        $parent->addChild($child);

        $parent->removeChild($child);

        self::assertCount(0, $parent->getChildren());
    }

    // ── UnitOfMeasure ─────────────────────────────────────────────────────────

    public function testUnitOfMeasureSettersAndGetters(): void
    {
        $uom = new UnitOfMeasure();
        $uom->setName('Kilogramo');
        $uom->setCode('KG');
        $uom->setType(1);
        $uom->setRatio('1000.00');
        $uom->setReference(true);

        self::assertSame('Kilogramo', $uom->getName());
        self::assertSame('KG', $uom->getCode());
        self::assertSame(1, $uom->getType());
        self::assertSame('1000.00', $uom->getRatio());
        self::assertTrue($uom->isReference());
        self::assertNull($uom->getId());
    }

    public function testUnitOfMeasureDefaultValues(): void
    {
        $uom = new UnitOfMeasure();

        self::assertSame('1.00', $uom->getRatio());
        self::assertFalse($uom->isReference());
    }

    // ── ProductUnit ───────────────────────────────────────────────────────────

    public function testProductUnitSettersAndGetters(): void
    {
        $product = new Product();
        $uom = new UnitOfMeasure();

        $pu = new ProductUnit();
        $pu->setProduct($product);
        $pu->setUnit($uom);
        $pu->setConversionToBase('12.000000');
        $pu->setDefaultSaleUnit(true);

        self::assertSame($product, $pu->getProduct());
        self::assertSame($uom, $pu->getUnit());
        self::assertSame('12.000000', $pu->getConversionToBase());
        self::assertTrue($pu->isDefaultSaleUnit());
        self::assertNull($pu->getPurchaseUnit());
        self::assertNull($pu->getId());
    }

    public function testProductUnitCreatedAtOnConstruct(): void
    {
        $before = new \DateTimeImmutable();
        $pu = new ProductUnit();
        $after = new \DateTimeImmutable();

        self::assertGreaterThanOrEqual($before, $pu->getCreatedAt());
        self::assertLessThanOrEqual($after, $pu->getCreatedAt());
    }

    public function testProductUnitPurchaseUnitIsSettable(): void
    {
        $pu = new ProductUnit();
        $purchaseUom = new UnitOfMeasure();
        $pu->setPurchaseUnit($purchaseUom);

        self::assertSame($purchaseUom, $pu->getPurchaseUnit());
    }

    // ── SunatCatalog ──────────────────────────────────────────────────────────

    public function testSunatCatalogSettersAndGetters(): void
    {
        $cat = new SunatCatalog();
        $cat->setCatalog(7);
        $cat->setCode('10');
        $cat->setDescription('Gravado - Operación Onerosa');
        $cat->setPercentage(18.0);
        $cat->setActive(true);

        self::assertSame(7, $cat->getCatalog());
        self::assertSame('10', $cat->getCode());
        self::assertSame('Gravado - Operación Onerosa', $cat->getDescription());
        self::assertEqualsWithDelta(18.0, $cat->getPercentage() ?? 0, 0.001);
        self::assertTrue($cat->isActive());
        self::assertNull($cat->getId());
    }

    public function testSunatCatalogDefaultActiveTrue(): void
    {
        $cat = new SunatCatalog();
        self::assertTrue($cat->isActive());
        self::assertNull($cat->getPercentage());
    }

    // ── VoucherType ───────────────────────────────────────────────────────────

    public function testVoucherTypeSettersAndGetters(): void
    {
        $vt = new VoucherType();
        $vt->setCode('01');
        $vt->setName('Factura');

        self::assertSame('01', $vt->getCode());
        self::assertSame('Factura', $vt->getName());
    }

    // ── PaymentMethod ─────────────────────────────────────────────────────────

    public function testPaymentMethodSettersAndGetters(): void
    {
        $pm = new PaymentMethod();
        $pm->setName('Efectivo');

        self::assertSame('Efectivo', $pm->getName());
        self::assertNull($pm->getId());
    }

    // ── ProductBomItem ────────────────────────────────────────────────────────

    public function testProductBomItemSettersAndGetters(): void
    {
        $parent = new Product();
        $component = new Product();

        $bom = new ProductBomItem();
        $bom->setParentProduct($parent);
        $bom->setComponentProduct($component);
        $bom->setComponentQtyBase('2.000000');
        $bom->setWastageFactor('1.0500');
        $bom->setIsOptional(false);
        $bom->setSortOrder(1);

        self::assertSame($parent, $bom->getParentProduct());
        self::assertSame($component, $bom->getComponentProduct());
        self::assertSame('2.000000', $bom->getComponentQtyBase());
        self::assertSame('1.0500', $bom->getWastageFactor());
        self::assertFalse($bom->isOptional());
        self::assertSame(1, $bom->getSortOrder());
        self::assertNull($bom->getId());
    }

    public function testProductBomItemEffectiveQty(): void
    {
        $bom = new ProductBomItem();
        $bom->setComponentQtyBase('2.000000');
        $bom->setWastageFactor('1.0500');

        // effectiveQty = 2.000000 × 1.0500 × parentQty
        // parentQty=3 → 2 * 1.05 * 3 = 6.3
        $result = $bom->effectiveQty('3.000000');

        self::assertEqualsWithDelta(6.3, (float) $result, 0.000001);
    }

    public function testProductBomItemCreatedAtOnConstruct(): void
    {
        $before = new \DateTimeImmutable();
        $bom = new ProductBomItem();
        $after = new \DateTimeImmutable();

        self::assertGreaterThanOrEqual($before, $bom->getCreatedAt());
        self::assertLessThanOrEqual($after, $bom->getCreatedAt());
    }

    public function testProductBomItemEffectiveQtyWithParentQtyOne(): void
    {
        $bom = new ProductBomItem();
        $bom->setComponentQtyBase('5.000000');
        $bom->setWastageFactor('1.0000');

        self::assertSame('5.000000', $bom->effectiveQty('1.000000'));
    }
}
