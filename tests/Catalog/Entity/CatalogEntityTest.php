<?php

declare(strict_types=1);

namespace App\Tests\Catalog\Entity;

use App\Catalog\Entity\Category;
use App\Catalog\Entity\PaymentMethod;
use App\Catalog\Entity\UnitOfMeasure;
use PHPUnit\Framework\TestCase;

final class CatalogEntityTest extends TestCase
{
    // ── Category ─────────────────────────────────────────────────────────────

    public function testCategoryNameRoundTrip(): void
    {
        $cat = new Category();
        $cat->setName('Beverages');

        self::assertSame('Beverages', $cat->getName());
        self::assertNull($cat->getId());
    }

    public function testCategoryParentRelationship(): void
    {
        $parent = new Category();
        $parent->setName('Food');

        $child = new Category();
        $child->setName('Dairy');
        $child->setParent($parent);

        self::assertSame($parent, $child->getParent());
    }

    public function testCategoryAddAndRemoveChild(): void
    {
        $parent = new Category();
        $parent->setName('Root');

        $child = new Category();
        $child->setName('Sub');

        $parent->addChild($child);
        self::assertCount(1, $parent->getChildren());

        $parent->removeChild($child);
        self::assertCount(0, $parent->getChildren());
    }

    public function testCategoryDefaultsToNoParent(): void
    {
        $cat = new Category();
        self::assertNull($cat->getParent());
        self::assertTrue($cat->getChildren()->isEmpty());
    }

    // ── PaymentMethod ─────────────────────────────────────────────────────────

    public function testPaymentMethodNameRoundTrip(): void
    {
        $pm = new PaymentMethod();
        $pm->setName('Cash');

        self::assertSame('Cash', $pm->getName());
        self::assertNull($pm->getId());
    }

    // ── UnitOfMeasure ─────────────────────────────────────────────────────────

    public function testUnitOfMeasureFields(): void
    {
        $uom = new UnitOfMeasure();
        $uom->setName('Kilogram')->setCode('KG')->setRatio('1.000')->setType(1);

        self::assertSame('Kilogram', $uom->getName());
        self::assertSame('KG', $uom->getCode());
        self::assertSame('1.000', $uom->getRatio());
        self::assertSame(1, $uom->getType());
        self::assertNull($uom->getId());
    }

    public function testUnitOfMeasureIsReferenceDefault(): void
    {
        $uom = new UnitOfMeasure();
        self::assertFalse($uom->isReference());
    }
}
