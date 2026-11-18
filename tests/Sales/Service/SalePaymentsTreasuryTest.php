<?php

declare(strict_types=1);

namespace App\Tests\Sales\Service;

use App\Catalog\Entity\PaymentMethod;
use App\Catalog\Enum\SettlementBehavior;
use App\Identity\Auth\BranchContextServiceInterface;
use App\Organization\Entity\Branch;
use App\Sales\Entity\SalePayment;
use App\Sales\Entity\SaleRecord;
use App\Sales\Service\SalePaymentsTreasury;
use App\Sales\Service\TreasuryReceipts;
use App\Tests\Treasury\TreasurySpy;
use App\Tests\Treasury\TreasuryTestDatabase;
use App\Treasury\Domain\AccountKind;
use App\Treasury\Domain\MovementSource;
use App\Treasury\Domain\TreasuryException;
use DateTime;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

/** The POS tells Treasury about non-cash payments without ever being able to fail a sale. */
final class SalePaymentsTreasuryTest extends TestCase
{
    private const int YAPE = 6;
    private const int CREDIT = 2;

    public function testOnlyNonCashImmediatePaymentsAreRecordedKeyedByTheirIdempotencyKey(): void
    {
        $spy = new TreasurySpy();
        $sale = $this->sale([
            $this->payment(PaymentMethod::CASH, '50.00', 'key-cash'),
            $this->payment(self::YAPE, '30.00', 'key-yape'),
            $this->payment(self::CREDIT, '20.00', 'key-credit', SettlementBehavior::RECEIVABLE),
            $this->payment(5, '10.00', null),
        ]);

        $this->collaborator($spy)->record($sale, 9);

        self::assertCount(1, $spy->recorded);
        $request = $spy->recorded[0];
        self::assertSame(MovementSource::SalePayment, $request->key->source);
        self::assertSame('key-yape', $request->key->id);
        self::assertSame('30.00', $request->amount);
        self::assertSame('PEN', $request->currency);
        self::assertSame(self::YAPE, $request->paymentMethodId);
        self::assertSame(TreasuryTestDatabase::BRANCH, $request->branchId);
        self::assertSame('Venta B001-15', $request->description);
    }

    public function testReplayingTheSameSaleRecordsEachPaymentOnce(): void
    {
        $kit = TreasuryTestDatabase::create();
        $kit->account('suspense', AccountKind::Suspense);
        $kit->account('yape', AccountKind::Wallet);
        $kit->accounts->map(TreasuryTestDatabase::BRANCH, self::YAPE, 'PEN', 'yape');
        $collaborator = new SalePaymentsTreasury(
            new TreasuryReceipts($kit->recorder, $this->context()),
            new RecordingLogger(),
        );

        // The gateway rebuilds the payment rows on every write: new objects, same derived idempotency keys.
        $collaborator->record($this->sale([$this->payment(self::YAPE, '30.00', 'key-yape')]), 9);
        $collaborator->record($this->sale([$this->payment(self::YAPE, '30.00', 'key-yape')]), 9);
        $collaborator->record($this->sale([$this->payment(self::YAPE, '30.00', 'key-yape')]), 9);

        self::assertSame(1, (int) $kit->db->fetchOne('SELECT COUNT(*) FROM treasury_movement'));
        self::assertSame('30.00', $kit->movements->balance('yape'));
    }

    public function testATreasuryFailureIsLoggedAndNeverBubblesUpToTheSale(): void
    {
        $spy = new TreasurySpy();
        $spy->failWith = new TreasuryException('No hay cuenta de suspenso en PEN.');
        $logger = new RecordingLogger();

        $this->collaborator($spy, $logger)->record($this->sale([$this->payment(self::YAPE, '30.00', 'key-yape')]), 9);

        self::assertCount(1, $logger->errors);
        self::assertStringContainsString('treasury:backfill', $logger->errors[0]);
    }

    private function collaborator(TreasurySpy $spy, ?RecordingLogger $logger = null): SalePaymentsTreasury
    {
        return new SalePaymentsTreasury(new TreasuryReceipts($spy, $this->context()), $logger ?? new RecordingLogger());
    }

    private function context(): BranchContextServiceInterface
    {
        return new class implements BranchContextServiceInterface {
            public function getCurrentBranchId(): string
            {
                return TreasuryTestDatabase::BRANCH;
            }

            public function hasBranch(): bool
            {
                return true;
            }
        };
    }

    /** @param list<SalePayment> $payments */
    private function sale(array $payments): SaleRecord
    {
        $branch = new Branch();
        new \ReflectionProperty(Branch::class, 'id')->setValue($branch, TreasuryTestDatabase::BRANCH);
        $sale = new SaleRecord()
            ->setBranch($branch)
            ->setSerie('B001')
            ->setNumero('15')
            ->setCurrency('PEN');
        foreach ($payments as $payment) {
            $sale->addSalePayment($payment);
        }

        return $sale;
    }

    private function payment(
        int $methodId,
        string $amount,
        ?string $key,
        SettlementBehavior $behavior = SettlementBehavior::COLLECTED,
    ): SalePayment {
        $method = new PaymentMethod()
            ->setSettlementBehavior($behavior)
            ->setIsActive(true);
        new \ReflectionProperty(PaymentMethod::class, 'id')->setValue($method, $methodId);

        return new SalePayment()
            ->setPaymentMethod($method)
            ->setAmount($amount)
            ->setCurrency('PEN')
            ->setPaidAt(new DateTime('2026-11-18 10:00:00'))
            ->setIdempotencyKey($key);
    }
}

final class RecordingLogger extends AbstractLogger
{
    /** @var list<string> */
    public array $errors = [];

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        if ($level === 'error') {
            $this->errors[] = (string) $message;
        }
    }
}
