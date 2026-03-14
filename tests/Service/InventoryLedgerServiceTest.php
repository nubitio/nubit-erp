<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Product;
use App\Entity\SaleRecord;
use App\Entity\SaleItem;
use App\Entity\Warehouse;
use App\Service\InventoryLedgerService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

final class InventoryLedgerServiceTest extends TestCase
{
    public function testGetStockPrefersBalancesOverLegacy(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchOne')
            ->with(
                $this->callback(static fn (string $sql): bool => str_contains($sql, 'FROM stock_balances')),
                $this->anything()
            )
            ->willReturn('7.250');

        $service = new InventoryLedgerService($connection);
        $stock = $service->getStockForProductInWarehouse($this->newProduct('prod-1'), $this->newWarehouse('wh-1'));

        self::assertSame(7.25, $stock);
    }

    public function testApplyMovementWritesLedgerAndCreatesBalance(): void
    {
        $connection = $this->createMock(Connection::class);

        $connection->expects($this->once())->method('beginTransaction');
        $connection->expects($this->once())->method('commit');
        $connection->expects($this->never())->method('rollBack');

        $connection->expects($this->once())
            ->method('fetchAssociative')
            ->with(
                $this->callback(static fn (string $sql): bool => str_contains($sql, 'FROM stock_balances')),
                $this->anything()
            )
            ->willReturn(false);

        $connection->expects($this->exactly(2))
            ->method('executeStatement')
            ->with(
                $this->callback(static function (string $sql): bool {
                    return str_contains($sql, 'INSERT INTO stock_movements')
                        || str_contains($sql, 'INSERT INTO stock_balances');
                }),
                $this->anything()
            );

        $service = new InventoryLedgerService($connection);

        $sale = (new SaleRecord())
            ->setId('sale-1')
            ->setTipoId(1)
            ->setFecha(new \DateTimeImmutable('2026-02-28 10:00:00'))
            ->setAlmacen($this->newWarehouse('wh-1'));

        $item = (new SaleItem())
            ->setProducto($this->newProduct('prod-1'))
            ->setCantidad(2.0)
            ->setPrecioUnitario('12.00')
            ->setPrecioTotal('24.00');

        $service->applyMovementFromSaleItem($sale, $item, 1, true);
    }

    public function testServiceProductDoesNotWriteInventoryMovements(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('beginTransaction');
        $connection->expects($this->never())->method('executeStatement');

        $service = new InventoryLedgerService($connection);

        $sale = (new SaleRecord())
            ->setId('sale-2')
            ->setTipoId(2)
            ->setFecha(new \DateTimeImmutable('2026-02-28 11:00:00'))
            ->setAlmacen($this->newWarehouse('wh-1'));

        $item = (new SaleItem())
            ->setProducto($this->newServiceProduct('svc-1'))
            ->setCantidad(1.0)
            ->setPrecioUnitario('20.00')
            ->setPrecioTotal('20.00');

        $service->applyMovementFromSaleItem($sale, $item, 1, true);
    }

    private function newProduct(string $id): Product
    {
        $product = new Product();
        $this->setEntityId($product, $id);
        $product->setType(1);
        $product->setPurchasePrice('5.00');
        $product->setMinStock('0.00');

        return $product;
    }

    private function newWarehouse(string $id): Warehouse
    {
        $warehouse = new Warehouse();
        $this->setEntityId($warehouse, $id);
        $warehouse->setName('Main');

        return $warehouse;
    }

    private function setEntityId(object $entity, string $id): void
    {
        $reflection = new \ReflectionObject($entity);
        $property = $reflection->getProperty('id');
        $property->setValue($entity, $id);
    }

    private function newServiceProduct(string $id): Product
    {
        $product = new Product();
        $this->setEntityId($product, $id);
        $product->setType(2);

        return $product;
    }
}
