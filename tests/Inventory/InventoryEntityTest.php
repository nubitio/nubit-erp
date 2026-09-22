<?php

declare(strict_types=1);

namespace App\Tests\Inventory;

use App\Catalog\Entity\Product;
use App\Efact\Enum\StockPolicy;
use App\Inventory\Dto\PriceItemDto;
use App\Inventory\Dto\PriceListDto;
use App\Inventory\Dto\PricesDto;
use App\Inventory\Entity\LogisticOperation;
use App\Inventory\Entity\PriceList;
use App\Inventory\Entity\ProductStock;
use App\Inventory\Entity\StockItem;
use App\Inventory\Entity\StockLot;
use App\Inventory\Entity\Warehouse;
use App\Organization\Entity\Branch;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * Entity and DTO value-object tests for the Inventory bounded context.
 */
final class InventoryEntityTest extends TestCase
{
    // ── Warehouse ─────────────────────────────────────────────────────────────

    public function testWarehouseSettersAndGetters(): void
    {
        $branch = new Branch();
        $wh = new Warehouse();
        $wh->setName('Almacén Central');
        $wh->setBranch($branch);

        self::assertSame('Almacén Central', $wh->getName());
        self::assertSame($branch, $wh->getBranch());
        self::assertNull($wh->getId()); // not persisted (UUID auto-generated)
        self::assertCount(0, $wh->getProductStocks());
    }

    public function testWarehouseFluentSetters(): void
    {
        $wh = new Warehouse();
        self::assertSame($wh, $wh->setName('WH'));
        self::assertSame($wh, $wh->setBranch(new Branch()));
    }

    // ── PriceList ─────────────────────────────────────────────────────────────

    public function testPriceListSettersAndGetters(): void
    {
        $pl = new PriceList();
        $pl->setName('Lista Mayorista');

        self::assertSame('Lista Mayorista', $pl->getName());
        self::assertNull($pl->getId());
    }

    // ── LogisticOperation ─────────────────────────────────────────────────────

    public function testLogisticOperationSettersAndGetters(): void
    {
        $op = new LogisticOperation();
        $op->setName('Transferencia interna');
        $op->setType(1);

        self::assertSame('Transferencia interna', $op->getName());
        self::assertSame(1, $op->getType());
        self::assertNull($op->getId());
    }

    // ── StockItem ─────────────────────────────────────────────────────────────

    private function makeStockItem(string $onHand = '10.000000', string $reserved = '2.000000'): StockItem
    {
        $wh = new Warehouse();
        $wh->setName('WH');
        $product = new Product();

        $item = new StockItem();
        $item->setWarehouse($wh);
        $item->setProduct($product);
        $item->setQtyOnHand($onHand);
        $item->setQtyReserved($reserved);

        return $item;
    }

    public function testStockItemGetQtyAvailable(): void
    {
        $item = $this->makeStockItem('10.000000', '3.000000');
        // 10 - 3 = 7
        self::assertEqualsWithDelta(7.0, (float) $item->getQtyAvailable(), 0.000001);
    }

    public function testStockItemGetQtyAvailableWhenNoReservation(): void
    {
        $item = $this->makeStockItem('5.000000', '0.000000');
        self::assertEqualsWithDelta(5.0, (float) $item->getQtyAvailable(), 0.000001);
    }

    public function testStockItemDefaultsAreZero(): void
    {
        $item = new StockItem();
        self::assertSame('0.000000', $item->getQtyOnHand());
        self::assertSame('0.000000', $item->getQtyReserved());
        self::assertSame(0, $item->getVersion());
        self::assertNull($item->getLastMovementId());
    }

    public function testStockItemIncrementVersion(): void
    {
        $item = new StockItem();
        self::assertSame(0, $item->getVersion());
        $item->incrementVersion();
        self::assertSame(1, $item->getVersion());
        $item->incrementVersion();
        self::assertSame(2, $item->getVersion());
    }

    public function testStockItemIdComposedFromWarehouseAndProduct(): void
    {
        $wh = new Warehouse();
        $product = new Product();
        // Set IDs via reflection (UUID/int auto-generated)
        $whRef = new \ReflectionProperty(Warehouse::class, 'id');
        $whRef->setValue($wh, 'wh-uuid-1');
        $prodRef = new \ReflectionProperty(Product::class, 'id');
        $prodRef->setValue($product, 'prod-uuid-1');

        $item = new StockItem();
        $item->setWarehouse($wh);
        $item->setProduct($product);

        self::assertSame('wh-uuid-1:prod-uuid-1', $item->getId());
    }

    // ── ProductStock ──────────────────────────────────────────────────────────

    public function testProductStockDefaultValues(): void
    {
        $ps = new ProductStock();

        self::assertSame(0.0, $ps->getStock());
        self::assertSame(0.0, $ps->getMinStock());
        self::assertNull($ps->getId());
        self::assertNull($ps->getUpdatedAt());
    }

    public function testProductStockSettersAndGetters(): void
    {
        $product = new Product();
        $wh = new Warehouse();

        $ps = new ProductStock();
        $ps->setProduct($product);
        $ps->setWarehouse($wh);
        $ps->setStock(15.5);
        $ps->setMinStock(5.0);

        self::assertSame($product, $ps->getProduct());
        self::assertSame($wh, $ps->getWarehouse());
        self::assertSame(15.5, $ps->getStock());
        self::assertSame(5.0, $ps->getMinStock());
    }

    public function testProductStockUpdateTimestamps(): void
    {
        $ps = new ProductStock();
        $before = new DateTimeImmutable();
        $ps->updateTimestamps();
        $after = new DateTimeImmutable();

        self::assertNotNull($ps->getUpdatedAt());
        self::assertGreaterThanOrEqual($before, $ps->getUpdatedAt());
        self::assertLessThanOrEqual($after, $ps->getUpdatedAt());
    }

    public function testProductStockCreatedAtIsSetOnConstruct(): void
    {
        $before = new DateTimeImmutable();
        $ps = new ProductStock();
        $after = new DateTimeImmutable();

        self::assertGreaterThanOrEqual($before, $ps->getCreatedAt());
        self::assertLessThanOrEqual($after, $ps->getCreatedAt());
    }

    // ── StockLot ──────────────────────────────────────────────────────────────

    public function testStockLotSettersAndGetters(): void
    {
        $product = new Product();
        $wh = new Warehouse();

        $lot = new StockLot();
        $lot->setProduct($product);
        $lot->setWarehouse($wh);
        $lot->setLotNumber('LOT-2026-001');
        $lot->setQtyOnHand('50.000000');

        self::assertSame($product, $lot->getProduct());
        self::assertSame($wh, $lot->getWarehouse());
        self::assertSame('LOT-2026-001', $lot->getLotNumber());
        self::assertSame('50.000000', $lot->getQtyOnHand());
        self::assertNull($lot->getExpirationDate());
        self::assertNull($lot->getCost());
    }

    public function testStockLotExpirationDate(): void
    {
        $lot = new StockLot();
        $expires = new DateTimeImmutable('2027-12-31');
        $lot->setExpirationDate($expires);

        self::assertSame($expires, $lot->getExpirationDate());
    }

    // ── DTOs ──────────────────────────────────────────────────────────────────

    public function testPriceItemDtoHoldsFields(): void
    {
        $dto = new PriceItemDto(productId: 'prod-1', price: '99.9900');

        self::assertSame('prod-1', $dto->productId);
        self::assertSame('99.9900', $dto->price);
    }

    public function testPriceItemDtoNullableDefaults(): void
    {
        $dto = new PriceItemDto();

        self::assertNull($dto->productId);
        self::assertNull($dto->price);
    }

    public function testPricesDtoHoldsFields(): void
    {
        $item = new PriceItemDto('p-1', '50.0000');
        $dto = new PricesDto(priceListId: 3, prices: [$item]);

        self::assertSame(3, $dto->priceListId);
        self::assertCount(1, $dto->prices);
        self::assertSame($item, $dto->prices[0]);
    }

    public function testPriceListDtoHoldsFields(): void
    {
        $dto = new PriceListDto(id: 'prod-1', name: 'Minorista', price: '125.5000');

        self::assertSame('prod-1', $dto->id);
        self::assertSame('Minorista', $dto->name);
        self::assertSame('125.5000', $dto->price);
        self::assertSame('base', $dto->priceSource);
    }

    public function testPriceListDtoNullableDefaults(): void
    {
        $dto = new PriceListDto();

        self::assertNull($dto->id);
        self::assertNull($dto->name);
        self::assertNull($dto->price);
        self::assertSame('base', $dto->priceSource);
    }

    public function testPriceListEntityMultiplier(): void
    {
        $pl = new PriceList();
        $pl->setName('Mayorista');

        self::assertNull($pl->getMultiplier());

        $pl->setMultiplier('0.9000');
        self::assertSame('0.9000', $pl->getMultiplier());

        $pl->setMultiplier(null);
        self::assertNull($pl->getMultiplier());
    }
}
