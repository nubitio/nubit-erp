<?php

declare(strict_types=1);

namespace App\Tests\Treasury;

use App\Treasury\Domain\AccountKind;
use App\Treasury\Domain\Direction;
use App\Treasury\Domain\MovementKey;
use App\Treasury\Domain\MovementSource;
use App\Treasury\Port\MoneyMovementRequest;
use App\Treasury\Service\TreasuryBackfill;
use App\Treasury\Service\TreasuryGaps;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/** Payments that slipped past Treasury are found and recorded, only from the activation day, once. */
final class TreasuryBackfillTest extends TestCase
{
    private const string FROM = '2026-11-10';

    public function testItFindsOnlyTheNonCashPosPaymentsAfterActivationThatHaveNoMovement(): void
    {
        $kit = $this->kit();
        $this->posPayment($kit, 'wanted', 6, '2026-11-12 10:00:00');
        $this->posPayment($kit, 'cash', 1, '2026-11-12 10:00:00');
        $this->posPayment($kit, 'before-activation', 6, '2026-11-01 10:00:00');
        $this->posPayment($kit, 'on-credit', 2, '2026-11-12 10:00:00', behavior: 'RECEIVABLE');
        $this->posPayment($kit, 'voided-sale', 6, '2026-11-12 10:00:00', saleStatus: 'VOIDED');
        $this->posPayment($kit, 'no-key', 6, '2026-11-12 10:00:00', key: null);
        $this->posPayment($kit, 'already-there', 6, '2026-11-12 10:00:00');
        $kit->recorder->record($this->request(MovementSource::SalePayment, 'already-there', Direction::In));

        $missing = new TreasuryGaps($kit->db)->missing(new DateTimeImmutable(self::FROM));

        self::assertSame(['wanted'], array_map(static fn($m): string => $m->sourceId, $missing));
        self::assertSame(Direction::In, $missing[0]->direction);
        self::assertSame(TreasuryTestDatabase::BRANCH, $missing[0]->branchId);
        self::assertSame('Venta B001-7', $missing[0]->description);
    }

    public function testItFindsNonCashSupplierPaymentsThatWereNotReversed(): void
    {
        $kit = $this->kit();
        $this->supplierPayment($kit, 101, 5, '2026-11-12');
        $this->supplierPayment($kit, 102, 1, '2026-11-12');
        $this->supplierPayment($kit, 103, 5, '2026-11-12', reversed: true);
        $this->supplierPayment($kit, 104, 5, '2026-11-01');

        $missing = new TreasuryGaps($kit->db)->missing(new DateTimeImmutable(self::FROM));

        self::assertSame(['101'], array_map(static fn($m): string => $m->sourceId, $missing));
        self::assertSame(Direction::Out, $missing[0]->direction);
        self::assertSame('supplier', $missing[0]->partyType);
    }

    public function testBackfillRecordsThroughTheMappingMarksTheOriginAndIsIdempotent(): void
    {
        $kit = $this->kit();
        $kit->account('yape', AccountKind::Wallet);
        $kit->accounts->map(TreasuryTestDatabase::BRANCH, 6, 'PEN', 'yape');
        $this->posPayment($kit, 'one', 6, '2026-11-12 10:00:00', amount: '30.00');
        $backfill = new TreasuryBackfill(new TreasuryGaps($kit->db), $kit->recorder);

        $first = $backfill->run(new DateTimeImmutable(self::FROM), false);
        $second = $backfill->run(new DateTimeImmutable(self::FROM), false);

        self::assertSame(1, $first['recorded']);
        self::assertSame(0, $first['suspense']);
        self::assertSame(0, $second['found'], 'nothing is left to find after the first run');
        self::assertSame('30.00', $kit->movements->balance('yape'));
        self::assertSame('backfill', $kit->db->fetchOne('SELECT origin FROM treasury_movement'));
    }

    public function testADryRunOnlyCounts(): void
    {
        $kit = $this->kit();
        $this->posPayment($kit, 'one', 6, '2026-11-12 10:00:00');

        $result = new TreasuryBackfill(new TreasuryGaps($kit->db), $kit->recorder)->run(
            new DateTimeImmutable(self::FROM),
            true,
        );

        self::assertSame(1, $result['found']);
        self::assertSame(0, $result['recorded']);
        self::assertSame(0, (int) $kit->db->fetchOne('SELECT COUNT(*) FROM treasury_movement'));
    }

    public function testAPaymentThatCannotBeRecordedIsReportedNotSwallowed(): void
    {
        $kit = TreasuryTestDatabase::create(); // no suspense account, no mapping
        $kit->db->insert('payment_method', ['id' => 6, 'name' => 'm6', 'settlement_behavior' => 'COLLECTED']);
        $this->posPayment($kit, 'orphan', 6, '2026-11-12 10:00:00');

        $result = new TreasuryBackfill(new TreasuryGaps($kit->db), $kit->recorder)->run(
            new DateTimeImmutable(self::FROM),
            false,
        );

        self::assertSame(1, $result['found']);
        self::assertSame(0, $result['recorded']);
        self::assertCount(1, $result['failed']);
        self::assertStringContainsString('orphan', $result['failed'][0]);
    }

    public function testTheActivationDayIsTheEarliestOpeningBalance(): void
    {
        $kit = $this->kit();
        $kit->account('yape', AccountKind::Wallet);
        $gaps = new TreasuryGaps($kit->db);
        self::assertNull($gaps->activationDate(), 'before any opening balance Treasury has not started counting');

        foreach (['2026-11-15', '2026-11-10'] as $i => $day) {
            $kit->account('acc-' . $i, AccountKind::Bank);
            $kit->recorder->record(new MoneyMovementRequest(
                key: new MovementKey(MovementSource::OpeningBalance, 'acc-' . $i),
                direction: Direction::In,
                amount: '100.00',
                currency: 'PEN',
                branchId: TreasuryTestDatabase::BRANCH,
                occurredAt: new DateTimeImmutable($day),
                accountId: 'acc-' . $i,
            ));
        }

        // A fresh instance: PHPStan remembers the null the same call returned above.
        $activation = new TreasuryGaps($kit->db)->activationDate();
        self::assertNotNull($activation);
        self::assertSame('2026-11-10', $activation->format('Y-m-d'));
    }

    private function kit(): TreasuryTestDatabase
    {
        $kit = TreasuryTestDatabase::create();
        $kit->account('suspense', AccountKind::Suspense);
        foreach ([[1, 'COLLECTED'], [5, 'COLLECTED'], [6, 'COLLECTED'], [2, 'RECEIVABLE']] as [$id, $behavior]) {
            $kit->db->insert('payment_method', ['id' => $id, 'name' => 'm' . $id, 'settlement_behavior' => $behavior]);
        }

        return $kit;
    }

    private function request(MovementSource $source, string $id, Direction $direction): MoneyMovementRequest
    {
        return new MoneyMovementRequest(
            key: new MovementKey($source, $id),
            direction: $direction,
            amount: '10.00',
            currency: 'PEN',
            branchId: TreasuryTestDatabase::BRANCH,
            occurredAt: new DateTimeImmutable('2026-11-12'),
            paymentMethodId: 6,
        );
    }

    private function posPayment(
        TreasuryTestDatabase $kit,
        string $saleId,
        int $method,
        string $paidAt,
        string $behavior = 'COLLECTED',
        string $saleStatus = 'COMPLETED',
        ?string $key = 'use-sale-id',
        string $amount = '10.00',
    ): void {
        if ($behavior !== 'COLLECTED') {
            $kit->db->update('payment_method', ['settlement_behavior' => $behavior], ['id' => $method]);
        }
        $kit->db->insert('sales', [
            'id' => $saleId,
            'series' => 'B001',
            'number' => '7',
            'branch_id' => TreasuryTestDatabase::BRANCH,
            'customer_id' => 3,
            'status' => $saleStatus,
            'currency' => 'PEN',
        ]);
        $kit->db->insert('sale_payments', [
            'sale_id' => $saleId,
            'payment_method_id' => $method,
            'amount' => $amount,
            'currency' => 'PEN',
            'paid_at' => $paidAt,
            'idempotency_key' => $key === 'use-sale-id' ? $saleId : $key,
        ]);
    }

    private function supplierPayment(
        TreasuryTestDatabase $kit,
        int $id,
        int $method,
        string $paidAt,
        bool $reversed = false,
    ): void {
        $kit->db->insert('supplier_bill', [
            'id' => 'bill-' . $id,
            'supplier_id' => 'sup-1',
            'branch_id' => TreasuryTestDatabase::BRANCH,
            'series' => 'F001',
            'number' => (string) $id,
            'currency' => 'PEN',
        ]);
        $kit->db->insert('supplier_payment', [
            'id' => $id,
            'supplier_bill_id' => 'bill-' . $id,
            'payment_method_id' => $method,
            'amount' => '50.00',
            'paid_at' => $paidAt,
            'reversed_at' => $reversed ? '2026-11-13 09:00:00' : null,
        ]);
    }
}
