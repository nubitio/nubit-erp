<?php

declare(strict_types=1);

namespace App\Tests\Sales\Service;

use App\Accounting\Message\EconomicEvent;
use App\Catalog\Entity\PaymentMethod;
use App\Catalog\Enum\SettlementBehavior;
use App\Efact\Observability\OperationLog;
use App\Identity\Entity\User;
use App\Sales\Entity\SaleRecord;
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
    /** @var list<EconomicEvent> */
    private array $dispatched = [];

    protected function setUp(): void
    {
        $this->writeGateway = $this->createMock(SalesWriteGateway::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
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
        return new SalePaymentService($em, $this->writeGateway, $this->messageBus, new OperationLog(new NullLogger()));
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
            self::assertSame([], $this->dispatched);
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
            ->with(self::anything(), self::anything(), 42, self::anything(), '40.00', PaymentMethod::CASH);

        $sale = $this->creditSale();
        $payment = $this->service($em)->registerPayment(
            $sale,
            $this->collectedMethod(PaymentMethod::CASH),
            '40.00',
            new DateTimeImmutable('2026-09-08'),
            null,
            new User(),
        );

        self::assertSame('50.00', $sale->getPaidTotal());
        self::assertSame('50.00', $sale->getBalanceDue());
        self::assertSame('40.00', $payment->getAmount());
        self::assertCount(1, $this->dispatched);
        self::assertSame(EconomicEvent::RECEIVABLE_COLLECT_CASH, $this->dispatched[0]->kind);
        self::assertSame(EconomicEvent::SOURCE_RECEIVABLE_COLLECT, $this->dispatched[0]->source);
        self::assertSame('40.00', $this->dispatched[0]->total);
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

        self::assertSame('0.00', $sale->getBalanceDue());
        self::assertCount(1, $this->dispatched);
        self::assertSame(EconomicEvent::RECEIVABLE_COLLECT_BANK, $this->dispatched[0]->kind);
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

        self::assertSame('40.00', $sale->getPaidTotal());
        self::assertSame('60.00', $sale->getBalanceDue());
        self::assertSame('30.00', $payment->getAmount());
        self::assertCount(1, $sale->getSalePayments());
        self::assertSame([], $this->dispatched, 'appendPayment must not dispatch a ledger event');
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
