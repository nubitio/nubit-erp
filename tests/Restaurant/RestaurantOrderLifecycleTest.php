<?php

declare(strict_types=1);

namespace App\Tests\Restaurant;

use App\Restaurant\Entity\RestaurantArea;
use App\Restaurant\Entity\RestaurantOrder;
use App\Restaurant\Entity\RestaurantOrderItem;
use App\Restaurant\Entity\RestaurantTable;
use App\Restaurant\Enum\RestaurantOrderItemStatus;
use App\Restaurant\Enum\RestaurantTableStatus;
use PHPUnit\Framework\TestCase;

final class RestaurantOrderLifecycleTest extends TestCase
{
    public function testComandaAdvancesPendingSentReadyDeliveredAndClosesOnce(): void
    {
        $area = new RestaurantArea('Salón');
        $table = new RestaurantTable('M1', $area, 4);
        $order = new RestaurantOrder($table->getId() ?? 1);
        $line = new RestaurantOrderItem($order, 'product-1', 'Ceviche', '25.00', 2, 'sin ají');
        $order->addItem($line);

        static::assertTrue($table->isFree());
        static::assertSame(RestaurantTableStatus::Free, $table->getStatus());
        $table->markOccupied();
        static::assertSame(RestaurantTableStatus::Occupied, $table->getStatus());

        static::assertSame(RestaurantOrderItemStatus::Pending, $line->getStatus());
        static::assertNull($line->getSentAt());

        $order->sendPendingItemsToKitchen();
        static::assertSame(RestaurantOrderItemStatus::Sent, $line->getStatus());
        static::assertNotNull($line->getSentAt());

        $line->markSent();
        static::assertSame(RestaurantOrderItemStatus::Sent, $line->getStatus());

        $line->markReady();
        static::assertSame(RestaurantOrderItemStatus::Ready, $line->getStatus());
        static::assertNotNull($line->getReadyAt());

        $line->markDelivered();
        static::assertSame(RestaurantOrderItemStatus::Delivered, $line->getStatus());
        static::assertNotNull($line->getDeliveredAt());

        static::assertTrue($order->isOpen());
        $order->close('sale-1');
        static::assertFalse($order->isOpen());
        static::assertSame('sale-1', $order->getSaleId());

        $order->close('sale-2');
        static::assertSame('sale-1', $order->getSaleId());
    }

    public function testKitchenTransitionsDoNotSkipStates(): void
    {
        $order = new RestaurantOrder(7);
        $line = new RestaurantOrderItem($order, 'product-2', 'Chicha', '8.00', 1);
        $order->addItem($line);

        $line->markReady();
        $line->markDelivered();
        static::assertSame(RestaurantOrderItemStatus::Pending, $line->getStatus());
        static::assertNull($line->getReadyAt());
        static::assertNull($line->getDeliveredAt());

        $order->sendPendingItemsToKitchen();
        $line->markDelivered();
        static::assertSame(RestaurantOrderItemStatus::Sent, $line->getStatus());
        static::assertNull($line->getDeliveredAt());
    }

    public function testSendingToKitchenLeavesLinesThatAlreadyLeftThePendingState(): void
    {
        $order = new RestaurantOrder(3);
        $pending = new RestaurantOrderItem($order, 'a', 'Arroz', '10.00', 1);
        $alreadySent = new RestaurantOrderItem($order, 'b', 'Pato', '30.00', 1);
        $order->addItem($pending);
        $order->addItem($alreadySent);
        $alreadySent->markSent();
        $sentAt = $alreadySent->getSentAt();

        $order->sendPendingItemsToKitchen();

        static::assertSame(RestaurantOrderItemStatus::Sent, $pending->getStatus());
        static::assertSame($sentAt, $alreadySent->getSentAt());
    }
}
