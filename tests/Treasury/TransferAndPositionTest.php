<?php

declare(strict_types=1);

namespace App\Tests\Treasury;

use App\Treasury\Domain\AccountKind;
use App\Treasury\Domain\CurrencyMismatch;
use App\Treasury\Domain\Direction;
use App\Treasury\Domain\MovementKey;
use App\Treasury\Domain\MovementSource;
use App\Treasury\Domain\TreasuryException;
use App\Treasury\Port\MoneyMovementRequest;
use App\Treasury\Service\DrawerRecorder;
use App\Treasury\Service\PositionQuery;
use App\Treasury\Service\TransferMoney;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class TransferAndPositionTest extends TestCase
{
    public function testATransferWritesTwoLegsThatShareAnIdAndSumToZero(): void
    {
        $kit = TreasuryTestDatabase::create();
        $kit->account('bcp');
        $kit->account('yape', AccountKind::Wallet);

        $id = $this->transfer($kit)->transfer(
            'bcp',
            'yape',
            '250.00',
            new DateTimeImmutable('2026-11-18 11:00:00'),
            'Recarga',
            5,
        );

        $rows = $kit->db->fetchAllAssociative(
            'SELECT account_id, signed_amount, leg, transfer_id FROM treasury_movement ORDER BY signed_amount',
        );
        self::assertCount(2, $rows);
        self::assertSame(['bcp', 'yape'], array_column($rows, 'account_id'));
        self::assertSame([$id, $id], array_column($rows, 'transfer_id'));
        self::assertSame('-250.00', $kit->movements->balance('bcp'));
        self::assertSame('250.00', $kit->movements->balance('yape'));
        self::assertEquals(0, (float) $kit->db->fetchOne('SELECT SUM(signed_amount) FROM treasury_movement'));
    }

    public function testDepositingCashFromTheDrawerToABankWritesTheDrawersLedgerAndTheBankLeg(): void
    {
        $kit = TreasuryTestDatabase::create();
        $kit->account('drawer', AccountKind::CashRegister, cashRegisterId: 3);
        $kit->account('bcp');
        $kit->openSession(10, 3, 7, '500.00');

        $id = $this->transfer($kit)->transfer(
            'drawer',
            'bcp',
            '200.00',
            new DateTimeImmutable('2026-11-18 11:00:00'),
            'Depósito',
            7,
        );

        self::assertSame('300.00', $kit->drawers->balanceOf(3), 'the arqueo sees the cash leaving the drawer');
        self::assertSame('200.00', $kit->movements->balance('bcp'));
        $leg = $kit->db->fetchAssociative(
            'SELECT movement_type, amount FROM cash_movements WHERE cash_session_id = 10',
        );
        self::assertIsArray($leg);
        self::assertSame('CASH_OUT', $leg['movement_type']);
        self::assertEquals(200, $leg['amount']);
        self::assertSame($id, $kit->db->fetchOne('SELECT transfer_id FROM treasury_movement'));
        self::assertSame(
            1,
            (int) $kit->db->fetchOne('SELECT COUNT(*) FROM treasury_movement'),
            'the drawer leg lives in the cash ledger',
        );
    }

    public function testWithdrawingFromTheBankIntoTheDrawerAddsCashToTheOpenSession(): void
    {
        $kit = TreasuryTestDatabase::create();
        $kit->account('drawer', AccountKind::CashRegister, cashRegisterId: 3);
        $kit->account('bcp');
        $kit->openSession(10, 3, 7, '50.00');

        $this->transfer($kit)->transfer(
            'bcp',
            'drawer',
            '120.00',
            new DateTimeImmutable('2026-11-18 11:00:00'),
            null,
            7,
        );

        self::assertSame('170.00', $kit->drawers->balanceOf(3));
        self::assertSame('-120.00', $kit->movements->balance('bcp'));
    }

    public function testADrawerTransferIsRefusedWithoutTheUsersOpenSessionOnThatRegisterAndWritesNothing(): void
    {
        $kit = TreasuryTestDatabase::create();
        $kit->account('drawer', AccountKind::CashRegister, cashRegisterId: 3);
        $kit->account('other-drawer', AccountKind::CashRegister, cashRegisterId: 4);
        $kit->account('bcp');
        $kit->openSession(10, 4, 7, '500.00'); // the user is on another register
        $transfer = $this->transfer($kit);
        $when = new DateTimeImmutable('2026-11-18');

        foreach ([
            ['drawer', 'bcp',          7],
            ['bcp',    'drawer',       7],
            ['drawer', 'bcp',          null],
            ['drawer', 'other-drawer', 7],
        ] as [$from, $to, $user]) {
            try {
                $transfer->transfer($from, $to, '10.00', $when, null, $user);
                self::fail("{$from} -> {$to} must be refused.");
            } catch (TreasuryException) {
                // expected
            }
        }
        self::assertSame(0, (int) $kit->db->fetchOne('SELECT COUNT(*) FROM treasury_movement'));
        self::assertSame(0, (int) $kit->db->fetchOne('SELECT COUNT(*) FROM cash_movements'));
    }

    public function testATransferIsRefusedWhenItCannotBeBalanced(): void
    {
        $kit = TreasuryTestDatabase::create();
        $kit->account('bcp');
        $kit->account('usd', currency: 'USD');
        $kit->account('closed', active: false);
        $kit->account('drawer', AccountKind::CashRegister, cashRegisterId: 3);
        $transfer = $this->transfer($kit);
        $when = new DateTimeImmutable('2026-11-18');

        $cases = [
            'same account' => ['bcp', 'bcp', '10.00', TreasuryException::class],
            'zero' => ['bcp', 'usd', '0.00', TreasuryException::class],
            'other currency' => ['bcp', 'usd', '10.00', CurrencyMismatch::class],
            'inactive' => ['bcp', 'closed', '10.00', TreasuryException::class],
            'unknown' => ['bcp', 'nope', '10.00', TreasuryException::class],
            'drawer without an open session' => ['bcp', 'drawer', '10.00', TreasuryException::class],
        ];
        foreach ($cases as $name => [$from, $to, $amount, $exception]) {
            try {
                $transfer->transfer($from, $to, $amount, $when);
                self::fail("Transfer '{$name}' must be refused.");
            } catch (TreasuryException $e) {
                self::assertInstanceOf($exception, $e, $name);
            }
        }
        self::assertSame(0, (int) $kit->db->fetchOne('SELECT COUNT(*) FROM treasury_movement'));
    }

    public function testThePositionListsEveryAccountAndTotalsPerCurrencyWithoutMixingThem(): void
    {
        $kit = TreasuryTestDatabase::create();
        $kit->account('bcp');
        $kit->account('bbva-usd', currency: 'USD');
        $kit->account('closed', active: false);
        $this->move($kit, 'bcp', Direction::In, '1000.00', 'A');
        $this->move($kit, 'bcp', Direction::Out, '0.10', 'B');
        $this->move($kit, 'bcp', Direction::Out, '0.20', 'C');
        $this->move($kit, 'bbva-usd', Direction::In, '300.00', 'D');

        $position = new PositionQuery($kit->accounts, $kit->movements, $kit->drawers);

        self::assertSame(['PEN' => '999.70', 'USD' => '300.00'], $position->totalsByCurrency());
        self::assertCount(2, $position->accounts(), 'inactive accounts are not part of the position');
    }

    public function testADrawerIsReadLikeTheSessionCloseComputesIt(): void
    {
        $kit = TreasuryTestDatabase::create();
        $kit->account('drawer', AccountKind::CashRegister, cashRegisterId: 3);
        $kit->openSession(10, 3, 7, '200.00');
        $kit->cashMovement(10, 'OPENING', '200.00'); // audit row, must not be counted
        $kit->cashMovement(10, 'SALE_IN', '85.50');
        $kit->cashMovement(10, 'CASH_IN', '14.50');
        $kit->cashMovement(10, 'CASH_OUT', '30.00');
        $kit->cashMovement(10, 'REFUND_OUT', '10.00');

        self::assertSame('260.00', $kit->drawers->balanceOf(3));
        self::assertSame(3, $kit->drawers->openRegisterIdFor(7));
        self::assertNull($kit->drawers->openRegisterIdFor(99));
    }

    public function testAClosedDrawerShowsWhatWasCountedAtTheClose(): void
    {
        $kit = TreasuryTestDatabase::create();
        $kit->db->insert('cash_sessions', [
            'id' => 1,
            'cash_register_id' => 3,
            'opened_by' => 7,
            'status' => 'CLOSED',
            'opening_amount' => '100.00',
            'expected_amount' => '180.00',
            'counted_amount' => '178.50',
            'opened_at' => '2026-11-17 08:00:00',
        ]);

        self::assertSame('178.50', $kit->drawers->balanceOf(3));
        self::assertSame('0.00', $kit->drawers->balanceOf(404), 'a register that never opened holds nothing');
    }

    private function transfer(TreasuryTestDatabase $kit): TransferMoney
    {
        return new TransferMoney(
            $kit->accounts,
            $kit->movements,
            $kit->db,
            new DrawerRecorder($kit->cash, $kit->drawers),
        );
    }

    private function move(
        TreasuryTestDatabase $kit,
        string $account,
        Direction $direction,
        string $amount,
        string $id,
    ): void {
        $kit->recorder->record(new MoneyMovementRequest(
            key: new MovementKey(MovementSource::OpeningBalance, $id),
            direction: $direction,
            amount: $amount,
            currency: $kit->accounts->find($account)->currency ?? 'PEN',
            branchId: TreasuryTestDatabase::BRANCH,
            occurredAt: new DateTimeImmutable('2026-11-18'),
            accountId: $account,
        ));
    }
}
