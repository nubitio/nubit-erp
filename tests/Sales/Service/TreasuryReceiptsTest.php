<?php

declare(strict_types=1);

namespace App\Tests\Sales\Service;

use App\Catalog\Entity\PaymentMethod;
use App\Identity\Auth\BranchContextServiceInterface;
use App\Sales\Service\TreasuryReceipt;
use App\Sales\Service\TreasuryReceipts;
use App\Tests\Treasury\TreasurySpy;
use App\Treasury\Domain\Direction;
use App\Treasury\Domain\MovementSource;
use App\Treasury\Domain\TreasuryException;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class TreasuryReceiptsTest extends TestCase
{
    public function testAMoneyReceiptIsRecordedAsAnInflowFromTheCustomer(): void
    {
        $spy = new TreasurySpy();

        $this->receipts($spy)->record(new TreasuryReceipt(
            source: MovementSource::ReceivableCollection,
            sourceId: 'pay-7',
            amount: '118.00',
            currency: 'PEN',
            paymentMethod: $this->method(6),
            paidAt: new DateTimeImmutable('2026-11-18'),
            userId: 9,
            branchId: 'branch-1',
            customerId: '42',
            reference: 'OP-1',
            description: 'Cobro',
            moneyAccountId: 'account-1',
        ));

        self::assertCount(1, $spy->recorded);
        $request = $spy->recorded[0];
        self::assertSame(MovementSource::ReceivableCollection, $request->key->source);
        self::assertSame('pay-7', $request->key->id);
        self::assertSame(Direction::In, $request->direction);
        self::assertSame('118.00', $request->amount);
        self::assertSame('branch-1', $request->branchId);
        self::assertSame(6, $request->paymentMethodId);
        self::assertSame('42', $request->party?->id);
        self::assertSame('account-1', $request->accountId);
    }

    public function testCashIsLeftToTheDrawerPathAndNeverDoubleCounted(): void
    {
        $spy = new TreasurySpy();

        $this->receipts($spy)->record(new TreasuryReceipt(
            source: MovementSource::ReceivableCollection,
            sourceId: 'pay-7',
            amount: '50.00',
            currency: 'PEN',
            paymentMethod: $this->method(PaymentMethod::CASH),
            paidAt: new DateTimeImmutable(),
            userId: 9,
            branchId: 'branch-1',
            customerId: null,
            reference: null,
            description: 'Cobro',
            moneyAccountId: null,
        ));

        self::assertSame([], $spy->recorded);
    }

    public function testASaleWithoutABranchUsesTheCurrentBranch(): void
    {
        $spy = new TreasurySpy();

        $this->receipts($spy, 'current-branch')->record(new TreasuryReceipt(
            source: MovementSource::CustomerAdvance,
            sourceId: 'adv-1',
            amount: '10.00',
            currency: 'PEN',
            paymentMethod: $this->method(6),
            paidAt: new DateTimeImmutable(),
            userId: 9,
            branchId: null,
            customerId: '42',
            reference: null,
            description: 'Anticipo',
            moneyAccountId: null,
        ));

        self::assertSame('current-branch', $spy->recorded[0]->branchId);
    }

    public function testATreasuryRuleBecomesA422(): void
    {
        $spy = new TreasurySpy();
        $spy->failWith = new TreasuryException('La cuenta es en PEN y el pago es en USD.');

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('USD');

        $this->receipts($spy)->record(new TreasuryReceipt(
            source: MovementSource::ReceivableCollection,
            sourceId: 'pay-7',
            amount: '50.00',
            currency: 'USD',
            paymentMethod: $this->method(6),
            paidAt: new DateTimeImmutable(),
            userId: 9,
            branchId: 'branch-1',
            customerId: null,
            reference: null,
            description: 'Cobro',
            moneyAccountId: null,
        ));
    }

    private function receipts(TreasurySpy $spy, ?string $currentBranch = 'ctx-branch'): TreasuryReceipts
    {
        $context = new class($currentBranch) implements BranchContextServiceInterface {
            public function __construct(
                private ?string $branch,
            ) {}

            public function getCurrentBranchId(): ?string
            {
                return $this->branch;
            }

            public function hasBranch(): bool
            {
                return $this->branch !== null;
            }
        };

        return new TreasuryReceipts($spy, $context);
    }

    private function method(int $id): PaymentMethod
    {
        $method = new PaymentMethod();
        new \ReflectionProperty(PaymentMethod::class, 'id')->setValue($method, $id);

        return $method;
    }
}
