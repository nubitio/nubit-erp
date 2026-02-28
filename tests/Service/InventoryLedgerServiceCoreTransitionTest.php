<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Core\Service\SettingsService;
use App\Core\Util\Constants;
use App\Entity\Product;
use App\Entity\Sale;
use App\Entity\SaleItem;
use App\Entity\Warehouse;
use App\Service\InventoryLedgerService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

final class InventoryLedgerServiceCoreTransitionTest extends TestCase
{
    public function testGetStockReadsFromStockItemsWhenFlagEnabled(): void
    {
        $settings = $this->createMock(SettingsService::class);
        $settings->method('get')->willReturnCallback(
            static fn (string $key, ?string $default = null): string|null => match ($key) {
                Constants::INVENTORY_CORE_READ_FROM_STOCK_ITEMS => 'true',
                default => $default,
            }
        );

        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())
            ->method('fetchOne')
            ->with(
                self::callback(static fn (string $sql): bool => str_contains($sql, 'FROM stock_items')),
                self::anything()
            )
            ->willReturn('11.500000');

        $service = new InventoryLedgerService($connection, $settings);

        $stock = $service->getStockForProductInWarehouse($this->newProduct('prod-1'), $this->newWarehouse('wh-1'));

        self::assertSame(11.5, $stock);
    }

    public function testApplyMovementDualWritesStockItemsAndExtendedColumnsWhenEnabled(): void
    {
        $settings = $this->createMock(SettingsService::class);
        $settings->method('get')->willReturnCallback(
            static fn (string $key, ?string $default = null): string|null => match ($key) {
                Constants::INVENTORY_CORE_DUAL_WRITE => 'true',
                Constants::INVENTORY_CORE_MODE => 'dual_write',
                default => $default,
            }
        );

        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('beginTransaction');
        $connection->expects(self::once())->method('commit');
        $connection->expects(self::never())->method('rollBack');

        $connection->method('fetchAssociative')->willReturn(false);

        $connection->method('fetchOne')->willReturnCallback(
            static function (string $sql, array $params = []): int|false {
                if (str_contains($sql, 'information_schema.columns')) {
                    if (($params['table'] ?? '') === 'stock_movements' && in_array(($params['column'] ?? ''), ['qty_delta_base', 'qty_original', 'unit_id'], true)) {
                        return 1;
                    }

                    if (($params['table'] ?? '') === 'stock_items' && ($params['column'] ?? '') === 'qty_on_hand') {
                        return 1;
                    }

                    return 0;
                }

                if (str_contains($sql, 'SELECT base_unit_id') && ($params['product_id'] ?? null) === 'prod-1') {
                    return 1;
                }

                return false;
            }
        );

        $executedSql = [];
        $connection->method('executeStatement')->willReturnCallback(
            static function (string $sql, array $params = []) use (&$executedSql): int {
                $executedSql[] = ['sql' => $sql, 'params' => $params];

                return 1;
            }
        );

        $service = new InventoryLedgerService($connection, $settings);

        $sale = (new Sale())
            ->setId('sale-1')
            ->setTipoId(2)
            ->setFecha(new \DateTimeImmutable('2026-02-28 10:00:00'))
            ->setAlmacen($this->newWarehouse('wh-1'));

        $item = (new SaleItem())
            ->setProducto($this->newProduct('prod-1'))
            ->setCantidad(2.0)
            ->setPrecioUnitario('12.00')
            ->setPrecioTotal('24.00');

        $service->applyMovementFromSaleItem($sale, $item, 1, false);

        $movementWrite = array_values(array_filter(
            $executedSql,
            static fn (array $call): bool => str_contains($call['sql'], 'INSERT INTO stock_movements')
        ));
        self::assertCount(1, $movementWrite);
        self::assertArrayHasKey('qty_delta_base', $movementWrite[0]['params']);
        self::assertArrayHasKey('qty_original', $movementWrite[0]['params']);
        self::assertSame(1, $movementWrite[0]['params']['unit_id']);

        $stockItemsWrite = array_values(array_filter(
            $executedSql,
            static fn (array $call): bool => str_contains($call['sql'], 'INSERT INTO stock_items')
        ));
        self::assertCount(1, $stockItemsWrite);
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
}
