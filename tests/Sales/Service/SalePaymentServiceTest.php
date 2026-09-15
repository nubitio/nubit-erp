<?php

declare(strict_types=1);

namespace App\Tests\Sales\Service;

use App\Accounting\Message\EconomicEvent;
use App\Catalog\Entity\PaymentMethod;
use App\Catalog\Enum\SettlementBehavior;
use App\Efact\Observability\OperationLog;
use App\Identity\Entity\User;
use App\Sales\Entity\CustomerAccountEntry;
use App\Sales\Entity\SaleRecord;
use App\Sales\Service\CustomerAccountLedger;
use App\Sales\Service\SalePaymentService;
use App\Sales\State\SalesWriteGateway;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

#[AllowMockObjectsWithoutExpectations]
final class SalePaymentServiceTest extends TestCase
{
    /** @var SalesWriteGateway&MockObject */
    private SalesWriteGateway $writeGateway;
    private MessageBusInterface $messageBus;
    private CustomerAccountLedger&MockObject $customerAccountLedger;
    /** @var list<EconomicEvent> */
    private array $dispatched = [];

    protected function setUp(): void
    {
        $this->writeGateway = $this->createMock(SalesWriteGateway::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->customerAccountLedger = $this->createMock(CustomerAccountLedger::class);
        $this->dispatched = [];
        $this->messageBus
            ->method('dispatch')
            ->willReturnCallback(function (object $message): Envelope {
                if ($message instanceof EconomicEvent) {
                    $this->dispatched[] = $message;
                }

                return new Envelope($message);
            });
    }

    private function service(EntityManagerInterface $em): SalePaymentService
    {
        return new SalePaymentService(
            $em,
            $this->writeGateway,
            $this->messageBus,
            new OperationLog(new NullLogger()),
            $this->customerAccountLedger,
        );
    }

    private function collectedMethod(int $id): PaymentMethod
    {
        $method = new PaymentMethod()
            ->setSettlementBehavior(SettlementBehavior::COLLECTED)
            ->setIsActive(true);
        new \ReflectionProperty(PaymentMethod::class, 'id')->setValue($method, $id);

        return $method;
    }

    private function creditSale(): SaleRecord
    {
        return new SaleRecord()
            ->setPaidTotal('10.00')
            ->setBalanceDue('90.00')
            ->setCurrency('PEN');
    }

    public function testReceivableAccountSurvivesFullPayment(): void
    {
        $sale = new SaleRecord();
        self::assertFalse($sale->isReceivableAccount());
        $sale->setBalanceDue('90.00');
        self::assertTrue($sale->isReceivableAccount());
        $sale->setBalanceDue('0.00');
        self::assertTrue($sale->isReceivableAccount());
    }

    /** @return iterable<string, array{SettlementBehavior, bool}> */
    public static function invalidMethods(): iterable
    {
        yield 'credit' => [SettlementBehavior::RECEIVABLE, true];
        yield 'inactive cash' => [SettlementBehavior::COLLECTED, false];
    }

    #[DataProvider('invalidMethods')]
    public function testRejectsInvalidMethodWithoutChangingDebt(SettlementBehavior $behavior, bool $active): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');
        $em->expects(self::never())->method('flush');
        $sale = $this->creditSale();
        $method = new PaymentMethod()
            ->setSettlementBehavior($behavior)
            ->setIsActive($active);

        try {
            $this->service($em)->registerPayment($sale, $method, '20.00', new DateTimeImmutable(), null, new User());
            self::fail('Invalid payment method was accepted.');
        } catch (UnprocessableEntityHttpException) {
            self::assertSame('10.00', $sale->getPaidTotal());
            self::assertSame('90.00', $sale->getBalanceDue());
            self::assertCount(0, $sale->getSalePayments());
            static::assertSame([], $this->dispatched);
        }
    }

    public function testCashCollectionWithoutOpenSessionIsRejectedBeforePersist(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');
        $em->expects(self::never())->method('flush');
        $this->writeGateway->method('findOpenCashSessionIdForUser')->willReturn(null);
        $this->writeGateway->expects(self::never())->method('writeReceivableCollectionMovement');
        $sale = $this->creditSale();

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('Abre tu caja para registrar cobros en efectivo.');

        $this->service($em)->registerPayment(
            $sale,
            $this->collectedMethod(PaymentMethod::CASH),
            '40.00',
            new DateTimeImmutable('2026-09-08'),
            null,
            new User(),
        );
    }

    public function testCashCollectionWritesDrawerMovementAndBooksCashEvent(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $this->writeGateway->method('findOpenCashSessionIdForUser')->willReturn(42);
        $this->writeGateway
            ->expects(self::once())
            ->method('writeReceivableCollectionMovement')
            ->with(static::anything(), static::anything(), 42, static::anything(), '40.00', PaymentMethod::CASH);

        $sale = $this->creditSale();
        $payment = $this->service($em)->registerPayment(
            $sale,
            $this->collectedMethod(PaymentMethod::CASH),
            '40.00',
            new DateTimeImmutable('2026-09-08'),
            null,
            new User(),
        );

        static::assertSame('50.00', $sale->getPaidTotal());
        static::assertSame('50.00', $sale->getBalanceDue());
        static::assertSame('40.00', $payment->getAmount());
        static::assertCount(1, $this->dispatched);
        static::assertSame(EconomicEvent::RECEIVABLE_COLLECT_CASH, $this->dispatched[0]->kind);
        static::assertSame(EconomicEvent::SOURCE_RECEIVABLE_COLLECT, $this->dispatched[0]->source);
        static::assertSame('40.00', $this->dispatched[0]->total);
    }

    public function testNonCashCollectionSkipsDrawerAndBooksBankEvent(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $this->writeGateway->expects(self::never())->method('findOpenCashSessionIdForUser');
        $this->writeGateway->expects(self::never())->method('writeReceivableCollectionMovement');

        $sale = $this->creditSale();
        $this->service($em)->registerPayment(
            $sale,
            $this->collectedMethod(PaymentMethod::CASH + 5),
            '90.00',
            new DateTimeImmutable('2026-09-08'),
            'OP-12345',
            new User(),
        );

        static::assertSame('0.00', $sale->getBalanceDue());
        static::assertCount(1, $this->dispatched);
        static::assertSame(EconomicEvent::RECEIVABLE_COLLECT_BANK, $this->dispatched[0]->kind);
    }

    public function testRegisterPaymentMirrorsTheCollectionOntoTheCustomerLedger(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $persisted = [];
        $em->method('persist')->willReturnCallback(static function (object $o) use (&$persisted): void {
            $persisted[] = $o;
        });

        $sale = $this->creditSale();
        $customer = new \App\Contacts\Entity\Customer();
        new \ReflectionProperty(\App\Contacts\Entity\Customer::class, 'id')->setValue($customer, 77);
        $sale->setCustomer($customer);

        $this->customerAccountLedger
            ->expects(self::once())
            ->method('recordPayment')
            ->with(
                77,
                static::anything(),
                '40.00',
                'PEN',
                static::anything(),
                'sale_payment',
                static::anything(),
                static::anything(),
            )
            ->willReturn($this->createStub(CustomerAccountEntry::class));

        $this->service($em)->registerPayment(
            $sale,
            $this->collectedMethod(PaymentMethod::CASH + 5),
            '40.00',
            new DateTimeImmutable('2026-09-08'),
            null,
            new User(),
        );

        static::assertCount(1, array_filter(
            $persisted,
            static fn(object $o): bool => $o instanceof \App\Sales\Entity\CustomerPaymentApplication,
        ));
    }

    public function testRegisterPaymentSkipsTheLedgerForAnAnonymousSale(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $this->customerAccountLedger->expects(self::never())->method('recordPayment');

        $this->service($em)->registerPayment(
            $this->creditSale(), // no customer
            $this->collectedMethod(PaymentMethod::CASH + 5),
            '40.00',
            new DateTimeImmutable('2026-09-08'),
            null,
            new User(),
        );
    }

    public function testInactiveCashMethodIsRejectedBeforeResolvingTheCashSession(): void
    {
        // Regression for the split: an inactive method whose id happens to be
        // CASH must still fail with the method error, not "abre tu caja", and
        // must not query for an open session.
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');
        $this->writeGateway->expects(self::never())->method('findOpenCashSessionIdForUser');

        $method = $this->collectedMethod(PaymentMethod::CASH)->setIsActive(false);

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('Seleccione un método de pago activo');

        $this->service($em)->registerPayment(
            $this->creditSale(),
            $method,
            '40.00',
            new DateTimeImmutable('2026-09-08'),
            null,
            new User(),
        );
    }

    public function testAppendPaymentMovesDebtWithoutBookingTheMoney(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist');
        $em->expects(self::once())->method('flush');
        $this->writeGateway->expects(self::never())->method('findOpenCashSessionIdForUser');
        $this->writeGateway->expects(self::never())->method('writeReceivableCollectionMovement');

        $sale = $this->creditSale();
        $payment = $this->service($em)->appendPayment(
            $sale,
            $this->collectedMethod(PaymentMethod::CASH),
            '30.00',
            new DateTimeImmutable('2026-09-08'),
            'partial',
        );

        static::assertSame('40.00', $sale->getPaidTotal());
        static::assertSame('60.00', $sale->getBalanceDue());
        static::assertSame('30.00', $payment->getAmount());
        static::assertCount(1, $sale->getSalePayments());
        static::assertSame([], $this->dispatched, 'appendPayment must not dispatch a ledger event');
    }

    public function testAppendAdvancePaymentMovesDebtWithAnAdvanceMethodAndNoSideEffects(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $this->writeGateway->expects(self::never())->method('writeReceivableCollectionMovement');
        $this->customerAccountLedger->expects(self::never())->method('recordPayment');

        $advanceMethod = new PaymentMethod()
            ->setSettlementBehavior(SettlementBehavior::ADVANCE)
            ->setIsActive(false);
        new \ReflectionProperty(PaymentMethod::class, 'id')->setValue($advanceMethod, 3);

        $sale = $this->creditSale();
        $payment = $this->service($em)->appendAdvancePayment(
            $sale,
            $advanceMethod,
            '25.00',
            new DateTimeImmutable('2026-09-10'),
            'F001-9',
        );

        static::assertSame('35.00', $sale->getPaidTotal());
        static::assertSame('65.00', $sale->getBalanceDue());
        static::assertSame('25.00', $payment->getAmount());
        static::assertSame([], $this->dispatched);
    }

    public function testAppendAdvancePaymentRejectsANonAdvanceMethod(): void
    {
        $this->expectException(UnprocessableEntityHttpException::class);
        $this->service($this->createMock(EntityManagerInterface::class))->appendAdvancePayment(
            $this->creditSale(),
            $this->collectedMethod(PaymentMethod::CASH),
            '25.00',
            new DateTimeImmutable(),
            null,
        );
    }

    public function testAppendPaymentRejectsAmountAboveBalanceBeforePersist(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');
        $em->expects(self::never())->method('flush');
        $sale = $this->creditSale();

        $this->expectException(UnprocessableEntityHttpException::class);

        $this->service($em)->appendPayment(
            $sale,
            $this->collectedMethod(PaymentMethod::CASH + 5),
            '90.01',
            new DateTimeImmutable(),
            null,
        );
    }

    public function testRejectsCollectionAboveOutstandingBalance(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('flush');
        $sale = $this->creditSale();

        $this->expectException(UnprocessableEntityHttpException::class);

        $this->service($em)->registerPayment(
            $sale,
            $this->collectedMethod(PaymentMethod::CASH + 5),
            '90.01',
            new DateTimeImmutable(),
            null,
            new User(),
        );
    }
}
