<?php

declare(strict_types=1);

namespace App\Tests\Purchasing\State;

use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Catalog\Entity\Product;
use App\Efact\Domain\ActiveReferenceGuard;
use App\Identity\Auth\BranchContextServiceInterface;
use App\Identity\Auth\OperationalBranchGuard;
use App\Identity\Entity\User;
use App\Inventory\Core\Contract\StockValidationPolicyInterface;
use App\Inventory\Core\Dto\StockValidationResult;
use App\Inventory\Core\Service\InventoryService;
use App\Inventory\Core\Service\UnitConversionService;
use App\Inventory\Entity\Warehouse;
use App\Organization\Entity\Branch;
use App\Organization\Repository\BranchRepository;
use App\Purchasing\Entity\Purchase;
use App\Purchasing\Entity\PurchaseItem;
use App\Purchasing\Service\PurchaseOrderReceiptService;
use App\Purchasing\Service\PurchaseReceiptSequenceService;
use App\Purchasing\Service\PurchaseSupplierDocumentValidator;
use App\Purchasing\State\PurchaseInventoryService;
use App\Purchasing\State\PurchaseProcessor;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Bundle\SecurityBundle\Security;

final class PurchaseProcessorInventoryTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);
        $this->connection->executeStatement(<<<'SQL'
            CREATE TABLE stock_movements (
                id TEXT PRIMARY KEY,
                warehouse_id TEXT NOT NULL,
                product_id TEXT NOT NULL,
                occurred_at TEXT NOT NULL,
                movement_type TEXT NOT NULL,
                quantity_delta NUMERIC NOT NULL,
                qty_delta_base NUMERIC NOT NULL,
                qty_original NUMERIC NOT NULL,
                unit_id INTEGER DEFAULT NULL,
                source_type TEXT NOT NULL,
                source_id TEXT DEFAULT NULL,
                note TEXT DEFAULT NULL,
                idempotency_key TEXT DEFAULT NULL UNIQUE,
                sync_status TEXT NOT NULL,
                synced_at TEXT DEFAULT NULL,
                version_no INTEGER NOT NULL,
                last_modified_at TEXT NOT NULL,
                created_at TEXT NOT NULL
            )
            SQL);
        $this->connection->executeStatement(<<<'SQL'
            CREATE TABLE stock_items (
                warehouse_id TEXT NOT NULL,
                product_id TEXT NOT NULL,
                qty_on_hand NUMERIC NOT NULL DEFAULT 0,
                qty_reserved NUMERIC NOT NULL DEFAULT 0,
                updated_at TEXT NOT NULL,
                last_movement_id TEXT DEFAULT NULL,
                version INTEGER NOT NULL DEFAULT 0,
                PRIMARY KEY (warehouse_id, product_id)
            )
            SQL);
        $this->connection->executeStatement(
            'CREATE TABLE purchase_receipt_sequences (id INTEGER PRIMARY KEY, last_number INTEGER NOT NULL)',
        );
        $this->connection->insert('stock_items', [
            'warehouse_id' => 'wh-1',
            'product_id' => 'prod-1',
            'qty_on_hand' => '2.000000',
            'qty_reserved' => '0.000000',
            'updated_at' => date('Y-m-d H:i:s'),
            'version' => 0,
        ]);
    }

    protected function tearDown(): void
    {
        $this->connection->close();
    }

    public function testNewPurchaseWritesStockKeyedByAssignedPurchaseId(): void
    {
        $persist = $this->createMock(ProcessorInterface::class);
        $persist->method('process')->willReturnArgument(0);

        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn(new User());

        $inventory = new InventoryService(
            $this->connection,
            new class() implements StockValidationPolicyInterface {
                public function validateSale(
                    Product $product,
                    string $warehouseId,
                    string $requestedQtyBase,
                    string $onHandQtyBase,
                ): StockValidationResult {
                    return StockValidationResult::allowed();
                }
            },
            new UnitConversionService($this->connection),
            new class() implements EventDispatcherInterface {
                public function dispatch(object $event): object
                {
                    return $event;
                }
            },
        );

        $branchContext = $this->createStub(BranchContextServiceInterface::class);

        $branch = new Branch();
        new \ReflectionProperty(Branch::class, 'id')->setValue($branch, 'branch-1');
        $branchContext->method('getCurrentBranchId')->willReturn('branch-1');
        $branches = $this->createStub(BranchRepository::class);
        $branches->method('find')->willReturn($branch);

        $processor = new PurchaseProcessor(
            $persist,
            $security,
            new OperationalBranchGuard($branchContext, $branches),
            new PurchaseInventoryService($inventory),
            new PurchaseReceiptSequenceService($this->connection),
            new PurchaseSupplierDocumentValidator(),
            new ActiveReferenceGuard(),
            new \ReflectionClass(PurchaseOrderReceiptService::class)->newInstanceWithoutConstructor(),
        );

        $warehouse = (new Warehouse())->setBranch($branch);
        new \ReflectionProperty(Warehouse::class, 'id')->setValue($warehouse, 'wh-1');
        $product = new Product();
        new \ReflectionProperty(Product::class, 'id')->setValue($product, 'prod-1');
        $product->setType(1)->setTrackInventory(true);

        $purchase = new Purchase()
            ->setWarehouse($warehouse)
            ->setBranch($branch)
            ->setReceiptNo(1)
            ->setSupplierDocType('01')
            ->setSupplierDocSeries('F001')
            ->setSupplierDocNumber('1');
        $purchase->addItem(
            new PurchaseItem()
                ->setProduct($product)
                ->setQty('5.000'),
        );

        $result = $processor->process($purchase, new Post());
        self::assertInstanceOf(Purchase::class, $result);
        self::assertNotNull($result->getId());

        self::assertEqualsWithDelta(
            7.0,
            (float) $this->connection->fetchOne('SELECT qty_on_hand FROM stock_items WHERE warehouse_id = :w AND product_id = :p', [
                'w' => 'wh-1',
                'p' => 'prod-1',
            ]),
            0.001,
        );

        $movement = $this->connection->fetchAssociative(
            'SELECT source_id, source_type, movement_type FROM stock_movements LIMIT 1',
        );
        self::assertIsArray($movement);
        self::assertSame('PURCHASE_IN', $movement['movement_type']);
        self::assertSame('PURCHASE_ITEM', $movement['source_type']);
        self::assertSame($result->getId() . ':1', $movement['source_id']);
    }
}
