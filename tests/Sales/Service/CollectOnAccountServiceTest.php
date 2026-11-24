<?php

declare(strict_types=1);

namespace App\Tests\Sales\Service;

use App\Accounting\Message\EconomicEvent;
use App\Catalog\Entity\PaymentMethod;
use App\Catalog\Enum\SettlementBehavior;
use App\Efact\Observability\OperationLog;
use App\Identity\Entity\User;
use App\Sales\Entity\CustomerAccountEntry;
use App\Sales\Entity\CustomerPaymentApplication;
use App\Sales\Entity\SalePayment;
use App\Sales\Entity\SaleRecord;
use App\Sales\Repository\CustomerAccountEntryRepository;
use App\Sales\Repository\SaleRecordRepository;
use App\Sales\Service\CollectOnAccountPlanner;
use App\Sales\Service\CollectOnAccountService;
use App\Sales\Service\CustomerAccountLedger;
use App\Sales\Service\SalePaymentService;
use App\Sales\State\SalesWriteGateway;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

#[AllowMockObjectsWithoutExpectations]
final class CollectOnAccountServiceTest extends TestCase
{
    /** @var list<EconomicEvent> */
    private array $dispatched = [];
    private SalesWriteGateway&MockObject $gateway;
    private SalePaymentService&MockObject $salePaymentService;

    protected function setUp(): void
    {
        $this->dispatched = [];
        $this->gateway = $this->createMock(SalesWriteGateway::class);
        $this->salePaymentService = $this->createMock(SalePaymentService::class);
        $this->salePaymentService
            ->method('appendPayment')
            ->willReturnCallback(fn(): SalePayment => $this->salePayment(random_int(1, 99999)));
    }

    public function testFifoAppliesAcrossSalesAndBooksTheMoneyOnce(): void
    {
        $sales = $this->salesRepo([$this->sale('s1', '70.00'), $this->sale('s2', '50.00')]);
        $ledger = $this->createMock(CustomerAccountLedger::class);
        $ledger
            ->expects(self::exactly(2))
            ->method('recordPayment')
            ->willReturn($this->createStub(CustomerAccountEntry::class));

        $cashWrite = static::once();
        $this->gateway->expects($cashWrite)->method('writeOnAccountCollectionMovement');
        $em = $this->createMock(EntityManagerInterface::class);
        $em
            ->expects(static::once())
            ->method('wrapInTransaction')
            ->willReturnCallback(static function (callable $operation) use ($cashWrite): mixed {
                static::assertFalse($cashWrite->hasBeenInvoked(), 'No drawer entry may precede the transaction.');
                $result = $operation();
                static::assertTrue($cashWrite->hasBeenInvoked(), 'The drawer entry must commit with the applications.');

                return $result;
            });
        $persisted = [];
        $em->method('persist')->willReturnCallback(static function (object $o) use (&$persisted): void {
            $persisted[] = $o;
        });

        $this->salePaymentService->expects(self::exactly(2))->method('appendPayment');
        $this->gateway->method('findOpenCashSessionIdForUser')->willReturn(42);
        $this->gateway->expects(static::once())->method('requireLockedOpenCashSessionForUser')->with(42, 0);
        $result = $this->service($em, $sales, $ledger)->collect(
            customerId: 9,
            paymentMethod: $this->method(PaymentMethod::CASH),
            amount: '120.00',
            paidAt: new DateTimeImmutable('2026-03-05'),
            reference: null,
            applications: [],
            createdBy: new User(),
        );

        self::assertCount(2, $result->applications);
        self::assertSame('120.00', $result->amount);
        self::assertSame('s1', $result->applications[0]->saleId);
        self::assertCount(2, array_filter(
            $persisted,
            static fn(object $o): bool => $o instanceof CustomerPaymentApplication,
        ));
        self::assertCount(1, $this->dispatched);
        self::assertSame(EconomicEvent::RECEIVABLE_COLLECT_CASH, $this->dispatched[0]->kind);
        self::assertSame('120.00', $this->dispatched[0]->total);
    }

    public function testAmountAboveTotalDebtIsRejectedAndNothingIsBooked(): void
    {
        $sales = $this->salesRepo([$this->sale('s1', '40.00')]);
        $this->salePaymentService->expects(self::never())->method('appendPayment');
        $this->gateway->expects(self::never())->method('writeOnAccountCollectionMovement');

        $this->expectException(UnprocessableEntityHttpException::class);

        $this->service($this->em(), $sales, $this->createMock(CustomerAccountLedger::class))->collect(
            9,
            $this->method(PaymentMethod::CASH + 3),
            '100.00',
            new DateTimeImmutable('2026-03-05'),
            null,
            [],
            new User(),
        );

        self::assertSame([], $this->dispatched);
    }

    public function testARetriedRequestReturnsTheFirstResultAndBooksNothingAgain(): void
    {
        $sale = $this->sale('s1', '30.00');
        $sales = $this->createMock(SaleRecordRepository::class);
        $sales->expects(self::never())->method('findOpenReceivablesForCustomer');
        $sales->method('find')->willReturn($sale);

        $entries = $this->createMock(CustomerAccountEntryRepository::class);
        $entries
            ->method('findCollectionApplications')
            ->with('col-1')
            ->willReturn([
                ['customerId' => 9, 'saleId' => 's1', 'applied' => '70.00', 'currency' => 'PEN'],
            ]);

        $this->salePaymentService->expects(self::never())->method('appendPayment');
        $this->gateway->expects(self::never())->method('writeOnAccountCollectionMovement');

        $result = $this->service(
            $this->em(),
            $sales,
            $this->createMock(CustomerAccountLedger::class),
            $entries,
        )->collect(
            customerId: 9,
            paymentMethod: $this->method(PaymentMethod::CASH),
            amount: '70.00',
            paidAt: new DateTimeImmutable('2026-03-05'),
            reference: null,
            applications: [],
            createdBy: new User(),
            collectionId: 'col-1',
        );

        self::assertSame('col-1', $result->collectionId);
        self::assertSame('70.00', $result->amount);
        self::assertCount(1, $result->applications);
        self::assertSame('70.00', $result->applications[0]->applied);
        self::assertSame([], $this->dispatched);
    }

    public function testTheSameKeyOnAnotherCustomerIsAConflict(): void
    {
        $entries = $this->createMock(CustomerAccountEntryRepository::class);
        $entries
            ->method('findCollectionApplications')
            ->willReturn([
                ['customerId' => 9, 'saleId' => 's1', 'applied' => '70.00', 'currency' => 'PEN'],
            ]);

        $this->expectException(ConflictHttpException::class);

        $this->service(
            $this->em(),
            $this->salesRepo([]),
            $this->createMock(CustomerAccountLedger::class),
            $entries,
        )->collect(
            customerId: 10,
            paymentMethod: $this->method(PaymentMethod::CASH),
            amount: '70.00',
            paidAt: new DateTimeImmutable('2026-03-05'),
            reference: null,
            applications: [],
            createdBy: new User(),
            collectionId: 'col-1',
        );
    }

    public function testRejectsWhenTheCustomerHasNoOpenSales(): void
    {
        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('no tiene comprobantes pendientes');

        $this->service($this->em(), $this->salesRepo([]), $this->createMock(CustomerAccountLedger::class))->collect(
            9,
            $this->method(PaymentMethod::CASH + 3),
            '10.00',
            new DateTimeImmutable(),
            null,
            [],
            new User(),
        );
    }

    public function testBankCollectionSkipsTheDrawerAndBooksABankEvent(): void
    {
        $sales = $this->salesRepo([$this->sale('s1', '90.00')]);
        $ledger = $this->createMock(CustomerAccountLedger::class);
        $ledger->method('recordPayment')->willReturn($this->createStub(CustomerAccountEntry::class));

        $this->gateway->expects(self::never())->method('findOpenCashSessionIdForUser');
        $this->gateway->expects(self::never())->method('writeOnAccountCollectionMovement');

        $this->service($this->em(), $sales, $ledger)->collect(
            9,
            $this->method(PaymentMethod::CASH + 3),
            '90.00',
            new DateTimeImmutable('2026-03-05'),
            'OP-1',
            [],
            new User(),
        );

        self::assertCount(1, $this->dispatched);
        self::assertSame(EconomicEvent::RECEIVABLE_COLLECT_BANK, $this->dispatched[0]->kind);
    }

    public function testCashCollectionWithoutAnOpenSessionIsRejected(): void
    {
        $sales = $this->salesRepo([$this->sale('s1', '90.00')]);
        $this->gateway->method('findOpenCashSessionIdForUser')->willReturn(null);
        $this->salePaymentService->expects(self::never())->method('appendPayment');

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('Abre tu caja');

        $this->service($this->em(), $sales, $this->createMock(CustomerAccountLedger::class))->collect(
            9,
            $this->method(PaymentMethod::CASH),
            '90.00',
            new DateTimeImmutable(),
            null,
            [],
            new User(),
        );
    }

    public function testCashCollectionCannotApplyPaymentsAfterItsDrawerCloses(): void
    {
        $this->gateway->method('findOpenCashSessionIdForUser')->willReturn(42);
        $this->gateway
            ->expects(static::once())
            ->method('requireLockedOpenCashSessionForUser')
            ->willThrowException(new ConflictHttpException());
        $this->gateway->expects(static::never())->method('writeOnAccountCollectionMovement');
        $this->salePaymentService->expects(static::never())->method('appendPayment');
        $ledger = $this->createMock(CustomerAccountLedger::class);
        $ledger->expects(static::never())->method('recordPayment');

        try {
            $this->service($this->em(), $this->salesRepo([$this->sale('s1', '90.00')]), $ledger)->collect(
                9,
                $this->method(PaymentMethod::CASH),
                '90.00',
                new DateTimeImmutable(),
                null,
                [],
                new User(),
            );
            static::fail('The collection was accepted after the drawer had closed.');
        } catch (ConflictHttpException) {
            static::assertSame([], $this->dispatched);
        }
    }

    private function service(
        EntityManagerInterface $em,
        SaleRecordRepository $sales,
        CustomerAccountLedger $ledger,
        ?CustomerAccountEntryRepository $entries = null,
    ): CollectOnAccountService {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(function (object $m): Envelope {
            if ($m instanceof EconomicEvent) {
                $this->dispatched[] = $m;
            }

            return new Envelope($m);
        });

        return new CollectOnAccountService(
            $em,
            $sales,
            new CollectOnAccountPlanner(),
            $this->salePaymentService,
            $ledger,
            $this->gateway,
            $bus,
            new OperationLog(new NullLogger()),
            null,
            $entries,
        );
    }

    private function em(): EntityManagerInterface&MockObject
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('wrapInTransaction')->willReturnCallback(static fn(callable $f): mixed => $f());

        return $em;
    }

    /**
     * @param list<SaleRecord> $open
     */
    private function salesRepo(array $open): SaleRecordRepository
    {
        $repo = $this->createMock(SaleRecordRepository::class);
        $repo->method('findOpenReceivablesForCustomer')->willReturn($open);

        return $repo;
    }

    private function sale(string $id, string $balanceDue): SaleRecord
    {
        $sale = new SaleRecord()
            ->setBalanceDue($balanceDue)
            ->setCurrency('PEN')
            ->setSerie('F001')
            ->setNumero(ltrim($id, 's'));
        new \ReflectionProperty(SaleRecord::class, 'id')->setValue($sale, $id);

        return $sale;
    }

    private function salePayment(int $id): SalePayment
    {
        $payment = new SalePayment();
        new \ReflectionProperty(SalePayment::class, 'id')->setValue($payment, $id);

        return $payment;
    }

    private function method(int $id): PaymentMethod
    {
        $method = new PaymentMethod()
            ->setSettlementBehavior(SettlementBehavior::COLLECTED)
            ->setIsActive(true);
        new \ReflectionProperty(PaymentMethod::class, 'id')->setValue($method, $id);

        return $method;
    }
}
