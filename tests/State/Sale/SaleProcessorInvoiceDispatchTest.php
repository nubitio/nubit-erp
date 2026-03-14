<?php

declare(strict_types=1);

namespace App\Tests\State\Sale;

use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Core\Application\Session\Security;
use App\Core\Security\Auth\BranchContextService;
use App\Core\Service\SettingsService;
use App\Core\Util\Constants;
use App\Dto\Auth\AuthUserDto;
use App\EInvoicing\Service\InvoiceCreationServiceInterface;
use App\Legacy\Sale;
use App\Message\SendInvoiceMessage;
use App\State\Purchase\PurchasesWritePolicy;
use App\State\Purchase\PurchasesWritePolicyResolver;
use App\State\Sale\SaleProcessor;
use App\State\Sale\SalesWritePolicy;
use App\State\Sale\SalesWritePolicyResolver;
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

    // SalesWritePolicyResolver and PurchasesWritePolicyResolver are final classes.
    // We create real (but constructor-bypassed) instances and wrap them with
    // closure-backed stubs to control return values, injected via reflection.
    /** @var SalesWritePolicyResolver */
    private SalesWritePolicyResolver $salesWritePolicyResolver;
    /** @var PurchasesWritePolicyResolver */
    private PurchasesWritePolicyResolver $purchasesWritePolicyResolver;

    protected function setUp(): void
    {
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->invoiceCreationService = $this->createMock(InvoiceCreationServiceInterface::class);
        $this->security = $this->createMock(Security::class);
        $this->persistProcessor = $this->createMock(ProcessorInterface::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->settings = $this->createMock(SettingsService::class);

        // Create final-class stubs via reflection (no constructor invoked).
        $this->salesWritePolicyResolver = $this->newInstanceWithoutConstructor(SalesWritePolicyResolver::class);
        $this->purchasesWritePolicyResolver = $this->newInstanceWithoutConstructor(
            PurchasesWritePolicyResolver::class
        );
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
            ->willReturn(new Envelope(new SendInvoiceMessage($invoiceId)));

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
    private function buildOperationalSale(bool $emitInvoice): Sale
    {
        $sale = new Sale();
        $sale->setTipoId(2);
        $sale->setEmitInvoice($emitInvoice);

        // Provide a minimal payments entry so resolveForWrite() succeeds.
        // total = '0' (no items), amount = '0.01' → diff = 0.01 ≤ 0.01 tolerance ✓.
        $sale->setPayments([['payment_method_id' => 1, 'amount' => '0.01']]);

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

        // Inject the legacy policy into the final SalesWritePolicyResolver stub.
        $swpr = new ReflectionClass(SalesWritePolicyResolver::class);
        $swPolicyProp = $swpr->getProperty('settings');
        $swPolicyProp->setValue($this->salesWritePolicyResolver, $this->buildLegacySalesSettings());

        // Inject the legacy policy into the final PurchasesWritePolicyResolver stub.
        $pwpr = new ReflectionClass(PurchasesWritePolicyResolver::class);
        $pwPolicyProp = $pwpr->getProperty('settings');
        $pwPolicyProp->setValue($this->purchasesWritePolicyResolver, $this->buildLegacySalesSettings());

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
        $this->set($processorReflection, $processor, 'salesWritePolicyResolver', $this->salesWritePolicyResolver);
        $this->set(
            $processorReflection,
            $processor,
            'purchasesWritePolicyResolver',
            $this->purchasesWritePolicyResolver
        );

        // Inject no-op stubs for final-class properties not exercised by tests.
        $this->injectNoOpStub($processorReflection, $processor, 'saleUtils');
        // branchContextService: inject a real-ish stub that returns null from getCurrentBranchId().
        // We do this by injecting a Symfony Security stub that returns null from getToken().
        $this->injectBranchContextStub($processorReflection, $processor);
        $this->injectNoOpStub($processorReflection, $processor, 'branchRepository');
        $this->injectNoOpStub($processorReflection, $processor, 'salesWriteGateway');
        $this->injectNoOpStub($processorReflection, $processor, 'goodsReceiptWriteGateway');
        $this->injectNoOpStub($processorReflection, $processor, 'cashSessionResolver');
        $this->injectNoOpStub($processorReflection, $processor, 'salePaymentPlanService');
        $this->injectNoOpStub($processorReflection, $processor, 'detractionCalculator');
        $this->injectNoOpStub($processorReflection, $processor, 'inventoryOrchestrator');

        return $processor;
    }

    /**
     * Build a SettingsService stub that always returns 'legacy' for any key.
     * Used to drive the SalesWritePolicyResolver and PurchasesWritePolicyResolver
     * into legacy (=no-schema-write, no-skip) mode without real DB access.
     */
    private function buildLegacySalesSettings(): SettingsService
    {
        /** @var SettingsService&MockObject $stub */
        $stub = $this->createMock(SettingsService::class);
        $stub->method('get')->willReturn('legacy');
        return $stub;
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

    /**
     * Instantiate any class without invoking its constructor.
     *
     * @template T of object
     * @param class-string<T> $className
     * @return T
     */
    private function newInstanceWithoutConstructor(string $className): object
    {
        /** @var T */
        return (new ReflectionClass($className))->newInstanceWithoutConstructor();
    }
}
