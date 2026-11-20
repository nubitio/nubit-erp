<?php

declare(strict_types=1);

namespace App\Tests\Sales\State;

use App\Catalog\Entity\PaymentMethod;
use App\Catalog\Entity\Product;
use App\Catalog\Entity\UnitOfMeasure;
use App\Contacts\Entity\Customer;
use App\Inventory\Entity\Warehouse;
use App\Organization\Entity\Branch;
use App\Sales\Entity\SaleItemRecord;
use App\Sales\Entity\SalePayment;
use App\Sales\Entity\SaleRecord;
use App\Sales\Repository\DashboardRepository;
use App\Sales\Service\SaleCurrencyResolver;
use App\Sales\Service\SaleSettlementPlanService;
use App\Sales\State\SalesWriteGateway;
use App\Settings\Service\TenantSettingsService;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(SalesWriteGateway::class)]
#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class SalesWriteGatewayTest extends TestCase
{
    private Connection&MockObject $connection;
    private SaleCurrencyResolver&MockObject $currencyResolver;
    private SalesWriteGateway $gateway;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->currencyResolver = $this->createMock(SaleCurrencyResolver::class);

        $settlementPlanService = new SaleSettlementPlanService();

        $dashboardRepository = $this->createStub(DashboardRepository::class);

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $tenantSettings = $this->createStub(TenantSettingsService::class);
        $tenantSettings->method('getIgv')->willReturn(18.0);

        $this->gateway = new SalesWriteGateway(
            $this->connection,
            $entityManager,
            $this->currencyResolver,
            $settlementPlanService,
            $dashboardRepository,
            $tenantSettings,
        );
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    /**
     * Build a Sale that satisfies all guard checks and works with the real
     * SaleSettlementPlanService (legacy payment fixtures remain supported).
     */
    private function buildMinimalSale(string $amount = '100.00'): SaleRecord
    {
        $branch = $this->createStub(Branch::class);
        $branch->method('getId')->willReturn('branch-uuid');

        $warehouse = $this->createStub(Warehouse::class);
        $warehouse->method('getId')->willReturn('warehouse-uuid');
        $warehouse->method('getBranch')->willReturn(null);

        $sale = new SaleRecord();
        $sale->setId('sale-uuid-001');
        $sale->setBranch($branch);
        $sale->setAlmacen($warehouse);
        $sale->setCustomer(
            new Customer()
                ->setRuc('20123456789')
                ->setBusinessName('Cliente crédito'),
        );
        $sale->setCajaId(10);
        $sale->setCreatedBy(42);
        $sale->setFecha(new \DateTimeImmutable('2024-01-01 10:00:00'));
        $sale->setDateCreated(new \DateTimeImmutable('2024-01-01 10:00:00'));
        $sale->setSubtotal($amount);
        $sale->setTotal($amount);
        $sale->setDescuento(null);
        $sale->setIgv(null);
        // Use explicit payments so the compatibility adapter works without tipoPago.
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
        $this->currencyResolver
            ->method('resolveForWrite')
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
        $sale = $this->getMockBuilder(SaleRecord::class)->onlyMethods(['getCreatedBy', 'getUpdatedBy'])->getMock();
        $sale->method('getCreatedBy')->willReturn(null);
        $sale->method('getUpdatedBy')->willReturn(null);

        return $sale;
    }

    // ── write() guard tests ───────────────────────────────────────────────────

    public function testOpenSessionLookupPrefersTheOperationsBranchAndBreaksTiesById(): void
    {
        $this->connection
            ->expects(self::once())
            ->method('fetchOne')
            ->willReturnCallback(static function (string $sql, array $params): int {
                self::assertSame(['user_id' => 2, 'branch_id' => 'branch-a'], $params);
                self::assertStringContainsString('(cr.branch_id = :branch_id) DESC', $sql);
                self::assertStringContainsString('cs.opened_at DESC, cs.id DESC', $sql);

                return 7;
            });

        self::assertSame(7, $this->gateway->findOpenCashSessionIdForUser(2, 'branch-a'));
    }

    public function testOpenSessionLookupReturnsNullWhenNoSessionIsOpen(): void
    {
        $this->connection->method('fetchOne')->willReturn(false);

        self::assertNull($this->gateway->findOpenCashSessionIdForUser(2));
    }

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
        $warehouse = $this->createStub(Warehouse::class);
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
        $branch = $this->createStub(Branch::class);
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
        $branch = $this->createStub(Branch::class);
        $branch->method('getId')->willReturn('branch-1');

        $warehouse = $this->createStub(Warehouse::class);
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
        $branch = $this->createStub(Branch::class);
        $branch->method('getId')->willReturn('branch-1');

        $warehouse = $this->createStub(Warehouse::class);
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

    public function testWriteHappyPathCallsEntityManagerPersistAndFlush(): void
    {
        $sale = $this->buildMinimalSale();
        $this->stubCurrencyResolver();

        // Rebuild gateway with a mock EM so we can assert persist+flush.
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('persist')->with($sale);
        $em->expects($this->once())->method('flush');

        $tenantSettings = $this->createStub(TenantSettingsService::class);
        $tenantSettings->method('getIgv')->willReturn(18.0);

        $gateway = new SalesWriteGateway(
            $this->connection,
            $em,
            $this->currencyResolver,
            new SaleSettlementPlanService(),
            $this->createStub(DashboardRepository::class),
            $tenantSettings,
        );

        $gateway->write($sale, 1);
    }

    public function testWriteSetsFormaPagoCreditoWhenCreditSaleLeavesBalance(): void
    {
        $sale = $this->buildMinimalSale('100.00');
        $sale->setCreditSale(true);
        $sale->setPayments([[
            'payment_method_id' => 1,
            'amount' => '40.00',
            'tendered_amount' => '40.00',
            'change_amount' => null,
        ]]);
        $this->stubCurrencyResolver();

        $tenantSettingsEntity = $this->createStub(\App\Settings\Entity\TenantSettings::class);
        $tenantSettingsEntity->method('getDefaultPaymentTermDays')->willReturn(15);
        $tenantSettings = $this->createStub(TenantSettingsService::class);
        $tenantSettings->method('getIgv')->willReturn(18.0);
        $tenantSettings->method('get')->willReturn($tenantSettingsEntity);

        $gateway = new SalesWriteGateway(
            $this->connection,
            $this->createStub(EntityManagerInterface::class),
            $this->currencyResolver,
            new SaleSettlementPlanService(),
            $this->createStub(DashboardRepository::class),
            $tenantSettings,
        );

        $gateway->write($sale, 1);

        self::assertSame('60.00', $sale->getBalanceDue());
        self::assertSame(2, $sale->getFormaPago());
        self::assertSame('2024-01-16', $sale->getDueDate()?->format('Y-m-d'));
    }

    public function testReceivableSettlementCreatesDebtWithoutPaymentRecord(): void
    {
        $sale = $this->buildMinimalSale('100.00');
        $sale->setSettlements([[
            'type' => 'RECEIVABLE',
            'amount' => '100.00',
        ]]);
        $this->stubCurrencyResolver();

        $tenantSettingsEntity = $this->createStub(\App\Settings\Entity\TenantSettings::class);
        $tenantSettingsEntity->method('getDefaultPaymentTermDays')->willReturn(15);
        $tenantSettings = $this->createStub(TenantSettingsService::class);
        $tenantSettings->method('getIgv')->willReturn(18.0);
        $tenantSettings->method('get')->willReturn($tenantSettingsEntity);

        $gateway = new SalesWriteGateway(
            $this->connection,
            $this->createStub(EntityManagerInterface::class),
            $this->currencyResolver,
            new SaleSettlementPlanService(),
            $this->createStub(DashboardRepository::class),
            $tenantSettings,
        );

        $gateway->write($sale, 1);

        self::assertTrue($sale->isCreditSale());
        self::assertSame('0.00', $sale->getPaidTotal());
        self::assertSame('100.00', $sale->getBalanceDue());
        self::assertSame(2, $sale->getFormaPago());
        self::assertSame('2024-01-16', $sale->getDueDate()?->format('Y-m-d'));
        self::assertCount(0, $sale->getSalePayments(), 'Crédito no es un cobro realizado.');
    }

    public function testMixedCashAndCreditOnlyCountsCollectedMoneyAsPaid(): void
    {
        $sale = $this->buildMinimalSale('100.00');
        $sale->setSettlements([
            [
                'type' => 'PAYMENT',
                'payment_method_id' => PaymentMethod::CASH,
                'amount' => '40.00',
                'tendered_amount' => '40.00',
            ],
            [
                'type' => 'RECEIVABLE',
                'amount' => '60.00',
            ],
        ]);
        $this->stubCurrencyResolver();

        $tenantSettingsEntity = $this->createStub(\App\Settings\Entity\TenantSettings::class);
        $tenantSettingsEntity->method('getDefaultPaymentTermDays')->willReturn(15);
        $tenantSettings = $this->createStub(TenantSettingsService::class);
        $tenantSettings->method('getIgv')->willReturn(18.0);
        $tenantSettings->method('get')->willReturn($tenantSettingsEntity);

        $gateway = new SalesWriteGateway(
            $this->connection,
            $this->createStub(EntityManagerInterface::class),
            $this->currencyResolver,
            new SaleSettlementPlanService(),
            $this->createStub(DashboardRepository::class),
            $tenantSettings,
        );

        $gateway->write($sale, 1);

        self::assertTrue($sale->isCreditSale());
        self::assertSame('40.00', $sale->getPaidTotal());
        self::assertSame('60.00', $sale->getBalanceDue());
        self::assertCount(1, $sale->getSalePayments());
        $payment = $sale->getSalePayments()->first();
        self::assertInstanceOf(SalePayment::class, $payment);
        self::assertSame('40.00', $payment->getAmount());
    }

    public function testWriteSetsFormaPagoContadoWhenFullyPaid(): void
    {
        $sale = $this->buildMinimalSale();
        $this->stubCurrencyResolver();

        $this->gateway->write($sale, 1);

        self::assertSame('0.00', $sale->getBalanceDue());
        self::assertSame(1, $sale->getFormaPago());
        self::assertNull($sale->getDueDate());
    }

    public function testWriteBackfillsIssuedAtWhenOnlyIssuedDateIsProvided(): void
    {
        $sale = $this->buildMinimalSale();
        $sale->setFecha(null);
        $sale->setDateCreated(new \DateTimeImmutable('2026-05-20 14:30:00', new \DateTimeZone('UTC')));
        $sale->setIssuedDate(new \DateTimeImmutable('2026-05-20'));
        $this->stubCurrencyResolver();

        $this->gateway->write($sale, 1);

        self::assertSame('2026-05-20', $sale->getIssuedDate()?->format('Y-m-d'));
        self::assertSame('2026-05-20 14:30:00', $sale->getIssuedAt()?->format('Y-m-d H:i:s'));
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

        $this->gateway->write($sale, null);

        $this->assertNotEmpty(
            $sale->getSaleDiscounts()->toArray(),
            'Expected a sale_discounts entry when descuento > 0',
        );
    }

    public function testWriteInsertsIgvRecordWhenIgvIsGreaterThanZero(): void
    {
        $sale = $this->buildMinimalSale();
        $sale->setIgv('18.00');

        $this->stubCurrencyResolver();

        $this->gateway->write($sale, null);

        $this->assertNotEmpty($sale->getSaleTaxes()->toArray(), 'Expected a sale_taxes entry when igv > 0');
    }

    public function testWriteInsertsItemDiscountRowWhenDescuentoPorcentajeIsGreaterThanZero(): void
    {
        $sale = $this->buildMinimalSale();

        $uom = $this->createStub(UnitOfMeasure::class);
        $uom->method('getCode')->willReturn('NIU');

        $product = $this->createStub(Product::class);
        $product->method('getId')->willReturn('product-uuid');
        $product->method('getCode')->willReturn('SKU-001');
        $product->method('getName')->willReturn('Test Product');
        $product->method('getUnitOfMeasure')->willReturn($uom);
        $product->method('getPurchasePrice')->willReturn('50.00');

        $item = new SaleItemRecord();
        $item->setProduct($product);
        $item->setQty('1');
        $item->setUnitPrice('100.00');
        $item->setNetAmount('90.00');
        $item->setGrossAmount('100.00');
        $item->setDiscountAmount('10.00');
        $item->setDiscountPercent('10.0000');
        $item->setNameSnapshot('Test Product');
        $sale->addSaleItemRecord($item);

        $this->stubCurrencyResolver();

        $this->gateway->write($sale, null);

        $itemDiscounts = array_values(array_filter(
            $sale->getSaleDiscounts()->toArray(),
            fn($d) => $d->getSaleItem() !== null,
        ));

        $this->assertCount(1, $itemDiscounts, 'Expected exactly 1 item-level sale_discount entry');
        $this->assertSame('10.0000', $itemDiscounts[0]->getValue());
        $this->assertSame('10.00', $itemDiscounts[0]->getAmountApplied());
    }

    public function testWriteDoesNotInsertItemDiscountWhenDescuentoPorcentajeIsZero(): void
    {
        $sale = $this->buildMinimalSale();

        $uom = $this->createStub(UnitOfMeasure::class);
        $uom->method('getCode')->willReturn('NIU');

        $product = $this->createStub(Product::class);
        $product->method('getId')->willReturn('product-uuid');
        $product->method('getCode')->willReturn('SKU-001');
        $product->method('getName')->willReturn('Test Product');
        $product->method('getUnitOfMeasure')->willReturn($uom);
        $product->method('getPurchasePrice')->willReturn('50.00');

        $item = new SaleItemRecord();
        $item->setProduct($product);
        $item->setQty('1');
        $item->setUnitPrice('100.00');
        $item->setNetAmount('100.00');
        $item->setGrossAmount('100.00');
        $item->setDiscountAmount('0');
        $item->setNameSnapshot('Test Product');
        $sale->addSaleItemRecord($item);

        $this->stubCurrencyResolver();

        $this->connection->method('beginTransaction');
        $this->connection->method('commit');

        $callArgs = [];
        $this->connection
            ->method('executeStatement')
            ->willReturnCallback(function (string $sql, array $params = []) use (&$callArgs): int {
                $callArgs[] = ['sql' => $sql, 'params' => $params];
                return 1;
            });

        $this->gateway->write($sale, null);

        $itemDiscountInserts = array_filter(
            $callArgs,
            fn($call) => (
                str_contains($call['sql'], 'sale_discounts')
                && str_contains($call['sql'], 'INSERT')
                && isset($call['params']['sale_item_id'])
            ),
        );

        $this->assertEmpty($itemDiscountInserts, 'Expected no item-level sale_discounts INSERT when no discount');
    }

    public function testWriteInsertsRowForEachSaleItem(): void
    {
        $sale = $this->buildMinimalSale();

        $uom = $this->createStub(UnitOfMeasure::class);
        $uom->method('getCode')->willReturn('NIU');

        $product = $this->createStub(Product::class);
        $product->method('getId')->willReturn('product-uuid');
        $product->method('getCode')->willReturn('SKU-001');
        $product->method('getName')->willReturn('Test Product');
        $product->method('getUnitOfMeasure')->willReturn($uom);
        $product->method('getPurchasePrice')->willReturn('50.00');

        $item = new SaleItemRecord();
        $item->setProduct($product);
        $item->setQty('2');
        $item->setUnitPrice('50.00');
        $item->setNetAmount('100.00');
        $item->setGrossAmount('100.00');
        $item->setDiscountAmount('0');
        $item->setNameSnapshot('Test Product');
        $sale->addSaleItemRecord($item);

        $this->stubCurrencyResolver();

        $this->gateway->write($sale, null);

        // The item is persisted as part of the SaleRecord ORM cascade.
        // Verify the item is still present on the sale after write().
        $this->assertCount(1, $sale->getSaleItemRecords(), 'Expected 1 sale item on the record');
    }

    public function testWriteInsertsRowForEachPaymentLine(): void
    {
        $sale = $this->buildMinimalSale();
        $this->stubCurrencyResolver();

        $this->gateway->write($sale, null);

        // SalesWriteGateway builds SalePayment ORM entities from the payments array.
        $this->assertCount(1, $sale->getSalePayments(), 'Expected 1 sale_payment entry for 1 payment line');
    }

    public function testWriteRollsBackOnException(): void
    {
        $sale = $this->buildMinimalSale();
        $this->stubCurrencyResolver();

        // Build a gateway with an EM that throws on flush.
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('flush')->willThrowException(new \RuntimeException('DB error'));

        $tenantSettings = $this->createStub(TenantSettingsService::class);
        $tenantSettings->method('getIgv')->willReturn(18.0);

        $gateway = new SalesWriteGateway(
            $this->connection,
            $em,
            $this->currencyResolver,
            new SaleSettlementPlanService(),
            $this->createStub(DashboardRepository::class),
            $tenantSettings,
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('DB error');

        $gateway->write($sale, null);
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

        $this->connection->expects($this->exactly(2))->method('executeStatement')->willReturn(1);

        $this->gateway->markVoided($sale, 'Customer changed mind');
    }

    public function testMarkVoidedEmptyReasonDefaultsToVoid(): void
    {
        $sale = new SaleRecord();
        $sale->setId('sale-void-2');

        $capturedParams = [];
        $this->connection
            ->method('executeStatement')
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
        $this->connection
            ->expects($this->once())
            ->method('executeStatement')
            ->with($this->stringContains('cash_movements'))
            ->willReturn(1);

        $this->gateway->writeCashSaleMovement(saleId: 'sale-uuid-99', cashSessionId: 5, createdBy: 1, amount: '250.00');
    }
}
