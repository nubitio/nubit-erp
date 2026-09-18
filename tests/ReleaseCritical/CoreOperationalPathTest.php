<?php

declare(strict_types=1);

namespace App\Tests\ReleaseCritical;

use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Billing\Sale\SaleVoidUseCase;
use App\Catalog\Entity\PaymentMethod;
use App\Catalog\Entity\Product;
use App\Contacts\Entity\Customer;
use App\Contacts\Repository\CustomerRepository;
use App\Efact\Domain\ActiveReferenceGuard;
use App\Identity\Auth\BranchContextService;
use App\Identity\Dto\AuthUserDto;
use App\Identity\Security\Security;
use App\Inventory\Core\Enum\StockPolicy;
use App\Organization\Repository\BranchRepository;
use App\Sales\Domain\SaleSettlementType;
use App\Sales\Entity\SaleRecord;
use App\Sales\Port\InvoiceCreatorInterface;
use App\Sales\Port\Result\InvoiceCreationResult;
use App\Sales\Repository\SaleRecordRepository;
use App\Sales\Service\SaleSettlementPlanService;
use App\Sales\State\CashSessionResolverInterface;
use App\Sales\State\DetractionCalculatorInterface;
use App\Sales\State\SaleInventoryOrchestrator;
use App\Sales\State\SaleProcessor;
use App\Sales\State\SalesWriteGateway;
use App\Sales\State\SaleUtils;
use App\Settings\Service\TenantSettingsService;
use App\Tests\ReleaseCritical\Support\CoreSaleFixture;
use App\Tests\ReleaseCritical\Support\CoreSaleWorld;
use Doctrine\ORM\EntityManagerInterface;
use Nubit\AdminBundle\Auth\JWTAuthenticationToken;
use Nubit\AdminBundle\Auth\JWTManagerInterface;
use Nubit\Platform\Exception\DomainProblemException;
use Nubit\Platform\Exception\ServiceException;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\SecurityBundle\Security as SecurityComponent;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Kernel characterization of the operational sale path (stock + cash).
 */
final class CoreOperationalPathTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    private CoreSaleFixture $fixture;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->fixture = new CoreSaleFixture(
            $this->entityManager,
            static::getContainer()->get(TenantSettingsService::class),
        );
    }

    public function testCreatedProductAndCustomerArePersistedAndSellable(): void
    {
        $world = $this->fixture->seed('1.000000');

        $product = $this->entityManager->find(Product::class, $world->product->getId());
        $customer = $this->entityManager->find(Customer::class, $world->customer->getId());
        self::assertInstanceOf(Product::class, $product);
        self::assertTrue($product->getTrackInventory());
        self::assertInstanceOf(Customer::class, $customer);
        self::assertNotSame('', $customer->getBusinessName());

        $result = $this->processor($world)->process(
            $this->newOperationalSale($world, PaymentMethod::CASH, '1'),
            new Post(),
        );
        self::assertInstanceOf(SaleRecord::class, $result);
        self::assertEqualsWithDelta(0.0, (float) $this->fixture->onHand($world->warehouse, $world->product), 0.001);
    }

    public function testCashSaleDecrementsStockAndWritesCashMovement(): void
    {
        $world = $this->fixture->seed('20.000000');
        $result = $this->processor($world)->process(
            $this->newOperationalSale($world, PaymentMethod::CASH, '2'),
            new Post(),
        );

        self::assertInstanceOf(SaleRecord::class, $result);
        self::assertSame('COMPLETED', $result->getStatus());
        self::assertSame('0.00', $result->getBalanceDue());
        self::assertEqualsWithDelta(18.0, (float) $this->fixture->onHand($world->warehouse, $world->product), 0.001);
        self::assertSame(1, $this->fixture->cashMovementCount((string) $result->getId()));

        $movement = $this->entityManager->getConnection()->fetchAssociative('SELECT source_id, source_type FROM stock_movements WHERE product_id = :p ORDER BY created_at DESC LIMIT 1', [
            'p' => (string) $world->product->getId(),
        ]);
        self::assertIsArray($movement);
        self::assertSame('SALE', $movement['source_type']);
        self::assertStringStartsWith((string) $result->getId(), (string) $movement['source_id']);
    }

    public function testIdempotentReplayDoesNotDoubleStockOrCash(): void
    {
        $world = $this->fixture->seed('10.000000');
        $key = Uuid::v7()->toRfc4122();
        $processor = $this->processor($world);

        $first = $processor->process(
            $this->newOperationalSale($world, PaymentMethod::CASH, '1'),
            new Post(),
            [],
            ['request' => $this->saleRequest($key)],
        );
        self::assertInstanceOf(SaleRecord::class, $first);

        $second = $processor->process(
            $this->newOperationalSale($world, PaymentMethod::CASH, '1'),
            new Post(),
            [],
            ['request' => $this->saleRequest($key)],
        );

        self::assertInstanceOf(SaleRecord::class, $second);
        self::assertSame($first->getId(), $second->getId());
        self::assertEqualsWithDelta(9.0, (float) $this->fixture->onHand($world->warehouse, $world->product), 0.001);
        self::assertSame(1, $this->fixture->cashMovementCount((string) $first->getId()));
    }

    public function testCreditSaleDoesNotWriteCashAndLeavesBalanceDue(): void
    {
        $world = $this->fixture->seed('10.000000');
        $result = $this->processor($world)->process(
            $this->newOperationalSale($world, PaymentMethod::CREDIT, '1'),
            new Post(),
        );

        self::assertInstanceOf(SaleRecord::class, $result);
        self::assertSame('10.00', $result->getBalanceDue());
        self::assertTrue($result->isReceivableAccount());
        self::assertSame(0, $this->fixture->cashMovementCount((string) $result->getId()));
        self::assertEqualsWithDelta(9.0, (float) $this->fixture->onHand($world->warehouse, $world->product), 0.001);
    }

    public function testVoidRestoresStockAndKeepsTheSaleRow(): void
    {
        $world = $this->fixture->seed('5.000000');
        $result = $this->processor($world)->process(
            $this->newOperationalSale($world, PaymentMethod::CASH, '1'),
            new Post(),
        );
        self::assertInstanceOf(SaleRecord::class, $result);
        $saleId = (string) $result->getId();

        static::getContainer()
            ->get(SaleVoidUseCase::class)
            ->__invoke($saleId, 'Anulación kernel', (int) $world->user->getId(), true);
        $this->entityManager->clear();

        $voided = static::getContainer()->get(SaleRecordRepository::class)->findByAnyId($saleId);
        self::assertInstanceOf(SaleRecord::class, $voided);
        self::assertSame('VOIDED', $voided->getStatus());
        self::assertEqualsWithDelta(5.0, (float) $this->fixture->onHand($world->warehouse, $world->product), 0.001);
        self::assertSame(1, $this->fixture->cashMovementCount($saleId));
        self::assertSame(1, $this->fixture->cashRefundCount($saleId));
    }

    public function testInsufficientStockUnderStrictPolicyDoesNotPersistSale(): void
    {
        $world = $this->fixture->seed('1.000000');
        $settings = static::getContainer()->get(TenantSettingsService::class);
        $tenant = $settings->get();
        $tenant->setNegativeStockPolicy(StockPolicy::STRICT);
        $settings->save($tenant);

        try {
            $this->processor($world)->process($this->newOperationalSale($world, PaymentMethod::CASH, '5'), new Post());
            self::fail('Expected insufficient-stock rejection.');
        } catch (ServiceException $exception) {
            self::assertMatchesRegularExpression('/Stock insuficiente/', $exception->getMessage());
        }

        self::assertEqualsWithDelta(1.0, (float) $this->fixture->onHand($world->warehouse, $world->product), 0.001);
        $count =
            (int) $this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM sales WHERE warehouse_id = :warehouse', [
                'warehouse' => (string) $world->warehouse->getId(),
            ]);
        self::assertSame(0, $count);
    }

    public function testSaleWithoutOpenCashSessionIsRejected(): void
    {
        $world = $this->fixture->seed();
        $this->fixture->closeSession($world->cashSession);

        $this->expectException(DomainProblemException::class);
        $this->expectExceptionMessageMatches('/caja abierta/');

        $this->processor($world)->process($this->newOperationalSale($world, PaymentMethod::CASH, '1'), new Post());
    }

    private function newOperationalSale(CoreSaleWorld $world, int $paymentMethodId, string $qty): SaleRecord
    {
        $method = $paymentMethodId === PaymentMethod::CREDIT ? $world->creditMethod : $world->cashMethod;
        $amount = number_format((float) $qty * 10, 2, '.', '');
        $sale = new SaleRecord()
            ->setTipoId(2)
            ->setWarehouse($world->warehouse)
            ->setBranch($world->branch)
            ->setCustomer($world->customer)
            ->setTipoPago($method)
            ->setCajaId($world->cashRegister->getId())
            ->setFecha(new \DateTimeImmutable())
            ->setCurrency('PEN')
            ->setDescuento('0')
            ->setEmitInvoice(false)
            ->setWriteItems([
                [
                    'product' => (string) $world->product->getId(),
                    'qty' => $qty,
                    'unitPrice' => '10.00',
                ],
            ]);

        if ($paymentMethodId === PaymentMethod::CREDIT) {
            $sale->setSettlements([
                [
                    'type' => SaleSettlementType::RECEIVABLE->value,
                    'amount' => $amount,
                    'currency' => 'PEN',
                ],
            ]);
        } else {
            $sale->setPayments([
                [
                    'payment_method_id' => PaymentMethod::CASH,
                    'amount' => $amount,
                ],
            ]);
        }

        return $sale;
    }

    private function processor(CoreSaleWorld $world): SaleProcessor
    {
        $container = static::getContainer();

        /** @var ProcessorInterface<mixed, mixed>&\PHPUnit\Framework\MockObject\Stub $persist */
        $persist = $this->createStub(ProcessorInterface::class);
        $persist->method('process')->willReturnArgument(0);
        /** @var ProcessorInterface<mixed, mixed>&\PHPUnit\Framework\MockObject\Stub $remove */
        $remove = $this->createStub(ProcessorInterface::class);

        $jwt = $container->get(JWTManagerInterface::class);
        $encoded = $jwt->encode(['branchId' => $world->branch->getId() ?? '', 'sub' => 'kernel-core']);
        $token = new JWTAuthenticationToken($world->user, 'api', ['ROLE_ADMIN'], $encoded);
        $symfonySecurity = $this->createStub(SecurityComponent::class);
        $symfonySecurity->method('getToken')->willReturn($token);

        return new SaleProcessor(
            $persist,
            $remove,
            $this->securityStub((int) $world->user->getId()),
            $this->entityManager,
            $container->get(SaleUtils::class),
            $container->get(TenantSettingsService::class),
            new class() implements InvoiceCreatorInterface {
                public function createAndDispatchInvoiceForSaleId(string $saleId): array
                {
                    return ['success' => false, 'message' => 'invoicing disabled in kernel characterization'];
                }

                public function createInvoiceForSaleId(
                    string $saleId,
                    ?\App\Sales\Domain\RequestedVoucherType $requestedVoucherType = null,
                ): InvoiceCreationResult {
                    return InvoiceCreationResult::failure('invoicing disabled in kernel characterization');
                }
            },
            $container->get(LoggerInterface::class),
            new BranchContextService($symfonySecurity, $jwt),
            $container->get(BranchRepository::class),
            $container->get(SalesWriteGateway::class),
            $container->get(CashSessionResolverInterface::class),
            $container->get(SaleSettlementPlanService::class),
            $container->get(DetractionCalculatorInterface::class),
            $container->get(SaleInventoryOrchestrator::class),
            $container->get(SaleRecordRepository::class),
            $container->get(CustomerRepository::class),
            $container->get(ActiveReferenceGuard::class),
            $container->get(MessageBusInterface::class),
        );
    }

    private function securityStub(int $userId): Security
    {
        return new class($userId) implements Security {
            public function __construct(
                private readonly int $userId,
            ) {}

            public function isAuthenticated(): bool
            {
                return true;
            }

            public function connectedUser(): AuthUserDto
            {
                return new AuthUserDto($this->userId, 'kernel-core', 1, ['ROLE_ADMIN']);
            }

            public function hasPermission(string $permission): bool
            {
                return true;
            }
        };
    }

    private function saleRequest(string $idempotencyKey): Request
    {
        $request = Request::create('/api/sales', 'POST');
        $request->headers->set('Idempotency-Key', $idempotencyKey);

        return $request;
    }
}
