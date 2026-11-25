<?php

declare(strict_types=1);

namespace App\Tests\Treasury;

use App\Treasury\Domain\AccountKind;
use App\Treasury\Domain\Direction;
use App\Treasury\Domain\MovementKey;
use App\Treasury\Domain\MovementSource;
use App\Treasury\Domain\TreasuryException;
use App\Treasury\Port\MoneyMovementRequest;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ExternalBankReceiptTest extends TestCase
{
    public function testOnlyTheMappedBankReceivesAnExternalReceipt(): void
    {
        $kit = TreasuryTestDatabase::create();
        $kit->account('bank');
        $kit->account('override');
        $kit->accounts->map(TreasuryTestDatabase::BRANCH, 5, 'PEN', 'bank');

        $result = $kit->recorder->record(self::request());

        static::assertSame('bank', $result->accountId);
        static::assertSame('30.00', $kit->movements->balance('bank'));
        static::assertSame(0, (int) $kit->db->fetchOne('SELECT COUNT(*) FROM cash_movements'));
    }

    public function testTheExternalReceiptCannotFallBackToSuspenseOrUseAWalletMapping(): void
    {
        $kit = TreasuryTestDatabase::create();
        $kit->account('override');
        $kit->account('suspense', AccountKind::Suspense);
        $kit->account('wallet', AccountKind::Wallet);

        foreach ([null, 'wallet'] as $mapping) {
            if ($mapping !== null) {
                $kit->accounts->map(TreasuryTestDatabase::BRANCH, 5, 'PEN', $mapping);
            }
            try {
                $kit->recorder->record(self::request());
                static::fail('An external receipt requires a mapped bank.');
            } catch (TreasuryException) {
                static::assertSame(0, (int) $kit->db->fetchOne('SELECT COUNT(*) FROM treasury_movement'));
            }
        }
    }

    private static function request(): MoneyMovementRequest
    {
        return new MoneyMovementRequest(
            key: new MovementKey(MovementSource::SalePayment, 'external-receipt'),
            direction: Direction::In,
            amount: '30.00',
            currency: 'PEN',
            branchId: TreasuryTestDatabase::BRANCH,
            occurredAt: new DateTimeImmutable('2026-11-24'),
            paymentMethodId: 5,
            accountId: 'override',
            requiredAccountKind: AccountKind::Bank,
        );
    }
}
