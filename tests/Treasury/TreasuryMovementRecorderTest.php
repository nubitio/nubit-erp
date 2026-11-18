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
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class TreasuryMovementRecorderTest extends TestCase
{
    private const int YAPE = 6;
    private const int CASH = 1;

    public function testAnOutflowIsStoredNegativeAndAnInflowPositive(): void
    {
        $kit = TreasuryTestDatabase::create();
        $kit->account('bank', AccountKind::Bank);
        $kit->accounts->map(TreasuryTestDatabase::BRANCH, 5, 'PEN', 'bank');

        $kit->recorder->record($this->request('S-1', Direction::Out, '118.00', method: 5));
        $kit->recorder->record($this->request('S-2', Direction::In, '50.50', method: 5));

        self::assertSame(
            '-118.00',
            $kit->movements->findByKey(new MovementKey(MovementSource::SupplierPayment, 'S-1'))?->signedAmount,
        );
        self::assertSame(
            '50.50',
            $kit->movements->findByKey(new MovementKey(MovementSource::SupplierPayment, 'S-2'))?->signedAmount,
        );
        self::assertSame('-67.50', $kit->movements->balance('bank'));
    }

    public function testRecordingTheSameSourceTwiceWritesOneRow(): void
    {
        $kit = TreasuryTestDatabase::create();
        $kit->account('bank');
        $kit->accounts->map(TreasuryTestDatabase::BRANCH, 5, 'PEN', 'bank');

        $first = $kit->recorder->record($this->request('S-1', Direction::In, '10.00', method: 5));
        $replay = $kit->recorder->record($this->request('S-1', Direction::In, '10.00', method: 5));

        self::assertFalse($first->duplicate);
        self::assertTrue($replay->duplicate);
        self::assertSame($first->movementId, $replay->movementId);
        self::assertSame(1, (int) $kit->db->fetchOne('SELECT COUNT(*) FROM treasury_movement'));
        self::assertSame('10.00', $kit->movements->balance('bank'));
    }

    public function testAnAmountThatIsNotPositiveIsRejected(): void
    {
        $kit = TreasuryTestDatabase::create();
        $kit->account('bank');

        foreach (['0', '0.00', '-5.00'] as $amount) {
            try {
                $kit->recorder->record($this->request('S-' . $amount, Direction::In, $amount, accountId: 'bank'));
                self::fail('An amount of ' . $amount . ' must be rejected.');
            } catch (TreasuryException $e) {
                self::assertStringContainsString('mayor a 0', $e->getMessage());
            }
        }
        self::assertSame(0, (int) $kit->db->fetchOne('SELECT COUNT(*) FROM treasury_movement'));
    }

    public function testThePickedAccountWinsOverTheMappingAndMustBeActive(): void
    {
        $kit = TreasuryTestDatabase::create();
        $kit->account('mapped');
        $kit->account('picked');
        $kit->account('closed', active: false);
        $kit->accounts->map(TreasuryTestDatabase::BRANCH, 5, 'PEN', 'mapped');

        $result = $kit->recorder->record($this->request(
            'S-1',
            Direction::Out,
            '20.00',
            method: 5,
            accountId: 'picked',
        ));
        self::assertSame('picked', $result->accountId);

        $this->expectException(TreasuryException::class);
        $kit->recorder->record($this->request('S-2', Direction::Out, '20.00', method: 5, accountId: 'closed'));
    }

    public function testAMovementWithoutABranchAsksForOneInsteadOfGuessingAnAccount(): void
    {
        $kit = TreasuryTestDatabase::create();
        $kit->account('suspense', AccountKind::Suspense);
        $request = new MoneyMovementRequest(
            key: new MovementKey(MovementSource::SupplierPayment, 'S-1'),
            direction: Direction::In,
            amount: '10.00',
            currency: 'PEN',
            branchId: '',
            occurredAt: new DateTimeImmutable('2026-11-18'),
            paymentMethodId: self::YAPE,
        );

        $this->expectException(TreasuryException::class);
        $this->expectExceptionMessage('Selecciona una sucursal');
        $kit->recorder->record($request);
    }

    public function testAMethodWithoutAnAccountLandsInSuspenseWithAWarning(): void
    {
        $kit = TreasuryTestDatabase::create();
        $kit->account('suspense', AccountKind::Suspense);

        $result = $kit->recorder->record($this->request('S-1', Direction::In, '30.00', method: self::YAPE));

        self::assertSame('suspense', $result->accountId);
        self::assertNotNull($result->warning);
        self::assertStringContainsString('suspenso', (string) $result->warning);
        self::assertSame('30.00', $kit->movements->balance('suspense'));
    }

    public function testAMappingOnlyAppliesToItsCurrency(): void
    {
        $kit = TreasuryTestDatabase::create();
        $kit->account('yape-pen', AccountKind::Wallet, 'PEN');
        $kit->account('yape-usd', AccountKind::Wallet, 'USD');
        $kit->account('suspense-usd', AccountKind::Suspense, 'USD');
        $kit->accounts->map(TreasuryTestDatabase::BRANCH, self::YAPE, 'PEN', 'yape-pen');

        $pen = $kit->recorder->record($this->request('S-1', Direction::In, '10.00', method: self::YAPE));
        $usd = $kit->recorder->record($this->request(
            'S-2',
            Direction::In,
            '10.00',
            method: self::YAPE,
            currency: 'USD',
        ));
        self::assertSame('yape-pen', $pen->accountId);
        self::assertSame('suspense-usd', $usd->accountId, 'no USD mapping, so it is not forced into the PEN account');

        $kit->accounts->map(TreasuryTestDatabase::BRANCH, self::YAPE, 'USD', 'yape-usd');
        $mapped = $kit->recorder->record($this->request(
            'S-3',
            Direction::In,
            '10.00',
            method: self::YAPE,
            currency: 'USD',
        ));
        self::assertSame('yape-usd', $mapped->accountId);
    }

    public function testASuspenseAccountIsPerCurrency(): void
    {
        $kit = TreasuryTestDatabase::create();
        $kit->account('suspense-pen', AccountKind::Suspense, 'PEN');

        $this->expectException(TreasuryException::class);
        $this->expectExceptionMessage('suspenso en USD');
        $kit->recorder->record($this->request('S-1', Direction::In, '30.00', method: self::YAPE, currency: 'USD'));
    }

    public function testAPaymentInAnotherCurrencyThanTheAccountIsRejectedNotConverted(): void
    {
        $kit = TreasuryTestDatabase::create();
        $kit->account('bank', currency: 'PEN');

        $this->expectException(CurrencyMismatch::class);
        $kit->recorder->record($this->request('S-1', Direction::In, '10.00', accountId: 'bank', currency: 'USD'));
    }

    public function testCashNeedsAnOpenSession(): void
    {
        $kit = TreasuryTestDatabase::create();

        $this->expectException(TreasuryException::class);
        $this->expectExceptionMessage('caja abierta');
        $kit->recorder->record($this->request(
            'S-1',
            Direction::Out,
            '10.00',
            method: self::CASH,
            isCash: true,
            userId: 7,
        ));
    }

    public function testACashPaymentLeavesTheOpenDrawerAndTheArqueoSeesIt(): void
    {
        $kit = TreasuryTestDatabase::create();
        $kit->account('drawer', AccountKind::CashRegister, cashRegisterId: 3);
        $kit->openSession(10, 3, 7, '200.00');

        $result = $kit->recorder->record($this->request(
            'P-1',
            Direction::Out,
            '45.50',
            method: self::CASH,
            isCash: true,
            userId: 7,
        ));

        self::assertSame(AccountKind::CashRegister, $result->accountKind);
        self::assertFalse($result->duplicate);
        $row = $kit->db->fetchAssociative('SELECT movement_type, amount, cash_session_id FROM cash_movements');
        self::assertIsArray($row);
        self::assertSame('CASH_OUT', $row['movement_type']);
        self::assertSame('45.50', number_format((float) $row['amount'], 2, '.', ''));
        self::assertSame(10, (int) $row['cash_session_id']);
        self::assertSame('154.50', $kit->drawers->balanceOf(3), 'the drawer shows 200.00 - 45.50');
        self::assertSame(
            0,
            (int) $kit->db->fetchOne('SELECT COUNT(*) FROM treasury_movement'),
            'drawers are not copied into the ledger',
        );
    }

    public function testReplayingACashPaymentDoesNotTakeMoneyOutTwice(): void
    {
        $kit = TreasuryTestDatabase::create();
        $kit->account('drawer', AccountKind::CashRegister, cashRegisterId: 3);
        $kit->openSession(10, 3, 7, '200.00');

        $first = $kit->recorder->record($this->request(
            'P-1',
            Direction::Out,
            '45.50',
            method: self::CASH,
            isCash: true,
            userId: 7,
        ));
        $replay = $kit->recorder->record($this->request(
            'P-1',
            Direction::Out,
            '45.50',
            method: self::CASH,
            isCash: true,
            userId: 7,
        ));

        self::assertTrue($replay->duplicate);
        self::assertSame($first->movementId, $replay->movementId);
        self::assertSame('154.50', $kit->drawers->balanceOf(3));
    }

    public function testACashPaymentOnAnotherRegistersSessionIsRefused(): void
    {
        $kit = TreasuryTestDatabase::create();
        $kit->account('drawer-a', AccountKind::CashRegister, cashRegisterId: 3);
        $kit->account('drawer-b', AccountKind::CashRegister, cashRegisterId: 4);
        $kit->openSession(10, 3, 7, '200.00');

        $this->expectException(TreasuryException::class);
        $this->expectExceptionMessage('otra caja');
        $kit->recorder->record($this->request('P-1', Direction::Out, '10.00', accountId: 'drawer-b', userId: 7));
    }

    public function testReversingABankPaymentWritesTheOppositeRowOnTheSameAccount(): void
    {
        $kit = TreasuryTestDatabase::create();
        $kit->account('bcp');
        $kit->account('other');
        $kit->accounts->map(TreasuryTestDatabase::BRANCH, 5, 'PEN', 'bcp');
        $paid = $kit->recorder->record($this->request('P-1', Direction::Out, '118.00', method: 5));
        // The mapping changes after the payment; the reversal must still go back to where the money left.
        $kit->accounts->map(TreasuryTestDatabase::BRANCH, 5, 'PEN', 'other');

        $reversed = $kit->recorder->reverse(
            new MovementKey(MovementSource::SupplierPayment, 'P-1'),
            new MovementKey(MovementSource::SupplierPaymentReversal, 'P-1'),
            new DateTimeImmutable('2026-11-19 09:00:00'),
            7,
        );

        self::assertNotNull($reversed);
        self::assertSame('bcp', $reversed->accountId);
        self::assertSame('0.00', $kit->movements->balance('bcp'));
        self::assertSame('0.00', $kit->movements->balance('other'));
        $reversal = $kit->movements->findByKey(new MovementKey(MovementSource::SupplierPaymentReversal, 'P-1'));
        self::assertNotNull($reversal);
        self::assertSame($paid->movementId, $reversal->correctsMovementId);
        self::assertSame('118.00', $reversal->signedAmount);
    }

    public function testReversingTwiceWritesOnlyOneCorrection(): void
    {
        $kit = TreasuryTestDatabase::create();
        $kit->account('bcp');
        $kit->accounts->map(TreasuryTestDatabase::BRANCH, 5, 'PEN', 'bcp');
        $kit->recorder->record($this->request('P-1', Direction::Out, '50.00', method: 5));
        $original = new MovementKey(MovementSource::SupplierPayment, 'P-1');
        $reversal = new MovementKey(MovementSource::SupplierPaymentReversal, 'P-1');
        $at = new DateTimeImmutable('2026-11-19');

        $kit->recorder->reverse($original, $reversal, $at, 7);
        $again = $kit->recorder->reverse($original, $reversal, $at, 7);

        self::assertTrue($again?->duplicate);
        self::assertSame('0.00', $kit->movements->balance('bcp'));
        self::assertSame(2, (int) $kit->db->fetchOne('SELECT COUNT(*) FROM treasury_movement'));
    }

    public function testReversingACashPaymentPutsTheCashBackInTheOpenDrawer(): void
    {
        $kit = TreasuryTestDatabase::create();
        $kit->account('drawer', AccountKind::CashRegister, cashRegisterId: 3);
        $kit->openSession(10, 3, 7, '200.00');
        $kit->recorder->record($this->request(
            'P-1',
            Direction::Out,
            '45.50',
            method: self::CASH,
            isCash: true,
            userId: 7,
        ));

        $result = $kit->recorder->reverse(
            new MovementKey(MovementSource::SupplierPayment, 'P-1'),
            new MovementKey(MovementSource::SupplierPaymentReversal, 'P-1'),
            new DateTimeImmutable('2026-11-18 12:00:00'),
            7,
        );

        self::assertSame(AccountKind::CashRegister, $result?->accountKind);
        self::assertSame('200.00', $kit->drawers->balanceOf(3));
        self::assertSame(
            ['CASH_OUT', 'CASH_IN'],
            $kit->db->fetchFirstColumn('SELECT movement_type FROM cash_movements ORDER BY id'),
        );
    }

    public function testReversingACashPaymentNeedsAnOpenDrawer(): void
    {
        $kit = TreasuryTestDatabase::create();
        $kit->account('drawer', AccountKind::CashRegister, cashRegisterId: 3);
        $kit->openSession(10, 3, 7, '200.00');
        $kit->recorder->record($this->request(
            'P-1',
            Direction::Out,
            '45.50',
            method: self::CASH,
            isCash: true,
            userId: 7,
        ));
        $kit->db->executeStatement("UPDATE cash_sessions SET status = 'CLOSED'");

        $this->expectException(TreasuryException::class);
        $this->expectExceptionMessage('caja abierta');
        $kit->recorder->reverse(
            new MovementKey(MovementSource::SupplierPayment, 'P-1'),
            new MovementKey(MovementSource::SupplierPaymentReversal, 'P-1'),
            new DateTimeImmutable('2026-11-19'),
            7,
        );
    }

    public function testReversingAPaymentTreasuryNeverSawDoesNothing(): void
    {
        $kit = TreasuryTestDatabase::create();

        $result = $kit->recorder->reverse(
            new MovementKey(MovementSource::SupplierPayment, 'old-payment'),
            new MovementKey(MovementSource::SupplierPaymentReversal, 'old-payment'),
            new DateTimeImmutable('2026-11-19'),
            7,
        );

        self::assertNull($result, 'a payment made before Treasury has nothing to undo');
    }

    private function request(
        string $sourceId,
        Direction $direction,
        string $amount,
        ?int $method = null,
        ?string $accountId = null,
        string $currency = 'PEN',
        bool $isCash = false,
        ?int $userId = null,
    ): MoneyMovementRequest {
        return new MoneyMovementRequest(
            key: new MovementKey(MovementSource::SupplierPayment, $sourceId),
            direction: $direction,
            amount: $amount,
            currency: $currency,
            branchId: TreasuryTestDatabase::BRANCH,
            occurredAt: new DateTimeImmutable('2026-11-18 10:00:00'),
            isCash: $isCash,
            paymentMethodId: $method,
            userId: $userId,
            accountId: $accountId,
        );
    }
}
