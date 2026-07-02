<?php

declare(strict_types=1);

namespace App\Tests\Sales\State;

use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Identity\Auth\BranchContextService;
use App\Identity\Dto\AuthUserDto;
use App\Identity\Security\Security;
use App\Organization\Entity\Branch;
use App\Organization\Repository\BranchRepository;
use App\Sales\Entity\SaleRecord;
use App\Sales\Port\InvoiceCreatorInterface;
use App\Sales\Repository\SaleRecordRepository;
use App\Sales\Service\SalePaymentPlanService;
use App\Sales\State\CashSessionResolver;
use App\Sales\State\DetractionCalculator;
use App\Sales\State\SaleInventoryOrchestrator;
use App\Sales\State\SaleProcessor;
use App\Sales\State\SalesWriteGateway;
use App\Sales\State\SaleUtils;
use App\Settings\Service\TenantSettingsService;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use ReflectionNamedType;
use Symfony\Bundle\SecurityBundle\Security as SecurityComponent;

/**
 * Unit tests for the invoicing-port behaviour in SaleProcessor::process().
 *
 * SaleProcessor has many final constructor dependencies. To avoid extending or
 * modifying production code solely for test purposes, we bypass the constructor
 * with ReflectionClass::newInstanceWithoutConstructor() and inject only the
 * properties exercised by the two test scenarios.
 *
 * Covers:
 * - TC-1: skipped invoice result is handled without throwing
 * - TC-2: successful invoice result is requested through the Sales invoicing port
 */
#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class SaleProcessorInvoiceDispatchTest extends TestCase
{
    /** @var InvoiceCreatorInterface&MockObject */
    private InvoiceCreatorInterface $saleInvoicing;
    /** @var Security&MockObject */
    private Security $security;
    /** @var ProcessorInterface<mixed, mixed>&MockObject */
    private ProcessorInterface $persistProcessor;
    /** @var EntityManagerInterface&MockObject */
    private EntityManagerInterface $entityManager;
    /** @var LoggerInterface&MockObject */
    private LoggerInterface $logger;
    /** @var TenantSettingsService&MockObject */
    private TenantSettingsService $tenantSettings;

    protected function setUp(): void
    {
        $this->saleInvoicing = $this->createMock(InvoiceCreatorInterface::class);
        $this->security = $this->createMock(Security::class);
        $this->persistProcessor = $this->createMock(ProcessorInterface::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->tenantSettings = $this->createMock(TenantSettingsService::class);
    }

    /**
     * TC-1: When SaleInvoicingPort returns ['success' => false], processing completes without throwing.
     */
    public function testSkippedInvoiceResultCompletesWithoutThrowing(): void
    {
        $sale = $this->buildOperationalSale(emitInvoice: true);

        $this->saleInvoicing
            ->expects($this->once())
            ->method('createAndDispatchInvoiceForSaleId')
            ->willReturn(['success' => false, 'message' => 'err']);

        $processor = $this->buildProcessor();
        $processor->process($sale, new Post(), [], []);
    }

    /**
     * TC-2: When SaleInvoicingPort returns success, SaleProcessor completes normally.
     */
    public function testSuccessfulInvoiceResultIsRequestedThroughPort(): void
    {
        $invoiceId = 'test-uuid-123';
        $sale = $this->buildOperationalSale(emitInvoice: true);

        $this->saleInvoicing
            ->expects($this->once())
            ->method('createAndDispatchInvoiceForSaleId')
            ->willReturn(['success' => true, 'invoiceId' => $invoiceId]);

        $processor = $this->buildProcessor();
        $result = $processor->process($sale, new Post(), [], []);

        self::assertSame($sale, $result);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Build a minimal Sale entity configured as a new operational sale (tipoId=2)
     * with no prior ID (so SaleProcessor treats it as a new record) and with the
     * emitInvoice flag set to drive the auto-invoice path.
     *
     * A minimal payments array is provided so SalePaymentPlanService can resolve
     * the payment plan without throwing. tipoPago is intentionally left null to
     * avoid triggering the SaleUtils::fillSaleValues() path (which requires DB).
     */
    private function buildOperationalSale(bool $emitInvoice): SaleRecord
    {
        $sale = new SaleRecord();
        $sale->setTipoId(2);
        $sale->setEmitInvoice($emitInvoice);

        // Provide a minimal payments entry so resolveForWrite() succeeds.
        // total = '0' (no items), amount = '0.01' → diff = 0.01 ≤ 0.01 tolerance ✓.
        $sale->setPayments([['payment_method_id' => 1, 'amount' => '0.01']]);

        // Resolver throws if branch is null — inject a branch with an id.
        $branch = new Branch();
        new \ReflectionClass(Branch::class)
            ->getProperty('id')
            ->setValue($branch, 'branch-1');
        $sale->setBranch($branch);
        $sale->setFecha(new \DateTimeImmutable('2026-01-01 10:00:00'));

        return $sale;
    }

    /**
     * Construct a SaleProcessor via reflection, bypassing its constructor so
     * that final-class dependencies do not need to be mocked with createMock().
     *
     * Properties NOT exercised in these tests (e.g. salesWriteGateway,
     * goodsReceiptWriteGateway, cashSessionResolver, saleUtils,
     * branchRepository, detractionCalculator, inventoryOrchestrator,
     * salePaymentPlanService) are never called because:
     * - tipoId=2 → no GoodsReceiptWriteGateway
     * - writeNewSchema=false → no SalesWriteGateway / CashSessionResolver
     * - tipoPago=null AND isNew → fillSaleValues / resolveForWrite never called
     * - branchContextService returns null-like via no-op stub
     */
    private function buildProcessor(): SaleProcessor
    {
        // connectedUser() is called to set createdBy on the new sale.
        $this->security->method('connectedUser')->willReturn(new AuthUserDto(userId: 1, username: 'test'));

        // persistProcessor returns the Sale entity unchanged.
        $this->persistProcessor->method('process')->willReturnArgument(0);

        // AUTO_INVOICE=false; emitInvoice flag on the entity drives the path.
        // REQUIRE_CASH_REGISTER=false so no cash-shift check.
        $this->tenantSettings->method('isAutoInvoice')->willReturn(false);
        $this->tenantSettings->method('isRequireCashRegister')->willReturn(false);

        // Build SaleProcessor bypassing constructor.
        $processorReflection = new ReflectionClass(SaleProcessor::class);
        /** @var SaleProcessor $processor */
        $processor = $processorReflection->newInstanceWithoutConstructor();

        // Inject all properties exercised by the test scenarios.
        $this->set($processorReflection, $processor, 'persistProcessor', $this->persistProcessor);
        $this->set($processorReflection, $processor, 'removeProcessor', $this->createMock(ProcessorInterface::class));
        $this->set($processorReflection, $processor, 'security', $this->security);
        $this->set($processorReflection, $processor, 'entityManager', $this->entityManager);
        $this->set($processorReflection, $processor, 'tenantSettings', $this->tenantSettings);
        $this->set($processorReflection, $processor, 'saleInvoicing', $this->saleInvoicing);
        $this->set($processorReflection, $processor, 'logger', $this->logger);

        // Inject no-op stubs for final-class properties not exercised by tests.
        $this->injectNoOpStub($processorReflection, $processor, 'saleUtils');
        // branchContextService: inject a real-ish stub that returns null from getCurrentBranchId().
        // We do this by injecting a Symfony Security stub that returns null from getToken().
        $this->injectBranchContextStub($processorReflection, $processor);
        $this->injectNoOpStub($processorReflection, $processor, 'branchRepository');
        // salesWriteGateway: use an interface mock so write() is a no-op (avoids warehouse/register guards).
        /** @var SalesWriteGateway&MockObject $salesWriteGatewayMock */
        $salesWriteGatewayMock = $this->createStub(SalesWriteGateway::class);
        $processorReflection->getProperty('salesWriteGateway')->setValue($processor, $salesWriteGatewayMock);
        $this->injectCashSessionResolverStub($processorReflection, $processor);
        $this->injectNoOpStub($processorReflection, $processor, 'salePaymentPlanService');
        $this->injectNoOpStub($processorReflection, $processor, 'detractionCalculator');
        $this->injectNoOpStub($processorReflection, $processor, 'inventoryOrchestrator');
        // saleRecordRepository: use a mock so findByAnyId() returns null without touching real Doctrine.
        /** @var SaleRecordRepository&MockObject $saleRecordRepoMock */
        $saleRecordRepoMock = $this->createStub(SaleRecordRepository::class);
        $processorReflection->getProperty('saleRecordRepository')->setValue($processor, $saleRecordRepoMock);

        return $processor;
    }

    /**
     * @template T of object
     * @param ReflectionClass<T> $reflection
     * @param T $target
     */
    private function set(ReflectionClass $reflection, object $target, string $name, mixed $value): void
    {
        $reflection->getProperty($name)->setValue($target, $value);
    }

    /**
     * Inject a no-op stub (newInstanceWithoutConstructor) for a named property.
     * The stub's own typed properties are initialized with further no-op stubs
     * or mocks to prevent "must not be accessed before initialization" errors.
     *
     * @param ReflectionClass<SaleProcessor> $processorReflection
     */
    private function injectNoOpStub(
        ReflectionClass $processorReflection,
        SaleProcessor $target,
        string $propertyName,
    ): void {
        $property = $processorReflection->getProperty($propertyName);
        $type = $property->getType();
        if (!$type instanceof ReflectionNamedType || $type->allowsNull()) {
            $property->setValue($target, null);
            return;
        }
        $className = $type->getName();
        if (!class_exists($className) && !interface_exists($className)) {
            $property->setValue($target, null);
            return;
        }
        $stubReflection = new ReflectionClass($className);
        if ($stubReflection->isInterface() || $stubReflection->isAbstract()) {
            $stub = $this->createStub($className);
        } else {
            $stub = $stubReflection->newInstanceWithoutConstructor();
            $this->initializeStubProperties($stubReflection, $stub);
        }
        $property->setValue($target, $stub);
    }

    /**
     * Inject a CashSessionResolver stub where tryResolveForSale() returns null
     * (no open session found) without throwing.
     *
     * @param ReflectionClass<SaleProcessor> $processorReflection
     */
    private function injectCashSessionResolverStub(ReflectionClass $processorReflection, SaleProcessor $target): void
    {
        $resolverRc = new ReflectionClass(CashSessionResolver::class);
        /** @var CashSessionResolver $resolver */
        $resolver = $resolverRc->newInstanceWithoutConstructor();

        /** @var Connection&MockObject $connMock */
        $connMock = $this->createStub(Connection::class);
        $connMock->method('fetchAssociative')->willReturn(false);

        $resolverRc->getProperty('connection')->setValue($resolver, $connMock);
        $processorReflection->getProperty('cashSessionResolver')->setValue($target, $resolver);
    }

    /**
     * Inject a BranchContextService stub into SaleProcessor::$branchContextService.
     *
     * BranchContextService is final readonly. Its getCurrentBranchId() calls
     * $this->security->getToken(). We bypass its constructor, then inject a
     * createMock(SecurityComponent::class) that returns null from getToken() —
     * which causes getCurrentBranchId() to return null safely (no branch set).
     *
     * @param ReflectionClass<SaleProcessor> $processorReflection
     */
    private function injectBranchContextStub(ReflectionClass $processorReflection, SaleProcessor $target): void
    {
        /** @var SecurityComponent&MockObject $securityComponentMock */
        $securityComponentMock = $this->createStub(SecurityComponent::class);
        $securityComponentMock->method('getToken')->willReturn(null);

        $branchContextReflection = new ReflectionClass(BranchContextService::class);
        /** @var BranchContextService $branchContextStub */
        $branchContextStub = $branchContextReflection->newInstanceWithoutConstructor();
        $branchContextReflection->getProperty('security')->setValue($branchContextStub, $securityComponentMock);

        $processorReflection->getProperty('branchContextService')->setValue($target, $branchContextStub);
    }

    /**
     * Recursively initialize typed properties of a stub object with safe defaults
     * (null when nullable, or a further no-op sub-stub when non-nullable).
     * This prevents "must not be accessed before initialization" errors when
     * the stub's real methods access its properties.
     *
     * @param ReflectionClass<object> $reflection
     */
    private function initializeStubProperties(ReflectionClass $reflection, object $stub): void
    {
        foreach ($reflection->getProperties() as $prop) {
            if ($prop->isInitialized($stub)) {
                continue;
            }
            $type = $prop->getType();
            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $depClass = $type->getName();
                if (class_exists($depClass) || interface_exists($depClass)) {
                    try {
                        $depReflection = new ReflectionClass($depClass);
                        if ($depReflection->isInterface() || $depReflection->isAbstract()) {
                            $depStub = $this->createStub($depClass);
                        } else {
                            $depStub = $depReflection->newInstanceWithoutConstructor();
                        }
                        $prop->setValue($stub, $depStub);
                        continue;
                    } catch (\Throwable) {
                        // fall through to null
                    }
                }
            }
            if (!$type instanceof ReflectionNamedType || $type->allowsNull()) {
                $prop->setValue($stub, null);
            }
        }
    }
}
