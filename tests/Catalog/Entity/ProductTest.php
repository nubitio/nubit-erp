<?php

declare(strict_types=1);

namespace App\Tests\Catalog\Entity;

use App\Catalog\Entity\Product;
use App\Inventory\Core\Enum\InventoryBehavior;
use App\Inventory\Core\Enum\LotTrackingStrategy;
use App\Inventory\Core\Enum\ProductKind;
use App\Inventory\Core\Enum\ProductType;
use App\Inventory\Entity\ProductStock;
use App\Inventory\Entity\Warehouse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Product::class)]
final class ProductTest extends TestCase
{
    // -------------------------------------------------------------------------
    // setType() — legacy type mapping
    // -------------------------------------------------------------------------

    public function testSetType1MapsToGoodStockableTracked(): void
    {
        $product = new Product();
        $product->setType(1);

        self::assertSame(ProductKind::GOOD->value, $product->getProductKind());
        self::assertSame(InventoryBehavior::TRACKED->value, $product->getInventoryBehavior());
        self::assertTrue($product->getTrackInventory());
        self::assertSame(ProductType::STOCKABLE->value, $product->getProductType());
    }

    public function testSetType2MapsToServiceNotTracked(): void
    {
        $product = new Product();
        $product->setType(2);

        self::assertSame(ProductKind::SERVICE->value, $product->getProductKind());
        self::assertSame(InventoryBehavior::NOT_TRACKED->value, $product->getInventoryBehavior());
        self::assertFalse($product->getTrackInventory());
        self::assertSame(ProductType::SERVICE->value, $product->getProductType());
    }

    public function testSetTypeNonOneMapsToService(): void
    {
        $product = new Product();
        $product->setType(99);

        self::assertSame(ProductKind::SERVICE->value, $product->getProductKind());
        self::assertSame(InventoryBehavior::NOT_TRACKED->value, $product->getInventoryBehavior());
        self::assertFalse($product->getTrackInventory());
    }

    public function testSetTypeDoesNotOverrideInventoryBehaviorWhenExplicitlySet(): void
    {
        $product = new Product();
        // Explicitly set inventory behavior first (v2 API)
        $product->setInventoryBehavior(InventoryBehavior::COMPOSITE);
        // Now call setType — should NOT override inventoryBehavior
        $product->setType(2);

        self::assertSame(InventoryBehavior::COMPOSITE->value, $product->getInventoryBehavior());
        self::assertTrue($product->getTrackInventory()); // COMPOSITE tracks inventory
    }

    public function testSetType1DoesNotOverrideInventoryBehaviorWhenExplicitlySet(): void
    {
        $product = new Product();
        $product->setInventoryBehavior(InventoryBehavior::NOT_TRACKED);
        $product->setType(1);

        self::assertSame(InventoryBehavior::NOT_TRACKED->value, $product->getInventoryBehavior());
        self::assertFalse($product->getTrackInventory());
    }

    // -------------------------------------------------------------------------
    // setProductType() — legacy string mapping
    // -------------------------------------------------------------------------

    public function testSetProductTypeStockable(): void
    {
        $product = new Product();
        $product->setProductType('STOCKABLE');

        self::assertSame(ProductKind::GOOD->value, $product->getProductKind());
        self::assertSame(InventoryBehavior::TRACKED->value, $product->getInventoryBehavior());
        self::assertTrue($product->getTrackInventory());
    }

    public function testSetProductTypeService(): void
    {
        $product = new Product();
        $product->setProductType('SERVICE');

        self::assertSame(ProductKind::SERVICE->value, $product->getProductKind());
        self::assertSame(InventoryBehavior::NOT_TRACKED->value, $product->getInventoryBehavior());
        self::assertFalse($product->getTrackInventory());
    }

    public function testSetProductTypeComposite(): void
    {
        $product = new Product();
        $product->setProductType('COMPOSITE');

        self::assertSame(ProductKind::GOOD->value, $product->getProductKind());
        self::assertSame(InventoryBehavior::COMPOSITE->value, $product->getInventoryBehavior());
        self::assertTrue($product->getTrackInventory());
    }

    public function testSetProductTypeDigital(): void
    {
        $product = new Product();
        $product->setProductType('DIGITAL');

        self::assertSame(ProductKind::DIGITAL->value, $product->getProductKind());
        self::assertSame(InventoryBehavior::NOT_TRACKED->value, $product->getInventoryBehavior());
        self::assertFalse($product->getTrackInventory());
    }

    public function testSetProductTypeConsumable(): void
    {
        $product = new Product();
        $product->setProductType('CONSUMABLE');

        self::assertSame(ProductKind::GOOD->value, $product->getProductKind());
        self::assertSame(InventoryBehavior::TRACKED->value, $product->getInventoryBehavior());
        self::assertTrue($product->getTrackInventory());
    }

    public function testSetProductTypeCaseInsensitive(): void
    {
        $product = new Product();
        $product->setProductType('stockable');

        self::assertSame(ProductKind::GOOD->value, $product->getProductKind());
        self::assertSame(InventoryBehavior::TRACKED->value, $product->getInventoryBehavior());
        self::assertTrue($product->getTrackInventory());
    }

    public function testSetProductTypeUnknownFallsBackToStockable(): void
    {
        $product = new Product();
        $product->setProductType('UNKNOWN_VALUE');

        self::assertSame(ProductType::STOCKABLE->value, $product->getProductType());
        self::assertSame(ProductKind::GOOD->value, $product->getProductKind());
        self::assertSame(InventoryBehavior::TRACKED->value, $product->getInventoryBehavior());
    }

    // -------------------------------------------------------------------------
    // setInventoryBehavior() — explicit v2 API
    // -------------------------------------------------------------------------

    public function testSetInventoryBehaviorTracked(): void
    {
        $product = new Product();
        $product->setInventoryBehavior(InventoryBehavior::TRACKED);

        self::assertSame(InventoryBehavior::TRACKED->value, $product->getInventoryBehavior());
        self::assertTrue($product->getTrackInventory());
    }

    public function testSetInventoryBehaviorNotTracked(): void
    {
        $product = new Product();
        $product->setInventoryBehavior(InventoryBehavior::NOT_TRACKED);

        self::assertSame(InventoryBehavior::NOT_TRACKED->value, $product->getInventoryBehavior());
        self::assertFalse($product->getTrackInventory());
    }

    public function testSetInventoryBehaviorComposite(): void
    {
        $product = new Product();
        $product->setInventoryBehavior(InventoryBehavior::COMPOSITE);

        self::assertSame(InventoryBehavior::COMPOSITE->value, $product->getInventoryBehavior());
        self::assertTrue($product->getTrackInventory());
    }

    public function testSetInventoryBehaviorAcceptsString(): void
    {
        $product = new Product();
        $product->setInventoryBehavior('NOT_TRACKED');

        self::assertSame(InventoryBehavior::NOT_TRACKED->value, $product->getInventoryBehavior());
        self::assertFalse($product->getTrackInventory());
    }

    public function testSetInventoryBehaviorSetsExplicitFlag(): void
    {
        $product = new Product();
        $product->setInventoryBehavior(InventoryBehavior::COMPOSITE);
        // setType should not override because flag is set
        $product->setType(2);

        self::assertSame(InventoryBehavior::COMPOSITE->value, $product->getInventoryBehavior());
    }

    // -------------------------------------------------------------------------
    // setSku() / setBarcode() — trim whitespace
    // -------------------------------------------------------------------------

    public function testSetSkuTrimsWhitespace(): void
    {
        $product = new Product();
        $product->setSku('  SKU-001  ');

        self::assertSame('SKU-001', $product->getSku());
    }

    public function testSetSkuNullReturnsNull(): void
    {
        $product = new Product();
        $product->setSku(null);

        self::assertNull($product->getSku());
    }

    public function testSetBarcodeTrimsWhitespace(): void
    {
        $product = new Product();
        $product->setBarcode('  1234567890  ');

        self::assertSame('1234567890', $product->getBarcode());
    }

    public function testSetBarcodeNullReturnsNull(): void
    {
        $product = new Product();
        $product->setBarcode(null);

        self::assertNull($product->getBarcode());
    }

    // -------------------------------------------------------------------------
    // setLotTrackingStrategy() — enum and string
    // -------------------------------------------------------------------------

    public function testSetLotTrackingStrategyAcceptsEnum(): void
    {
        $product = new Product();
        $product->setLotTrackingStrategy(LotTrackingStrategy::FIFO);

        self::assertSame(LotTrackingStrategy::FIFO->value, $product->getLotTrackingStrategy());
    }

    public function testSetLotTrackingStrategyAcceptsString(): void
    {
        $product = new Product();
        $product->setLotTrackingStrategy('FEFO');

        self::assertSame(LotTrackingStrategy::FEFO->value, $product->getLotTrackingStrategy());
    }

    public function testSetLotTrackingStrategyNoneByDefault(): void
    {
        $product = new Product();

        self::assertSame(LotTrackingStrategy::NONE->value, $product->getLotTrackingStrategy());
    }

    // -------------------------------------------------------------------------
    // getStockByWarehouse()
    // -------------------------------------------------------------------------

    public function testGetStockByWarehouseReturnsMatchingStock(): void
    {
        $warehouse = $this->createStub(Warehouse::class);

        $stockEntry = $this->createStub(ProductStock::class);
        $stockEntry->method('getWarehouse')->willReturn($warehouse);
        $stockEntry->method('getStock')->willReturn(5.5);

        $product = new Product();
        $product->addStock($stockEntry);

        self::assertSame(5.5, $product->getStockByWarehouse($warehouse));
    }

    public function testGetStockByWarehouseReturnsZeroWhenNotFound(): void
    {
        $warehouse = $this->createStub(Warehouse::class);
        $otherWarehouse = $this->createStub(Warehouse::class);

        $stockEntry = $this->createStub(ProductStock::class);
        $stockEntry->method('getWarehouse')->willReturn($otherWarehouse);

        $product = new Product();
        $product->addStock($stockEntry);

        self::assertSame(0.0, $product->getStockByWarehouse($warehouse));
    }

    public function testGetStockByWarehouseReturnsZeroWhenNoStocks(): void
    {
        $warehouse = $this->createStub(Warehouse::class);
        $product = new Product();

        self::assertSame(0.0, $product->getStockByWarehouse($warehouse));
    }

    // -------------------------------------------------------------------------
    // addStock() / removeStock() — collection management
    // -------------------------------------------------------------------------

    public function testAddStockAddsToCollection(): void
    {
        $stockEntry = $this->createMock(ProductStock::class);
        $stockEntry->expects(self::once())->method('setProduct');

        $product = new Product();
        $product->addStock($stockEntry);

        self::assertCount(1, $product->getStocks());
        self::assertTrue($product->getStocks()->contains($stockEntry));
    }

    public function testAddStockDoesNotAddDuplicate(): void
    {
        $stockEntry = $this->createMock(ProductStock::class);
        $stockEntry->expects(self::once())->method('setProduct');

        $product = new Product();
        $product->addStock($stockEntry);
        $product->addStock($stockEntry); // second call should be ignored

        self::assertCount(1, $product->getStocks());
    }

    public function testRemoveStockRemovesFromCollection(): void
    {
        $stockEntry = $this->createStub(ProductStock::class);
        $stockEntry->method('setProduct');

        $product = new Product();
        $product->addStock($stockEntry);
        $product->removeStock($stockEntry);

        self::assertCount(0, $product->getStocks());
    }
}
