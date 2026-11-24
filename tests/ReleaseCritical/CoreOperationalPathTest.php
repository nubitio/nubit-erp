<?php

declare(strict_types=1);

namespace App\Tests\ReleaseCritical;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\State\ProcessorInterface;
use App\Billing\Sale\SaleVoidUseCase;
use App\CashRegister\Dto\CashRegisterCloseDto;
use App\CashRegister\State\CashRegisterCloseProcessor;
use App\CashRegister\State\CashRegisterDashboardProvider;
use App\CashRegister\State\CashSessionClosureSummaryProvider;
use App\CashRegister\State\CashSessionMovementsProvider;
use App\CashRegister\State\CashSessionXReportProvider;
use App\Catalog\Entity\PaymentMethod;
use App\Catalog\Entity\Product;
use App\Contacts\Entity\Customer;
use App\Contacts\Repository\CustomerRepository;
use App\Efact\Domain\ActiveReferenceGuard;
use App\Efact\Enum\StockPolicy;
use App\Identity\Auth\BranchContextService;
use App\Identity\Auth\BranchContextServiceInterface;
use App\Identity\Dto\AuthUserDto;
use App\Identity\Security\Security;
use App\Organization\Repository\BranchRepository;
use App\Sales\Domain\SaleSettlementType;
use App\Sales\Entity\SaleRecord;
use App\Sales\Port\InvoiceCreatorInterface;
use App\Sales\Port\Result\InvoiceCreationResult;
use App\Sales\Report\PaymentMethodBreakdownReport;
use App\Sales\Repository\SaleRecordRepository;
use App\Sales\Service\SalePaymentService;
use App\Sales\Service\SaleSettlementPlanService;
use App\Sales\State\CashSessionResolverInterface;
use App\Sales\State\DetractionCalculatorInterface;
use App\Sales\State\SaleInventoryOrchestrator;
use App\Sales\State\SaleProcessor;
use App\Sales\State\SalesWriteGateway;
use App\Sales\State\SaleUtils;
use App\Settings\Service\SunatStateService;
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
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
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
        $saleId = (string) $result->getId();
        if ($saleId === '') {
            self::fail('The persisted sale has no ID.');
        }
        self::assertStringStartsWith($saleId, (string) $movement['source_id']);
    }

    public function testIdempotentReplayDoesNotDoubleStockOrCash(): void
    {
        $world = $this->fixture->seed('10.000000');
        $key = Uuid::v7()->toRfc4122();
        $processor = $this->processor($world);
        $request = Request::create('/api/sales', 'POST');
        $request->headers->set('Idempotency-Key', $key);

        $first = $processor->process(
            $this->newOperationalSale($world, PaymentMethod::CASH, '1'),
            new Post(),
            [],
            ['request' => clone $request],
        );
        self::assertInstanceOf(SaleRecord::class, $first);

        $second = $processor->process(
            $this->newOperationalSale($world, PaymentMethod::CASH, '1'),
            new Post(),
            [],
            ['request' => clone $request],
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

    public function testMariaCreditReportAndCashReconcileBeforeAndAfterPartialCollection(): void
    {
        $world = $this->fixture->seed();
        foreach (['101' => 'Caja', '121' => 'Facturas por cobrar'] as $code => $name) {
            $accountRepository = $this->entityManager->getRepository(\App\Accounting\Entity\Account::class);
            if ($accountRepository->findOneBy(['code' => (string) $code]) === null) {
                $this->entityManager->persist(
                    new \App\Accounting\Entity\Account()
                        ->setCode((string) $code)
                        ->setName($name),
                );
            }
        }
        $this->entityManager->flush();
        $this->recordOpeningMovement($world);
        $world->customer->setBusinessName('SRA MARIA');
        $sale = $this->newOperationalSale($world, PaymentMethod::CREDIT, '1');
        $sale->setWriteItems([[
            'product' => (string) $world->product->getId(),
            'qty' => '1',
            'unitPrice' => '13.88',
        ]]);
        $sale->setSettlements([['type' => 'RECEIVABLE', 'amount' => '13.88']]);
        $this->processor($world)->process($sale, new Post());

        $row = $this->paymentBreakdown($world, $sale);
        static::assertSame('13.88', $row['pm_2']);
        static::assertSame('0.00', $row['total_paid']);
        static::assertSame('13.88', $row['sale_total']);
        $this->assertCashReconciles($world, '50.00');

        $saleId = $sale->getId();
        $this->entityManager->clear();
        $sale = $this->entityManager->find(SaleRecord::class, $saleId);
        static::assertInstanceOf(SaleRecord::class, $sale);
        $cash = $this->entityManager->find(PaymentMethod::class, PaymentMethod::CASH);
        static::assertInstanceOf(PaymentMethod::class, $cash);
        static::getContainer()
            ->get(SalePaymentService::class)
            ->registerPayment($sale, $cash, '3.88', new \DateTimeImmutable(), 'Abono María', $world->user);
        $row = $this->paymentBreakdown($world, $sale);
        static::assertSame('10.00', $row['pm_2']);
        static::assertSame('3.88', $row['pm_1']);
        static::assertSame('3.88', $row['total_paid']);
        $this->assertCashReconciles($world, '53.88');

        // Several collections must not multiply the credit pivot or count the
        // whole invoice as cash each time. Finally settle the remaining debt.
        foreach ([
            ['amount' => '1.12', 'pending' => '8.88', 'paid' => '5.00', 'cash' => '55.00'],
            ['amount' => '8.88', 'pending' => '0.00', 'paid' => '13.88', 'cash' => '63.88'],
        ] as $collection) {
            static::getContainer()
                ->get(SalePaymentService::class)
                ->registerPayment(
                    $sale,
                    $cash,
                    $collection['amount'],
                    new \DateTimeImmutable(),
                    'Abono adicional María',
                    $world->user,
                );
            $row = $this->paymentBreakdown($world, $sale);
            static::assertSame($collection['pending'], $row['pm_2']);
            static::assertSame($collection['paid'], $row['pm_1']);
            static::assertSame($collection['paid'], $row['total_paid']);
            $this->assertCashReconciles($world, $collection['cash']);
        }
        $this->closeWithExpectedCount($world, '63.88');
    }

    public function testVoidingUnpaidCreditDoesNotRefundMoneyThatNeverEnteredTheDrawer(): void
    {
        $world = $this->fixture->seed();
        $this->recordOpeningMovement($world);
        $sale = $this->processor($world)->process(
            $this->newOperationalSale($world, PaymentMethod::CREDIT, '1'),
            new Post(),
        );
        static::assertInstanceOf(SaleRecord::class, $sale);
        $saleId = (string) $sale->getId();
        $this->entityManager->clear();
        static::getContainer()
            ->get(SaleVoidUseCase::class)
            ->__invoke($saleId, 'Anulación de crédito sin abonos', (int) $world->user->getId(), true);
        static::assertSame(0, $this->fixture->cashRefundCount($saleId));
        $this->assertCashReconciles($world, '50.00');
        $this->closeWithExpectedCount($world, '50.00');
    }

    public function testMixedCashAndCreditReconcileAndDoNotAllowVoidingAppliedPayments(): void
    {
        $world = $this->fixture->seed();
        $this->recordOpeningMovement($world);
        $sale = $this->newOperationalSale($world, PaymentMethod::CASH, '1');
        $sale->setSettlements([
            ['type' => 'PAYMENT', 'paymentMethodId' => PaymentMethod::CASH, 'amount' => '4.00'],
            ['type' => 'RECEIVABLE', 'amount' => '6.00'],
        ]);
        $this->processor($world)->process($sale, new Post());
        $row = $this->paymentBreakdown($world, $sale);
        static::assertSame('4.00', $row['pm_1']);
        static::assertSame('6.00', $row['pm_2']);
        static::assertSame('4.00', $row['total_paid']);
        $this->assertCashReconciles($world, '54.00');

        try {
            static::getContainer()
                ->get(SaleVoidUseCase::class)
                ->__invoke((string) $sale->getId(), 'Anulación mixta', (int) $world->user->getId(), true);
            static::fail('Credit with applied payments must not be voided.');
        } catch (\App\Sales\Domain\SaleException $exception) {
            static::assertStringContainsString('cobros aplicados', $exception->getMessage());
        }
        $this->assertCashReconciles($world, '54.00');
        $this->entityManager->clear();
        $this->closeWithExpectedCount($world, '54.00');
    }

    public function testMixedCashAndDigitalVoidRefundsOnlyCashAndEveryCashViewAgrees(): void
    {
        $world = $this->fixture->seed();
        $this->recordOpeningMovement($world);
        $digital = new PaymentMethod()
            ->setName('Yape kernel')
            ->setIsActive(true);
        $this->entityManager->persist($digital);
        $this->entityManager->flush();
        $sale = $this->newOperationalSale($world, PaymentMethod::CASH, '1');
        $sale->setSettlements([
            ['type' => 'PAYMENT', 'paymentMethodId' => PaymentMethod::CASH, 'amount' => '4.00'],
            ['type' => 'PAYMENT', 'paymentMethodId' => $digital->getId(), 'amount' => '6.00'],
        ]);
        $this->processor($world)->process($sale, new Post());
        $this->assertCashReconciles($world, '54.00');
        $saleId = $sale->getId();
        $this->entityManager->clear();
        static::getContainer()
            ->get(SaleVoidUseCase::class)
            ->__invoke((string) $saleId, 'Anulación con pago mixto', (int) $world->user->getId(), true);
        static::assertSame('4.00', $this->entityManager->getConnection()->fetchOne("SELECT amount FROM cash_movements WHERE sale_id = :sale AND movement_type = 'REFUND_OUT'", [
            'sale' => $saleId,
        ]));
        $this->assertCashReconciles($world, '50.00');
        $this->closeWithExpectedCount($world, '50.00');
    }

    public function testClosingUsesSessionOpeningAndRejectsASecondCloseFromAStaleEntity(): void
    {
        $world = $this->fixture->seed();
        $this->closeWithExpectedCount($world, '50.00');

        $sessionId = $world->cashSession->getId();
        // Simulate a request whose identity map was populated before the first close committed.
        $world->cashSession->setStatus('OPEN');

        $dto = new CashRegisterCloseDto();
        $dto->countedAmount = '99.00';
        $processor = new CashRegisterCloseProcessor(
            $this->entityManager,
            $this->securityStub((int) $world->user->getId()),
        );
        try {
            $processor->process($dto, new Put(), ['id' => $sessionId]);
            static::fail('A second close must not overwrite the persisted cut.');
        } catch (ConflictHttpException) {
            static::assertSame('50.00', $this->entityManager->getConnection()->fetchOne('SELECT counted_amount FROM cash_sessions WHERE id = ?', [
                $sessionId,
            ]));
        }
    }

    private function recordOpeningMovement(CoreSaleWorld $world): void
    {
        $this->entityManager->getConnection()->executeStatement("INSERT INTO cash_movements (cash_session_id, movement_type, amount, created_by, created_at)
             VALUES (?, 'OPENING', ?, ?, NOW())", [
            $world->cashSession->getId(),
            $world->cashSession->getOpeningAmount(),
            $world->user->getId(),
        ]);
    }

    /** @return array<string, mixed> */
    private function paymentBreakdown(CoreSaleWorld $world, SaleRecord $sale): array
    {
        $sunat = $this->createStub(SunatStateService::class);
        $sunat->method('isReceiptRequired')->willReturn(false);
        $connection = $this->entityManager->getConnection();
        $report = new PaymentMethodBreakdownReport($sunat, $connection);
        $row = $connection->fetchAssociative($report->sql('AND s.id = :sale'), [
            'date_from' => $sale->getIssuedDate()?->format('Y-m-d'),
            'date_to' => $sale->getIssuedDate()?->format('Y-m-d'),
            'local_id' => $world->branch->getId(),
            'sale' => $sale->getId(),
        ]);
        static::assertIsArray($row);

        return $row;
    }

    private function assertCashReconciles(CoreSaleWorld $world, string $expected): void
    {
        $container = static::getContainer();
        $connection = $this->entityManager->getConnection();
        $branchContext = $this->createStub(BranchContextServiceInterface::class);
        $branchContext->method('getCurrentBranchId')->willReturn($world->branch->getId());
        $dashboard = new CashRegisterDashboardProvider($connection, $branchContext)->provide(new GetCollection());
        static::assertEqualsWithDelta((float) $expected, $dashboard[0]->balance, 0.001, 'Cash dashboard');
        $variables = ['id' => $world->cashSession->getId()];
        $summary = $container->get(CashSessionClosureSummaryProvider::class)->provide(new GetCollection(), $variables);
        static::assertSame($expected, $summary[0]->expectedAmount, 'Closure preview');
        $xReport = $container->get(CashSessionXReportProvider::class)->provide(new Get(), $variables);
        static::assertSame($expected, $xReport->currentBalance, 'X report');
        $movements = $container->get(CashSessionMovementsProvider::class)->provide(new GetCollection(), $variables);
        $gridBalance = 0.0;
        foreach ($movements as $movement) {
            $gridBalance += ($movement->getType()->value === 'income' ? 1 : -1) * (float) $movement->getAmount();
        }
        static::assertEqualsWithDelta((float) $expected, $gridBalance, 0.001, 'Movement grid balance');
    }

    private function closeWithExpectedCount(CoreSaleWorld $world, string $expected): void
    {
        $dto = new CashRegisterCloseDto();
        $dto->closureItems = [['countedAmount' => $expected, 'paymentMethod' => '/api/payment-methods/1']];
        new CashRegisterCloseProcessor($this->entityManager, $this->securityStub((int) $world->user->getId()))->process(
            $dto,
            new Put(),
            ['id' => $world->cashSession->getId()],
        );
        $closed = $this->entityManager->find(\App\CashRegister\Entity\CashSession::class, $world->cashSession->getId());
        static::assertInstanceOf(\App\CashRegister\Entity\CashSession::class, $closed);
        static::assertSame($expected, $closed->getExpectedAmount());
        static::assertSame('0.00', $closed->getDifferenceAmount());
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
}
