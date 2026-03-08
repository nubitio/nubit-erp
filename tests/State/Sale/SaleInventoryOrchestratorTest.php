<?php

declare(strict_types=1);

namespace App\Tests\State\Sale;

use App\Core\Exception\ServiceException;
use App\Core\Service\SettingsService;
use App\Core\Util\Constants;
use App\Dto\Auth\AuthUserDto;
use App\Entity\Product;
use App\Entity\Sale;
use App\Entity\SaleItem;
use App\Entity\Warehouse;
use App\Inventory\Core\Contract\StockValidationPolicyInterface;
use App\Inventory\Core\Dto\StockValidationResult;
use App\Inventory\Core\Enum\LotTrackingStrategy;
use App\Service\InventoryLedgerService;
use App\State\Sale\NegativeStockPolicyResolver;
use App\State\Sale\SaleInventoryOrchestrator;
use Doctrine\DBAL\Connection;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class SaleInventoryOrchestratorTest extends TestCase
{
    private Connection&MockObject $connection;
    private SettingsService&MockObject $settings;
    private StockValidationPolicyInterface&MockObject $validationPolicy;
    private InventoryLedgerService $ledger;
    private NegativeStockPolicyResolver $policyResolver;
    private SaleInventoryOrchestrator $orchestrator;
    private AuthUserDto $user;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->settings = $this->createMock(SettingsService::class);
        $this->validationPolicy = $this->createMock(StockValidationPolicyInterface::class);

        $this->ledger = new InventoryLedgerService($this->connection, $this->settings);
        $this->policyResolver = new NegativeStockPolicyResolver($this->settings);

        $this->orchestrator = new SaleInventoryOrchestrator(
            $this->ledger,
            $this->validationPolicy,
            $this->policyResolver,
            new NullLogger(),
        );

        $this->user = new AuthUserDto(userId: 1, username: 'test');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeWarehouse(string $id = 'wh-1'): Warehouse&MockObject
    {
        $warehouse = $this->createMock(Warehouse::class);
        $warehouse->method('getId')->willReturn($id);

        return $warehouse;
    }

    private function makeProduct(bool $tracked = true, string $id = 'prod-1'): Product&MockObject
    {
        $product = $this->createMock(Product::class);
        $product->method('getTrackInventory')->willReturn($tracked);
        $product->method('getId')->willReturn($id);
        $product->method('getName')->willReturn('Widget');
        $product->method('getLotTrackingStrategyEnum')->willReturn(LotTrackingStrategy::NONE);

        return $product;
    }

    /**
     * @param list<SaleItem> $items
     */
    private function makeSale(int $tipoId, Warehouse $warehouse, array $items = []): Sale&MockObject
    {
        $sale = $this->createMock(Sale::class);
        $sale->method('getAlmacen')->willReturn($warehouse);
        $sale->method('getTipoId')->willReturn($tipoId);
        $sale->method('getId')->willReturn('sale-1');
        $sale->method('getItems')->willReturn(new ArrayCollection($items));
        $sale->method('getFecha')->willReturn(new \DateTimeImmutable());

        return $sale;
    }

    private function makeSaleItem(Product $product, float $cantidad): SaleItem&MockObject
    {
        $item = $this->createMock(SaleItem::class);
        $item->method('getProducto')->willReturn($product);
        $item->method('getCantidad')->willReturn($cantidad);
        $item->method('getDescuento')->willReturn(null);
        $item->method('getPrecioUnitario')->willReturn('10.00');
        $item->method('getPrecioTotal')->willReturn('10.00');

        return $item;
    }

    /**
     * Configure settings for legacy (no-core-validation) path with a given stock policy.
     */
    private function configureSettings(
        string $stockPolicy = 'STRICT',
        string $coreMode = 'legacy',
        string $dualWrite = 'false',
        string $readFromStockItems = 'false',
    ): void {
        $this->settings->method('get')
            ->willReturnMap([
                [Constants::INVENTORY_NEGATIVE_STOCK_POLICY, '', $stockPolicy],
                [Constants::INVENTORY_CORE_MODE, 'legacy', $coreMode],
                [Constants::INVENTORY_CORE_DUAL_WRITE, 'false', $dualWrite],
                [Constants::INVENTORY_CORE_READ_FROM_STOCK_ITEMS, 'false', $readFromStockItems],
            ]);
    }

    /**
     * Make Connection return a given stock balance from stock_balances.
     */
    private function stubStockBalance(float $qty): void
    {
        $this->connection->method('fetchOne')
            ->willReturn((string) $qty);
    }

    /**
     * Stub connection for a successful ledger write (beginTransaction + executeStatement + commit).
     */
    private function stubSuccessfulLedgerWrite(): void
    {
        $this->connection->method('fetchAssociative')->willReturn([
            'on_hand_qty' => '100.000000',
            'reserved_qty' => '0.000000',
            'available_qty' => '100.000000',
            'avg_cost' => null,
        ]);
        $this->connection->method('executeStatement')->willReturn(1);
    }

    // -------------------------------------------------------------------------
    // Gate: no warehouse → early return, no DB calls
    // -------------------------------------------------------------------------

    public function testNoWarehouseSkipsAllProcessing(): void
    {
        $sale = $this->createMock(Sale::class);
        $sale->method('getAlmacen')->willReturn(null);

        $this->connection->expects(self::never())->method('fetchOne');
        $this->connection->expects(self::never())->method('executeStatement');

        $this->orchestrator->process($sale, $this->user);
    }

    // -------------------------------------------------------------------------
    // Offline sale → skip stock validation, apply ledger movements
    // -------------------------------------------------------------------------

    public function testOfflineSaleSkipsStockValidationAndAppliesLedger(): void
    {
        $warehouse = $this->makeWarehouse();
        $product = $this->makeProduct();
        $item = $this->makeSaleItem($product, 2.0);
        $sale = $this->makeSale(2, $warehouse, [$item]);

        // Legacy path (no core validation)
        $this->configureSettings('STRICT', 'legacy');
        $this->stubStockBalance(10.0);
        $this->stubSuccessfulLedgerWrite();

        // validateSale must NOT be called for offline sales
        $this->validationPolicy->expects(self::never())->method('validateSale');

        $this->connection->expects(self::once())->method('beginTransaction');

        $this->orchestrator->process($sale, $this->user, true);
    }

    // -------------------------------------------------------------------------
    // tipoId === 2 (sale), sufficient stock, BLOCK policy → ledger applied
    // -------------------------------------------------------------------------

    public function testSufficientStockBlockPolicyAppliesLedger(): void
    {
        $warehouse = $this->makeWarehouse();
        $product = $this->makeProduct();
        $item = $this->makeSaleItem($product, 3.0);
        $sale = $this->makeSale(2, $warehouse, [$item]);

        $this->configureSettings('STRICT', 'legacy'); // BLOCK, no allow
        $this->stubStockBalance(10.0); // plenty of stock
        $this->stubSuccessfulLedgerWrite();

        $this->connection->expects(self::once())->method('beginTransaction');

        $this->orchestrator->process($sale, $this->user);
    }

    // -------------------------------------------------------------------------
    // tipoId === 2, insufficient stock, BLOCK policy → throws ServiceException
    // -------------------------------------------------------------------------

    public function testInsufficientStockBlockPolicyThrowsException(): void
    {
        $warehouse = $this->makeWarehouse();
        $product = $this->makeProduct();
        $item = $this->makeSaleItem($product, 10.0);
        $sale = $this->makeSale(2, $warehouse, [$item]);

        $this->configureSettings('STRICT', 'legacy'); // BLOCK
        $this->stubStockBalance(5.0); // not enough

        $this->expectException(ServiceException::class);
        $this->expectExceptionMessageMatches('/Stock insuficiente/');

        $this->orchestrator->process($sale, $this->user);
    }

    // -------------------------------------------------------------------------
    // tipoId === 2, insufficient stock, ALLOW_ALL policy → proceeds, no exception
    // -------------------------------------------------------------------------

    public function testInsufficientStockAllowAllProceedsWithoutException(): void
    {
        $warehouse = $this->makeWarehouse();
        $product = $this->makeProduct();
        $item = $this->makeSaleItem($product, 10.0);
        $sale = $this->makeSale(2, $warehouse, [$item]);

        $this->configureSettings('ALLOW_NEGATIVE', 'legacy'); // maps to ALLOW_ALL → allowNegative=true
        $this->stubStockBalance(1.0); // below requested qty
        $this->stubSuccessfulLedgerWrite();

        // Should not throw
        $this->connection->expects(self::once())->method('beginTransaction');

        $this->orchestrator->process($sale, $this->user);
    }

    // -------------------------------------------------------------------------
    // tipoId === 2, ALLOW_WITH_ROLE, admin user → proceeds (canSell = true)
    // -------------------------------------------------------------------------

    public function testAllowWithRoleAdminUserProceeds(): void
    {
        $warehouse = $this->makeWarehouse();
        $product = $this->makeProduct();
        $item = $this->makeSaleItem($product, 10.0);
        $sale = $this->makeSale(2, $warehouse, [$item]);

        $this->configureSettings('WARNING_ONLY', 'legacy'); // maps to ALLOW_WITH_ROLE
        $this->stubStockBalance(1.0);
        $this->stubSuccessfulLedgerWrite();

        $adminUser = new AuthUserDto(userId: 1, username: 'admin', roles: ['ROLE_ADMIN']);

        $this->connection->expects(self::once())->method('beginTransaction');

        $this->orchestrator->process($sale, $adminUser);
    }

    // -------------------------------------------------------------------------
    // tipoId === 2, ALLOW_WITH_ROLE, non-admin user → enforcement ON → throws
    // -------------------------------------------------------------------------

    public function testAllowWithRoleNonAdminUserThrowsWhenInsufficient(): void
    {
        $warehouse = $this->makeWarehouse();
        $product = $this->makeProduct();
        $item = $this->makeSaleItem($product, 10.0);
        $sale = $this->makeSale(2, $warehouse, [$item]);

        $this->configureSettings('WARNING_ONLY', 'legacy'); // ALLOW_WITH_ROLE
        $this->stubStockBalance(1.0);

        $regularUser = new AuthUserDto(userId: 2, username: 'cashier', roles: ['ROLE_USER']);

        $this->expectException(ServiceException::class);

        $this->orchestrator->process($sale, $regularUser);
    }

    // -------------------------------------------------------------------------
    // useCoreValidation=true (dual_write mode) → StockValidationPolicyInterface called
    // -------------------------------------------------------------------------

    public function testCoreValidationPathCallsValidateSale(): void
    {
        $warehouse = $this->makeWarehouse();
        $product = $this->makeProduct();
        $item = $this->makeSaleItem($product, 2.0);
        $sale = $this->makeSale(2, $warehouse, [$item]);

        $this->configureSettings('STRICT', 'dual_write'); // shouldUseCoreValidation = true
        $this->stubStockBalance(10.0);
        $this->stubSuccessfulLedgerWrite();

        $this->validationPolicy
            ->expects(self::once())
            ->method('validateSale')
            ->willReturn(StockValidationResult::allowed());

        $this->connection->expects(self::once())->method('beginTransaction');

        $this->orchestrator->process($sale, $this->user);
    }

    // -------------------------------------------------------------------------
    // useCoreValidation=true, policy blocks → throws ServiceException
    // -------------------------------------------------------------------------

    public function testCoreValidationBlockedThrowsException(): void
    {
        $warehouse = $this->makeWarehouse();
        $product = $this->makeProduct();
        $item = $this->makeSaleItem($product, 5.0);
        $sale = $this->makeSale(2, $warehouse, [$item]);

        $this->configureSettings('STRICT', 'dual_write'); // core validation ON
        $this->stubStockBalance(3.0);

        $this->validationPolicy
            ->method('validateSale')
            ->willReturn(StockValidationResult::blocked('STRICT'));

        $this->expectException(ServiceException::class);

        $this->orchestrator->process($sale, $this->user);
    }

    // -------------------------------------------------------------------------
    // useCoreValidation=true, warning result → no exception, ledger applied
    // -------------------------------------------------------------------------

    public function testCoreValidationWarningProceedsWithLedger(): void
    {
        $warehouse = $this->makeWarehouse();
        $product = $this->makeProduct();
        $item = $this->makeSaleItem($product, 2.0);
        $sale = $this->makeSale(2, $warehouse, [$item]);

        $this->configureSettings('WARNING_ONLY', 'dual_write');
        $this->stubStockBalance(1.0);
        $this->stubSuccessfulLedgerWrite();

        $this->validationPolicy
            ->method('validateSale')
            ->willReturn(StockValidationResult::warning('Low stock'));

        $this->connection->expects(self::once())->method('beginTransaction');

        $this->orchestrator->process($sale, $this->user);
    }

    // -------------------------------------------------------------------------
    // tipoId === 1 (purchase) → validation skipped, ledger applied (stock increases)
    // -------------------------------------------------------------------------

    public function testPurchaseSaleTipoId1SkipsValidationAndAppliesLedger(): void
    {
        $warehouse = $this->makeWarehouse();
        $product = $this->makeProduct();
        $item = $this->makeSaleItem($product, 5.0);
        $sale = $this->makeSale(1, $warehouse, [$item]); // tipoId=1 purchase

        $this->configureSettings('STRICT', 'legacy');
        $this->stubStockBalance(0.0);
        $this->stubSuccessfulLedgerWrite();

        // No call to validateSale for purchases
        $this->validationPolicy->expects(self::never())->method('validateSale');

        $this->connection->expects(self::once())->method('beginTransaction');

        $this->orchestrator->process($sale, $this->user);
    }

    // -------------------------------------------------------------------------
    // Product with trackInventory=false → skipped entirely
    // -------------------------------------------------------------------------

    public function testItemWithUntrackedProductIsSkipped(): void
    {
        $warehouse = $this->makeWarehouse();
        $product = $this->makeProduct(false); // not tracked
        $item = $this->makeSaleItem($product, 5.0);
        $sale = $this->makeSale(2, $warehouse, [$item]);

        $this->configureSettings('STRICT', 'legacy');

        // No DB calls for stock or ledger writes
        $this->connection->expects(self::never())->method('fetchOne');
        $this->connection->expects(self::never())->method('beginTransaction');

        $this->orchestrator->process($sale, $this->user);
    }

    // -------------------------------------------------------------------------
    // Item with null product → skipped
    // -------------------------------------------------------------------------

    public function testItemWithNullProductIsSkipped(): void
    {
        $warehouse = $this->makeWarehouse();

        $item = $this->createMock(SaleItem::class);
        $item->method('getProducto')->willReturn(null);
        $item->method('getDescuento')->willReturn(null);

        $sale = $this->makeSale(2, $warehouse, [$item]);

        $this->configureSettings('STRICT', 'legacy');

        $this->connection->expects(self::never())->method('fetchOne');
        $this->connection->expects(self::never())->method('beginTransaction');

        $this->orchestrator->process($sale, $this->user);
    }

    // -------------------------------------------------------------------------
    // Empty items array → no DB calls
    // -------------------------------------------------------------------------

    public function testEmptyItemsArrayMakesNoDatabaseCalls(): void
    {
        $warehouse = $this->makeWarehouse();
        $sale = $this->makeSale(2, $warehouse, []);

        $this->configureSettings('STRICT', 'legacy');

        $this->connection->expects(self::never())->method('fetchOne');
        $this->connection->expects(self::never())->method('beginTransaction');

        $this->orchestrator->process($sale, $this->user);
    }

    // -------------------------------------------------------------------------
    // Multiple tracked items → one transaction per item
    // -------------------------------------------------------------------------

    public function testMultipleItemsEachGetLedgerMovement(): void
    {
        $warehouse = $this->makeWarehouse();

        $productA = $this->makeProduct(true, 'prod-a');
        $productB = $this->makeProduct(true, 'prod-b');

        $itemA = $this->makeSaleItem($productA, 1.0);
        $itemB = $this->makeSaleItem($productB, 2.0);

        $sale = $this->makeSale(2, $warehouse, [$itemA, $itemB]);

        $this->configureSettings('ALLOW_NEGATIVE', 'legacy');
        $this->stubStockBalance(10.0);

        $this->connection->method('fetchAssociative')->willReturn([
            'on_hand_qty' => '100.000000',
            'reserved_qty' => '0.000000',
            'available_qty' => '100.000000',
            'avg_cost' => null,
        ]);
        $this->connection->method('executeStatement')->willReturn(1);

        // Two items → two transactions
        $this->connection->expects(self::exactly(2))->method('beginTransaction');
        $this->connection->expects(self::exactly(2))->method('commit');

        $this->orchestrator->process($sale, $this->user);
    }
}
