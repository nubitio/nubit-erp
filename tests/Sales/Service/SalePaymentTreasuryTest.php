<?php

declare(strict_types=1);

namespace App\Tests\Sales\Service;

use App\Catalog\Entity\PaymentMethod;
use App\Catalog\Enum\SettlementBehavior;
use App\Efact\Observability\OperationLog;
use App\Identity\Auth\BranchContextServiceInterface;
use App\Identity\Entity\User;
use App\Organization\Entity\Branch;
use App\Sales\Entity\SalePayment;
use App\Sales\Entity\SaleRecord;
use App\Sales\Service\CustomerAccountLedger;
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

/** A receivable collection tells Treasury where the money landed, in the same transaction as the payment. */
#[AllowMockObjectsWithoutExpectations]
final class SalePaymentTreasuryTest extends TestCase
{
    public function testANonCashCollectionIsRecordedInTreasuryKeyedByThePayment(): void
    {
        $spy = new TreasurySpy();

        $this->service($spy)->registerPayment(
            $this->creditSale(),
            $this->method(6),
            '40.00',
            new DateTimeImmutable('2026-11-18'),
            'OP-9',
            $this->user(9),
            'account-1',
        );

        self::assertCount(1, $spy->recorded);
        $request = $spy->recorded[0];
        self::assertSame(MovementSource::ReceivableCollection, $request->key->source);
        self::assertSame('55', $request->key->id);
        self::assertSame('40.00', $request->amount);
        self::assertSame('PEN', $request->currency);
        self::assertSame('branch-1', $request->branchId);
        self::assertSame('account-1', $request->accountId);
        self::assertSame(9, $request->userId);
    }

    public function testACashCollectionDoesNotGoThroughTreasury(): void
    {
        $spy = new TreasurySpy();

        $this->service($spy, openSession: 14)->registerPayment(
            $this->creditSale(),
            $this->method(PaymentMethod::CASH),
            '40.00',
            new DateTimeImmutable(),
            null,
            $this->user(9),
        );

        self::assertSame([], $spy->recorded, 'cash lands in the drawer through the sales gateway');
    }

    public function testATreasuryRefusalFailsTheCollection(): void
    {
        $spy = new TreasurySpy();
        $spy->failWith = new TreasuryException('No hay cuenta de suspenso en PEN.');

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('suspenso');

        $this->service($spy)->registerPayment(
            $this->creditSale(),
            $this->method(6),
            '40.00',
            new DateTimeImmutable(),
            null,
            $this->user(9),
        );
    }

    private function service(TreasurySpy $spy, ?int $openSession = null): SalePaymentService
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('wrapInTransaction')->willReturnCallback(static fn(callable $callback): mixed => $callback());
        $em->method('persist')->willReturnCallback(static function (object $entity): void {
            if ($entity instanceof SalePayment) {
                new \ReflectionProperty(SalePayment::class, 'id')->setValue($entity, 55);
            }
        });

        $gateway = $this->createMock(SalesWriteGateway::class);
        $gateway->method('findOpenCashSessionIdForUser')->willReturn($openSession);
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(static fn(object $message): Envelope => new Envelope($message));
        $context = new class implements BranchContextServiceInterface {
            public function getCurrentBranchId(): string
            {
                return 'ctx-branch';
            }

            public function hasBranch(): bool
            {
                return true;
            }
        };

        return new SalePaymentService(
            $em,
            $gateway,
            $bus,
            new OperationLog(new NullLogger()),
            $this->createMock(CustomerAccountLedger::class),
            new TreasuryReceipts($spy, $context),
        );
    }

    private function creditSale(): SaleRecord
    {
        $branch = new Branch();
        new \ReflectionProperty(Branch::class, 'id')->setValue($branch, 'branch-1');

        return new SaleRecord()
            ->setPaidTotal('10.00')
            ->setBalanceDue('90.00')
            ->setCurrency('PEN')
            ->setBranch($branch);
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
