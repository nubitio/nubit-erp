<?php

declare(strict_types=1);

namespace App\Tests\Inventory\State;

use App\Catalog\Entity\Product;
use App\Identity\Entity\User;
use App\Inventory\Core\Contract\StockValidationPolicyInterface;
use App\Inventory\Core\Dto\StockValidationResult;
use App\Inventory\Core\Enum\InventoryBehavior;
use App\Inventory\Core\Enum\StockEntryType;
use App\Inventory\Core\Enum\StockMovementType;
use App\Inventory\Core\Service\InventoryService;
use App\Inventory\Core\Service\UnitConversionService;
use App\Inventory\Entity\LogisticOperation;
use App\Inventory\Entity\StockEntry;
use App\Inventory\Entity\StockEntryItem;
use App\Inventory\Entity\Warehouse;
use App\Inventory\State\StockEntryProcessor;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Integration tests for StockEntryProcessor.
 *
 * Strategy:
 *   - Real InventoryService backed by SQLite in-memory → ledger writes are real
 *   - Mocked EntityManager → ORM persist is captured, wrapInTransaction runs the callback
 *   - Mocked Security → returns a stub User
 *   - TenantStockValidationPolicy → injected as mock (controlled per test)
 *
 * Covers:
 *   RECEIPT    — MANUAL_IN generated, stock increases
 *   ISSUE      — MANUAL_OUT generated, stock decreases
 *   ISSUE      — fails when stock insufficient (STRICT)
 *   TRANSFER   — TRANSFER_OUT + TRANSFER_IN generated
 *   TRANSFER   — fails when same warehouse
 *   TRANSFER   — fails when no target warehouse
 *   ADJUSTMENT — snapshot + delta, stock corrected
 *   ADJUSTMENT — no movement when delta = 0
 *   ADJUSTMENT — requires reason
 *   ADJUSTMENT — reason OTHER requires note
 *   NOT_TRACKED products are silently skipped
 */
#[AllowMockObjectsWithoutExpectations]
final class StockEntryProcessorTest extends TestCase
{
    private Connection $conn;

    /** @var EntityManagerInterface&MockObject */
    private MockObject $em;

    /** @var Security&MockObject */
    private MockObject $security;

    /** @var StockValidationPolicyInterface&MockObject */
    private MockObject $policy;

    /** @var EventDispatcherInterface&MockObject */
    private MockObject $dispatcher;

    private InventoryService $inventoryService;

    private UnitConversionService $unitConversion;

    /** @var list<string> */
    private array $grantedPermissions;

    protected function setUp(): void
    {
        $this->conn = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);

        $this->createSchema();

        $this->dispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->security = $this->createMock(Security::class);
        $this->policy = $this->createMock(StockValidationPolicyInterface::class);
        $this->grantedPermissions = [
            'perm:inventory.adjust',
            'perm:inventory.transfer',
            'perm:inventory.cycle_count',
        ];

        $this->unitConversion = new UnitConversionService($this->conn);

        $this->inventoryService = new InventoryService(
            $this->conn,
            $this->policy,
            $this->unitConversion,
            $this->dispatcher,
        );

        // wrapInTransaction: just run the callback
        $this->em
            ->method('wrapInTransaction')
            ->willReturnCallback(static function (callable $cb): void {
                $cb();
            });

        // persist + flush: assign IDs via reflection, no real ORM
        $this->em->method('persist')->willReturnCallback($this->assignIds(...));

        $this->em->method('flush');

        // Security: return a stub user
        $user = $this->createStub(User::class);
        $this->security->method('getUser')->willReturn($user);
        $this->security
            ->method('isGranted')
            ->willReturnCallback(fn(string $attribute): bool => in_array($attribute, $this->grantedPermissions, true));

        // Policy: allow by default
        $this->policy->method('validateSale')->willReturn(StockValidationResult::allowed());
    }

    protected function tearDown(): void
    {
        $this->conn->close();
    }

    // ─── RECEIPT ─────────────────────────────────────────────────────────────

    public function testReceiptGeneratesManualInAndIncreasesStock(): void
    {
        $this->seedStock('wh-1', 'prod-a', '10.000000');

        $entry = $this->makeEntry(StockEntryType::RECEIPT, 'wh-1');
        $entry->addItem($this->makeItem('prod-a', '5.000000'));

        $this->processor()->process($entry, $this->stubOperation());

        self::assertOnHand('wh-1', 'prod-a', 15.0);
        $this->assertMovementType('prod-a', StockMovementType::MANUAL_IN);
        $this->assertQtyDeltaInLedger('wh-1', 'prod-a', '5.000000');
    }

    public function testReceiptWithMultipleItemsAddsEachProduct(): void
    {
        $this->seedStock('wh-1', 'prod-a', '0.000000');
        $this->seedStock('wh-1', 'prod-b', '0.000000');

        $entry = $this->makeEntry(StockEntryType::RECEIPT, 'wh-1');
        $entry->addItem($this->makeItem('prod-a', '3.000000'));
        $entry->addItem($this->makeItem('prod-b', '7.000000'));

        $this->processor()->process($entry, $this->stubOperation());

        self::assertOnHand('wh-1', 'prod-a', 3.0);
        self::assertOnHand('wh-1', 'prod-b', 7.0);
    }

    public function testReceiptWithRepeatedProductLinesAppliesEveryLine(): void
    {
        $this->seedStock('wh-1', 'prod-a', '0.000000');

        $entry = $this->makeEntry(StockEntryType::RECEIPT, 'wh-1');
        $entry->addItem($this->makeItem('prod-a', '3.000000'));
        $entry->addItem($this->makeItem('prod-a', '7.000000'));

        $this->processor()->process($entry, $this->stubOperation());

        self::assertOnHand('wh-1', 'prod-a', 10.0);
        $count = $this->conn->fetchOne("SELECT COUNT(*) FROM stock_movements WHERE product_id = 'prod-a'");
        self::assertSame(2, (int) $count);
    }

    // ─── ISSUE ───────────────────────────────────────────────────────────────

    public function testIssueGeneratesManualOutAndDecreasesStock(): void
    {
        $this->seedStock('wh-1', 'prod-a', '20.000000');

        $entry = $this->makeEntry(StockEntryType::ISSUE, 'wh-1');
        $entry->addItem($this->makeItem('prod-a', '4.000000'));

        $this->processor()->process($entry, $this->stubOperation());

        self::assertOnHand('wh-1', 'prod-a', 16.0);
        $this->assertMovementType('prod-a', StockMovementType::MANUAL_OUT);
        $this->assertQtyDeltaInLedger('wh-1', 'prod-a', '-4.000000');
    }

    public function testIssueThrowsWhenPolicyBlocksAndStockInsufficient(): void
    {
        $blockingPolicy = $this->createMock(StockValidationPolicyInterface::class);
        $blockingPolicy->method('validateSale')->willReturn(StockValidationResult::blocked('stock insuficiente'));

        $entry = $this->makeEntry(StockEntryType::ISSUE, 'wh-1');
        $entry->addItem($this->makeItem('prod-a', '999.000000'));

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessageMatches('/Stock insuficiente/i');

        $this->processorWith($blockingPolicy)->process($entry, $this->stubOperation());
    }

    // ─── TRANSFER ────────────────────────────────────────────────────────────

    public function testTransferGeneratesTwoMovementsAndMovesStock(): void
    {
        $this->seedStock('wh-src', 'prod-a', '30.000000');
        $this->seedStock('wh-dst', 'prod-a', '5.000000');

        $entry = $this->makeTransferEntry('wh-src', 'wh-dst');
        $entry->addItem($this->makeItem('prod-a', '10.000000'));

        $this->processor()->process($entry, $this->stubOperation());

        self::assertOnHand('wh-src', 'prod-a', 20.0);
        self::assertOnHand('wh-dst', 'prod-a', 15.0);

        $rows = $this->conn->fetchAllAssociative(
            "SELECT movement_type, qty_delta_base FROM stock_movements WHERE product_id = 'prod-a' ORDER BY qty_delta_base ASC",
        );
        self::assertCount(2, $rows);
        self::assertSame('TRANSFER_OUT', $rows[0]['movement_type']);
        self::assertSame('TRANSFER_IN', $rows[1]['movement_type']);
    }

    public function testTransferThrowsWhenSameWarehouse(): void
    {
        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessageMatches('/different/i');

        $entry = $this->makeTransferEntry('wh-1', 'wh-1');
        $entry->addItem($this->makeItem('prod-a', '1.000000'));

        $this->processor()->process($entry, $this->stubOperation());
    }

    public function testTransferThrowsWhenNoTargetWarehouse(): void
    {
        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessageMatches('/targetWarehouse/i');

        $entry = $this->makeEntry(StockEntryType::TRANSFER, 'wh-1');
        $entry->addItem($this->makeItem('prod-a', '1.000000'));

        $this->processor()->process($entry, $this->stubOperation());
    }

    public function testTransferValidatesRepeatedProductLinesAsOneRequest(): void
    {
        $this->seedStock('wh-src', 'prod-a', '30.000000');

        $blockingPolicy = $this->createMock(StockValidationPolicyInterface::class);
        $blockingPolicy
            ->method('validateSale')
            ->willReturnCallback(static function (
                Product $product,
                string $warehouseId,
                string $requestedQty,
                string $onHand,
            ): StockValidationResult {
                return bccomp($requestedQty, $onHand, 6) === 1
                    ? StockValidationResult::blocked('stock insuficiente')
                    : StockValidationResult::allowed();
            });

        $entry = $this->makeTransferEntry('wh-src', 'wh-dst');
        $entry->addItem($this->makeItem('prod-a', '20.000000'));
        $entry->addItem($this->makeItem('prod-a', '20.000000'));

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessageMatches('/Stock insuficiente/i');

        $this->processorWith($blockingPolicy)->process($entry, $this->stubOperation());
    }

    // ─── ADJUSTMENT ──────────────────────────────────────────────────────────

    public function testAdjustmentSetsSnapshotAndCorrectsDelta(): void
    {
        $this->seedStock('wh-1', 'prod-a', '50.000000');

        $entry = $this->makeAdjustment('wh-1', 'COUNT_CORRECTION');
        $item = $this->makeItem('prod-a', '47.000000');
        $entry->addItem($item);

        $this->processor()->process($entry, $this->stubOperation());

        // Stock corrected to 47
        self::assertOnHand('wh-1', 'prod-a', 47.0);

        // Item snapshot set
        self::assertEqualsWithDelta(50.0, (float) $item->getQtyBefore(), 0.000001);
        self::assertEqualsWithDelta(-3.0, (float) $item->getQtyDelta(), 0.000001);

        $this->assertMovementType('prod-a', StockMovementType::ADJUSTMENT);
        $this->assertQtyDeltaInLedger('wh-1', 'prod-a', '-3.000000');
    }

    public function testAdjustmentUpIncreasesStock(): void
    {
        $this->seedStock('wh-1', 'prod-a', '10.000000');

        $entry = $this->makeAdjustment('wh-1', 'INITIAL_STOCK');
        $entry->addItem($this->makeItem('prod-a', '25.000000'));

        $this->processor()->process($entry, $this->stubOperation());

        self::assertOnHand('wh-1', 'prod-a', 25.0);
        $this->assertQtyDeltaInLedger('wh-1', 'prod-a', '15.000000');
    }

    public function testAdjustmentWithNoDeltaGeneratesNoMovement(): void
    {
        $this->seedStock('wh-1', 'prod-a', '10.000000');

        $entry = $this->makeAdjustment('wh-1', 'COUNT_CORRECTION');
        $entry->addItem($this->makeItem('prod-a', '10.000000')); // same as current

        $this->processor()->process($entry, $this->stubOperation());

        // Stock unchanged
        self::assertOnHand('wh-1', 'prod-a', 10.0);

        // No movement in ledger
        $count = $this->conn->fetchOne("SELECT COUNT(*) FROM stock_movements WHERE product_id = 'prod-a'");
        self::assertSame(0, (int) $count);
    }

    public function testAdjustmentRequiresReason(): void
    {
        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessageMatches('/reason/i');

        $entry = $this->makeEntry(StockEntryType::ADJUSTMENT, 'wh-1');
        $entry->addItem($this->makeItem('prod-a', '5.000000'));

        $this->processor()->process($entry, $this->stubOperation());
    }

    public function testAdjustmentReasonOtherRequiresNote(): void
    {
        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessageMatches('/note/i');

        $entry = $this->makeAdjustment('wh-1', 'OTHER'); // no note set
        $entry->addItem($this->makeItem('prod-a', '5.000000'));

        $this->processor()->process($entry, $this->stubOperation());
    }

    public function testAdjustmentReasonOtherWithNoteSucceeds(): void
    {
        $this->seedStock('wh-1', 'prod-a', '10.000000');

        $entry = $this->makeAdjustment('wh-1', 'OTHER', 'Motivo especial');
        $entry->addItem($this->makeItem('prod-a', '8.000000'));

        $this->processor()->process($entry, $this->stubOperation());

        self::assertOnHand('wh-1', 'prod-a', 8.0);
    }

    // ─── NOT_TRACKED products ─────────────────────────────────────────────────

    public function testNotTrackedProductIsSkippedSilently(): void
    {
        $entry = $this->makeEntry(StockEntryType::RECEIPT, 'wh-1');
        $entry->addItem($this->makeItem('prod-svc', '99.000000', behavior: InventoryBehavior::NOT_TRACKED));

        $this->processor()->process($entry, $this->stubOperation());

        $count = $this->conn->fetchOne('SELECT COUNT(*) FROM stock_movements');
        self::assertSame(0, (int) $count);
    }

    // ─── General validation ───────────────────────────────────────────────────

    public function testThrowsWhenNoItems(): void
    {
        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessageMatches('/item/i');

        $entry = $this->makeEntry(StockEntryType::RECEIPT, 'wh-1');
        $this->processor()->process($entry, $this->stubOperation());
    }

    public function testTransferRequiresTransferPermission(): void
    {
        $this->grantedPermissions = ['perm:inventory.adjust'];
        $entry = $this->makeTransferEntry('wh-1', 'wh-2');
        $entry->addItem($this->makeItem('prod-a', '1.000000'));

        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('inventory.transfer');

        $this->processor()->process($entry, $this->stubOperation());
    }

    public function testCountCorrectionRequiresCycleCountPermission(): void
    {
        $this->grantedPermissions = ['perm:inventory.adjust'];
        $entry = $this->makeAdjustment('wh-1', 'COUNT_CORRECTION');
        $entry->addItem($this->makeItem('prod-a', '1.000000'));

        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('inventory.cycle_count');

        $this->processor()->process($entry, $this->stubOperation());
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function processor(): StockEntryProcessor
    {
        return $this->processorWith($this->policy);
    }

    private function processorWith(StockValidationPolicyInterface $policy): StockEntryProcessor
    {
        $persistProcessor = $this->createStub(\ApiPlatform\State\ProcessorInterface::class);

        return new StockEntryProcessor(
            $this->inventoryService,
            $this->unitConversion,
            $this->em,
            $this->security,
            $policy,
            $persistProcessor,
            new \App\Efact\Domain\ActiveReferenceGuard(),
        );
    }

    private function stubOperation(): \ApiPlatform\Metadata\Operation
    {
        return $this->createStub(\ApiPlatform\Metadata\Operation::class);
    }

    private function makeEntry(StockEntryType $type, string $warehouseId): StockEntry
    {
        $warehouse = $this->makeWarehouse($warehouseId);
        $entry = new StockEntry();
        $entry->setType($type);
        $entry->setWarehouse($warehouse);

        return $entry;
    }

    private function makeTransferEntry(string $sourceId, string $targetId): StockEntry
    {
        $entry = $this->makeEntry(StockEntryType::TRANSFER, $sourceId);
        $entry->setTargetWarehouse($this->makeWarehouse($targetId));

        return $entry;
    }

    private function makeAdjustment(string $warehouseId, string $reason, ?string $note = null): StockEntry
    {
        $entry = $this->makeEntry(StockEntryType::ADJUSTMENT, $warehouseId);
        $entry->setReason($reason);
        if ($note !== null) {
            $entry->setNote($note);
        }

        return $entry;
    }

    private function makeWarehouse(string $id): Warehouse
    {
        $warehouse = new Warehouse();
        $ref = new \ReflectionProperty(Warehouse::class, 'id');
        $ref->setValue($warehouse, $id);
        $warehouse->setName('Almacén ' . $id);

        return $warehouse;
    }

    private function makeItem(
        string $productId,
        string $qty,
        InventoryBehavior $behavior = InventoryBehavior::TRACKED,
    ): StockEntryItem {
        $product = new Product();
        $ref = new \ReflectionProperty(Product::class, 'id');
        $ref->setValue($product, $productId);
        $product->setInventoryBehavior($behavior);
        $product->setTrackInventory($behavior !== InventoryBehavior::NOT_TRACKED);
        $product->setName('Producto ' . $productId);

        $item = new StockEntryItem();
        $item->setProduct($product);
        $item->setQty($qty);

        // seed product_units for base conversion
        $exists = $this->conn->fetchOne('SELECT COUNT(*) FROM product_units WHERE product_id = ? AND unit_id = 1', [
            $productId,
        ]);
        if (!(int) $exists) {
            $this->conn->insert('product_units', [
                'product_id' => $productId,
                'unit_id' => 1,
                'conversion_to_base' => '1.000000',
            ]);
        }

        return $item;
    }

    private function seedStock(string $warehouseId, string $productId, string $qty): void
    {
        $exists = $this->conn->fetchOne('SELECT COUNT(*) FROM stock_items WHERE warehouse_id = ? AND product_id = ?', [
            $warehouseId,
            $productId,
        ]);

        if ((int) $exists) {
            $this->conn->update(
                'stock_items',
                ['qty_on_hand' => $qty],
                [
                    'warehouse_id' => $warehouseId,
                    'product_id' => $productId,
                ],
            );
        } else {
            $this->conn->insert('stock_items', [
                'warehouse_id' => $warehouseId,
                'product_id' => $productId,
                'qty_on_hand' => $qty,
                'qty_reserved' => '0.000000',
                'updated_at' => date('Y-m-d H:i:s'),
                'version' => 0,
            ]);
        }
    }

    private function getOnHand(string $warehouseId, string $productId): string
    {
        return (string) (
            $this->conn->fetchOne('SELECT qty_on_hand FROM stock_items WHERE warehouse_id = ? AND product_id = ?', [
                $warehouseId,
                $productId,
            ]) ?: '0'
        );
    }

    private function assertOnHand(string $warehouseId, string $productId, float $expected): void
    {
        $actual = (float) $this->getOnHand($warehouseId, $productId);
        self::assertEqualsWithDelta($expected, $actual, 0.000001, "qty_on_hand for {$productId} in {$warehouseId}");
    }

    private function assertMovementType(string $productId, StockMovementType $expected): void
    {
        $type = $this->conn->fetchOne('SELECT movement_type FROM stock_movements WHERE product_id = ? LIMIT 1', [
            $productId,
        ]);
        self::assertSame($expected->value, $type, "Expected movement type {$expected->value} for product {$productId}");
    }

    private function assertQtyDeltaInLedger(string $warehouseId, string $productId, string $expected): void
    {
        $delta = $this->conn->fetchOne('SELECT SUM(qty_delta_base) FROM stock_movements WHERE warehouse_id = ? AND product_id = ?', [
            $warehouseId,
            $productId,
        ]);
        self::assertEqualsWithDelta((float) $expected, (float) $delta, 0.000001);
    }

    /** Assign IDs to StockEntry and its items via reflection (simulates UuidGenerator + IDENTITY). */
    private function assignIds(mixed $entity): void
    {
        if ($entity instanceof StockEntry && $entity->getId() === null) {
            $ref = new \ReflectionProperty(StockEntry::class, 'id');
            $ref->setValue($entity, \Symfony\Component\Uid\Uuid::v7()->toRfc4122());

            $counter = 1;
            foreach ($entity->getItems() as $item) {
                if ($item->getId() === null) {
                    $ref2 = new \ReflectionProperty(StockEntryItem::class, 'id');
                    $ref2->setValue($item, $counter++);
                }
            }
        }
    }

    // ─── Schema ───────────────────────────────────────────────────────────────

    private function createSchema(): void
    {
        $this->conn->executeStatement(<<<'SQL'
                CREATE TABLE stock_movements (
                    id              VARCHAR(36)   NOT NULL,
                    warehouse_id    VARCHAR(36)   NOT NULL,
                    product_id      VARCHAR(36)   NOT NULL,
                    occurred_at     DATETIME      NOT NULL,
                    movement_type   VARCHAR(32)   NOT NULL,
                    quantity_delta  DECIMAL(18,6) NOT NULL,
                    qty_delta_base  DECIMAL(18,6) NOT NULL,
                    qty_original    DECIMAL(18,6) NOT NULL,
                    unit_id         INTEGER       DEFAULT NULL,
                    source_type     VARCHAR(64)   NOT NULL,
                    source_id       VARCHAR(255)  NOT NULL,
                    note            TEXT          DEFAULT NULL,
                    idempotency_key VARCHAR(64)   NOT NULL UNIQUE,
                    sync_status     VARCHAR(20)   NOT NULL DEFAULT 'SYNCED',
                    synced_at       DATETIME      DEFAULT NULL,
                    version_no      INTEGER       NOT NULL DEFAULT 1,
                    last_modified_at DATETIME     NOT NULL,
                    created_at      DATETIME      NOT NULL,
                    PRIMARY KEY (id)
                )
            SQL);

        $this->conn->executeStatement(<<<'SQL'
                CREATE TABLE stock_items (
                    warehouse_id VARCHAR(36)   NOT NULL,
                    product_id   VARCHAR(36)   NOT NULL,
                    qty_on_hand  DECIMAL(18,6) NOT NULL DEFAULT '0.000000',
                    qty_reserved DECIMAL(18,6) NOT NULL DEFAULT '0.000000',
                    updated_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    last_movement_id VARCHAR(36) DEFAULT NULL,
                    version      INTEGER       NOT NULL DEFAULT 0,
                    PRIMARY KEY (warehouse_id, product_id)
                )
            SQL);

        $this->conn->executeStatement(<<<'SQL'
                CREATE TABLE product_units (
                    product_id          VARCHAR(36)   NOT NULL,
                    unit_id             INTEGER       NOT NULL,
                    conversion_to_base  DECIMAL(18,6) NOT NULL,
                    PRIMARY KEY (product_id, unit_id)
                )
            SQL);
        $this->conn->executeStatement(<<<'SQL'
                CREATE TABLE stock_balances (
                    warehouse_id VARCHAR(36)   NOT NULL,
                    product_id   VARCHAR(36)   NOT NULL,
                    on_hand_qty  DECIMAL(18,6) NOT NULL DEFAULT '0.000000',
                    PRIMARY KEY (warehouse_id, product_id)
                )
            SQL);
    }
}
