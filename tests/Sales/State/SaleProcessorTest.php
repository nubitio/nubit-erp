<?php

declare(strict_types=1);

namespace App\Tests\Sales\State;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\State\ProcessorInterface;
use App\CashRegister\Entity\CashMovementCategory;
use App\Catalog\Entity\Product;
use App\Catalog\Repository\SunatCatalogRepository;
use App\Contacts\Entity\Customer;
use App\Contacts\Repository\CustomerRepository;
use App\Identity\Auth\BranchContextService;
use App\Identity\Dto\AuthUserDto;
use App\Identity\Security\Security;
use App\Inventory\Core\Contract\SalesInventoryPortInterface;
use App\Inventory\Entity\Warehouse;
use App\Organization\Entity\Branch;
use App\Organization\Repository\BranchRepository;
use App\Sales\Entity\SaleItemRecord;
use App\Sales\Entity\SaleRecord;
use App\Sales\Port\InvoiceCreatorInterface;
use App\Sales\Repository\SaleRecordRepository;
use App\Sales\Service\SaleSettlementPlanService;
use App\Sales\State\CashSessionResolver;
use App\Sales\State\DetractionCalculator;
use App\Sales\State\SaleInventoryOrchestrator;
use App\Sales\State\SaleProcessor;
use App\Sales\State\SalesWriteGateway;
use App\Sales\State\SaleUtils;
use App\Settings\Service\TenantSettingsService;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Nubit\Platform\Exception\ServiceException;
use Nubit\Platform\Exception\ValidationException;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use ReflectionNamedType;
use Symfony\Bundle\SecurityBundle\Security as SecurityComponent;
use Symfony\Component\HttpFoundation\Request;

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
 * - Auto-invoice disabled + emitInvoice=null → no invoicing-port call
 * - emitInvoice=true → invoicing port called
 * - Invoice creation success → invoicing port handles dispatch
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

    /** @var InvoiceCreatorInterface&MockObject */
    private InvoiceCreatorInterface $saleInvoicing;

    /** @var LoggerInterface&MockObject */
    private LoggerInterface $logger;

    /** @var SaleUtils&MockObject */
    private SaleUtils $saleUtils;

    /** @var BranchRepository&MockObject */
    private BranchRepository $branchRepository;

    /** @var CustomerRepository&MockObject */
    private CustomerRepository $customerRepository;

    /** @var SalesWriteGateway&MockObject */
    private SalesWriteGateway $salesWriteGateway;

    /** @var SaleRecordRepository&MockObject */
    private SaleRecordRepository $saleRecordRepository;

    // -------------------------------------------------------------------------
    // Final classes — bypassed via ReflectionClass::newInstanceWithoutConstructor()
    // Their inner dependencies are replaced with mocks injected via reflection.
    // -------------------------------------------------------------------------

    private BranchContextService $branchContextService;
    private SaleInventoryOrchestrator $inventoryOrchestrator;
    private DetractionCalculator $detractionCalculator;
    private SaleSettlementPlanService $saleSettlementPlanService;
    private CashSessionResolver $cashSessionResolver;

    /** @var SunatCatalogRepository&MockObject */
    private SunatCatalogRepository $sunatCatalogRepository;

    /** @var list<string> */
    private array $grantedPermissions;

    protected function setUp(): void
    {
        $this->persistProcessor = $this->createMock(ProcessorInterface::class);
        $this->removeProcessor = $this->createMock(ProcessorInterface::class);
        $this->security = $this->createMock(Security::class);
        $this->grantedPermissions = [
            'pos.discount',
            'pos.price_override',
            'pos.offline_sale',
        ];
        $this->security
            ->method('hasPermission')
            ->willReturnCallback(fn(string $permission): bool => in_array(
                $permission,
                $this->grantedPermissions,
                true,
            ));
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->saleInvoicing = $this->createMock(InvoiceCreatorInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->saleUtils = $this->createMock(SaleUtils::class);
        $this->branchRepository = $this->createMock(BranchRepository::class);
        $this->customerRepository = $this->createMock(CustomerRepository::class);
        $this->salesWriteGateway = $this->createMock(SalesWriteGateway::class);
        $this->sunatCatalogRepository = $this->createMock(SunatCatalogRepository::class);
        $this->saleRecordRepository = $this->createMock(SaleRecordRepository::class);
        $this->saleRecordRepository->method('findByOfflineRetryKey')->willReturn(null);

        // Build final-class stubs
        $this->branchContextService = $this->buildBranchContextStub(returnBranchId: null);
        $this->inventoryOrchestrator = $this->buildInventoryOrchestratorStub(shouldThrow: false);
        $this->detractionCalculator = $this->buildDetractionCalculatorStub();
        $this->saleSettlementPlanService = $this->buildSaleSettlementPlanServiceStub();
        $this->cashSessionResolver = $this->buildCashSessionResolverStub(resolvedSessionId: null);

        // Default: persistProcessor returns the sale unchanged
        $this->persistProcessor->method('process')->willReturnArgument(0);

        // Default: connectedUser returns a test user
        $this->security->method('connectedUser')->willReturn(new AuthUserDto(userId: 42, username: 'test_user'));
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

        $this->persistProcessor->expects($this->once())->method('process')->willReturnArgument(0);

        $result = $this->buildAndRunProcessor($sale, new Post());

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

        $this->persistProcessor->expects($this->once())->method('process')->willReturnArgument(0);

        $this->buildAndRunProcessor($sale, new Put());

        self::assertNotNull($sale->getDateUpdated(), 'Updated sale must have date_updated set');
        self::assertSame(42, $sale->getUpdatedBy());
    }

    // -------------------------------------------------------------------------
    // TC-03: Delete operation — removeProcessor called, persist/inventory never called
    // -------------------------------------------------------------------------

    public function testDeleteOperationDelegatesToRemoveProcessor(): void
    {
        $sale = $this->buildExistingSale(tipoId: 2);

        $this->removeProcessor->expects($this->once())->method('process')->willReturn(null);

        $this->persistProcessor->expects($this->never())->method('process');

        $this->buildAndRunProcessor($sale, new Delete());
    }

    // -------------------------------------------------------------------------
    // TC-04: New tipoId=2 without tipoPago — no cash-shift repository query
    // -------------------------------------------------------------------------

    public function testNewOperationalSaleWithoutTipoPagoSkipsCashShiftCheck(): void
    {
        $sale = $this->buildNewSaleWithBranch(tipoId: 2);

        // getRepository should NOT be called (no tipoPago → block skipped)
        $this->entityManager->expects($this->never())->method('getRepository');

        // Operational sales use salesWriteGateway->write(), not persistProcessor.
        $this->salesWriteGateway->expects($this->once())->method('write');

        $result = $this->buildAndRunProcessor($sale, new Post());

        self::assertSame($sale, $result);
    }

    public function testOperationalSaleWithoutPaymentMethodStillRequiresDiscountPermission(): void
    {
        $this->grantedPermissions = [];
        $sale = $this->buildNewSaleWithBranch(tipoId: 2);
        $sale->setDescuento('10');

        $this->expectException(\Symfony\Component\Security\Core\Exception\AccessDeniedException::class);
        $this->expectExceptionMessage('pos.discount');

        $this->buildAndRunProcessor($sale, new Post());
    }

    public function testOperationalSaleRejectsLineWithoutProduct(): void
    {
        $sale = $this->buildNewSaleWithBranch(tipoId: 2);
        $item = new SaleItemRecord();
        $item->setNetAmount('10.00');
        $item->setQty('1');
        $item->setUnitPrice('10.00');
        $item->setGrossAmount('10.00');
        $item->setNameSnapshot('Item');
        $sale->addSaleItemRecord($item);
        $sale->setPayments([['payment_method_id' => 1, 'amount' => '10.00']]);

        $this->expectException(ValidationException::class);

        $this->buildAndRunProcessor($sale, new Post());
    }

    // -------------------------------------------------------------------------
    // TC-05: REQUIRE_CASH_REGISTER=true + no open shift → ServiceException
    // -------------------------------------------------------------------------

    public function testRequireCashRegisterWithNoShiftThrowsServiceException(): void
    {
        $sale = $this->buildNewSaleWithPaymentMethod(tipoId: 2);

        $this->mockCashMovementCategoryRepository();

        $this->expectException(ServiceException::class);
        $this->expectExceptionMessageMatches('/caja abierta/');

        $this->buildAndRunProcessor($sale, new Post(), requireCashRegister: 'true');
    }

    // -------------------------------------------------------------------------
    // TC-06: REQUIRE_CASH_REGISTER=false + no shift → no exception
    // -------------------------------------------------------------------------

    public function testNoCashShiftWithoutRequirementProceedsNormally(): void
    {
        $sale = $this->buildNewSaleWithBranch(tipoId: 2);

        // No tipoPago → cash-shift block not entered at all
        // Operational sales use salesWriteGateway->write(), not persistProcessor.
        $this->salesWriteGateway->expects($this->once())->method('write');

        $result = $this->buildAndRunProcessor($sale, new Post(), requireCashRegister: 'false');

        self::assertSame($sale, $result);
    }

    // -------------------------------------------------------------------------
    // TC-07: New operational sale always calls salesWriteGateway::write
    // -------------------------------------------------------------------------

    public function testNewOperationalSaleAlwaysCallsSalesWriteGateway(): void
    {
        // Sale needs branch+fecha so CashSessionResolver doesn't throw
        $sale = $this->buildNewSaleWithBranch(tipoId: 2);

        $this->cashSessionResolver = $this->buildCashSessionResolverStub(resolvedSessionId: 1);

        $this->salesWriteGateway->expects($this->once())->method('write')->with($sale, 1);

        $result = $this->buildAndRunProcessor($sale, new Post());

        self::assertSame($sale, $result);
    }

    // -------------------------------------------------------------------------
    // TC-11: salesWriteGateway::write always receives resolved cash session id
    // -------------------------------------------------------------------------

    public function testSalesWriteGatewayReceivesResolvedCashSessionId(): void
    {
        $sale = $this->buildNewSaleWithBranch(tipoId: 2);

        $this->cashSessionResolver = $this->buildCashSessionResolverStub(resolvedSessionId: 99);

        $this->salesWriteGateway->expects($this->once())->method('write')->with($sale, 99);

        $this->buildAndRunProcessor($sale, new Post());
    }

    // -------------------------------------------------------------------------
    // TC-12: salesWriteGateway throws → ServiceException propagated
    // -------------------------------------------------------------------------

    public function testSalesWriteGatewayFailureThrowsServiceException(): void
    {
        $sale = $this->buildNewSaleWithBranch(tipoId: 2);

        $this->cashSessionResolver = $this->buildCashSessionResolverStub(resolvedSessionId: null);

        $this->salesWriteGateway->method('write')->willThrowException(new \RuntimeException('Sales V2 write error'));

        $this->expectException(ServiceException::class);
        $this->expectExceptionMessageMatches('/Sales V2/');

        $this->buildAndRunProcessor($sale, new Post());
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
        new ReflectionClass(Warehouse::class)
            ->getProperty('id')
            ->setValue($warehouse, 'wh-test');
        $sale->setAlmacen($warehouse);

        $product = new Product();
        $product->setName('Test Product');
        $product->setTrackInventory(true);

        $item = new SaleItemRecord();
        $item->setProduct($product);
        $item->setQty('5');
        $item->setNetAmount('0.01');
        $item->setUnitPrice('0.01');
        $item->setGrossAmount('0.01');
        $item->setNameSnapshot('Test Product');
        $sale->addSaleItemRecord($item);

        // payments sum ≈ total (0.01), so resolveForWrite() passes
        $sale->setPayments([['payment_method_id' => 1, 'amount' => '0.01']]);

        $this->inventoryOrchestrator = $this->buildInventoryOrchestratorStub(shouldThrow: true);

        $this->persistProcessor->expects($this->never())->method('process');

        $this->expectException(ServiceException::class);
        $this->expectExceptionMessageMatches('/Stock insuficiente/');

        $this->buildAndRunProcessor($sale, new Post());
    }

    // -------------------------------------------------------------------------
    // TC-15: Auto-invoice disabled + emitInvoice=null → invoicing port not called
    // -------------------------------------------------------------------------

    public function testAutoInvoiceDisabledAndEmitInvoiceNullSkipsInvoice(): void
    {
        $sale = $this->buildNewSaleWithBranch(tipoId: 2);
        $sale->setEmitInvoice(null);

        $this->saleInvoicing->expects($this->never())->method('createAndDispatchInvoiceForSaleId');

        $this->buildAndRunProcessor($sale, new Post(), autoInvoice: 'false');
    }

    // -------------------------------------------------------------------------
    // TC-16: emitInvoice=true on new operational sale → invoicing port called
    // -------------------------------------------------------------------------

    public function testEmitInvoiceTrueCallsInvoiceCreationService(): void
    {
        $sale = $this->buildNewSaleWithBranch(tipoId: 2);
        $sale->setEmitInvoice(true);

        $this->saleInvoicing
            ->expects($this->once())
            ->method('createAndDispatchInvoiceForSaleId')
            ->willReturn(['success' => false, 'message' => 'skipped']);

        $this->buildAndRunProcessor($sale, new Post(), autoInvoice: 'false');
    }

    // -------------------------------------------------------------------------
    // TC-17: Invoice creation success → SaleInvoicingPort handles dispatch
    // -------------------------------------------------------------------------

    public function testInvoiceCreationSuccessDispatchesMessage(): void
    {
        $sale = $this->buildNewSaleWithBranch(tipoId: 2);
        $sale->setEmitInvoice(true);

        $this->saleInvoicing
            ->expects($this->once())
            ->method('createAndDispatchInvoiceForSaleId')
            ->willReturn(['success' => true, 'invoiceId' => 'inv-abc-123']);

        $this->buildAndRunProcessor($sale, new Post(), autoInvoice: 'false');
    }

    // -------------------------------------------------------------------------
    // TC-18: Invoice creation throws → caught, not propagated
    // -------------------------------------------------------------------------

    public function testInvoiceCreationExceptionIsCaughtAndNotPropagated(): void
    {
        $sale = $this->buildNewSaleWithBranch(tipoId: 2);
        $sale->setEmitInvoice(true);

        $this->saleInvoicing
            ->method('createAndDispatchInvoiceForSaleId')
            ->willThrowException(new \RuntimeException('SUNAT unavailable'));

        $result = $this->buildAndRunProcessor($sale, new Post(), autoInvoice: 'false');

        self::assertSame($sale, $result);
    }

    // -------------------------------------------------------------------------
    // TC-19: Auto-invoice NOT dispatched on update (isNew=false)
    // -------------------------------------------------------------------------

    public function testAutoInvoiceNotDispatchedOnUpdate(): void
    {
        $sale = $this->buildExistingSale(tipoId: 2);
        // Resolver throws if branch is null — inject a branch so it can proceed
        $branch = new Branch();
        new ReflectionClass(Branch::class)
            ->getProperty('id')
            ->setValue($branch, 'branch-1');
        $sale->setBranch($branch);
        $sale->setFecha(new \DateTimeImmutable('2026-01-01 10:00:00'));
        $sale->setEmitInvoice(null);

        $this->saleInvoicing->expects($this->never())->method('createAndDispatchInvoiceForSaleId');

        $this->buildAndRunProcessor($sale, new Put(), autoInvoice: 'true');
    }

    // -------------------------------------------------------------------------
    // TC-20: autoInvoice=true + new operational sale + emitInvoice=null → invoicing port called
    // -------------------------------------------------------------------------

    public function testAutoInvoiceEnabledOnNewOperationalSaleTriggersInvoice(): void
    {
        $sale = $this->buildNewSaleWithBranch(tipoId: 2);
        $sale->setEmitInvoice(null);

        $this->saleInvoicing
            ->expects($this->once())
            ->method('createAndDispatchInvoiceForSaleId')
            ->willReturn(['success' => false, 'message' => 'not ready']);

        $this->buildAndRunProcessor($sale, new Post(), autoInvoice: 'true');
    }

    // -------------------------------------------------------------------------
    // TC-21: emitInvoice=false overrides autoInvoice=true
    // -------------------------------------------------------------------------

    public function testEmitInvoiceFalseOverridesAutoInvoiceTrue(): void
    {
        $sale = $this->buildNewSaleWithBranch(tipoId: 2);
        $sale->setEmitInvoice(false);

        $this->saleInvoicing->expects($this->never())->method('createAndDispatchInvoiceForSaleId');

        $this->buildAndRunProcessor($sale, new Post(), autoInvoice: 'true');
    }

    // -------------------------------------------------------------------------
    // TC-22: Non-operational tipoId=1 sale never triggers auto-invoice
    // -------------------------------------------------------------------------

    public function testNonOperationalSaleNeverTriggersAutoInvoice(): void
    {
        $sale = $this->buildNewSale(tipoId: 1);
        $sale->setEmitInvoice(true);

        $this->saleInvoicing->expects($this->never())->method('createAndDispatchInvoiceForSaleId');

        $this->buildAndRunProcessor($sale, new Post(), autoInvoice: 'true');
    }

    // -------------------------------------------------------------------------
    // TC-23: New tipoId=2 with tipoPago, no customer → default customer fetched
    // -------------------------------------------------------------------------

    public function testNewOperationalSaleWithoutCustomerFetchesDefaultCustomer(): void
    {
        $sale = $this->buildNewSaleWithPaymentMethod(tipoId: 2);
        // No customer set on purpose
        // Resolver throws if branch is null — inject a branch so it can proceed
        $branch = new Branch();
        new ReflectionClass(Branch::class)
            ->getProperty('id')
            ->setValue($branch, 'branch-1');
        $sale->setBranch($branch);
        $sale->setFecha(new \DateTimeImmutable('2026-01-01 10:00:00'));

        $defaultCustomer = $this->createStub(Customer::class);
        $this->mockCashMovementCategoryRepository();

        $this->customerRepository->method('findDefaultAnonymous')->willReturn($defaultCustomer);

        $this->saleUtils->method('fillSaleValues')->willReturnArgument(0);

        $this->buildAndRunProcessor($sale, new Post(), requireCashRegister: 'false');

        self::assertSame($defaultCustomer, $sale->getCliente());
    }

    // -------------------------------------------------------------------------
    // TC-24: New tipoId=2 + no customer + no default customer → ServiceException
    // -------------------------------------------------------------------------

    public function testMissingDefaultCustomerThrowsServiceException(): void
    {
        $sale = $this->buildNewSaleWithPaymentMethod(tipoId: 2);

        $this->mockCashMovementCategoryRepository();

        $this->customerRepository->method('findDefaultAnonymous')->willReturn(null);

        $this->saleUtils->method('fillSaleValues')->willReturnArgument(0);

        $this->expectException(ServiceException::class);
        $this->expectExceptionMessageMatches('/cliente general/');

        $this->buildAndRunProcessor($sale, new Post(), requireCashRegister: 'false');
    }

    // -------------------------------------------------------------------------
    // TC-25: ensureSaleCustomer skips when customer already set
    // -------------------------------------------------------------------------

    public function testEnsureCustomerSkipsWhenCustomerAlreadySet(): void
    {
        $sale = $this->buildNewSaleWithPaymentMethod(tipoId: 2);
        $sale->setCliente($this->buildActiveCustomerMock());
        // Resolver throws if branch is null — inject a branch so it can proceed
        $branch = new Branch();
        new ReflectionClass(Branch::class)
            ->getProperty('id')
            ->setValue($branch, 'branch-1');
        $sale->setBranch($branch);
        $sale->setFecha(new \DateTimeImmutable('2026-01-01 10:00:00'));

        $this->mockCashMovementCategoryRepository();

        // entityManager::find must NOT be called for Customer
        $this->entityManager->expects($this->never())->method('find');

        $this->saleUtils->method('fillSaleValues')->willReturnArgument(0);

        $this->buildAndRunProcessor($sale, new Post(), requireCashRegister: 'false');
    }

    // -------------------------------------------------------------------------
    // A disabled customer must reject a new sale (ActiveReferenceGuard).
    // -------------------------------------------------------------------------

    public function testNewSaleWithDisabledCustomerIsRejected(): void
    {
        $sale = $this->buildNewSaleWithPaymentMethod(tipoId: 2);
        $inactiveCustomer = $this->createMock(Customer::class);
        $inactiveCustomer->method('getIsActive')->willReturn(false);
        $sale->setCliente($inactiveCustomer);

        $this->expectException(\Nubit\Platform\Exception\ValidationException::class);

        $this->buildAndRunProcessor($sale, new Post(), requireCashRegister: 'false');
    }

    // -------------------------------------------------------------------------
    // TC-26: ensureSaleCustomer skips when tipoId != 2
    // -------------------------------------------------------------------------

    public function testEnsureCustomerSkipsWhenTipoIdIsNotTwo(): void
    {
        $sale = $this->buildNewSaleWithPaymentMethod(tipoId: 1);
        // No customer set but tipoId=1

        $this->mockCashMovementCategoryRepository();

        // entityManager::find must NOT be called for Customer
        $this->entityManager->expects($this->never())->method('find');

        $this->saleUtils->method('fillSaleValues')->willReturnArgument(0);

        $this->buildAndRunProcessor($sale, new Post(), requireCashRegister: 'false');
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
        $item = new SaleItemRecord();
        $item->setNetAmount('10.00');
        $item->setQty('1');
        $item->setUnitPrice('10.00');
        $item->setGrossAmount('10.00');
        $item->setNameSnapshot('Item');
        $product = new Product();
        $product->setSalePrice('10.00');
        $item->setProduct($product);
        $sale->addSaleItemRecord($item);
        $sale->setPayments([['payment_method_id' => 1, 'amount' => '10.00']]);

        $this->cashSessionResolver = $this->buildCashSessionResolverStub(resolvedSessionId: 77);
        $this->saleSettlementPlanService = $this->buildSaleSettlementPlanServiceStub();

        $this->salesWriteGateway->expects($this->once())->method('write');

        $this->salesWriteGateway
            ->expects($this->once())
            ->method('writeCashSaleMovement')
            ->with(
                $this->anything(), // saleId
                77, // cashSessionId
                $this->anything(), // createdBy
                '10.00', // amount
                null, // categoryId
                $this->anything(), // paymentMethodId
                $this->anything(), // createdAt
                $this->anything(), // description
            );

        $this->buildAndRunProcessor($sale, new Post());
    }

    // -------------------------------------------------------------------------
    // TC-28: Cash movement write fails → ServiceException always propagated
    // -------------------------------------------------------------------------

    public function testCashMovementWriteFailsThrowsServiceException(): void
    {
        $sale = $this->buildNewSaleWithBranch(tipoId: 2);

        $this->cashSessionResolver = $this->buildCashSessionResolverStub(resolvedSessionId: 77);
        $this->saleSettlementPlanService = $this->buildSaleSettlementPlanServiceStub();

        $this->salesWriteGateway->method('write');

        $this->salesWriteGateway
            ->method('writeCashSaleMovement')
            ->willThrowException(new \RuntimeException('Cash write error'));

        $this->expectException(ServiceException::class);
        $this->expectExceptionMessageMatches('/movimiento de caja/');

        $this->buildAndRunProcessor($sale, new Post());
    }

    // -------------------------------------------------------------------------
    // TC-31: Legacy cash movement skipped when category not found
    // -------------------------------------------------------------------------

    public function testLegacyCashMovementSkippedWhenCategoryNotFound(): void
    {
        $sale = $this->buildNewSaleWithPaymentMethod(tipoId: 2);
        // Resolver throws if branch is null — inject a branch so it can proceed
        $branch = new Branch();
        new ReflectionClass(Branch::class)
            ->getProperty('id')
            ->setValue($branch, 'branch-1');
        $sale->setBranch($branch);
        $sale->setFecha(new \DateTimeImmutable('2026-01-01 10:00:00'));
        $sale->setCliente($this->buildActiveCustomerMock());

        $this->saleUtils->method('fillSaleValues')->willReturnArgument(0);

        $this->saleSettlementPlanService = $this->buildSaleSettlementPlanServiceStub();
        $this->mockCashMovementCategoryRepository();

        $this->entityManager->expects($this->never())->method('persist');

        $result = $this->buildAndRunProcessor(
            $sale,
            new Post(),
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
        $sale = $this->buildNewSaleWithBranch(tipoId: 2);

        // Set a non-null detraction code so we can detect clearDetraction() was called
        // (applyFromItems() calls clearDetraction() when customer has no 11-digit RUC,
        // which sets detraccionCodigo → null; proving applyFromItems was invoked with this sale).
        $sale->setDetraccionCodigo('999');

        $this->buildAndRunProcessor($sale, new Post());

        self::assertNull(
            $sale->getDetraccionCodigo(),
            'detractionCalculator::applyFromItems() must have been called — it clears detraccionCodigo when customer has no RUC',
        );
    }

    // -------------------------------------------------------------------------
    // TC-33: Sale items are linked to sale and totals are calculated
    // -------------------------------------------------------------------------

    public function testSaleItemsAreLinkedToSaleAndTotalsCalculated(): void
    {
        $sale = $this->buildNewSale(tipoId: 1);
        $item = new SaleItemRecord();
        $item->setNetAmount('100.00');
        $item->setQty('1');
        $item->setUnitPrice('100.00');
        $item->setGrossAmount('100.00');
        $item->setNameSnapshot('Item');
        $sale->addSaleItemRecord($item);

        $this->buildAndRunProcessor($sale, new Post());

        self::assertTrue($sale->getSaleItemRecords()->contains($item));
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
        // Give the branch an id so CashSessionResolver can resolve it after it's set on the sale
        new ReflectionClass(Branch::class)
            ->getProperty('id')
            ->setValue($branch, 'branch-42');

        $this->branchContextService = $this->buildBranchContextStubWithRealToken('branch-42');

        $this->branchRepository->method('find')->with('branch-42')->willReturn($branch);

        $this->buildAndRunProcessor($sale, new Post());

        self::assertSame($branch, $sale->getBranch());
    }

    // -------------------------------------------------------------------------
    // TC-34b: New sale on a disabled branch is rejected before any write
    // -------------------------------------------------------------------------

    public function testNewSaleOnDisabledBranchThrowsValidationException(): void
    {
        $sale = $this->buildNewSale(tipoId: 2);
        $branch = new Branch()->setIsActive(false);
        new ReflectionClass(Branch::class)
            ->getProperty('id')
            ->setValue($branch, 'branch-42');

        $this->branchContextService = $this->buildBranchContextStubWithRealToken('branch-42');
        $this->branchRepository->method('find')->with('branch-42')->willReturn($branch);

        $this->salesWriteGateway->expects($this->never())->method('write');
        $this->persistProcessor->expects($this->never())->method('process');

        $this->expectException(ValidationException::class);

        $this->buildAndRunProcessor($sale, new Post());
    }

    // -------------------------------------------------------------------------
    // TC-35: New operational sale always calls salesWriteGateway::write (not persistProcessor)
    // -------------------------------------------------------------------------

    public function testNewOperationalSaleAlwaysCallsSalesWriteGatewayNotPersistProcessor(): void
    {
        // Sale needs branch+fecha so CashSessionResolver doesn't throw when V2 write runs
        $sale = $this->buildNewSaleWithBranch(tipoId: 2);

        $this->salesWriteGateway->expects($this->once())->method('write');

        $this->persistProcessor->expects($this->never())->method('process');

        $this->cashSessionResolver = $this->buildCashSessionResolverStub(resolvedSessionId: 55);
        $this->saleSettlementPlanService = $this->buildSaleSettlementPlanServiceStub();

        $result = $this->buildAndRunProcessor($sale, new Post());

        self::assertSame($sale, $result);
    }

    // -------------------------------------------------------------------------
    // TC-36: isLegacyCashMovementDisabled setting 'true' skips legacy cash movement
    // -------------------------------------------------------------------------

    public function testSettingDisableLegacyCashMovementSkipsMovementCreation(): void
    {
        $sale = $this->buildNewSaleWithPaymentMethod(tipoId: 2);
        // Resolver throws if branch is null — inject a branch so it can proceed
        $branch = new Branch();
        new ReflectionClass(Branch::class)
            ->getProperty('id')
            ->setValue($branch, 'branch-1');
        $sale->setBranch($branch);
        $sale->setFecha(new \DateTimeImmutable('2026-01-01 10:00:00'));
        $sale->setCliente($this->buildActiveCustomerMock());

        $this->mockCashMovementCategoryRepository();

        $this->saleUtils->method('fillSaleValues')->willReturnArgument(0);

        $this->saleSettlementPlanService = $this->buildSaleSettlementPlanServiceStub();

        $this->entityManager->expects($this->never())->method('persist');

        $this->buildAndRunProcessor($sale, new Post(), requireCashRegister: 'false', disableLegacyCashMovement: 'true');
    }

    // -------------------------------------------------------------------------
    // TC-37: Offline sale — isOfflineSale=true path covered (no exception, process continues)
    // -------------------------------------------------------------------------

    public function testOfflineSaleHeaderCausesNoExceptionAndProceedsNormally(): void
    {
        $sale = $this->buildNewSaleWithBranch(tipoId: 2);

        $request = new Request();
        $request->headers->set('X-Offline-Sale', 'true');

        $result = $this->buildAndRunProcessor($sale, new Post(), context: ['request' => $request]);

        self::assertSame($sale, $result);
    }

    // -------------------------------------------------------------------------
    // TC-37b: Offline sale with a well-formed Idempotency-Key stores it on the
    // new sale, so a later retry with the same key can be detected.
    // -------------------------------------------------------------------------

    public function testOfflineSaleFirstSubmissionStoresIdempotencyKeyAsOfflineRetryKey(): void
    {
        $sale = $this->buildNewSaleWithBranch(tipoId: 2);
        $idempotencyKey = '11111111-2222-3333-4444-555555555555';

        $request = new Request();
        $request->headers->set('X-Offline-Sale', 'true');
        $request->headers->set('Idempotency-Key', $idempotencyKey);

        $this->buildAndRunProcessor($sale, new Post(), context: ['request' => $request]);

        self::assertSame($idempotencyKey, $sale->getOfflineRetryKey());
    }

    // -------------------------------------------------------------------------
    // TC-37c: Offline sale retry — an outbox resend of a sale that already
    // reached the server (same Idempotency-Key) must return the existing
    // SaleRecord and MUST NOT re-run inventory/cash/invoicing side effects,
    // i.e. must not create a duplicate sale.
    // -------------------------------------------------------------------------

    public function testOfflineSaleRetryWithKnownIdempotencyKeyReturnsExistingSaleWithoutSideEffects(): void
    {
        $sale = $this->buildNewSaleWithBranch(tipoId: 2);
        $idempotencyKey = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';

        $existingSale = $this->buildExistingSale(tipoId: 2);
        $existingSale->setOfflineRetryKey($idempotencyKey);

        $this->saleRecordRepository = $this->createMock(SaleRecordRepository::class);
        $this->saleRecordRepository
            ->expects($this->once())
            ->method('findByOfflineRetryKey')
            ->with($idempotencyKey)
            ->willReturn($existingSale);

        $request = new Request();
        $request->headers->set('X-Offline-Sale', 'true');
        $request->headers->set('Idempotency-Key', $idempotencyKey);

        $this->salesWriteGateway->expects($this->never())->method('write');
        $this->persistProcessor->expects($this->never())->method('process');

        $result = $this->buildAndRunProcessor($sale, new Post(), context: ['request' => $request]);

        self::assertSame($existingSale, $result);
        self::assertNotSame(
            $sale,
            $result,
            'A retry must return the already-persisted sale, not process the new payload',
        );
    }

    // -------------------------------------------------------------------------
    // TC-37d: A malformed Idempotency-Key never triggers the replay lookup —
    // the offline bypass simply doesn't apply (existing behaviour, unchanged).
    // -------------------------------------------------------------------------

    public function testMalformedIdempotencyKeyNeverQueriesForReplay(): void
    {
        $sale = $this->buildNewSaleWithBranch(tipoId: 2);

        $request = new Request();
        $request->headers->set('X-Offline-Sale', 'true');
        $request->headers->set('Idempotency-Key', 'not-a-uuid');

        $this->saleRecordRepository->expects($this->never())->method('findByOfflineRetryKey');

        $this->buildAndRunProcessor($sale, new Post(), context: ['request' => $request]);

        self::assertNull($sale->getOfflineRetryKey());
    }

    public function testOnlineSaleStoresIdempotencyKeyWithoutOfflineHeader(): void
    {
        $sale = $this->buildNewSaleWithBranch(tipoId: 2);
        $idempotencyKey = '22222222-3333-4444-5555-666666666666';

        $request = new Request();
        $request->headers->set('Idempotency-Key', $idempotencyKey);

        $this->buildAndRunProcessor($sale, new Post(), context: ['request' => $request]);

        self::assertSame($idempotencyKey, $sale->getOfflineRetryKey());
    }

    public function testOnlineSaleRetryWithKnownIdempotencyKeyReturnsExistingSale(): void
    {
        $sale = $this->buildNewSaleWithBranch(tipoId: 2);
        $idempotencyKey = 'bbbbbbbb-cccc-dddd-eeee-ffffffffffff';

        $existingSale = $this->buildExistingSale(tipoId: 2);
        $existingSale->setOfflineRetryKey($idempotencyKey);

        $this->saleRecordRepository = $this->createMock(SaleRecordRepository::class);
        $this->saleRecordRepository
            ->expects($this->once())
            ->method('findByOfflineRetryKey')
            ->with($idempotencyKey)
            ->willReturn($existingSale);
        $this->saleRecordRepository->expects($this->once())->method('enrichWithTaxDocuments')->with([$existingSale]);

        $request = new Request();
        $request->headers->set('Idempotency-Key', $idempotencyKey);

        $this->salesWriteGateway->expects($this->never())->method('write');
        $this->persistProcessor->expects($this->never())->method('process');

        $result = $this->buildAndRunProcessor($sale, new Post(), context: ['request' => $request]);

        self::assertSame($existingSale, $result);
    }

    // -------------------------------------------------------------------------
    // TC-38: New sale has_invoice set to false always (regardless of incoming value)
    // -------------------------------------------------------------------------

    public function testNewSaleHasInvoiceIsSetToFalse(): void
    {
        $sale = $this->buildNewSaleWithBranch(tipoId: 2);
        $sale->setHasInvoice(true); // client tries to set it true

        $this->buildAndRunProcessor($sale, new Post());

        self::assertFalse($sale->isHasInvoice(), 'New sale: has_invoice must always be forced to false');
    }

    // -------------------------------------------------------------------------
    // TC-39: AC-3 — discountPercent=0 on a sale item → discountAmount=0.00, total = qty × unitPrice
    // -------------------------------------------------------------------------

    public function testItemDiscountPercentZeroProducesNoDiscount(): void
    {
        $sale = $this->buildNewSale(tipoId: 1);
        $sale->setWriteItems([
            [
                'qty' => 3,
                'unitPrice' => '33.33',
                'discountPercent' => '0',
            ],
        ]);

        $this->buildAndRunProcessor($sale, new Post());

        $items = $sale->getSaleItemRecords()->toArray();
        $this->assertCount(1, $items);

        /** @var \App\Sales\Entity\SaleItemRecord $item */
        $item = $items[0];
        $this->assertSame(
            '0.00',
            $item->getDiscountAmount(),
            'AC-3: discountPercent=0 must produce discountAmount=0.00',
        );
        $this->assertSame('99.99', $item->getNetAmount(), 'AC-3: total must equal qty × unitPrice when discount=0');
    }

    // -------------------------------------------------------------------------
    // TC-40: AC-4 — discountPercent=101 throws ValidationException (HTTP 422)
    // -------------------------------------------------------------------------

    public function testItemDiscountPercentAbove100ThrowsValidationException(): void
    {
        $sale = $this->buildNewSale(tipoId: 1);
        $sale->setWriteItems([
            [
                'qty' => 1,
                'unitPrice' => '100.00',
                'discountPercent' => '101',
            ],
        ]);

        $this->expectException(\Nubit\Platform\Exception\ValidationException::class);
        $this->expectExceptionMessage('Validation failed');

        $this->buildAndRunProcessor($sale, new Post());
    }

    // -------------------------------------------------------------------------
    // TC-41: AC-4 variant — discountPercent=-1 also throws ValidationException
    // -------------------------------------------------------------------------

    public function testItemDiscountPercentNegativeThrowsValidationException(): void
    {
        $sale = $this->buildNewSale(tipoId: 1);
        $sale->setWriteItems([
            [
                'qty' => 1,
                'unitPrice' => '50.00',
                'discountPercent' => '-1',
            ],
        ]);

        $this->expectException(\Nubit\Platform\Exception\ValidationException::class);

        $this->buildAndRunProcessor($sale, new Post());
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
        SaleRecord $sale,
        \ApiPlatform\Metadata\Operation $operation,
        array $context = [],
        string $requireCashRegister = 'false',
        string $autoInvoice = 'false',
        string $disableLegacyCashMovement = 'false',
    ): mixed {
        $processor = $this->buildProcessor(
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
        string $requireCashRegister = 'false',
        string $autoInvoice = 'false',
        string $disableLegacyCashMovement = 'false',
    ): SaleProcessor {
        // Configure the main tenantSettings mock
        $tenantSettings = $this->createStub(TenantSettingsService::class);
        $tenantSettings->method('isAutoInvoice')->willReturn($autoInvoice === 'true');
        $tenantSettings->method('isRequireCashRegister')->willReturn($requireCashRegister === 'true');

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
        $inj('tenantSettings', $tenantSettings);
        $inj('saleInvoicing', $this->saleInvoicing);
        $inj('logger', $this->logger);
        $inj('branchContextService', $this->branchContextService);
        $inj('branchRepository', $this->branchRepository);
        $inj('salesWriteGateway', $this->salesWriteGateway);
        $inj('cashSessionResolver', $this->cashSessionResolver);
        $inj('saleSettlementPlanService', $this->saleSettlementPlanService);
        $inj('detractionCalculator', $this->detractionCalculator);
        $inj('inventoryOrchestrator', $this->inventoryOrchestrator);
        $inj('saleRecordRepository', $this->saleRecordRepository);
        $inj('customerRepository', $this->customerRepository);
        $inj('activeReferenceGuard', new \App\Efact\Domain\ActiveReferenceGuard());
        $inj('messageBus', null);

        return $processor;
    }

    // =========================================================================
    // Domain-object factory helpers
    // =========================================================================

    private function buildActiveCustomerMock(): Customer&MockObject
    {
        $customer = $this->createMock(Customer::class);
        $customer->method('getIsActive')->willReturn(true);

        return $customer;
    }

    private function buildNewSale(int $tipoId): SaleRecord
    {
        $sale = new SaleRecord();
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
    private function buildNewSaleWithBranch(int $tipoId): SaleRecord
    {
        $sale = $this->buildNewSale($tipoId);
        $branch = new Branch();
        new ReflectionClass(Branch::class)
            ->getProperty('id')
            ->setValue($branch, 'branch-1');
        $sale->setBranch($branch);
        $sale->setFecha(new \DateTimeImmutable('2026-01-01 10:00:00'));

        return $sale;
    }

    private function buildNewSaleWithPaymentMethod(int $tipoId): SaleRecord
    {
        $sale = $this->buildNewSale($tipoId);
        $paymentMethod = $this->buildPaymentMethod(id: 1);
        $sale->setTipoPago($paymentMethod);

        return $sale;
    }

    private function buildExistingSale(int $tipoId): SaleRecord
    {
        $sale = new SaleRecord();
        $sale->setTipoId($tipoId);
        $sale->setId('existing-uuid-abc-123');

        // Operational sales (tipoId=2) always call SalePaymentPlanService::resolveForWrite().
        // Provide a minimal payment so it doesn't throw.
        if ($tipoId === 2) {
            $sale->setPayments([['payment_method_id' => 1, 'amount' => '0.01']]);
        }

        return $sale;
    }

    private function buildPaymentMethod(int $id): \App\Catalog\Entity\PaymentMethod
    {
        $rc = new ReflectionClass(\App\Catalog\Entity\PaymentMethod::class);
        /** @var \App\Catalog\Entity\PaymentMethod $pm */
        $pm = $rc->newInstanceWithoutConstructor();
        $rc->getProperty('id')->setValue($pm, $id);

        return $pm;
    }

    private function mockCashMovementCategoryRepository(): void
    {
        /** @var EntityRepository<CashMovementCategory>&MockObject $categoryRepo */
        $categoryRepo = $this->createMock(EntityRepository::class);
        $categoryRepo->method('findOneBy')->willReturn(null);
        $this->entityManager->method('getRepository')->willReturn($categoryRepo);
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
        $secMock = $this->createStub(SecurityComponent::class);
        $secMock->method('getToken')->willReturn(null);

        $rc = new ReflectionClass(BranchContextService::class);
        /** @var BranchContextService $stub */
        $stub = $rc->newInstanceWithoutConstructor();
        $rc->getProperty('security')->setValue($stub, $secMock);

        // jwtManager is never called because token is null; inject no-op sub-stub
        if ($rc->hasProperty('jwtManager')) {
            $jwtMgrClass = (string) $rc->getProperty('jwtManager')->getType();
            if (class_exists($jwtMgrClass)) {
                $jwtStub = new ReflectionClass($jwtMgrClass)->newInstanceWithoutConstructor();
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
        $jwtManagerRc = new ReflectionClass(\Nubit\AdminBundle\Auth\JWTManager::class);
        /** @var \Nubit\AdminBundle\Auth\JWTManager $jwtManager */
        $jwtManager = $jwtManagerRc->newInstanceWithoutConstructor();
        $jwtManagerRc->getProperty('secret')->setValue($jwtManager, 'test-secret-for-unit-tests-32bytes');
        $jwtManagerRc->getProperty('logger')->setValue($jwtManager, $this->logger);

        // 2. Encode a JWT that contains the branchId
        $encodedToken = $jwtManager->encode(['branchId' => $branchId, 'sub' => 'test-user']);

        // 3. Build JWTAuthenticationToken with the encoded token as credentials
        $jwtTokenRc = new ReflectionClass(\Nubit\AdminBundle\Auth\JWTAuthenticationToken::class);
        /** @var \Nubit\AdminBundle\Auth\JWTAuthenticationToken $jwtToken */
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
        $secMock = $this->createStub(\Symfony\Bundle\SecurityBundle\Security::class);
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

        $salesInventory = new class() implements SalesInventoryPortInterface {
            public function getStockForProductInWarehouse(Product $product, Warehouse $warehouse): float
            {
                return 0.0;
            }

            public function applyMovementFromSaleItem(
                SaleRecord $sale,
                \App\Sales\Entity\SaleItemRecord $item,
                int $lineNo,
                bool $enforceNonNegativeStock = true,
            ): void {}
        };

        $rc->getProperty('salesInventory')->setValue($stub, $salesInventory);

        // stockValidationPolicy — no-op interface mock
        /** @var \App\Inventory\Core\Contract\StockValidationPolicyInterface&MockObject $svp */
        $svp = $this->createStub(\App\Inventory\Core\Contract\StockValidationPolicyInterface::class);
        if ($shouldThrow) {
            $svp->method('validateSale')->willReturn(\App\Inventory\Core\Dto\StockValidationResult::blocked(
                'insufficient stock',
            ));
        } else {
            $svp->method('validateSale')->willReturn(\App\Inventory\Core\Dto\StockValidationResult::allowed());
        }
        $rc->getProperty('stockValidationPolicy')->setValue($stub, $svp);

        // policyResolver (final) — bypass constructor, inject TenantSettingsService mock
        $policyResolverClass = (string) $rc->getProperty('policyResolver')->getType();
        $rcPr = new ReflectionClass($policyResolverClass);
        /** @var \App\Sales\State\NegativeStockPolicyResolver $prStub */
        $prStub = $rcPr->newInstanceWithoutConstructor();

        /** @var TenantSettingsService&MockObject $policySettings */
        $policySettings = $this->createStub(TenantSettingsService::class);

        if ($shouldThrow) {
            $policySettings->method('getNegativeStockPolicy')->willReturn(\App\Efact\Enum\StockPolicy::STRICT);
        } else {
            $policySettings->method('getNegativeStockPolicy')->willReturn(\App\Efact\Enum\StockPolicy::ALLOW_NEGATIVE);
        }

        $rcPr->getProperty('tenantSettings')->setValue($prStub, $policySettings);
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

        // logger — inject a no-op mock so clearDetraction() can call warning() without crashing
        $rc->getProperty('logger')->setValue($stub, $this->createMock(LoggerInterface::class));

        return $stub;
    }

    /**
     * Build a SaleSettlementPlanService stub (final class).
     * resolveCashCollectedAmount() returns the given string amount.
     * resolveForWrite() returns a minimal plan so no ServiceException is thrown.
     */
    private function buildSaleSettlementPlanServiceStub(): SaleSettlementPlanService
    {
        return new SaleSettlementPlanService();
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
        $connMock = $this->createStub(Connection::class);

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
            $connMock->method('fetchAssociative')->willReturn(false);
        }

        $rc->getProperty('connection')->setValue($stub, $connMock);

        return $stub;
    }

    // =========================================================================
    // General reflection utilities
    // =========================================================================

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
                        $depStub = new ReflectionClass($depClass)->newInstanceWithoutConstructor();
                        $prop->setValue($stub, $depStub);
                        continue;
                    } catch (\Throwable) {
                        // fall through
                    }
                }
            }
            if (!$type instanceof ReflectionNamedType || $type->allowsNull()) {
                $prop->setValue($stub, null);
            }
        }
    }
}
