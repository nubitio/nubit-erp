<?php

declare(strict_types=1);

namespace App\Tests\Inventory\Core\Service;

use App\Catalog\Entity\Product;
use App\Catalog\Entity\ProductBomItem;
use App\Catalog\Repository\ProductBomItemRepository;
use App\Inventory\Core\Dto\MovementDraft;
use App\Inventory\Core\Enum\InventoryBehavior;
use App\Inventory\Core\Enum\StockMovementType;
use App\Inventory\Core\Service\BomExplosionService;
use App\Sales\Entity\SaleRecord;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class BomExplosionServiceTest extends TestCase
{
    private function makeParentProduct(string $behavior = 'COMPOSITE'): Product
    {
        $product = new Product();
        $product->setInventoryBehavior(InventoryBehavior::from($behavior));

        return $product;
    }

    private function makeComponent(
        string $productId,
        string $componentQty = '1.000000',
        string $wastage = '1.000000',
        bool $optional = false,
    ): ProductBomItem {
        $component = new Product();
        // Use reflection to set the ID since it's generated
        $ref = new \ReflectionProperty(Product::class, 'id');
        $ref->setValue($component, $productId);

        $line = new ProductBomItem();
        $line->setComponentProduct($component);
        $line->setComponentQtyBase($componentQty);
        $line->setWastageFactor($wastage);
        $line->setIsOptional($optional);

        return $line;
    }

    private function makeDraft(string $productId, string $qty = '-2.000000'): MovementDraft
    {
        return new MovementDraft(
            warehouseId: 'wh-1',
            productId: $productId,
            movementType: StockMovementType::SALE,
            qtyDeltaBase: $qty,
            unitId: 0,
            qtyOriginal: ltrim($qty, '-'),
            sourceType: 'sale',
            sourceId: 'sale-1',
        );
    }

    // ── Pass-through for non-composite products ───────────────────────────────

    public function testTrackedProductPassesThroughUnchanged(): void
    {
        $product = $this->makeParentProduct('TRACKED');

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('find')->willReturn($product);

        $repo = $this->createStub(ProductBomItemRepository::class);

        $service = new BomExplosionService($repo, $em);
        $draft = $this->makeDraft('prod-1');

        $result = $service->enrichSaleMovements(new SaleRecord(), [$draft]);

        self::assertCount(1, $result);
        self::assertSame($draft, $result[0]);
    }

    public function testNotTrackedProductPassesThroughUnchanged(): void
    {
        $product = $this->makeParentProduct('NOT_TRACKED');

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('find')->willReturn($product);

        $repo = $this->createStub(ProductBomItemRepository::class);

        $service = new BomExplosionService($repo, $em);
        $result = $service->enrichSaleMovements(new SaleRecord(), [$this->makeDraft('prod-1')]);

        self::assertCount(1, $result);
    }

    public function testUnknownProductPassesThroughUnchanged(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('find')->willReturn(null);

        $repo = $this->createStub(ProductBomItemRepository::class);

        $service = new BomExplosionService($repo, $em);
        $draft = $this->makeDraft('prod-unknown');

        $result = $service->enrichSaleMovements(new SaleRecord(), [$draft]);

        self::assertCount(1, $result);
        self::assertSame($draft, $result[0]);
    }

    // ── BOM explosion ─────────────────────────────────────────────────────────

    public function testCompositeProductIsReplacedByComponents(): void
    {
        $parent = $this->makeParentProduct('COMPOSITE');

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('find')->willReturn($parent);

        $component1 = $this->makeComponent('comp-a', '2.000000', '1.000000');
        $component2 = $this->makeComponent('comp-b', '1.000000', '1.000000');

        $repo = $this->createStub(ProductBomItemRepository::class);
        $repo->method('findByParent')->willReturn([$component1, $component2]);

        $service = new BomExplosionService($repo, $em);
        $result = $service->enrichSaleMovements(new SaleRecord(), [$this->makeDraft('parent-1', '-3.000000')]);

        // Parent removed, 2 components added
        self::assertCount(2, $result);
        self::assertSame('comp-a', $result[0]->productId);
        self::assertSame('comp-b', $result[1]->productId);
    }

    public function testComponentQuantityIsScaledByParentQuantity(): void
    {
        $parent = $this->makeParentProduct('COMPOSITE');

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('find')->willReturn($parent);

        // Component: 2 base units per parent, wastage 1.1
        $component = $this->makeComponent('comp-a', '2.000000', '1.100000');

        $repo = $this->createStub(ProductBomItemRepository::class);
        $repo->method('findByParent')->willReturn([$component]);

        $service = new BomExplosionService($repo, $em);
        // Parent qty = 3 → component qty = 2 * 1.1 * 3 = 6.6
        $result = $service->enrichSaleMovements(new SaleRecord(), [$this->makeDraft('parent-1', '-3.000000')]);

        self::assertCount(1, $result);
        // Should be negative (outbound) and scaled
        self::assertStringStartsWith('-', $result[0]->qtyDeltaBase);
        $absQty = ltrim($result[0]->qtyDeltaBase, '-');
        self::assertEqualsWithDelta(6.6, (float) $absQty, 0.001);
    }

    public function testOptionalComponentsAreSkipped(): void
    {
        $parent = $this->makeParentProduct('COMPOSITE');

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('find')->willReturn($parent);

        $mandatory = $this->makeComponent('comp-a', '1.000000', '1.000000', optional: false);
        $optional = $this->makeComponent('comp-b', '1.000000', '1.000000', optional: true);

        $repo = $this->createStub(ProductBomItemRepository::class);
        $repo->method('findByParent')->willReturn([$mandatory, $optional]);

        $service = new BomExplosionService($repo, $em);
        $result = $service->enrichSaleMovements(new SaleRecord(), [$this->makeDraft('parent-1')]);

        // Only mandatory component included
        self::assertCount(1, $result);
        self::assertSame('comp-a', $result[0]->productId);
    }

    public function testSignIsPreservedOnComponentDrafts(): void
    {
        $parent = $this->makeParentProduct('COMPOSITE');

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('find')->willReturn($parent);

        $component = $this->makeComponent('comp-a', '1.000000', '1.000000');

        $repo = $this->createStub(ProductBomItemRepository::class);
        $repo->method('findByParent')->willReturn([$component]);

        $service = new BomExplosionService($repo, $em);

        // Negative parent (sale outbound)
        $negative = $service->enrichSaleMovements(new SaleRecord(), [$this->makeDraft('parent-1', '-2.000000')]);
        self::assertStringStartsWith('-', $negative[0]->qtyDeltaBase);

        // Positive parent (purchase inbound)
        $positive = $service->enrichSaleMovements(new SaleRecord(), [$this->makeDraft('parent-1', '2.000000')]);
        self::assertStringStartsWith('2', $positive[0]->qtyDeltaBase);
    }

    public function testSourceIdSuffixedWithBomLineId(): void
    {
        $parent = $this->makeParentProduct('COMPOSITE');

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('find')->willReturn($parent);

        $component = $this->makeComponent('comp-a');

        $repo = $this->createStub(ProductBomItemRepository::class);
        $repo->method('findByParent')->willReturn([$component]);

        $service = new BomExplosionService($repo, $em);
        $result = $service->enrichSaleMovements(new SaleRecord(), [$this->makeDraft('parent-1', '-1.000000')]);

        self::assertStringStartsWith('sale-1:bom:', $result[0]->sourceId);
    }

    public function testEmptyBomProducesNoMovements(): void
    {
        $parent = $this->makeParentProduct('COMPOSITE');

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('find')->willReturn($parent);

        $repo = $this->createStub(ProductBomItemRepository::class);
        $repo->method('findByParent')->willReturn([]);

        $service = new BomExplosionService($repo, $em);
        $result = $service->enrichSaleMovements(new SaleRecord(), [$this->makeDraft('parent-1')]);

        self::assertCount(0, $result);
    }
}
