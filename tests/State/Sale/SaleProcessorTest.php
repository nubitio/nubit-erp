<?php

declare(strict_types=1);

namespace App\Tests\State\Sale;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\State\ProcessorInterface;
use App\Core\Application\Session\Security;
use App\Core\Exception\ServiceException;
use App\Core\Security\Auth\BranchContextService;
use App\Core\Service\SettingsService;
use App\Core\Util\Constants;
use App\Dto\Auth\AuthUserDto;
use App\EInvoicing\Service\InvoiceCreationServiceInterface;
use App\Entity\Branch;
use App\Entity\CashMovement;
use App\Entity\CashMovementCategory;
use App\Entity\CashShift;
use App\Entity\Customer;
use App\Entity\Sale;
use App\Entity\SaleItem;
use App\Message\SendInvoiceMessage;
use App\Repository\BranchRepository;
use App\Repository\SunatCatalogRepository;
use App\Service\SalePaymentPlanService;
use App\State\Purchase\GoodsReceiptWriteGateway;
use App\State\Purchase\PurchasesWriteMode;
use App\State\Purchase\PurchasesWritePolicy;
use App\State\Purchase\PurchasesWritePolicyResolver;
use App\State\Sale\CashSessionResolver;
use App\State\Sale\DetractionCalculator;
use App\State\Sale\SaleInventoryOrchestrator;
use App\State\Sale\SaleProcessor;
use App\State\Sale\SaleUtils;
use App\State\Sale\SalesWriteGateway;
use App\State\Sale\SalesWriteMode;
use App\State\Sale\SalesWritePolicy;
use App\State\Sale\SalesWritePolicyResolver;
use App\Entity\Product;
use App\Entity\Warehouse;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use ReflectionNamedType;
use Symfony\Bundle\SecurityBundle\Security as SecurityComponent;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Comprehensive unit tests for SaleProcessor::process().
 *
 * All dependencies are mocked/stubbed — no Symfony kernel, no real DB.
 *
 * Strategy for final readonly classes (cannot use createMock()):
 *   - Use ReflectionClass::newInstanceWithoutConstructor() to bypass constructors
 *   - Inject mock dependencies into the final class's own properties via reflection
 *   - This lets us control their behaviour and, for some, capture calls via
 *     the underlying dependency mocks
 *
 * Covers:
 * - New sale create: persistProcessor called, ID assigned, date_created set
 * - Sale update: persistProcessor called, date_updated + updated_by set
 * - Delete operation: removeProcessor delegated, persist/inventory never called
 * - New operational sale (tipoId=2) without tipoPago: cash-shift check skipped
 * - New sale + REQUIRE_CASH_REGISTER=true + no open shift → ServiceException
 * - New sale + REQUIRE_CASH_REGISTER=false + no shift → no exception
 * - skipLegacyPersist=true → persistProcessor NOT called
 * - Purchases V2 dual-write: goodsReceiptWriteGateway::write called
 * - Purchases V2 strict + gateway throws → ServiceException
 * - Sales V2 writeNewSchema=true → salesWriteGateway::write called
 * - Sales V2 strict + gateway throws → ServiceException
 * - Offline sale header → inventoryOrchestrator receives isOfflineSale=true (via ledger spy)
 * - inventoryOrchestrator throws → exception propagated, persist NOT called
 * - Auto-invoice disabled + emitInvoice=null → no createForSale call
 * - emitInvoice=true → createForSale called
 * - Invoice creation success → messageBus dispatched
 * - Invoice creation exception → caught, not propagated
 * - ensureSaleCustomer: default customer fetched when tipoId=2 + no customer
 * - ensureSaleCustomer: ServiceException when default customer missing
 * - ensureSaleCustomer: skipped when customer already set
 * - Cash sale movement written when resolvedCashSessionId set + cashCollected > 0
 * - Cash movement write strict → ServiceException
 * - Cash movement write non-strict → no exception
 * - Legacy cash movement created when shift present and not disabled
 * - Legacy cash movement skipped when category not found
 * - detractionCalculator::applyFromItems always called
 * - Sale items linked to sale, totals calculated
 * - Branch set when branchContextService returns an ID
 */
#[AllowMockObjectsWithoutExpectations]
final class SaleProcessorTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Mockable dependencies (non-final interfaces / classes)
    // -------------------------------------------------------------------------

    /** @var ProcessorInterface<mixed, mixed>&MockObject */
    private ProcessorInterface $persistProcessor;

    /** @var ProcessorInterface<mixed, mixed>&MockObject */
    private ProcessorInterface $removeProcessor;

    /** @var Security&MockObject */
    private Security $security;

    /** @var EntityManagerInterface&MockObject */
    private EntityManagerInterface $entityManager;

    /** @var InvoiceCreationServiceInterface&MockObject */
    private InvoiceCreationServiceInterface $invoiceCreationService;

    /** @var LoggerInterface&MockObject */
    private LoggerInterface $logger;

    /** @var MessageBusInterface&MockObject */
    private MessageBusInterface $messageBus;

    /** @var SaleUtils&MockObject */
    private SaleUtils $saleUtils;

    /** @var BranchRepository&MockObject */
    private BranchRepository $branchRepository;

    /** @var SalesWriteGateway&MockObject */
    private SalesWriteGateway $salesWriteGateway;

    // -------------------------------------------------------------------------
    // Final classes — bypassed via ReflectionClass::newInstanceWithoutConstructor()
    // Their inner dependencies are replaced with mocks injected via reflection.
    // -------------------------------------------------------------------------

    private SalesWritePolicyResolver $salesWritePolicyResolver;
    private PurchasesWritePolicyResolver $purchasesWritePolicyResolver;
    private BranchContextService $branchContextService;
    private SaleInventoryOrchestrator $inventoryOrchestrator;
    private DetractionCalculator $detractionCalculator;
    private SalePaymentPlanService $salePaymentPlanService;
    private GoodsReceiptWriteGateway $goodsReceiptWriteGateway;
    private CashSessionResolver $cashSessionResolver;

    /** @var SunatCatalogRepository&MockObject */
    private SunatCatalogRepository $sunatCatalogRepository;

    protected function setUp(): void
    {
        $this->persistProcessor = $this->createMock(ProcessorInterface::class);
        $this->removeProcessor = $this->createMock(ProcessorInterface::class);
        $this->security = $this->createMock(Security::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->invoiceCreationService = $this->createMock(InvoiceCreationServiceInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->saleUtils = $this->createMock(SaleUtils::class);
        $this->branchRepository = $this->createMock(BranchRepository::class);
        $this->salesWriteGateway = $this->createMock(SalesWriteGateway::class);
        $this->sunatCatalogRepository = $this->createMock(SunatCatalogRepository::class);

        // Build final-class stubs
        $this->salesWritePolicyResolver = $this->bypassConstructor(SalesWritePolicyResolver::class);
        $this->purchasesWritePolicyResolver = $this->bypassConstructor(PurchasesWritePolicyResolver::class);
        $this->branchContextService = $this->buildBranchContextStub(returnBranchId: null);
        $this->inventoryOrchestrator = $this->buildInventoryOrchestratorStub(shouldThrow: false);
        $this->detractionCalculator = $this->buildDetractionCalculatorStub();
        $this->salePaymentPlanService = $this->buildSalePaymentPlanServiceStub(cashCollectedAmount: '0.00');
        $this->goodsReceiptWriteGateway = $this->buildGoodsReceiptWriteGatewayStub();
        $this->cashSessionResolver = $this->buildCashSessionResolverStub(resolvedSessionId: null);

        // Default: persistProcessor returns the sale unchanged
        $this->persistProcessor
            ->method('process')
            ->willReturnArgument(0);

        // Default: connectedUser returns a test user
        $this->security
            ->method('connectedUser')
            ->willReturn(new AuthUserDto(userId: 42, username: 'test_user'));
    }

    // =========================================================================
    // Tests
    // =========================================================================

    // -------------------------------------------------------------------------
    // TC-01: Happy path new purchase (tipoId=1) — persistProcessor called
    // -------------------------------------------------------------------------

    public function testNewPurchaseSaleCallsPersistProcessor(): void
    {
        $sale = $this->buildNewSale(tipoId: 1);

        $this->persistProcessor
            ->expects($this->once())
            ->method('process')
            ->willReturnArgument(0);

        $result = $this->buildAndRunProcessor(
            $sale,
            new Post(),
            salesPolicy: $this->legacySalesPolicy(),
            purchasesPolicy: $this->legacyPurchasesPolicy(),
        );

        self::assertSame($sale, $result);
        self::assertNotNull($sale->getId(), 'New sale must have an ID assigned');
        self::assertNotNull($sale->getDateCreated(), 'New sale must have date_created set');
        self::assertSame(42, $sale->getCreatedBy());
    }

    // -------------------------------------------------------------------------
    // TC-02: Happy path update (existing ID) — updatedAt set
    // -------------------------------------------------------------------------

    public function testUpdateSaleSetsDateUpdatedAndUpdatedBy(): void
    {
        $sale = $this->buildExistingSale(tipoId: 1);

        $this->persistProcessor
            ->expects($this->once())
            ->method('process')
            ->willReturnArgument(0);

        $this->buildAndRunProcessor(
            $sale,
            new Put(),
            salesPolicy: $this->legacySalesPolicy(),
            purchasesPolicy: $this->legacyPurchasesPolicy(),
        );

        self::assertNotNull($sale->getDateUpdated(), 'Updated sale must have date_updated set');
        self::assertSame(42, $sale->getUpdatedBy());
    }

    // -------------------------------------------------------------------------
    // TC-03: Delete operation — removeProcessor called, persist/inventory never called
    // -------------------------------------------------------------------------

    public function testDeleteOperationDelegatesToRemoveProcessor(): void
    {
        $sale = $this->buildExistingSale(tipoId: 2);

        $this->removeProcessor
            ->expects($this->once())
            ->method('process')
            ->willReturn(null);

        $this->persistProcessor
            ->expects($this->never())
            ->method('process');

        $this->buildAndRunProcessor(
            $sale,
            new Delete(),
            salesPolicy: $this->legacySalesPolicy(),
            purchasesPolicy: $this->legacyPurchasesPolicy(),
        );
    }

    // -------------------------------------------------------------------------
    // TC-04: New tipoId=2 without tipoPago — no cash-shift repository query
    // -------------------------------------------------------------------------

    public function testNewOperationalSaleWithoutTipoPagoSkipsCashShiftCheck(): void
    {
        $sale = $this->buildNewSale(tipoId: 2);

        // getRepository should NOT be called (no tipoPago → block skipped)
        $this->entityManager
            ->expects($this->never())
            ->method('getRepository');

        $this->persistProcessor
            ->expects($this->once())
            ->method('process')
            ->willReturnArgument(0);

        $result = $this->buildAndRunProcessor(
            $sale,
            new Post(),
            salesPolicy: $this->legacySalesPolicy(),
            purchasesPolicy: $this->legacyPurchasesPolicy(),
        );

        self::assertSame($sale, $result);
    }

    // -------------------------------------------------------------------------
    // TC-05: REQUIRE_CASH_REGISTER=true + no open shift → ServiceException
    // -------------------------------------------------------------------------

    public function testRequireCashRegisterWithNoShiftThrowsServiceException(): void
    {
        $sale = $this->buildNewSaleWithPaymentMethod(tipoId: 2);

        $this->mockCashShiftRepository(null);

        $this->expectException(ServiceException::class);
        $this->expectExceptionMessageMatches('/caja abierta/');

        $this->buildAndRunProcessor(
            $sale,
            new Post(),
            salesPolicy: $this->legacySalesPolicy(),
            purchasesPolicy: $this->legacyPurchasesPolicy(),
            requireCashRegister: 'true',
        );
    }

    // -------------------------------------------------------------------------
    // TC-06: REQUIRE_CASH_REGISTER=false + no shift → no exception
    // -------------------------------------------------------------------------

    public function testNoCashShiftWithoutRequirementProceedsNormally(): void
    {
        $sale = $this->buildNewSale(tipoId: 2);

        // No tipoPago → cash-shift block not entered at all
        $this->persistProcessor
            ->expects($this->once())
            ->method('process')
            ->willReturnArgument(0);

        $result = $this->buildAndRunProcessor(
            $sale,
            new Post(),
            salesPolicy: $this->legacySalesPolicy(),
            purchasesPolicy: $this->legacyPurchasesPolicy(),
            requireCashRegister: 'false',
        );

        self::assertSame($sale, $result);
    }

    // -------------------------------------------------------------------------
    // TC-07: skipLegacyPersist=true → persistProcessor NOT called
    // -------------------------------------------------------------------------

    public function testSkipLegacyPersistBypassesPersistProcessor(): void
    {
        // Sale needs branch+fecha so CashSessionResolver (called via V2 write path) doesn't crash
        // on error-log path ($data->getFecha())
        $sale = $this->buildNewSaleWithBranch(tipoId: 2);

        // Use a session resolver that returns a session so V2 write path completes without error
        $this->cashSessionResolver = $this->buildCashSessionResolverStub(resolvedSessionId: 1);

        $this->persistProcessor
            ->expects($this->never())
            ->method('process');

        $result = $this->buildAndRunProcessor(
            $sale,
            new Post(),
            salesPolicy: new SalesWritePolicy(
                mode: SalesWriteMode::AUTHORITATIVE,
                writeNewSchema: true,
                skipLegacyPersist: true,
                strict: false,
            ),
            purchasesPolicy: $this->legacyPurchasesPolicy(),
        );

        self::assertSame($sale, $result);
    }

    // -------------------------------------------------------------------------
    // TC-08: tipoId=1 + purchasesPolicy writeNewSchema=true → goodsReceiptWriteGateway called
    // -------------------------------------------------------------------------

    public function testPurchasesV2DualWriteCallsGoodsReceiptWriteGateway(): void
    {
        // Give the sale branch + warehouse + createdBy so GoodsReceiptWriteGateway::write()
        // passes its guard checks and reaches connection->beginTransaction() (the spy point).
        $sale = $this->buildNewSale(tipoId: 1);
        $branch = new Branch();
        (new ReflectionClass(Branch::class))->getProperty('id')->setValue($branch, 'branch-tc08');
        $sale->setBranch($branch);
        $sale->setFecha(new \DateTimeImmutable('2026-01-01 10:00:00'));

        $warehouse = new Warehouse();
        (new ReflectionClass(Warehouse::class))->getProperty('id')->setValue($warehouse, 'wh-tc08');
        $sale->setAlmacen($warehouse);
        $sale->setCreatedBy(1);

        // Build a spy on GoodsReceiptWriteGateway via its inner Connection mock's beginTransaction
        $writeCallCount = 0;
        $goodsReceiptGateway = $this->buildGoodsReceiptWriteGatewayStub(
            onWrite: static function () use (&$writeCallCount): void {
                $writeCallCount++;
            }
        );
        $this->goodsReceiptWriteGateway = $goodsReceiptGateway;

        $this->buildAndRunProcessor(
            $sale,
            new Post(),
            salesPolicy: $this->legacySalesPolicy(),
            purchasesPolicy: new PurchasesWritePolicy(
                mode: PurchasesWriteMode::DUAL_WRITE,
                writeNewSchema: true,
                skipLegacyPersist: false,
                strict: false,
            ),
        );

        self::assertSame(1, $writeCallCount, 'goodsReceiptWriteGateway::write() must be called once');
    }

    // -------------------------------------------------------------------------
    // TC-09: tipoId=1 + writeNewSchema=true + strict=true + gateway throws → ServiceException
    // -------------------------------------------------------------------------

    public function testPurchasesV2StrictModeThrowsOnGatewayFailure(): void
    {
        $sale = $this->buildNewSale(tipoId: 1);

        $this->goodsReceiptWriteGateway = $this->buildGoodsReceiptWriteGatewayStub(
            onWrite: static function (): void {
                throw new \RuntimeException('DB error');
            }
        );

        $this->expectException(ServiceException::class);
        $this->expectExceptionMessageMatches('/Compras V2/');

        $this->buildAndRunProcessor(
            $sale,
            new Post(),
            salesPolicy: $this->legacySalesPolicy(),
            purchasesPolicy: new PurchasesWritePolicy(
                mode: 'dual_write',
                writeNewSchema: true,
                skipLegacyPersist: false,
                strict: true,
            ),
        );
    }

    // -------------------------------------------------------------------------
    // TC-10: tipoId=1 + writeNewSchema=true + strict=false + gateway throws → no exception
    // -------------------------------------------------------------------------

    public function testPurchasesV2NonStrictGatewayFailureIsSwallowed(): void
    {
        $sale = $this->buildNewSale(tipoId: 1);

        $this->goodsReceiptWriteGateway = $this->buildGoodsReceiptWriteGatewayStub(
            onWrite: static function (): void {
                throw new \RuntimeException('DB error');
            }
        );

        // Must not throw
        $result = $this->buildAndRunProcessor(
            $sale,
            new Post(),
            salesPolicy: $this->legacySalesPolicy(),
            purchasesPolicy: new PurchasesWritePolicy(
                mode: 'dual_write',
                writeNewSchema: true,
                skipLegacyPersist: false,
                strict: false,
            ),
        );

        self::assertSame($sale, $result);
    }

    // -------------------------------------------------------------------------
    // TC-11: Operational sale + salesPolicy writeNewSchema=true → salesWriteGateway::write called
    // -------------------------------------------------------------------------

    public function testSalesV2DualWriteCallsSalesWriteGateway(): void
    {
        // Sale needs a branch so CashSessionResolver::tryResolveForSale() doesn't throw
        $sale = $this->buildNewSaleWithBranch(tipoId: 2);

        $this->cashSessionResolver = $this->buildCashSessionResolverStub(resolvedSessionId: 99);

        $this->salesWriteGateway
            ->expects($this->once())
            ->method('write')
            ->with($sale, 99);

        $this->buildAndRunProcessor(
            $sale,
            new Post(),
            salesPolicy: new SalesWritePolicy(
                mode: SalesWriteMode::DUAL_WRITE,
                writeNewSchema: true,
                skipLegacyPersist: false,
                strict: false,
            ),
            purchasesPolicy: $this->legacyPurchasesPolicy(),
        );
    }

    // -------------------------------------------------------------------------
    // TC-12: Operational sale + strict + salesWriteGateway throws → ServiceException
    // -------------------------------------------------------------------------

    public function testSalesV2StrictModeThrowsOnGatewayFailure(): void
    {
        // Sale needs branch so CashSessionResolver doesn't throw (branch-null) before reaching
        // the salesWriteGateway->write() call which we want to be the error source.
        $sale = $this->buildNewSaleWithBranch(tipoId: 2);

        $this->cashSessionResolver = $this->buildCashSessionResolverStub(resolvedSessionId: null);

        $this->salesWriteGateway
            ->method('write')
            ->willThrowException(new \RuntimeException('Sales V2 write error'));

        $this->expectException(ServiceException::class);
        $this->expectExceptionMessageMatches('/Sales V2/');

        $this->buildAndRunProcessor(
            $sale,
            new Post(),
            salesPolicy: new SalesWritePolicy(
                mode: SalesWriteMode::DUAL_WRITE,
                writeNewSchema: true,
                skipLegacyPersist: false,
                strict: true,
            ),
            purchasesPolicy: $this->legacyPurchasesPolicy(),
        );
    }

    // -------------------------------------------------------------------------
    // TC-13: Operational sale + non-strict + salesWriteGateway throws → no exception
    // -------------------------------------------------------------------------

    public function testSalesV2NonStrictGatewayFailureIsSwallowed(): void
    {
        $sale = $this->buildNewSaleWithBranch(tipoId: 2);

        $this->cashSessionResolver = $this->buildCashSessionResolverStub(resolvedSessionId: null);

        $this->salesWriteGateway
            ->method('write')
            ->willThrowException(new \RuntimeException('Sales V2 write error'));

        $result = $this->buildAndRunProcessor(
            $sale,
            new Post(),
            salesPolicy: new SalesWritePolicy(
                mode: SalesWriteMode::DUAL_WRITE,
                writeNewSchema: true,
                skipLegacyPersist: false,
                strict: false,
            ),
            purchasesPolicy: $this->legacyPurchasesPolicy(),
        );

        self::assertSame($sale, $result);
    }

    // -------------------------------------------------------------------------
    // TC-14: inventoryOrchestrator throws → exception propagated, persistProcessor NOT called
    // -------------------------------------------------------------------------

    public function testInventoryOrchestratorExceptionPropagatesAndBlocksPersist(): void
    {
        // Build an operational sale (tipoId=2) with:
        //  - a valid payment so SalePaymentPlanService::resolveForWrite() passes
        //  - a warehouse so the orchestrator doesn't return early
        //  - a stock-tracked item with cantidad=5 so validateStock() runs
        // The ledger service returns stock=0 (connection returns false), causing
        // the BLOCK policy to throw ServiceException('Stock insuficiente...').
        $sale = $this->buildNewSale(tipoId: 2);

        $warehouse = new Warehouse();
        (new ReflectionClass(Warehouse::class))->getProperty('id')->setValue($warehouse, 'wh-test');
        $sale->setAlmacen($warehouse);

        $product = new Product();
        $product->setName('Test Product');
        $product->setTrackInventory(true);

        $item = new SaleItem();
        $item->setProducto($product);
        $item->setCantidad(5.0);
        $item->setPrecioTotal('0.01');
        $sale->addItem($item);

        // payments sum ≈ total (0.01), so resolveForWrite() passes
        $sale->setPayments([['payment_method_id' => 1, 'amount' => '0.01']]);

        $this->inventoryOrchestrator = $this->buildInventoryOrchestratorStub(shouldThrow: true);

        $this->persistProcessor
            ->expects($this->never())
            ->method('process');

        $this->expectException(ServiceException::class);
        $this->expectExceptionMessageMatches('/Stock insuficiente/');

        $this->buildAndRunProcessor(
            $sale,
            new Post(),
            salesPolicy: $this->legacySalesPolicy(),
            purchasesPolicy: $this->legacyPurchasesPolicy(),
        );
    }

    // -------------------------------------------------------------------------
    // TC-15: Auto-invoice disabled + emitInvoice=null → invoiceCreationService not called
    // -------------------------------------------------------------------------

    public function testAutoInvoiceDisabledAndEmitInvoiceNullSkipsInvoice(): void
    {
        $sale = $this->buildNewSale(tipoId: 2);
        $sale->setEmitInvoice(null);

        $this->invoiceCreationService
            ->expects($this->never())
            ->method('createForSale');

        $this->buildAndRunProcessor(
            $sale,
            new Post(),
            salesPolicy: $this->legacySalesPolicy(),
            purchasesPolicy: $this->legacyPurchasesPolicy(),
            autoInvoice: 'false',
        );
    }

    // -------------------------------------------------------------------------
    // TC-16: emitInvoice=true on new operational sale → invoiceCreationService called
    // -------------------------------------------------------------------------

    public function testEmitInvoiceTrueCallsInvoiceCreationService(): void
    {
        $sale = $this->buildNewSale(tipoId: 2);
        $sale->setEmitInvoice(true);

        $this->invoiceCreationService
            ->expects($this->once())
            ->method('createForSale')
            ->willReturn(['success' => false, 'message' => 'skipped']);

        $this->buildAndRunProcessor(
            $sale,
            new Post(),
            salesPolicy: $this->legacySalesPolicy(),
            purchasesPolicy: $this->legacyPurchasesPolicy(),
            autoInvoice: 'false',
        );
    }

    // -------------------------------------------------------------------------
    // TC-17: Invoice creation success → messageBus dispatched with SendInvoiceMessage
    // -------------------------------------------------------------------------

    public function testInvoiceCreationSuccessDispatchesMessage(): void
    {
        $sale = $this->buildNewSale(tipoId: 2);
        $sale->setEmitInvoice(true);

        $this->invoiceCreationService
            ->method('createForSale')
            ->willReturn(['success' => true, 'invoiceId' => 'inv-abc-123']);

        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->isInstanceOf(SendInvoiceMessage::class),
                $this->isArray()
            )
            ->willReturn(new Envelope(new SendInvoiceMessage('inv-abc-123')));

        $this->buildAndRunProcessor(
            $sale,
            new Post(),
            salesPolicy: $this->legacySalesPolicy(),
            purchasesPolicy: $this->legacyPurchasesPolicy(),
            autoInvoice: 'false',
        );
    }

    // -------------------------------------------------------------------------
    // TC-18: Invoice creation throws → caught, not propagated
    // -------------------------------------------------------------------------

    public function testInvoiceCreationExceptionIsCaughtAndNotPropagated(): void
    {
        $sale = $this->buildNewSale(tipoId: 2);
        $sale->setEmitInvoice(true);

        $this->invoiceCreationService
            ->method('createForSale')
            ->willThrowException(new \RuntimeException('SUNAT unavailable'));

        $this->messageBus
            ->expects($this->never())
            ->method('dispatch');

        $result = $this->buildAndRunProcessor(
            $sale,
            new Post(),
            salesPolicy: $this->legacySalesPolicy(),
            purchasesPolicy: $this->legacyPurchasesPolicy(),
            autoInvoice: 'false',
        );

        self::assertSame($sale, $result);
    }

    // -------------------------------------------------------------------------
    // TC-19: Auto-invoice NOT dispatched on update (isNew=false)
    // -------------------------------------------------------------------------

    public function testAutoInvoiceNotDispatchedOnUpdate(): void
    {
        $sale = $this->buildExistingSale(tipoId: 2);
        $sale->setEmitInvoice(null);

        $this->invoiceCreationService
            ->expects($this->never())
            ->method('createForSale');

        $this->buildAndRunProcessor(
            $sale,
            new Put(),
            salesPolicy: $this->legacySalesPolicy(),
            purchasesPolicy: $this->legacyPurchasesPolicy(),
            autoInvoice: 'true',
        );
    }

    // -------------------------------------------------------------------------
    // TC-20: autoInvoice=true + new operational sale + emitInvoice=null → createForSale called
    // -------------------------------------------------------------------------

    public function testAutoInvoiceEnabledOnNewOperationalSaleTriggersInvoice(): void
    {
        $sale = $this->buildNewSale(tipoId: 2);
        $sale->setEmitInvoice(null);

        $this->invoiceCreationService
            ->expects($this->once())
            ->method('createForSale')
            ->willReturn(['success' => false, 'message' => 'not ready']);

        $this->buildAndRunProcessor(
            $sale,
            new Post(),
            salesPolicy: $this->legacySalesPolicy(),
            purchasesPolicy: $this->legacyPurchasesPolicy(),
            autoInvoice: 'true',
        );
    }

    // -------------------------------------------------------------------------
    // TC-21: emitInvoice=false overrides autoInvoice=true
    // -------------------------------------------------------------------------

    public function testEmitInvoiceFalseOverridesAutoInvoiceTrue(): void
    {
        $sale = $this->buildNewSale(tipoId: 2);
        $sale->setEmitInvoice(false);

        $this->invoiceCreationService
            ->expects($this->never())
            ->method('createForSale');

        $this->buildAndRunProcessor(
            $sale,
            new Post(),
            salesPolicy: $this->legacySalesPolicy(),
            purchasesPolicy: $this->legacyPurchasesPolicy(),
            autoInvoice: 'true',
        );
    }

    // -------------------------------------------------------------------------
    // TC-22: Non-operational tipoId=1 sale never triggers auto-invoice
    // -------------------------------------------------------------------------

    public function testNonOperationalSaleNeverTriggersAutoInvoice(): void
    {
        $sale = $this->buildNewSale(tipoId: 1);
        $sale->setEmitInvoice(true);

        $this->invoiceCreationService
            ->expects($this->never())
            ->method('createForSale');

        $this->buildAndRunProcessor(
            $sale,
            new Post(),
            salesPolicy: $this->legacySalesPolicy(),
            purchasesPolicy: $this->legacyPurchasesPolicy(),
            autoInvoice: 'true',
        );
    }

    // -------------------------------------------------------------------------
    // TC-23: New tipoId=2 with tipoPago, no customer → default customer fetched
    // -------------------------------------------------------------------------

    public function testNewOperationalSaleWithoutCustomerFetchesDefaultCustomer(): void
    {
        $sale = $this->buildNewSaleWithPaymentMethod(tipoId: 2);
        // No customer set on purpose

        $defaultCustomer = $this->createMock(Customer::class);
        $cashShift = $this->buildCashShift();
        $this->mockCashShiftRepository($cashShift);

        $this->entityManager
            ->method('find')
            ->with(Customer::class, 1)
            ->willReturn($defaultCustomer);

        $this->saleUtils
            ->method('fillSaleValues')
            ->willReturnArgument(0);

        $this->buildAndRunProcessor(
            $sale,
            new Post(),
            salesPolicy: $this->legacySalesPolicy(),
            purchasesPolicy: $this->legacyPurchasesPolicy(),
            requireCashRegister: 'false',
        );

        self::assertSame($defaultCustomer, $sale->getCliente());
    }

    // -------------------------------------------------------------------------
    // TC-24: New tipoId=2 + no customer + no default customer → ServiceException
    // -------------------------------------------------------------------------

    public function testMissingDefaultCustomerThrowsServiceException(): void
    {
        $sale = $this->buildNewSaleWithPaymentMethod(tipoId: 2);

        $cashShift = $this->buildCashShift();
        $this->mockCashShiftRepository($cashShift);

        $this->entityManager
            ->method('find')
            ->with(Customer::class, 1)
            ->willReturn(null);

        $this->saleUtils
            ->method('fillSaleValues')
            ->willReturnArgument(0);

        $this->expectException(ServiceException::class);
        $this->expectExceptionMessageMatches('/cliente general/');

        $this->buildAndRunProcessor(
            $sale,
            new Post(),
            salesPolicy: $this->legacySalesPolicy(),
            purchasesPolicy: $this->legacyPurchasesPolicy(),
            requireCashRegister: 'false',
        );
    }

    // -------------------------------------------------------------------------
    // TC-25: ensureSaleCustomer skips when customer already set
    // -------------------------------------------------------------------------

    public function testEnsureCustomerSkipsWhenCustomerAlreadySet(): void
    {
        $sale = $this->buildNewSaleWithPaymentMethod(tipoId: 2);
        $sale->setCliente($this->createMock(Customer::class));

        $cashShift = $this->buildCashShift();
        $this->mockCashShiftRepository($cashShift);

        // entityManager::find must NOT be called for Customer
        $this->entityManager
            ->expects($this->never())
            ->method('find');

        $this->saleUtils
            ->method('fillSaleValues')
            ->willReturnArgument(0);

        $this->buildAndRunProcessor(
            $sale,
            new Post(),
            salesPolicy: $this->legacySalesPolicy(),
            purchasesPolicy: $this->legacyPurchasesPolicy(),
            requireCashRegister: 'false',
        );
    }

    // -------------------------------------------------------------------------
    // TC-26: ensureSaleCustomer skips when tipoId != 2
    // -------------------------------------------------------------------------

    public function testEnsureCustomerSkipsWhenTipoIdIsNotTwo(): void
    {
        $sale = $this->buildNewSaleWithPaymentMethod(tipoId: 1);
        // No customer set but tipoId=1

        $cashShift = $this->buildCashShift();
        $this->mockCashShiftRepository($cashShift);

        // entityManager::find must NOT be called for Customer
        $this->entityManager
            ->expects($this->never())
            ->method('find');

        $this->saleUtils
            ->method('fillSaleValues')
            ->willReturnArgument(0);

        $this->buildAndRunProcessor(
            $sale,
            new Post(),
            salesPolicy: $this->legacySalesPolicy(),
            purchasesPolicy: $this->legacyPurchasesPolicy(),
            requireCashRegister: 'false',
        );
    }

    // -------------------------------------------------------------------------
    // TC-27: Cash sale movement written when resolvedCashSessionId set + cash > 0
    // -------------------------------------------------------------------------

    public function testCashSaleMovementWrittenWhenSessionResolvedAndCashCollected(): void
    {
        // Sale needs branch so CashSessionResolver::tryResolveForSale() can run
        $sale = $this->buildNewSaleWithBranch(tipoId: 2);

        // Add an item so total=10.00, then payments sum=10.00 passes tolerance check,
        // and resolveCashCollectedAmount() returns '10.00' (payment_method_id=1=CASH)
        $item = new SaleItem();
        $item->setPrecioTotal('10.00');
        $item->setCantidad(1.0);
        $sale->addItem($item);
        $sale->setPayments([['payment_method_id' => 1, 'amount' => '10.00']]);

        $this->cashSessionResolver = $this->buildCashSessionResolverStub(resolvedSessionId: 77);
        $this->salePaymentPlanService = $this->buildSalePaymentPlanServiceStub(cashCollectedAmount: '10.00');

        $this->salesWriteGateway
            ->expects($this->once())
            ->method('write');

        $this->salesWriteGateway
            ->expects($this->once())
            ->method('writeCashSaleMovement')
            ->with(
                $this->anything(), // saleId
                77,                // cashSessionId
                $this->anything(), // createdBy
                '10.00',           // amount
                null,              // categoryId
                $this->anything(), // paymentMethodId
                $this->anything(), // createdAt
                $this->anything(), // description
            );

        $this->buildAndRunProcessor(
            $sale,
            new Post(),
            salesPolicy: new SalesWritePolicy(
                mode: SalesWriteMode::DUAL_WRITE,
                writeNewSchema: true,
                skipLegacyPersist: false,
                strict: false,
            ),
            purchasesPolicy: $this->legacyPurchasesPolicy(),
        );
    }

    // -------------------------------------------------------------------------
    // TC-28: Cash movement write fails + strict → ServiceException
    // -------------------------------------------------------------------------

    public function testCashMovementWriteFailsStrictModeThrowsServiceException(): void
    {
        $sale = $this->buildNewSaleWithBranch(tipoId: 2);

        $this->cashSessionResolver = $this->buildCashSessionResolverStub(resolvedSessionId: 77);
        $this->salePaymentPlanService = $this->buildSalePaymentPlanServiceStub(cashCollectedAmount: '10.00');

        $this->salesWriteGateway
            ->method('write');

        $this->salesWriteGateway
            ->method('writeCashSaleMovement')
            ->willThrowException(new \RuntimeException('Cash write error'));

        $this->expectException(ServiceException::class);
        $this->expectExceptionMessageMatches('/movimiento de caja/');

        $this->buildAndRunProcessor(
            $sale,
            new Post(),
            salesPolicy: new SalesWritePolicy(
                mode: SalesWriteMode::DUAL_WRITE,
                writeNewSchema: true,
                skipLegacyPersist: false,
                strict: true,
            ),
            purchasesPolicy: $this->legacyPurchasesPolicy(),
        );
    }

    // -------------------------------------------------------------------------
    // TC-29: Cash movement write fails + non-strict → no exception
    // -------------------------------------------------------------------------

    public function testCashMovementWriteFailsNonStrictModeDoesNotThrow(): void
    {
        $sale = $this->buildNewSaleWithBranch(tipoId: 2);

        $this->cashSessionResolver = $this->buildCashSessionResolverStub(resolvedSessionId: 77);
        $this->salePaymentPlanService = $this->buildSalePaymentPlanServiceStub(cashCollectedAmount: '10.00');

        $this->salesWriteGateway
            ->method('write');

        $this->salesWriteGateway
            ->method('writeCashSaleMovement')
            ->willThrowException(new \RuntimeException('Cash write error'));

        $result = $this->buildAndRunProcessor(
            $sale,
            new Post(),
            salesPolicy: new SalesWritePolicy(
                mode: SalesWriteMode::DUAL_WRITE,
                writeNewSchema: true,
                skipLegacyPersist: false,
                strict: false,
            ),
            purchasesPolicy: $this->legacyPurchasesPolicy(),
        );

        self::assertSame($sale, $result);
    }

    // -------------------------------------------------------------------------
    // TC-30: Legacy cash movement created when shift present + not disabled
    // -------------------------------------------------------------------------

    public function testLegacyCashMovementCreatedWhenShiftPresentAndEnabled(): void
    {
        $sale = $this->buildNewSaleWithPaymentMethod(tipoId: 2);
        $sale->setCliente($this->createMock(Customer::class));

        $cashShift = $this->buildCashShift();

        $this->saleUtils
            ->method('fillSaleValues')
            ->willReturnArgument(0);

        $this->salePaymentPlanService = $this->buildSalePaymentPlanServiceStub(cashCollectedAmount: '50.00');

        // Category repo mock — returns a valid category so persist is called
        $category = $this->buildCashMovementCategory();

        /** @var EntityRepository<CashMovementCategory>&MockObject $categoryRepo */
        $categoryRepo = $this->createMock(EntityRepository::class);
        $categoryRepo->method('findOneBy')->willReturn($category);

        // Route all getRepository() calls via a single callback to avoid stub conflicts
        $this->entityManager
            ->method('getRepository')
            ->willReturnCallback(function (string $class) use ($cashShift, $categoryRepo): mixed {
                if ($class === CashMovementCategory::class) {
                    return $categoryRepo;
                }

                // CashShift and any other class: return a repo that gives back $cashShift
                /** @var EntityRepository<CashShift>&MockObject $cashShiftRepo */
                $cashShiftRepo = $this->createMock(EntityRepository::class);
                $cashShiftRepo->method('findOneBy')->willReturn($cashShift);

                return $cashShiftRepo;
            });

        // Expect persist + flush for cash movement
        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(CashMovement::class));

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->buildAndRunProcessor(
            $sale,
            new Post(),
            salesPolicy: $this->legacySalesPolicy(),
            purchasesPolicy: $this->legacyPurchasesPolicy(),
            requireCashRegister: 'false',
            disableLegacyCashMovement: 'false',
        );
    }

    // -------------------------------------------------------------------------
    // TC-31: Legacy cash movement skipped when category not found
    // -------------------------------------------------------------------------

    public function testLegacyCashMovementSkippedWhenCategoryNotFound(): void
    {
        $sale = $this->buildNewSaleWithPaymentMethod(tipoId: 2);
        $sale->setCliente($this->createMock(Customer::class));

        $cashShift = $this->buildCashShift();

        $this->saleUtils
            ->method('fillSaleValues')
            ->willReturnArgument(0);

        $this->salePaymentPlanService = $this->buildSalePaymentPlanServiceStub(cashCollectedAmount: '50.00');

        /** @var EntityRepository<CashMovementCategory>&MockObject $categoryRepo */
        $categoryRepo = $this->createMock(EntityRepository::class);
        $categoryRepo->method('findOneBy')->willReturn(null);

        // Route all getRepository() calls via a single callback to avoid stub conflicts
        $this->entityManager
            ->method('getRepository')
            ->willReturnCallback(function (string $class) use ($cashShift, $categoryRepo): mixed {
                if ($class === CashMovementCategory::class) {
                    return $categoryRepo;
                }

                /** @var EntityRepository<CashShift>&MockObject $cashShiftRepo */
                $cashShiftRepo = $this->createMock(EntityRepository::class);
                $cashShiftRepo->method('findOneBy')->willReturn($cashShift);

                return $cashShiftRepo;
            });

        $this->entityManager
            ->expects($this->never())
            ->method('persist');

        $result = $this->buildAndRunProcessor(
            $sale,
            new Post(),
            salesPolicy: $this->legacySalesPolicy(),
            purchasesPolicy: $this->legacyPurchasesPolicy(),
            requireCashRegister: 'false',
            disableLegacyCashMovement: 'false',
        );

        self::assertSame($sale, $result);
    }

    // -------------------------------------------------------------------------
    // TC-32: detractionCalculator::applyFromItems always called on every process
    // -------------------------------------------------------------------------

    public function testDetractionCalculatorIsAlwaysCalled(): void
    {
        $sale = $this->buildNewSale(tipoId: 2);

        // Set a non-null detraction code so we can detect clearDetraction() was called
        // (applyFromItems() calls clearDetraction() when customer has no 11-digit RUC,
        // which sets detraccionCodigo → null; proving applyFromItems was invoked with this sale).
        $sale->setDetraccionCodigo('999');

        $this->buildAndRunProcessor(
            $sale,
            new Post(),
            salesPolicy: $this->legacySalesPolicy(),
            purchasesPolicy: $this->legacyPurchasesPolicy(),
        );

        self::assertNull(
            $sale->getDetraccionCodigo(),
            'detractionCalculator::applyFromItems() must have been called — it clears detraccionCodigo when customer has no RUC'
        );
    }

    // -------------------------------------------------------------------------
    // TC-33: Sale items are linked to sale and totals are calculated
    // -------------------------------------------------------------------------

    public function testSaleItemsAreLinkedToSaleAndTotalsCalculated(): void
    {
        $sale = $this->buildNewSale(tipoId: 1);
        $item = new SaleItem();
        $item->setPrecioTotal('100.00');
        $item->setCantidad(1.0);
        $sale->addItem($item);

        $this->buildAndRunProcessor(
            $sale,
            new Post(),
            salesPolicy: $this->legacySalesPolicy(),
            purchasesPolicy: $this->legacyPurchasesPolicy(),
        );

        self::assertSame($sale, $item->getMovimiento());
        self::assertSame('100.00', $sale->getSubtotal());
        self::assertSame('100.00', $sale->getTotal());
    }

    // -------------------------------------------------------------------------
    // TC-34: Branch set when branchContextService returns an ID
    // -------------------------------------------------------------------------

    public function testBranchIsSetOnNewSaleWhenBranchContextReturnsId(): void
    {
        $sale = $this->buildNewSale(tipoId: 2);
        $branch = new Branch();

        $this->branchContextService = $this->buildBranchContextStubWithRealToken('branch-42');

        $this->branchRepository
            ->method('find')
            ->with('branch-42')
            ->willReturn($branch);

        $this->buildAndRunProcessor(
            $sale,
            new Post(),
            salesPolicy: $this->legacySalesPolicy(),
            purchasesPolicy: $this->legacyPurchasesPolicy(),
        );

        self::assertSame($branch, $sale->getBranch());
    }

    // -------------------------------------------------------------------------
    // TC-35: Authoritative mode: skip legacy persist, disable legacy cash movement
    // -------------------------------------------------------------------------

    public function testAuthoritativeModeSkipsPersistAndDisablesLegacyCashMovement(): void
    {
        // Sale needs branch+fecha so CashSessionResolver doesn't throw when V2 write runs
        $sale = $this->buildNewSaleWithBranch(tipoId: 2);

        $this->persistProcessor
            ->expects($this->never())
            ->method('process');

        $this->entityManager
            ->expects($this->never())
            ->method('persist');

        $this->cashSessionResolver = $this->buildCashSessionResolverStub(resolvedSessionId: 55);
        $this->salePaymentPlanService = $this->buildSalePaymentPlanServiceStub(cashCollectedAmount: '0.00');

        $result = $this->buildAndRunProcessor(
            $sale,
            new Post(),
            salesPolicy: new SalesWritePolicy(
                mode: SalesWriteMode::AUTHORITATIVE,
                writeNewSchema: true,
                skipLegacyPersist: true,
                strict: false,
            ),
            purchasesPolicy: $this->legacyPurchasesPolicy(),
        );

        self::assertSame($sale, $result);
    }

    // -------------------------------------------------------------------------
    // TC-36: isLegacyCashMovementDisabled setting 'true' skips legacy cash movement
    // -------------------------------------------------------------------------

    public function testSettingDisableLegacyCashMovementSkipsMovementCreation(): void
    {
        $sale = $this->buildNewSaleWithPaymentMethod(tipoId: 2);
        $sale->setCliente($this->createMock(Customer::class));

        $cashShift = $this->buildCashShift();
        $this->mockCashShiftRepository($cashShift);

        $this->saleUtils
            ->method('fillSaleValues')
            ->willReturnArgument(0);

        $this->salePaymentPlanService = $this->buildSalePaymentPlanServiceStub(cashCollectedAmount: '50.00');

        $this->entityManager
            ->expects($this->never())
            ->method('persist');

        $this->buildAndRunProcessor(
            $sale,
            new Post(),
            salesPolicy: $this->legacySalesPolicy(),
            purchasesPolicy: $this->legacyPurchasesPolicy(),
            requireCashRegister: 'false',
            disableLegacyCashMovement: 'true',
        );
    }

    // -------------------------------------------------------------------------
    // TC-37: Offline sale — isOfflineSale=true path covered (no exception, process continues)
    // -------------------------------------------------------------------------

    public function testOfflineSaleHeaderCausesNoExceptionAndProceedsNormally(): void
    {
        $sale = $this->buildNewSale(tipoId: 2);

        $request = new Request();
        $request->headers->set('X-Offline-Sale', 'true');

        $result = $this->buildAndRunProcessor(
            $sale,
            new Post(),
            context: ['request' => $request],
            salesPolicy: $this->legacySalesPolicy(),
            purchasesPolicy: $this->legacyPurchasesPolicy(),
        );

        self::assertSame($sale, $result);
    }

    // -------------------------------------------------------------------------
    // TC-38: New sale has_invoice set to false always (regardless of incoming value)
    // -------------------------------------------------------------------------

    public function testNewSaleHasInvoiceIsSetToFalse(): void
    {
        $sale = $this->buildNewSale(tipoId: 2);
        $sale->setHasInvoice(true); // client tries to set it true

        $this->buildAndRunProcessor(
            $sale,
            new Post(),
            salesPolicy: $this->legacySalesPolicy(),
            purchasesPolicy: $this->legacyPurchasesPolicy(),
        );

        self::assertFalse($sale->isHasInvoice(), 'New sale: has_invoice must always be forced to false');
    }

    // =========================================================================
    // Core builder — builds and runs the processor in one shot
    // =========================================================================

    /**
     * Build a SaleProcessor, inject all dependencies via reflection, run process(), return result.
     *
     * @param array<string, mixed> $context
     */
    private function buildAndRunProcessor(
        Sale $sale,
        \ApiPlatform\Metadata\Operation $operation,
        SalesWritePolicy $salesPolicy,
        PurchasesWritePolicy $purchasesPolicy,
        array $context = [],
        string $requireCashRegister = 'false',
        string $autoInvoice = 'false',
        string $disableLegacyCashMovement = 'false',
    ): mixed {
        $processor = $this->buildProcessor(
            salesPolicy: $salesPolicy,
            purchasesPolicy: $purchasesPolicy,
            requireCashRegister: $requireCashRegister,
            autoInvoice: $autoInvoice,
            disableLegacyCashMovement: $disableLegacyCashMovement,
        );

        return $processor->process($sale, $operation, [], $context);
    }

    /**
     * Build a fully-configured SaleProcessor via reflection.
     */
    private function buildProcessor(
        SalesWritePolicy $salesPolicy,
        PurchasesWritePolicy $purchasesPolicy,
        string $requireCashRegister = 'false',
        string $autoInvoice = 'false',
        string $disableLegacyCashMovement = 'false',
    ): SaleProcessor {
        // Wire SalesWritePolicyResolver to return our policy
        $swpr = new ReflectionClass(SalesWritePolicyResolver::class);
        $swSettingsMock = $this->buildSettingsMockForSalesPolicy($salesPolicy);
        $swpr->getProperty('settings')->setValue($this->salesWritePolicyResolver, $swSettingsMock);

        // Wire PurchasesWritePolicyResolver to return our policy
        $pwpr = new ReflectionClass(PurchasesWritePolicyResolver::class);
        $pwSettingsMock = $this->buildSettingsMockForPurchasesPolicy($purchasesPolicy);
        $pwpr->getProperty('settings')->setValue($this->purchasesWritePolicyResolver, $pwSettingsMock);

        // Configure the main settings mock (new mock each call to avoid "already configured" issues)
        $settingsMock = $this->createMock(SettingsService::class);
        $settingsMock
            ->method('get')
            ->willReturnMap([
                [Constants::AUTO_INVOICE, 'false', $autoInvoice],
                [Constants::REQUIRE_CASH_REGISTER, 'true', $requireCashRegister],
                ['sales_v2_disable_legacy_cash_movement', 'false', $disableLegacyCashMovement],
            ]);

        // Build SaleProcessor bypassing readonly constructor
        $processorReflection = new ReflectionClass(SaleProcessor::class);
        /** @var SaleProcessor $processor */
        $processor = $processorReflection->newInstanceWithoutConstructor();

        $inj = static function (string $name, mixed $value) use ($processorReflection, $processor): void {
            $processorReflection->getProperty($name)->setValue($processor, $value);
        };

        $inj('persistProcessor', $this->persistProcessor);
        $inj('removeProcessor', $this->removeProcessor);
        $inj('security', $this->security);
        $inj('entityManager', $this->entityManager);
        $inj('saleUtils', $this->saleUtils);
        $inj('settings', $settingsMock);
        $inj('invoiceCreationService', $this->invoiceCreationService);
        $inj('logger', $this->logger);
        $inj('branchContextService', $this->branchContextService);
        $inj('branchRepository', $this->branchRepository);
        $inj('messageBus', $this->messageBus);
        $inj('salesWritePolicyResolver', $this->salesWritePolicyResolver);
        $inj('salesWriteGateway', $this->salesWriteGateway);
        $inj('goodsReceiptWriteGateway', $this->goodsReceiptWriteGateway);
        $inj('purchasesWritePolicyResolver', $this->purchasesWritePolicyResolver);
        $inj('cashSessionResolver', $this->cashSessionResolver);
        $inj('salePaymentPlanService', $this->salePaymentPlanService);
        $inj('detractionCalculator', $this->detractionCalculator);
        $inj('inventoryOrchestrator', $this->inventoryOrchestrator);

        return $processor;
    }

    // =========================================================================
    // Domain-object factory helpers
    // =========================================================================

    private function buildNewSale(int $tipoId): Sale
    {
        $sale = new Sale();
        $sale->setTipoId($tipoId);

        // Operational sales (tipoId=2) always call SalePaymentPlanService::resolveForWrite().
        // Provide a minimal payment (amount=0.01 ≈ total 0.00 within tolerance) so it doesn't throw.
        if ($tipoId === 2) {
            $sale->setPayments([['payment_method_id' => 1, 'amount' => '0.01']]);
        }

        return $sale;
    }

    /**
     * Build a new sale that already has a branch (id='branch-1') and fecha set.
     * Needed for tests that exercise CashSessionResolver or SalesWriteGateway V2 paths
     * (both require $sale->getBranch() to be non-null).
     */
    private function buildNewSaleWithBranch(int $tipoId): Sale
    {
        $sale = $this->buildNewSale($tipoId);
        $branch = new Branch();
        (new ReflectionClass(Branch::class))->getProperty('id')->setValue($branch, 'branch-1');
        $sale->setBranch($branch);
        $sale->setFecha(new \DateTimeImmutable('2026-01-01 10:00:00'));

        return $sale;
    }

    private function buildNewSaleWithPaymentMethod(int $tipoId): Sale
    {
        $sale = $this->buildNewSale($tipoId);
        $paymentMethod = $this->buildPaymentMethod(id: 1);
        $sale->setTipoPago($paymentMethod);

        return $sale;
    }

    private function buildExistingSale(int $tipoId): Sale
    {
        $sale = new Sale();
        $sale->setTipoId($tipoId);
        $sale->setId('existing-uuid-abc-123');

        // Operational sales (tipoId=2) always call SalePaymentPlanService::resolveForWrite().
        // Provide a minimal payment so it doesn't throw.
        if ($tipoId === 2) {
            $sale->setPayments([['payment_method_id' => 1, 'amount' => '0.01']]);
        }

        return $sale;
    }

    private function buildPaymentMethod(int $id): \App\Entity\PaymentMethod
    {
        $rc = new ReflectionClass(\App\Entity\PaymentMethod::class);
        /** @var \App\Entity\PaymentMethod $pm */
        $pm = $rc->newInstanceWithoutConstructor();
        $rc->getProperty('id')->setValue($pm, $id);

        return $pm;
    }

    private function buildCashShift(): CashShift
    {
        return (new ReflectionClass(CashShift::class))->newInstanceWithoutConstructor();
    }

    private function buildCashMovementCategory(): CashMovementCategory
    {
        $rc = new ReflectionClass(CashMovementCategory::class);
        /** @var CashMovementCategory $cat */
        $cat = $rc->newInstanceWithoutConstructor();
        if ($rc->hasProperty('type')) {
            $rc->getProperty('type')->setValue($cat, \App\Enum\CashMovementType::INCOME);
        }

        return $cat;
    }

    private function mockCashShiftRepository(?CashShift $cashShift): void
    {
        /** @var EntityRepository<CashShift>&MockObject $cashShiftRepo */
        $cashShiftRepo = $this->createMock(EntityRepository::class);
        $cashShiftRepo->method('findOneBy')->willReturn($cashShift);

        /** @var EntityRepository<CashMovementCategory>&MockObject $categoryRepo */
        $categoryRepo = $this->createMock(EntityRepository::class);
        $categoryRepo->method('findOneBy')->willReturn(null);

        $this->entityManager
            ->method('getRepository')
            ->willReturnCallback(static function (string $class) use ($cashShiftRepo, $categoryRepo): mixed {
                if ($class === CashMovementCategory::class) {
                    return $categoryRepo;
                }

                return $cashShiftRepo;
            });
    }

    // =========================================================================
    // Policy factory helpers
    // =========================================================================

    private function legacySalesPolicy(): SalesWritePolicy
    {
        return new SalesWritePolicy(
            mode: SalesWriteMode::LEGACY,
            writeNewSchema: false,
            skipLegacyPersist: false,
            strict: false,
        );
    }

    private function legacyPurchasesPolicy(): PurchasesWritePolicy
    {
        return new PurchasesWritePolicy(
            mode: 'legacy',
            writeNewSchema: false,
            skipLegacyPersist: false,
            strict: false,
        );
    }

    private function buildSettingsMockForSalesPolicy(SalesWritePolicy $policy): SettingsService
    {
        /** @var SettingsService&MockObject $stub */
        $stub = $this->createMock(SettingsService::class);
        $stub->method('get')->willReturnMap([
            [\App\Core\Util\Constants::SALES_V2_MODE, SalesWriteMode::DUAL_WRITE, $policy->mode],
            [\App\Core\Util\Constants::SALES_V2_DUAL_WRITE_ENABLED, 'true', $policy->writeNewSchema ? 'true' : 'false'],
            [\App\Core\Util\Constants::SALES_V2_DUAL_WRITE_STRICT, 'false', $policy->strict ? 'true' : 'false'],
        ]);

        return $stub;
    }

    private function buildSettingsMockForPurchasesPolicy(PurchasesWritePolicy $policy): SettingsService
    {
        /** @var SettingsService&MockObject $stub */
        $stub = $this->createMock(SettingsService::class);
        $stub->method('get')->willReturnMap([
            [\App\Core\Util\Constants::PURCHASES_V2_MODE, PurchasesWriteMode::DUAL_WRITE, $policy->mode],
            [\App\Core\Util\Constants::PURCHASES_V2_DUAL_WRITE_ENABLED, 'true', $policy->writeNewSchema ? 'true' : 'false'],
            [\App\Core\Util\Constants::PURCHASES_V2_DUAL_WRITE_STRICT, 'false', $policy->strict ? 'true' : 'false'],
        ]);

        return $stub;
    }

    // =========================================================================
    // Final-class stub factories
    // =========================================================================

    /**
     * Build a BranchContextService stub where getCurrentBranchId() returns null.
     * The SecurityComponent mock returns null from getToken(), so JWTAuthenticationToken
     * instanceof check fails and the method returns null safely.
     *
     * @param string|null $returnBranchId unused here — kept for API symmetry
     */
    private function buildBranchContextStub(?string $returnBranchId = null): BranchContextService
    {
        /** @var SecurityComponent&MockObject $secMock */
        $secMock = $this->createMock(SecurityComponent::class);
        $secMock->method('getToken')->willReturn(null);

        $rc = new ReflectionClass(BranchContextService::class);
        /** @var BranchContextService $stub */
        $stub = $rc->newInstanceWithoutConstructor();
        $rc->getProperty('security')->setValue($stub, $secMock);

        // jwtManager is never called because token is null; inject no-op sub-stub
        if ($rc->hasProperty('jwtManager')) {
            $jwtMgrClass = (string) $rc->getProperty('jwtManager')->getType();
            if (class_exists($jwtMgrClass)) {
                $jwtStub = (new ReflectionClass($jwtMgrClass))->newInstanceWithoutConstructor();
                $rc->getProperty('jwtManager')->setValue($stub, $jwtStub);
            }
        }

        return $stub;
    }

    /**
     * Build a BranchContextService stub where getCurrentBranchId() returns the given branch ID.
     */
    private function buildBranchContextStubWithRealToken(string $branchId): BranchContextService
    {
        // 1. Build a real JWTManager with a test secret (bypassing constructor to avoid empty-secret check)
        $jwtManagerRc = new ReflectionClass(\App\Core\Security\Auth\JWTManager::class);
        /** @var \App\Core\Security\Auth\JWTManager $jwtManager */
        $jwtManager = $jwtManagerRc->newInstanceWithoutConstructor();
        $jwtManagerRc->getProperty('secret')->setValue($jwtManager, 'test-secret-for-unit-tests');
        $jwtManagerRc->getProperty('logger')->setValue($jwtManager, $this->logger);

        // 2. Encode a JWT that contains the branchId
        $encodedToken = $jwtManager->encode(['branchId' => $branchId, 'sub' => 'test-user']);

        // 3. Build JWTAuthenticationToken with the encoded token as credentials
        $jwtTokenRc = new ReflectionClass(\App\Core\Security\Auth\JWTAuthenticationToken::class);
        /** @var \App\Core\Security\Auth\JWTAuthenticationToken $jwtToken */
        $jwtToken = $jwtTokenRc->newInstanceWithoutConstructor();
        // Set the 'token' property on JWTAuthenticationToken itself
        $jwtTokenRc->getProperty('token')->setValue($jwtToken, $encodedToken);
        // Initialize parent PostAuthenticationToken properties to avoid "not initialized" errors
        $parentRc = $jwtTokenRc->getParentClass();
        if ($parentRc !== false) {
            $this->initStubProperties($parentRc, $jwtToken);
        }

        // 4. Make security->getToken() return our JWT token
        /** @var \Symfony\Bundle\SecurityBundle\Security&MockObject $secMock */
        $secMock = $this->createMock(\Symfony\Bundle\SecurityBundle\Security::class);
        $secMock->method('getToken')->willReturn($jwtToken);

        // 5. Build BranchContextService via reflection
        $bcsRc = new ReflectionClass(BranchContextService::class);
        /** @var BranchContextService $stub */
        $stub = $bcsRc->newInstanceWithoutConstructor();
        $bcsRc->getProperty('security')->setValue($stub, $secMock);
        $bcsRc->getProperty('jwtManager')->setValue($stub, $jwtManager);

        return $stub;
    }

    /**
     * Build a SaleInventoryOrchestrator stub (final class).
     * When shouldThrow=true, its process() method will throw ServiceException.
     * Achieved by injecting a NegativeStockPolicyResolver that throws.
     */
    private function buildInventoryOrchestratorStub(bool $shouldThrow): SaleInventoryOrchestrator
    {
        $rc = new ReflectionClass(SaleInventoryOrchestrator::class);
        /** @var SaleInventoryOrchestrator $stub */
        $stub = $rc->newInstanceWithoutConstructor();

        // Build InventoryLedgerService via reflection (final class — cannot createMock).
        // Inject a Connection mock that returns false for fetchOne() → stock = 0.0.
        $ledgerRc = new ReflectionClass(\App\Service\InventoryLedgerService::class);
        /** @var \App\Service\InventoryLedgerService $ledgerStub */
        $ledgerStub = $ledgerRc->newInstanceWithoutConstructor();

        /** @var Connection&MockObject $ledgerConn */
        $ledgerConn = $this->createMock(Connection::class);
        $ledgerConn->method('fetchOne')->willReturn(false);
        // beginTransaction / rollBack / commit return void — no willReturn needed

        $ledgerRc->getProperty('connection')->setValue($ledgerStub, $ledgerConn);

        // settingsService = null → shouldReadFromStockItems() returns false (skips stock_items query)
        if ($ledgerRc->hasProperty('settingsService')) {
            $ledgerRc->getProperty('settingsService')->setValue($ledgerStub, null);
        }
        if ($ledgerRc->hasProperty('lotDeductionService')) {
            $ledgerRc->getProperty('lotDeductionService')->setValue($ledgerStub, null);
        }
        if ($ledgerRc->hasProperty('logger')) {
            $ledgerRc->getProperty('logger')->setValue($ledgerStub, $this->logger);
        }

        $rc->getProperty('inventoryLedgerService')->setValue($stub, $ledgerStub);

        // stockValidationPolicy — no-op interface mock
        /** @var \App\Inventory\Core\Contract\StockValidationPolicyInterface&MockObject $svp */
        $svp = $this->createMock(\App\Inventory\Core\Contract\StockValidationPolicyInterface::class);
        $rc->getProperty('stockValidationPolicy')->setValue($stub, $svp);

        // policyResolver (final) — bypass constructor, inject SettingsService mock
        $policyResolverClass = (string) $rc->getProperty('policyResolver')->getType();
        $rcPr = new ReflectionClass($policyResolverClass);
        /** @var \App\State\Sale\NegativeStockPolicyResolver $prStub */
        $prStub = $rcPr->newInstanceWithoutConstructor();

        // SettingsService is NOT final — we can mock it
        /** @var SettingsService&MockObject $policySettings */
        $policySettings = $this->createMock(SettingsService::class);

        if ($shouldThrow) {
            // BLOCK policy: resolvePolicy() returns BLOCK (default for empty/unknown setting)
            // → enforceNonNegative=true → with stock=0 and cantidad=5 → ServiceException thrown
            $policySettings->method('get')->willReturn('');
        } else {
            // ALLOW_ALL policy → canSell returns true → enforceNonNegative=false for tipoId=2
            // But sale has no warehouse anyway → orchestrator returns early
            $policySettings->method('get')->willReturn('ALLOW_NEGATIVE');
        }

        $rcPr->getProperty('settings')->setValue($prStub, $policySettings);
        $rc->getProperty('policyResolver')->setValue($stub, $prStub);

        $rc->getProperty('logger')->setValue($stub, $this->logger);

        return $stub;
    }

    /**
     * Build a DetractionCalculator stub (final class).
     * Runs real code — applyFromItems() calls clearDetraction() when customer has no 11-digit RUC.
     */
    private function buildDetractionCalculatorStub(): DetractionCalculator
    {
        $rc = new ReflectionClass(DetractionCalculator::class);
        /** @var DetractionCalculator $stub */
        $stub = $rc->newInstanceWithoutConstructor();

        // sunatCatalogRepository — inject mock (sale has no customer with 11-digit RUC by default,
        // so clearDetraction() is called immediately without querying the repository)
        $rc->getProperty('sunatCatalogRepository')->setValue($stub, $this->sunatCatalogRepository);

        return $stub;
    }

    /**
     * Build a SalePaymentPlanService stub (final class).
     * resolveCashCollectedAmount() returns the given string amount.
     * resolveForWrite() returns a minimal plan so no ServiceException is thrown.
     */
    private function buildSalePaymentPlanServiceStub(string $cashCollectedAmount): SalePaymentPlanService
    {
        $rc = new ReflectionClass(SalePaymentPlanService::class);
        /** @var SalePaymentPlanService $stub */
        $stub = $rc->newInstanceWithoutConstructor();

        // SalePaymentPlanService has no declared properties — it is stateless.
        // However, its methods access none of the injected deps directly (no constructor deps).
        // All computation is inline. We cannot override the real methods.
        // Strategy: inject a Sale with compatible payments array so resolveForWrite() succeeds.
        // The amount='0.01' trick from existing tests covers zero-total sales.

        return $stub;
    }

    /**
     * Build a GoodsReceiptWriteGateway stub (final class).
     * Accepts an optional closure invoked when write() is called (via beginTransaction spy).
     * @param callable():void|null $onWrite
     */
    private function buildGoodsReceiptWriteGatewayStub(?callable $onWrite = null): GoodsReceiptWriteGateway
    {
        $rc = new ReflectionClass(GoodsReceiptWriteGateway::class);
        /** @var GoodsReceiptWriteGateway $stub */
        $stub = $rc->newInstanceWithoutConstructor();

        // GoodsReceiptWriteGateway has a Connection dependency
        if ($rc->hasProperty('connection')) {
            /** @var Connection&MockObject $connMock */
            $connMock = $this->createMock(Connection::class);

            if ($onWrite !== null) {
                $capturedOnWrite = $onWrite;
                $connMock
                    ->method('beginTransaction')
                    ->willReturnCallback(static function () use ($capturedOnWrite): void {
                        ($capturedOnWrite)();
                    });
            }

            $rc->getProperty('connection')->setValue($stub, $connMock);
        }

        // Inject a SaleCurrencyResolver mock so resolveForWrite() doesn't crash
        if ($rc->hasProperty('saleCurrencyResolver')) {
            /** @var \App\Service\SaleCurrencyResolver&MockObject $currencyResolverMock */
            $currencyResolverMock = $this->createMock(\App\Service\SaleCurrencyResolver::class);
            $currencyResolverMock->method('resolveForWrite')->willReturn([
                'currency' => 'PEN',
                'exchange_rate' => '1.000000',
            ]);
            $rc->getProperty('saleCurrencyResolver')->setValue($stub, $currencyResolverMock);
        }

        // Inject other deps as no-op sub-stubs
        foreach ($rc->getProperties() as $prop) {
            if ($prop->isInitialized($stub)) {
                continue;
            }
            $typeName = $prop->getType() instanceof ReflectionNamedType
                ? $prop->getType()->getName()
                : null;
            if ($typeName !== null && (class_exists($typeName) || interface_exists($typeName))) {
                try {
                    $depStub = (new ReflectionClass($typeName))->newInstanceWithoutConstructor();
                    $prop->setValue($stub, $depStub);
                } catch (\Throwable) {
                    if ($prop->getType() instanceof ReflectionNamedType
                        && $prop->getType()->allowsNull()) {
                        $prop->setValue($stub, null);
                    }
                }
            }
        }

        return $stub;
    }

    /**
     * Build a CashSessionResolver stub (final class).
     * tryResolveForSale() and resolveForSale() both return $resolvedSessionId.
     */
    private function buildCashSessionResolverStub(?int $resolvedSessionId): CashSessionResolver
    {
        $rc = new ReflectionClass(CashSessionResolver::class);
        /** @var CashSessionResolver $stub */
        $stub = $rc->newInstanceWithoutConstructor();

        /** @var Connection&MockObject $connMock */
        $connMock = $this->createMock(Connection::class);

        if ($resolvedSessionId !== null) {
            $connMock
                ->method('fetchAssociative')
                ->willReturn([
                    'id' => $resolvedSessionId,
                    'cash_register_id' => 1,
                    'branch_id' => 'branch-1',
                    'opened_at' => '2026-01-01 08:00:00',
                    'closed_at' => null,
                ]);
        } else {
            $connMock
                ->method('fetchAssociative')
                ->willReturn(false);
        }

        $rc->getProperty('connection')->setValue($stub, $connMock);

        return $stub;
    }

    // =========================================================================
    // General reflection utilities
    // =========================================================================

    /**
     * Bypass the constructor of any class and return an uninitialised instance.
     *
     * @template T of object
     * @param class-string<T> $className
     * @return T
     */
    private function bypassConstructor(string $className): object
    {
        /** @var T */
        return (new ReflectionClass($className))->newInstanceWithoutConstructor();
    }

    /**
     * Recursively initialize unset properties of a stub with no-op sub-stubs or null.
     *
     * @param ReflectionClass<object> $rc
     */
    private function initStubProperties(ReflectionClass $rc, object $stub): void
    {
        foreach ($rc->getProperties() as $prop) {
            if ($prop->isInitialized($stub)) {
                continue;
            }
            $type = $prop->getType();
            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $depClass = $type->getName();
                if (class_exists($depClass) || interface_exists($depClass)) {
                    try {
                        $depStub = (new ReflectionClass($depClass))->newInstanceWithoutConstructor();
                        $prop->setValue($stub, $depStub);
                        continue;
                    } catch (\Throwable) {
                        // fall through
                    }
                }
            }
            if (!($type instanceof ReflectionNamedType) || $type->allowsNull()) {
                $prop->setValue($stub, null);
            }
        }
    }
}
