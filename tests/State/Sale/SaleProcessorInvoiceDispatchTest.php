<?php

declare(strict_types=1);

namespace App\Tests\State\Sale;

use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Core\Application\Session\Security;
use App\Core\Security\Auth\BranchContextService;
use App\Efact\Service\SettingsService;
use App\Efact\Config\EfactConstants as Constants;
use App\Identity\Dto\AuthUserDto;
use App\EInvoicing\Service\InvoiceCreationServiceInterface;
use App\Organization\Entity\Branch;
use App\Sales\Entity\SaleRecord;
use App\Message\SendInvoiceMessage;
use App\Organization\Repository\BranchRepository;
use App\Sales\Repository\SaleRecordRepository;
use App\Sales\State\CashSessionResolver;
use App\Sales\State\DetractionCalculator;
use App\Sales\State\SaleInventoryOrchestrator;
use App\Sales\Service\SalePaymentPlanService;
use App\Sales\State\SaleUtils;
use App\Sales\State\SalesWriteGateway;
use Doctrine\DBAL\Connection;
use App\Sales\State\SaleProcessor;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use ReflectionNamedType;
use Symfony\Bundle\SecurityBundle\Security as SecurityComponent;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

/**
 * Unit tests for the invoice dispatch behaviour in SaleProcessor::process().
 *
 * SaleProcessor has many final constructor dependencies. To avoid extending or
 * modifying production code solely for test purposes, we bypass the constructor
 * with ReflectionClass::newInstanceWithoutConstructor() and inject only the
 * properties exercised by the two test scenarios.
 *
 * Covers:
 * - TC-1: dispatch() is NOT called when createForSale() returns success=false
 * - TC-2: dispatch() carries a DispatchAfterCurrentBusStamp when createForSale() succeeds
 */
final class SaleProcessorInvoiceDispatchTest extends TestCase
{
    /** @var MessageBusInterface&MockObject */
    private MessageBusInterface $messageBus;
    /** @var InvoiceCreationServiceInterface&MockObject */
    private InvoiceCreationServiceInterface $invoiceCreationService;
    /** @var Security&MockObject */
    private Security $security;
    /** @var ProcessorInterface<mixed, mixed>&MockObject */
    private ProcessorInterface $persistProcessor;
    /** @var EntityManagerInterface&MockObject */
    private EntityManagerInterface $entityManager;
    /** @var LoggerInterface&MockObject */
    private LoggerInterface $logger;
    /** @var SettingsService&MockObject */
    private SettingsService $settings;

    protected function setUp(): void
    {
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->invoiceCreationService = $this->createMock(InvoiceCreationServiceInterface::class);
        $this->security = $this->createMock(Security::class);
        $this->persistProcessor = $this->createMock(ProcessorInterface::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->settings = $this->createMock(SettingsService::class);
    }

    /**
     * TC-1: When InvoiceCreationService::createForSale() returns ['success' => false],
     * MessageBusInterface::dispatch() must never be called.
     */
    public function testDispatchNotCalledWhenCreateForSaleFails(): void
    {
        $sale = $this->buildOperationalSale(emitInvoice: true);

        $this->invoiceCreationService
            ->method('createForSale')
            ->willReturn(['success' => false, 'message' => 'err']);

        $this->messageBus
            ->expects($this->never())
            ->method('dispatch');

        $processor = $this->buildProcessor();
        $processor->process($sale, new Post(), [], []);
    }

    /**
     * TC-2: When InvoiceCreationService::createForSale() returns ['success' => true],
     * dispatch() must be called with:
     * - a SendInvoiceMessage carrying the returned invoiceId, and
     * - a stamps array that contains a DispatchAfterCurrentBusStamp instance.
     */
    public function testDispatchIncludesDispatchAfterCurrentBusStamp(): void
    {
        $invoiceId = 'test-uuid-123';
        $sale = $this->buildOperationalSale(emitInvoice: true);

        $this->invoiceCreationService
            ->method('createForSale')
            ->willReturn(['success' => true, 'invoiceId' => $invoiceId]);

        /** @var SendInvoiceMessage|null $capturedMessage */
        $capturedMessage = null;
        /** @var array<object>|null $capturedStamps */
        $capturedStamps = null;

        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->callback(
                    static function (mixed $msg) use (&$capturedMessage): bool {
                        if (!$msg instanceof SendInvoiceMessage) {
                            return false;
                        }
                        $capturedMessage = $msg;
                        return true;
                    }
                ),
                $this->callback(
                    static function (mixed $stamps) use (&$capturedStamps): bool {
                        if (!is_array($stamps)) {
                            return false;
                        }
                        $capturedStamps = $stamps;
                        return true;
                    }
                ),
            )
            ->willReturn(new Envelope(new SendInvoiceMessage($invoiceId, 'test-tenant')));

        $processor = $this->buildProcessor();
        $processor->process($sale, new Post(), [], []);

        self::assertInstanceOf(SendInvoiceMessage::class, $capturedMessage);
        self::assertSame($invoiceId, $capturedMessage->taxDocumentId);

        self::assertIsArray($capturedStamps);
        $hasStamp = false;
        foreach ($capturedStamps as $stamp) {
            if ($stamp instanceof DispatchAfterCurrentBusStamp) {
                $hasStamp = true;
                break;
            }
        }
        self::assertTrue(
            $hasStamp,
            'Stamps array must contain a DispatchAfterCurrentBusStamp instance'
        );
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
        (new \ReflectionClass(Branch::class))->getProperty('id')->setValue($branch, 'branch-1');
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
        $this->security
            ->method('connectedUser')
            ->willReturn(new AuthUserDto(userId: 1, username: 'test'));

        // persistProcessor returns the Sale entity unchanged.
        $this->persistProcessor
            ->method('process')
            ->willReturnArgument(0);

        // AUTO_INVOICE=false; emitInvoice flag on the entity drives the path.
        // REQUIRE_CASH_REGISTER=false so no cash-shift check.
        $this->settings
            ->method('get')
            ->willReturnMap([
                [Constants::AUTO_INVOICE, 'false', 'false'],
                [Constants::REQUIRE_CASH_REGISTER, 'true', 'false'],
            ]);

        // Build SaleProcessor bypassing constructor.
        $processorReflection = new ReflectionClass(SaleProcessor::class);
        /** @var SaleProcessor $processor */
        $processor = $processorReflection->newInstanceWithoutConstructor();

        // Inject all properties exercised by the test scenarios.
        $this->set($processorReflection, $processor, 'persistProcessor', $this->persistProcessor);
        $this->set($processorReflection, $processor, 'removeProcessor', $this->createMock(ProcessorInterface::class));
        $this->set($processorReflection, $processor, 'security', $this->security);
        $this->set($processorReflection, $processor, 'entityManager', $this->entityManager);
        $this->set($processorReflection, $processor, 'settings', $this->settings);
        $this->set($processorReflection, $processor, 'invoiceCreationService', $this->invoiceCreationService);
        $this->set($processorReflection, $processor, 'logger', $this->logger);
        $this->set($processorReflection, $processor, 'messageBus', $this->messageBus);

        // Inject no-op stubs for final-class properties not exercised by tests.
        $this->injectNoOpStub($processorReflection, $processor, 'saleUtils');
        // branchContextService: inject a real-ish stub that returns null from getCurrentBranchId().
        // We do this by injecting a Symfony Security stub that returns null from getToken().
        $this->injectBranchContextStub($processorReflection, $processor);
        $this->injectNoOpStub($processorReflection, $processor, 'branchRepository');
        // salesWriteGateway: use an interface mock so write() is a no-op (avoids warehouse/register guards).
        /** @var SalesWriteGateway&MockObject $salesWriteGatewayMock */
        $salesWriteGatewayMock = $this->createMock(SalesWriteGateway::class);
        $processorReflection->getProperty('salesWriteGateway')->setValue($processor, $salesWriteGatewayMock);
        $this->injectCashSessionResolverStub($processorReflection, $processor);
        $this->injectNoOpStub($processorReflection, $processor, 'salePaymentPlanService');
        $this->injectNoOpStub($processorReflection, $processor, 'detractionCalculator');
        $this->injectNoOpStub($processorReflection, $processor, 'inventoryOrchestrator');
        // saleRecordRepository: use a mock so findByAnyId() returns null without touching real Doctrine.
        /** @var SaleRecordRepository&MockObject $saleRecordRepoMock */
        $saleRecordRepoMock = $this->createMock(SaleRecordRepository::class);
        $processorReflection->getProperty('saleRecordRepository')->setValue($processor, $saleRecordRepoMock);
        $processorReflection->getProperty('tenantContext')->setValue($processor, new \App\Core\Context\TenantContext());

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
        string $propertyName
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
            $stub = $this->createMock($className);
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
    private function injectCashSessionResolverStub(
        ReflectionClass $processorReflection,
        SaleProcessor $target
    ): void {
        $resolverRc = new ReflectionClass(CashSessionResolver::class);
        /** @var CashSessionResolver $resolver */
        $resolver = $resolverRc->newInstanceWithoutConstructor();

        /** @var Connection&MockObject $connMock */
        $connMock = $this->createMock(Connection::class);
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
    private function injectBranchContextStub(
        ReflectionClass $processorReflection,
        SaleProcessor $target
    ): void {
        /** @var SecurityComponent&MockObject $securityComponentMock */
        $securityComponentMock = $this->createMock(SecurityComponent::class);
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
                            $depStub = $this->createMock($depClass);
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
            if (!($type instanceof ReflectionNamedType) || $type->allowsNull()) {
                $prop->setValue($stub, null);
            }
        }
    }
}
