<?php

declare(strict_types=1);

namespace App\Tests\Inventory\Core\Service;

use App\Catalog\Entity\Product;
use App\Inventory\Core\Contract\StockMovementEnricherInterface;
use App\Inventory\Core\Contract\StockValidationPolicyInterface;
use App\Inventory\Core\Contract\UnitConversionServiceInterface;
use App\Inventory\Core\Dto\MovementDraft;
use App\Inventory\Core\Dto\StockValidationResult;
use App\Inventory\Core\Enum\InventoryBehavior;
use App\Inventory\Core\Enum\StockMovementType;
use App\Inventory\Core\Service\InventoryService;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class InventoryServiceTest extends TestCase
{
    private function makeProduct(string $behavior = 'TRACKED', bool $trackInventory = true): Product
    {
        $product = new Product();
        $product->setInventoryBehavior(InventoryBehavior::from($behavior));
        $product->setTrackInventory($trackInventory);

        return $product;
    }

    private function makeDraft(string $productId = 'prod-1', string $qty = '-2', int $unitId = 0): MovementDraft
    {
        return new MovementDraft(
            warehouseId: 'wh-1',
            productId: $productId,
            movementType: StockMovementType::SALE,
            qtyDeltaBase: $qty,
            unitId: $unitId,
            qtyOriginal: ltrim($qty, '-'),
            sourceType: 'sale',
            sourceId: 'sale-uuid-1',
        );
    }

    /** @param list<StockMovementEnricherInterface> $enrichers */
    private function makeService(
        Connection $connection,
        ?StockValidationPolicyInterface $validationPolicy = null,
        ?UnitConversionServiceInterface $unitConversion = null,
        ?EventDispatcherInterface $dispatcher = null,
        array $enrichers = [],
    ): InventoryService {
        $validationPolicy ??= $this->createStub(StockValidationPolicyInterface::class);
        $unitConversion ??= $this->createStub(UnitConversionServiceInterface::class);
        $dispatcher ??= $this->createStub(EventDispatcherInterface::class);

        return new InventoryService($connection, $validationPolicy, $unitConversion, $dispatcher, $enrichers);
    }

    // ── getOnHand ─────────────────────────────────────────────────────────────

    public function testGetOnHandReadsFromStockItems(): void
    {
        $conn = $this->createStub(Connection::class);
        $conn->method('fetchOne')->willReturn('12.500000');

        $service = $this->makeService($conn);
        self::assertSame('12.500000', $service->getOnHand('wh-1', 'prod-1'));
    }

    public function testGetOnHandReturnsZeroWhenStockItemIsMissing(): void
    {
        $conn = $this->createStub(Connection::class);
        $conn->method('fetchOne')->willReturn(false);

        $service = $this->makeService($conn);
        self::assertSame('0', $service->getOnHand('wh-1', 'prod-1'));
    }

    public function testGetOnHandReturnsZeroWhenNothingFound(): void
    {
        $conn = $this->createStub(Connection::class);
        $conn->method('fetchOne')->willReturn(false);

        $service = $this->makeService($conn);
        self::assertSame('0', $service->getOnHand('wh-1', 'prod-999'));
    }

    // ── validateForSale ───────────────────────────────────────────────────────

    public function testValidateForSaleAllowsNonTrackedProduct(): void
    {
        $product = $this->makeProduct('NOT_TRACKED', false);

        $conn = $this->createStub(Connection::class);
        $policy = $this->createMock(StockValidationPolicyInterface::class);
        $policy->expects(self::never())->method('validateSale');

        $service = $this->makeService($conn, $policy);
        $result = $service->validateForSale($product, 'wh-1', '999', 0);

        self::assertTrue($result->allowed);
    }

    public function testValidateForSaleDelegatesToPolicy(): void
    {
        $product = $this->makeProduct();
        $expected = StockValidationResult::blocked('No stock');

        $conn = $this->createStub(Connection::class);
        $conn->method('fetchOne')->willReturn('0'); // on-hand = 0

        $conversion = $this->createStub(UnitConversionServiceInterface::class);
        $conversion->method('toBase')->willReturn('5.000000');

        $policy = $this->createMock(StockValidationPolicyInterface::class);
        $policy->expects(self::once())->method('validateSale')->willReturn($expected);

        $service = $this->makeService($conn, $policy, $conversion);
        $result = $service->validateForSale($product, 'wh-1', '5', 1);

        self::assertFalse($result->allowed);
        self::assertSame('No stock', $result->reason);
    }

    // ── recordMovement ────────────────────────────────────────────────────────

    public function testRecordMovementSkipsNotTrackedProducts(): void
    {
        $conn = $this->createMock(Connection::class);
        $conn->expects(self::never())->method('beginTransaction');

        $service = $this->makeService($conn);
        $draft = $this->makeDraft();

        // NOT_TRACKED behavior — should exit immediately
        $service->recordMovement($draft, null, InventoryBehavior::NOT_TRACKED);
    }

    public function testRecordMovementUsesBaseUnitPassthroughWhenUnitIdIsZero(): void
    {
        $conn = $this->createMock(Connection::class);
        $conn->expects(self::once())->method('beginTransaction');
        $conn->expects(self::once())->method('commit');
        $conn->method('executeStatement')->willReturn(1);
        $conn->method('fetchOne')->willReturn(false); // no idempotency match

        $unitConversion = $this->createMock(UnitConversionServiceInterface::class);
        $unitConversion->expects(self::never())->method('toBase'); // unit=0 → passthrough

        $service = $this->makeService($conn, unitConversion: $unitConversion);
        $draft = $this->makeDraft(qty: '-2', unitId: 0);

        $service->recordMovement($draft);
    }

    public function testRecordMovementCallsUnitConversionWhenUnitIdNonZero(): void
    {
        $conn = $this->createMock(Connection::class);
        $conn->expects(self::once())->method('beginTransaction');
        $conn->expects(self::once())->method('commit');
        $conn->method('executeStatement')->willReturn(1);
        $conn->method('fetchOne')->willReturn(false);

        $unitConversion = $this->createMock(UnitConversionServiceInterface::class);
        $unitConversion->expects(self::once())->method('toBase')->with('prod-1', 5, '2')->willReturn('6.000000');

        $service = $this->makeService($conn, unitConversion: $unitConversion);
        $draft = $this->makeDraft(qty: '-2', unitId: 5);

        $service->recordMovement($draft);
    }

    public function testRecordMovementDispatchesEventAfterCommit(): void
    {
        $conn = $this->createStub(Connection::class);
        $conn->method('executeStatement')->willReturn(1);
        $conn->method('fetchOne')->willReturn(false);

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects(self::once())->method('dispatch');

        $service = $this->makeService($conn, dispatcher: $dispatcher);
        $service->recordMovement($this->makeDraft());
    }

    public function testRecordMovementRunsEnricherChain(): void
    {
        $conn = $this->createStub(Connection::class);
        $conn->method('executeStatement')->willReturn(1);
        $conn->method('fetchOne')->willReturn(false);

        $enricher = $this->createMock(StockMovementEnricherInterface::class);
        $enricher->expects(self::once())->method('enrichSaleMovements')->willReturnArgument(1); // pass-through

        $service = $this->makeService($conn, enrichers: [$enricher]);
        $service->recordMovement($this->makeDraft());
    }

    // ── reserve / release ─────────────────────────────────────────────────────

    public function testReserveExecutesUpsert(): void
    {
        $conn = $this->createMock(Connection::class);
        $conn
            ->expects(self::once())
            ->method('executeStatement')
            ->with(self::stringContains('qty_reserved'), self::arrayHasKey('qty'));

        $service = $this->makeService($conn);
        $service->reserve('wh-1', 'prod-1', '3.000000');
    }

    public function testReleaseExecutesUpdate(): void
    {
        $conn = $this->createMock(Connection::class);
        $conn
            ->expects(self::once())
            ->method('executeStatement')
            ->with(self::stringContains('GREATEST'), self::arrayHasKey('qty'));

        $service = $this->makeService($conn);
        $service->release('wh-1', 'prod-1', '3.000000');
    }
}
