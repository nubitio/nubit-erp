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

        self::assertTrue($table->isFree());
        self::assertSame(RestaurantTableStatus::Free, $table->getStatus());
        $table->markOccupied();
        self::assertSame(RestaurantTableStatus::Occupied, $table->getStatus());

        self::assertSame(RestaurantOrderItemStatus::Pending, $line->getStatus());
        self::assertNull($line->getSentAt());

        $order->sendPendingItemsToKitchen();
        self::assertSame(RestaurantOrderItemStatus::Sent, $line->getStatus());
        self::assertNotNull($line->getSentAt());

        $line->markSent();
        self::assertSame(RestaurantOrderItemStatus::Sent, $line->getStatus());

        $line->markReady();
        self::assertSame(RestaurantOrderItemStatus::Ready, $line->getStatus());
        self::assertNotNull($line->getReadyAt());

        $line->markDelivered();
        self::assertSame(RestaurantOrderItemStatus::Delivered, $line->getStatus());
        self::assertNotNull($line->getDeliveredAt());

        self::assertTrue($order->isOpen());
        $order->close('sale-1');
        self::assertFalse($order->isOpen());
        self::assertSame('sale-1', $order->getSaleId());

        $order->close('sale-2');
        self::assertSame('sale-1', $order->getSaleId());
    }

    public function testKitchenTransitionsDoNotSkipStates(): void
    {
        $order = new RestaurantOrder(7);
        $line = new RestaurantOrderItem($order, 'product-2', 'Chicha', '8.00', 1);
        $order->addItem($line);

        $line->markReady();
        $line->markDelivered();
        self::assertSame(RestaurantOrderItemStatus::Pending, $line->getStatus());
        self::assertNull($line->getReadyAt());
        self::assertNull($line->getDeliveredAt());

        $order->sendPendingItemsToKitchen();
        $line->markDelivered();
        self::assertSame(RestaurantOrderItemStatus::Sent, $line->getStatus());
        self::assertNull($line->getDeliveredAt());
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

        self::assertSame(RestaurantOrderItemStatus::Sent, $pending->getStatus());
        self::assertSame($sentAt, $alreadySent->getSentAt());
    }
}
