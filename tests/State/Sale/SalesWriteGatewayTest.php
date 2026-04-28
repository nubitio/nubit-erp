<?php

declare(strict_types=1);

namespace App\Tests\State\Sale;

use App\Organization\Entity\Branch;
use App\Catalog\Entity\PaymentMethod;
use App\Sales\Entity\SaleRecord;
use App\Sales\Entity\SaleItem;
use App\Inventory\Entity\Warehouse;
use App\Catalog\Entity\Product;
use App\Catalog\Entity\UnitOfMeasure;
use App\Sales\Repository\DashboardRepository;
use App\Service\SaleCurrencyResolver;
use App\Service\SalePaymentPlanService;
use App\State\Sale\SalesWriteGateway;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(SalesWriteGateway::class)]
class SalesWriteGatewayTest extends TestCase
{
    private Connection&MockObject $connection;
    private SaleCurrencyResolver&MockObject $currencyResolver;
    private SalesWriteGateway $gateway;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->currencyResolver = $this->createMock(SaleCurrencyResolver::class);

        // SalePaymentPlanService is final (no-arg constructor) — instantiate directly
        $paymentPlanService = new SalePaymentPlanService();

        $dashboardRepository = $this->createMock(DashboardRepository::class);

        $this->gateway = new SalesWriteGateway(
            $this->connection,
            $this->currencyResolver,
            $paymentPlanService,
            $dashboardRepository,
        );
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    /**
     * Build a Sale that satisfies all guard checks and works with the real
     * SalePaymentPlanService (uses sale.payments array to avoid querying tipoPago).
     */
    private function buildMinimalSale(string $amount = '100.00'): SaleRecord
    {
        $branch = $this->createMock(Branch::class);
        $branch->method('getId')->willReturn('branch-uuid');

        $warehouse = $this->createMock(Warehouse::class);
        $warehouse->method('getId')->willReturn('warehouse-uuid');
        $warehouse->method('getBranch')->willReturn(null);

        $sale = new SaleRecord();
        $sale->setId('sale-uuid-001');
        $sale->setBranch($branch);
        $sale->setAlmacen($warehouse);
        $sale->setCajaId(10);
        $sale->setCreatedBy(42);
        $sale->setFecha(new \DateTimeImmutable('2024-01-01 10:00:00'));
        $sale->setDateCreated(new \DateTimeImmutable('2024-01-01 10:00:00'));
        $sale->setSubtotal($amount);
        $sale->setTotal($amount);
        $sale->setDescuento(null);
        $sale->setIgv(null);
        // Use explicit payments array so SalePaymentPlanService.resolveForWrite works without tipoPago
        $sale->setPayments([[
            'payment_method_id' => 1,
            'amount' => $amount,
            'tendered_amount' => $amount,
            'change_amount' => null,
        ]]);

        return $sale;
    }

    private function stubCurrencyResolver(): void
    {
        $this->currencyResolver->method('resolveForWrite')
            ->willReturn(['currency' => 'PEN', 'exchange_rate' => '1.000000']);
    }

    /**
     * Create a partial mock of Sale that makes getCreatedBy/getUpdatedBy return null,
     * used for guard tests that need sellerUserId=null without triggering type errors.
     *
     * @return SaleRecord&\PHPUnit\Framework\MockObject\MockObject
     */
    private function buildSaleWithNullSeller(): SaleRecord
    {
        $sale = $this->getMockBuilder(SaleRecord::class)
            ->onlyMethods(['getCreatedBy', 'getUpdatedBy'])
            ->getMock();
        $sale->method('getCreatedBy')->willReturn(null);
        $sale->method('getUpdatedBy')->willReturn(null);

        return $sale;
    }

    // ── write() guard tests ───────────────────────────────────────────────────

    public function testWriteThrowsWhenSaleIdIsNull(): void
    {
        // Partial mock so getCreatedBy() returns null safely (property is int, cannot be null)
        $sale = $this->buildSaleWithNullSeller();
        // $id is null by default

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/sale id is null/');

        $this->gateway->write($sale, null);
    }

    public function testWriteThrowsWhenBranchIsNull(): void
    {
        $warehouse = $this->createMock(Warehouse::class);
        $warehouse->method('getId')->willReturn('wh-1');
        $warehouse->method('getBranch')->willReturn(null);

        $sale = $this->buildSaleWithNullSeller();
        $sale->setId('sale-1');
        $sale->setAlmacen($warehouse);
        // branch is null AND warehouse.branch is null → branchId = null

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/branch is null/');

        $this->gateway->write($sale, null);
    }

    public function testWriteThrowsWhenWarehouseIsNull(): void
    {
        $branch = $this->createMock(Branch::class);
        $branch->method('getId')->willReturn('branch-1');

        $sale = $this->buildSaleWithNullSeller();
        $sale->setId('sale-2');
        $sale->setBranch($branch);
        // almacen is null → warehouseId = null

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/warehouse is null/');

        $this->gateway->write($sale, null);
    }

    public function testWriteThrowsWhenCashRegisterIsNull(): void
    {
        $branch = $this->createMock(Branch::class);
        $branch->method('getId')->willReturn('branch-1');

        $warehouse = $this->createMock(Warehouse::class);
        $warehouse->method('getId')->willReturn('wh-1');

        $sale = $this->buildSaleWithNullSeller();
        $sale->setId('sale-3');
        $sale->setBranch($branch);
        $sale->setAlmacen($warehouse);
        // cajaId is null

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/cash register is null/');

        $this->gateway->write($sale, null);
    }

    public function testWriteThrowsWhenSellerUserIsNull(): void
    {
        $branch = $this->createMock(Branch::class);
        $branch->method('getId')->willReturn('branch-1');

        $warehouse = $this->createMock(Warehouse::class);
        $warehouse->method('getId')->willReturn('wh-1');

        $sale = $this->buildSaleWithNullSeller();
        $sale->setId('sale-4');
        $sale->setBranch($branch);
        $sale->setAlmacen($warehouse);
        $sale->setCajaId(5);
        // getCreatedBy() and getUpdatedBy() both return null → sellerUserId null

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/seller user is null/');

        $this->gateway->write($sale, null);
    }

    // ── write() happy path ────────────────────────────────────────────────────

    public function testWriteHappyPathBeginsTransactionAndCommits(): void
    {
        $sale = $this->buildMinimalSale();
        $this->stubCurrencyResolver();

        $this->connection->expects($this->once())->method('beginTransaction');
        $this->connection->expects($this->once())->method('commit');
        $this->connection->expects($this->never())->method('rollBack');
        $this->connection->method('executeStatement')->willReturn(1);

        $this->gateway->write($sale, 1);
    }

    public function testWriteInsertsDiscountRecordWhenDescuentoIsGreaterThanZero(): void
    {
        $sale = $this->buildMinimalSale('90.00');
        $sale->setDescuento('10.00');
        $sale->setSubtotal('100.00');
        $sale->setTotal('90.00');
        // Adjust payments to match the total
        $sale->setPayments([[
            'payment_method_id' => 1,
            'amount' => '90.00',
            'tendered_amount' => '90.00',
            'change_amount' => null,
        ]]);

        $this->stubCurrencyResolver();

        $this->connection->method('beginTransaction');
        $this->connection->method('commit');

        $callArgs = [];
        $this->connection->method('executeStatement')
            ->willReturnCallback(function (string $sql, array $params = []) use (&$callArgs): int {
                $callArgs[] = ['sql' => $sql, 'params' => $params];
                return 1;
            });

        $this->gateway->write($sale, null);

        $discountInserts = array_filter(
            $callArgs,
            fn ($call) => str_contains($call['sql'], 'sale_discounts') && str_contains($call['sql'], 'INSERT')
        );

        $this->assertNotEmpty($discountInserts, 'Expected a sale_discounts INSERT when descuento > 0');
    }

    public function testWriteInsertsIgvRecordWhenIgvIsGreaterThanZero(): void
    {
        $sale = $this->buildMinimalSale();
        $sale->setIgv('18.00');

        $this->stubCurrencyResolver();

        $this->connection->method('beginTransaction');
        $this->connection->method('commit');

        $callArgs = [];
        $this->connection->method('executeStatement')
            ->willReturnCallback(function (string $sql, array $params = []) use (&$callArgs): int {
                $callArgs[] = ['sql' => $sql, 'params' => $params];
                return 1;
            });

        $this->gateway->write($sale, null);

        $taxInserts = array_filter(
            $callArgs,
            fn ($call) => str_contains($call['sql'], 'sale_taxes') && str_contains($call['sql'], 'INSERT')
        );

        $this->assertNotEmpty($taxInserts, 'Expected a sale_taxes INSERT when igv > 0');
    }

    public function testWriteInsertsRowForEachSaleItem(): void
    {
        $sale = $this->buildMinimalSale();

        $uom = $this->createMock(UnitOfMeasure::class);
        $uom->method('getCode')->willReturn('NIU');

        $product = $this->createMock(Product::class);
        $product->method('getId')->willReturn('product-uuid');
        $product->method('getCode')->willReturn('SKU-001');
        $product->method('getName')->willReturn('Test Product');
        $product->method('getUnitOfMeasure')->willReturn($uom);
        $product->method('getPurchasePrice')->willReturn('50.00');

        $item = new SaleItem();
        $item->setProducto($product);
        $item->setCantidad(2.0);
        $item->setPrecioUnitario('50.00');
        $item->setPrecioTotal('100.00');
        $item->setDescuento('0');
        $sale->addItem($item);

        $this->stubCurrencyResolver();

        $this->connection->method('beginTransaction');
        $this->connection->method('commit');

        $itemInserts = 0;
        $this->connection->method('executeStatement')
            ->willReturnCallback(function (string $sql) use (&$itemInserts): int {
                if (str_contains($sql, 'sale_items') && str_contains($sql, 'INSERT')) {
                    ++$itemInserts;
                }
                return 1;
            });

        $this->gateway->write($sale, null);

        $this->assertSame(1, $itemInserts, 'Expected 1 sale_items INSERT for 1 item');
    }

    public function testWriteInsertsRowForEachPaymentLine(): void
    {
        $sale = $this->buildMinimalSale();
        $this->stubCurrencyResolver();

        $this->connection->method('beginTransaction');
        $this->connection->method('commit');

        $paymentInserts = 0;
        $this->connection->method('executeStatement')
            ->willReturnCallback(function (string $sql) use (&$paymentInserts): int {
                if (str_contains($sql, 'sale_payments') && str_contains($sql, 'INSERT')) {
                    ++$paymentInserts;
                }
                return 1;
            });

        $this->gateway->write($sale, null);

        $this->assertSame(1, $paymentInserts, 'Expected 1 sale_payments INSERT for 1 payment line');
    }

    public function testWriteRollsBackOnException(): void
    {
        $sale = $this->buildMinimalSale();
        $this->stubCurrencyResolver();

        $this->connection->method('beginTransaction');
        $this->connection->method('executeStatement')
            ->willThrowException(new \RuntimeException('DB error'));
        $this->connection->expects($this->once())->method('rollBack');
        $this->connection->expects($this->never())->method('commit');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('DB error');

        $this->gateway->write($sale, null);
    }

    // ── markVoided() ──────────────────────────────────────────────────────────

    public function testMarkVoidedThrowsWhenSaleIdIsNull(): void
    {
        $sale = new SaleRecord();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/sale id is null/');

        $this->gateway->markVoided($sale, 'reason');
    }

    public function testMarkVoidedHappyPath(): void
    {
        $sale = new SaleRecord();
        $sale->setId('sale-void-1');

        $this->connection->expects($this->exactly(2))
            ->method('executeStatement')
            ->willReturn(1);

        $this->gateway->markVoided($sale, 'Customer changed mind');
    }

    public function testMarkVoidedEmptyReasonDefaultsToVoid(): void
    {
        $sale = new SaleRecord();
        $sale->setId('sale-void-2');

        $capturedParams = [];
        $this->connection->method('executeStatement')
            ->willReturnCallback(function (string $sql, array $params = []) use (&$capturedParams): int {
                $capturedParams[] = $params;
                return 1;
            });

        $this->gateway->markVoided($sale, '   ');

        // Second call is the INSERT into sale_discounts with reason_text = 'VOID'
        $this->assertCount(2, $capturedParams);
        $this->assertSame('VOID', $capturedParams[1]['reason_text']);
    }

    // ── writeCashSaleMovement() ───────────────────────────────────────────────

    public function testWriteCashSaleMovementExecutesInsert(): void
    {
        $this->connection->expects($this->once())
            ->method('executeStatement')
            ->with($this->stringContains('cash_movements'))
            ->willReturn(1);

        $this->gateway->writeCashSaleMovement(
            saleId: 'sale-uuid-99',
            cashSessionId: 5,
            createdBy: 1,
            amount: '250.00',
        );
    }
}
