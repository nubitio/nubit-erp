<?php

declare(strict_types=1);

namespace App\Tests\Sales\Service;

use App\Accounting\Message\EconomicEvent;
use App\Catalog\Entity\PaymentMethod;
use App\Catalog\Enum\SettlementBehavior;
use App\Efact\Observability\OperationLog;
use App\Identity\Entity\User;
use App\Sales\Entity\CustomerAccountEntry;
use App\Sales\Repository\CustomerAccountEntryRepository;
use App\Sales\Service\CustomerAccountLedger;
use App\Sales\Service\ReceiveAdvanceService;
use App\Sales\State\SalesWriteGateway;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

#[AllowMockObjectsWithoutExpectations]
final class ReceiveAdvanceServiceTest extends TestCase
{
    /** @var list<EconomicEvent> */
    private array $dispatched = [];
    private CustomerAccountLedger&MockObject $ledger;
    private CustomerAccountEntryRepository&MockObject $entries;
    private SalesWriteGateway&MockObject $gateway;

    protected function setUp(): void
    {
        $this->dispatched = [];
        $this->ledger = $this->createMock(CustomerAccountLedger::class);
        $this->ledger->method('recordAdvanceReceived')->willReturn($this->createStub(CustomerAccountEntry::class));
        $this->entries = $this->createMock(CustomerAccountEntryRepository::class);
        $this->gateway = $this->createMock(SalesWriteGateway::class);
    }

    public function testCashAdvanceAppendsTheLedgerEntryAndBooksTheMoneyOnce(): void
    {
        $this->gateway->method('findOpenCashSessionIdForUser')->willReturn(42);
        $this->gateway->expects(self::once())->method('writeCustomerAdvanceMovement');
        $this->ledger
            ->expects(self::once())
            ->method('recordAdvanceReceived')
            ->with(7, self::anything(), '500.00', 'PEN', 'advance_receipt', self::anything(), self::anything())
            ->willReturn($this->createStub(CustomerAccountEntry::class));
        $this->entries->method('availableAdvance')->with(7)->willReturn('500.00');

        $result = $this->service()->receive(
            7,
            $this->method(PaymentMethod::CASH),
            '500.00',
            new DateTimeImmutable('2026-09-09'),
            null,
            new User(),
        );

        self::assertSame(7, $result->customerId);
        self::assertSame('500.00', $result->amount);
        self::assertSame('500.00', $result->availableAdvance);
        self::assertNotSame('', $result->advanceId);
        self::assertCount(1, $this->dispatched);
        self::assertSame(EconomicEvent::CUSTOMER_ADVANCE_RECEIVED_CASH, $this->dispatched[0]->kind);
        self::assertSame(EconomicEvent::SOURCE_CUSTOMER_ADVANCE, $this->dispatched[0]->source);
        self::assertSame('500.00', $this->dispatched[0]->total);
    }

    public function testBankAdvanceSkipsTheDrawerAndBooksABankEvent(): void
    {
        $this->gateway->expects(self::never())->method('findOpenCashSessionIdForUser');
        $this->gateway->expects(self::never())->method('writeCustomerAdvanceMovement');
        $this->entries->method('availableAdvance')->willReturn('300.00');

        $this->service()->receive(
            7,
            $this->method(PaymentMethod::CASH + 4),
            '300.00',
            new DateTimeImmutable('2026-09-09'),
            'OP-99',
            new User(),
        );

        self::assertCount(1, $this->dispatched);
        self::assertSame(EconomicEvent::CUSTOMER_ADVANCE_RECEIVED_BANK, $this->dispatched[0]->kind);
    }

    public function testCashAdvanceWithoutAnOpenSessionIsRejected(): void
    {
        $this->gateway->method('findOpenCashSessionIdForUser')->willReturn(null);
        $this->ledger->expects(self::never())->method('recordAdvanceReceived');

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('Abre tu caja');

        $this->service()->receive(
            7,
            $this->method(PaymentMethod::CASH),
            '100.00',
            new DateTimeImmutable(),
            null,
            new User(),
        );
    }

    public function testInactiveMethodIsRejected(): void
    {
        $this->ledger->expects(self::never())->method('recordAdvanceReceived');
        $method = $this->method(PaymentMethod::CASH + 4)->setIsActive(false);

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->service()->receive(7, $method, '100.00', new DateTimeImmutable(), null, new User());
    }

    public function testNonPositiveAmountIsRejected(): void
    {
        $this->ledger->expects(self::never())->method('recordAdvanceReceived');

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->service()->receive(
            7,
            $this->method(PaymentMethod::CASH + 4),
            '0',
            new DateTimeImmutable(),
            null,
            new User(),
        );
    }

    private function service(): ReceiveAdvanceService
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('wrapInTransaction')->willReturnCallback(static fn(callable $f): mixed => $f());

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(function (object $m): Envelope {
            if ($m instanceof EconomicEvent) {
                $this->dispatched[] = $m;
            }

            return new Envelope($m);
        });

        return new ReceiveAdvanceService(
            $em,
            $this->ledger,
            $this->entries,
            $this->gateway,
            $bus,
            new OperationLog(new NullLogger()),
        );
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
