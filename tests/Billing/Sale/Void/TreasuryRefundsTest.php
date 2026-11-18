<?php

declare(strict_types=1);

namespace App\Tests\Billing\Sale\Void;

use App\Billing\Sale\Void\TreasuryRefunds;
use App\Sales\Entity\SaleRecord;
use App\Tests\Treasury\TreasurySpy;
use App\Tests\Treasury\TreasuryTestDatabase;
use App\Treasury\Domain\AccountKind;
use App\Treasury\Domain\Direction;
use App\Treasury\Domain\MovementKey;
use App\Treasury\Domain\MovementSource;
use App\Treasury\Port\MoneyMovementRequest;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/** Voiding a sale gives back what was paid by card, Yape or transfer, on the account it landed in. */
final class TreasuryRefundsTest extends TestCase
{
    public function testOnlyTheNonCashPaymentsWithAKeyAreReversed(): void
    {
        $kit = $this->kit();
        $this->payment($kit, 1, 'key-cash');
        $this->payment($kit, 6, 'key-yape');
        $this->payment($kit, 5, 'key-transfer');
        $this->payment($kit, 6, null);
        $spy = new TreasurySpy();

        new TreasuryRefunds($kit->db, $spy)->reverseNonCashPayments($this->sale(), 9);

        $reversed = array_map(static fn(array $r): string => $r[0]->id, $spy->reversed);
        sort($reversed);
        self::assertSame(['key-transfer', 'key-yape'], $reversed);
        self::assertSame(MovementSource::SalePaymentReversal, $spy->reversed[0][1]->source);
        self::assertSame(9, $spy->reversed[0][2]);
    }

    public function testTheMoneyGoesBackToTheAccountItLandedInEvenIfTheMappingChanged(): void
    {
        $kit = $this->kit();
        $kit->account('yape');
        $kit->account('other');
        $kit->accounts->map(TreasuryTestDatabase::BRANCH, 6, 'PEN', 'yape');
        $kit->recorder->record(new MoneyMovementRequest(
            key: new MovementKey(MovementSource::SalePayment, 'key-yape'),
            direction: Direction::In,
            amount: '30.00',
            currency: 'PEN',
            branchId: TreasuryTestDatabase::BRANCH,
            occurredAt: new DateTimeImmutable('2026-11-18'),
            paymentMethodId: 6,
        ));
        $kit->accounts->map(TreasuryTestDatabase::BRANCH, 6, 'PEN', 'other');
        $this->payment($kit, 6, 'key-yape');

        $refunds = new TreasuryRefunds($kit->db, $kit->recorder);
        $refunds->reverseNonCashPayments($this->sale(), 9);
        $refunds->reverseNonCashPayments($this->sale(), 9);

        self::assertSame('0.00', $kit->movements->balance('yape'));
        self::assertSame('0.00', $kit->movements->balance('other'));
        self::assertSame(
            2,
            (int) $kit->db->fetchOne('SELECT COUNT(*) FROM treasury_movement'),
            'a second void attempt adds nothing',
        );
    }

    public function testAPaymentTreasuryNeverSawIsSkipped(): void
    {
        $kit = $this->kit();
        $this->payment($kit, 6, 'key-old');

        new TreasuryRefunds($kit->db, $kit->recorder)->reverseNonCashPayments($this->sale(), 9);

        self::assertSame(0, (int) $kit->db->fetchOne('SELECT COUNT(*) FROM treasury_movement'));
    }

    private function kit(): TreasuryTestDatabase
    {
        $kit = TreasuryTestDatabase::create();
        $kit->account('suspense', AccountKind::Suspense);
        $kit->db->executeStatement(
            'CREATE TABLE sale_payments (id INTEGER PRIMARY KEY AUTOINCREMENT, sale_id TEXT NOT NULL, payment_method_id INTEGER NOT NULL, idempotency_key TEXT NULL)',
        );

        return $kit;
    }

    private function payment(TreasuryTestDatabase $kit, int $method, ?string $key): void
    {
        $kit->db->insert('sale_payments', [
            'sale_id' => 'sale-1',
            'payment_method_id' => $method,
            'idempotency_key' => $key,
        ]);
    }

    private function sale(): SaleRecord
    {
        $sale = new SaleRecord()
            ->setSerie('B001')
            ->setNumero('15');
        new \ReflectionProperty(SaleRecord::class, 'id')->setValue($sale, 'sale-1');

        return $sale;
    }
}
