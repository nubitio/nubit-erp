<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Product;
use App\Inventory\Core\Enum\LotTrackingStrategy;
use PHPUnit\Framework\TestCase;

final class ProductLotTrackingStrategyTest extends TestCase
{
    public function testSetLotTrackingStrategyAcceptsValidString(): void
    {
        $product = new Product();
        $product->setLotTrackingStrategy('NONE');
        self::assertSame('NONE', $product->getLotTrackingStrategy());

        $product->setLotTrackingStrategy('FIFO');
        self::assertSame('FIFO', $product->getLotTrackingStrategy());

        $product->setLotTrackingStrategy('FEFO');
        self::assertSame('FEFO', $product->getLotTrackingStrategy());
    }

    public function testSetLotTrackingStrategyAcceptsEnumDirectly(): void
    {
        $product = new Product();
        $product->setLotTrackingStrategy(LotTrackingStrategy::NONE);
        self::assertSame('NONE', $product->getLotTrackingStrategy());

        $product->setLotTrackingStrategy(LotTrackingStrategy::FIFO);
        self::assertSame('FIFO', $product->getLotTrackingStrategy());
    }

    public function testStringAndEnumProduceSameResult(): void
    {
        $byString = new Product();
        $byString->setLotTrackingStrategy('FIFO');

        $byEnum = new Product();
        $byEnum->setLotTrackingStrategy(LotTrackingStrategy::FIFO);

        self::assertSame($byEnum->getLotTrackingStrategy(), $byString->getLotTrackingStrategy());
    }

    public function testSetLotTrackingStrategyThrowsOnInvalidString(): void
    {
        $this->expectException(\ValueError::class);
        $product = new Product();
        $product->setLotTrackingStrategy('INVALID');
    }

    public function testGetLotTrackingStrategyEnumConsistency(): void
    {
        $product = new Product();
        $product->setLotTrackingStrategy('FEFO');
        self::assertSame(LotTrackingStrategy::FEFO, $product->getLotTrackingStrategyEnum());
    }
}
