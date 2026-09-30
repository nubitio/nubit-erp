<?php

declare(strict_types=1);

namespace App\Tests\Restaurant\State;

use ApiPlatform\Metadata\Post;
use App\Restaurant\Entity\RestaurantOrder;
use App\Restaurant\Entity\RestaurantTable;
use App\Restaurant\Repository\RestaurantOrderRepository;
use App\Restaurant\Repository\RestaurantTableRepository;
use App\Restaurant\State\CloseOrderInput;
use App\Restaurant\State\CloseOrderProcessor;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class CloseOrderProcessorTest extends TestCase
{
    public function testClosesOrderAndFreesItsTableAfterTheSaleExists(): void
    {
        $order = new RestaurantOrder(8);
        $table = $this->createMock(RestaurantTable::class);
        $table->expects(self::once())->method('markFree');

        $orders = $this->createMock(RestaurantOrderRepository::class);
        $orders->expects(self::once())->method('find')->with('order-1')->willReturn($order);

        $tables = $this->createMock(RestaurantTableRepository::class);
        $tables->expects(self::once())->method('find')->with(8)->willReturn($table);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');

        $result = new CloseOrderProcessor($orders, $tables, $entityManager)->process(
            $this->input('sale-12'),
            new Post(),
            ['id' => 'order-1'],
        );

        static::assertSame($order, $result);
        static::assertSame('sale-12', $order->getSaleId());
        static::assertFalse($order->isOpen());
    }

    public function testRetryWithTheSameSaleDoesNotFreeATableThatMayHaveBeenReopened(): void
    {
        $order = new RestaurantOrder(8);
        $order->close('sale-12');

        $orders = $this->createMock(RestaurantOrderRepository::class);
        $orders->expects(self::once())->method('find')->with('order-1')->willReturn($order);

        $tables = $this->createMock(RestaurantTableRepository::class);
        $tables->expects(self::never())->method('find');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('flush');

        $result = new CloseOrderProcessor($orders, $tables, $entityManager)->process(
            $this->input('sale-12'),
            new Post(),
            ['id' => 'order-1'],
        );

        static::assertSame($order, $result);
    }

    public function testRejectsClosingTheOrderAgainstAnotherSale(): void
    {
        $order = new RestaurantOrder(8);
        $order->close('sale-12');

        $orders = $this->createMock(RestaurantOrderRepository::class);
        $orders->expects(self::once())->method('find')->with('order-1')->willReturn($order);

        $tables = $this->createMock(RestaurantTableRepository::class);
        $tables->expects(self::never())->method('find');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('flush');

        $this->expectException(UnprocessableEntityHttpException::class);
        new CloseOrderProcessor($orders, $tables, $entityManager)->process($this->input('sale-13'), new Post(), [
            'id' => 'order-1',
        ]);
    }

    public function testThrowsWhenOrderDoesNotExist(): void
    {
        $orders = $this->createMock(RestaurantOrderRepository::class);
        $orders->expects(self::once())->method('find')->with('missing')->willReturn(null);

        $tables = $this->createMock(RestaurantTableRepository::class);
        $tables->expects(self::never())->method('find');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('flush');

        $this->expectException(NotFoundHttpException::class);
        new CloseOrderProcessor($orders, $tables, $entityManager)->process($this->input('sale-12'), new Post(), [
            'id' => 'missing',
        ]);
    }

    private function input(string $saleId): CloseOrderInput
    {
        $input = new CloseOrderInput();
        $input->saleId = $saleId;

        return $input;
    }
}
