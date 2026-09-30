<?php

declare(strict_types=1);

namespace App\Tests\Restaurant\Entity;

use App\Restaurant\Entity\RestaurantOrder;
use DomainException;
use PHPUnit\Framework\TestCase;

final class RestaurantOrderTest extends TestCase
{
    public function testCloseAssociatesTheSaleAndClosesTheOrder(): void
    {
        $order = new RestaurantOrder(8);

        $order->close('sale-12');

        static::assertFalse($order->isOpen());
        static::assertSame('sale-12', $order->getSaleId());
        static::assertNotNull($order->getClosedAt());
    }

    public function testClosingAgainWithTheSameSaleIsIdempotent(): void
    {
        $order = new RestaurantOrder(8);
        $order->close('sale-12');
        $closedAt = $order->getClosedAt();

        $order->close('sale-12');

        static::assertSame($closedAt, $order->getClosedAt());
        static::assertSame('sale-12', $order->getSaleId());
    }

    public function testCannotAssociateAClosedOrderWithAnotherSale(): void
    {
        $order = new RestaurantOrder(8);
        $order->close('sale-12');

        $this->expectException(DomainException::class);
        $order->close('sale-13');
    }

    public function testCannotCloseWithoutASaleId(): void
    {
        $this->expectException(DomainException::class);
        new RestaurantOrder(8)->close('');
    }
}
