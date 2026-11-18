<?php

declare(strict_types=1);

namespace App\Tests\Sales\Service;

use App\Catalog\Entity\PaymentMethod;
use App\Catalog\Enum\SettlementBehavior;
use App\Efact\Observability\OperationLog;
use App\Identity\Auth\BranchContextServiceInterface;
use App\Identity\Entity\User;
use App\Organization\Entity\Branch;
use App\Sales\Entity\CustomerAccountEntry;
use App\Sales\Entity\SalePayment;
use App\Sales\Entity\SaleRecord;
use App\Sales\Repository\CustomerAccountEntryRepository;
use App\Sales\Repository\SaleRecordRepository;
use App\Sales\Service\CollectOnAccountPlanner;
use App\Sales\Service\CollectOnAccountService;
use App\Sales\Service\CustomerAccountLedger;
use App\Sales\Service\ReceiveAdvanceService;
use App\Sales\Service\SalePaymentService;
use App\Sales\Service\TreasuryReceipts;
use App\Sales\State\SalesWriteGateway;
use App\Tests\Treasury\TreasurySpy;
use App\Treasury\Domain\MovementSource;
use App\Treasury\Domain\TreasuryException;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/** The two deliberate money-in flows besides a single collection: on-account collections and customer advances. */
#[AllowMockObjectsWithoutExpectations]
final class OnAccountAndAdvanceTreasuryTest extends TestCase
{
    public function testAnOnAccountCollectionRecordsTheWholeAmountOnceNotPerSale(): void
    {
        $spy = new TreasurySpy();
        $sales = $this->createMock(SaleRecordRepository::class);
        $sales
            ->method('findOpenReceivablesForCustomer')
            ->willReturn([$this->sale('s1', '70.00'), $this->sale('s2', '50.00')]);
        $payments = $this->createMock(SalePaymentService::class);
        $payments
            ->method('appendPayment')
            ->willReturnCallback(fn(): SalePayment => $this->payment(random_int(1, 99999)));
        $ledger = $this->createMock(CustomerAccountLedger::class);
        $ledger->method('recordPayment')->willReturn($this->createStub(CustomerAccountEntry::class));

        $result = new CollectOnAccountService(
            $this->em(),
            $sales,
            new CollectOnAccountPlanner(),
            $payments,
            $ledger,
            $this->createMock(SalesWriteGateway::class),
            $this->bus(),
            new OperationLog(new NullLogger()),
            new TreasuryReceipts($spy, $this->context()),
        )->collect(
            customerId: 9,
            paymentMethod: $this->method(6),
            amount: '120.00',
            paidAt: new DateTimeImmutable('2026-11-18'),
            reference: 'OP-5',
            applications: [],
            createdBy: $this->user(9),
            moneyAccountId: 'account-1',
        );

        self::assertCount(2, $result->applications);
        self::assertCount(1, $spy->recorded, 'one money movement for the whole collection');
        $request = $spy->recorded[0];
        self::assertSame(MovementSource::ReceivableCollection, $request->key->source);
        self::assertSame($result->collectionId, $request->key->id);
        self::assertSame('120.00', $request->amount);
        self::assertSame('branch-1', $request->branchId);
        self::assertSame('account-1', $request->accountId);
        self::assertSame('9', $request->party?->id);
    }

    public function testABankAdvanceIsRecordedInTreasuryAndACashOneIsNot(): void
    {
        $spy = new TreasurySpy();

        $bank = $this->advances($spy)->receive(
            7,
            $this->method(6),
            '500.00',
            new DateTimeImmutable('2026-11-18'),
            null,
            $this->user(9),
        );
        $this->advances($spy, openSession: 42)->receive(
            7,
            $this->method(PaymentMethod::CASH),
            '20.00',
            new DateTimeImmutable('2026-11-18'),
            null,
            $this->user(9),
        );

        self::assertCount(1, $spy->recorded);
        $request = $spy->recorded[0];
        self::assertSame(MovementSource::CustomerAdvance, $request->key->source);
        self::assertSame($bank->advanceId, $request->key->id);
        self::assertSame('500.00', $request->amount);
        self::assertSame('ctx-branch', $request->branchId, 'an advance has no sale, so the current branch applies');
        self::assertSame('7', $request->party?->id);
    }

    public function testATreasuryRefusalStopsTheAdvanceBeforeAnyLedgerEvent(): void
    {
        $spy = new TreasurySpy();
        $spy->failWith = new TreasuryException('No hay cuenta de suspenso en PEN.');
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        $this->expectException(UnprocessableEntityHttpException::class);

        $this->advances($spy, bus: $bus)->receive(
            7,
            $this->method(6),
            '500.00',
            new DateTimeImmutable(),
            null,
            $this->user(9),
        );
    }

    private function advances(
        TreasurySpy $spy,
        ?int $openSession = null,
        ?MessageBusInterface $bus = null,
    ): ReceiveAdvanceService {
        $ledger = $this->createMock(CustomerAccountLedger::class);
        $ledger->method('recordAdvanceReceived')->willReturn($this->createStub(CustomerAccountEntry::class));
        $entries = $this->createMock(CustomerAccountEntryRepository::class);
        $entries->method('availableAdvance')->willReturn('500.00');
        $gateway = $this->createMock(SalesWriteGateway::class);
        $gateway->method('findOpenCashSessionIdForUser')->willReturn($openSession);

        return new ReceiveAdvanceService(
            $this->em(),
            $ledger,
            $entries,
            $gateway,
            $bus ?? $this->bus(),
            new OperationLog(new NullLogger()),
            new TreasuryReceipts($spy, $this->context()),
        );
    }

    private function em(): EntityManagerInterface
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('wrapInTransaction')->willReturnCallback(static fn(callable $f): mixed => $f());

        return $em;
    }

    private function bus(): MessageBusInterface
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(static fn(object $m): Envelope => new Envelope($m));

        return $bus;
    }

    private function context(): BranchContextServiceInterface
    {
        return new class implements BranchContextServiceInterface {
            public function getCurrentBranchId(): string
            {
                return 'ctx-branch';
            }

            public function hasBranch(): bool
            {
                return true;
            }
        };
    }

    private function sale(string $id, string $balanceDue): SaleRecord
    {
        $branch = new Branch();
        new \ReflectionProperty(Branch::class, 'id')->setValue($branch, 'branch-1');
        $sale = new SaleRecord()
            ->setBalanceDue($balanceDue)
            ->setCurrency('PEN')
            ->setSerie('F001')
            ->setNumero(ltrim($id, 's'))
            ->setBranch($branch);
        new \ReflectionProperty(SaleRecord::class, 'id')->setValue($sale, $id);

        return $sale;
    }

    private function payment(int $id): SalePayment
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

    private function user(int $id): User
    {
        $user = new User();
        new \ReflectionProperty(User::class, 'id')->setValue($user, $id);

        return $user;
    }
}
