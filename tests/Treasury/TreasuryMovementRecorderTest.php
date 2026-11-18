<?php

declare(strict_types=1);

namespace App\Tests\Treasury;

use App\Treasury\Domain\AccountKind;
use App\Treasury\Domain\CurrencyMismatch;
use App\Treasury\Domain\Direction;
use App\Treasury\Domain\DrawerWriteNotAvailable;
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

    public function testCashWithAnOpenSessionReachesTheDrawerWhichIsNotWritableYet(): void
    {
        $kit = TreasuryTestDatabase::create();
        $kit->account('drawer', AccountKind::CashRegister, cashRegisterId: 3);
        $kit->openSession(10, 3, 7, '200.00');

        $this->expectException(DrawerWriteNotAvailable::class);
        $kit->recorder->record($this->request(
            'S-1',
            Direction::Out,
            '10.00',
            method: self::CASH,
            isCash: true,
            userId: 7,
        ));
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
