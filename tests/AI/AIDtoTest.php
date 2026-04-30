<?php

declare(strict_types=1);

namespace App\Tests\AI;

use App\AI\Dto\CashRegisterStatusDto;
use PHPUnit\Framework\TestCase;

final class AIDtoTest extends TestCase
{
    public function testCashRegisterStatusDtoHoldsAllFields(): void
    {
        $dto = new CashRegisterStatusDto(
            cashRegisterId: 1,
            cashRegisterName: 'Caja 1',
            status: 'open',
            shiftId: 42,
            openedAt: '2026-04-30T08:00:00',
            openedBy: 'juan',
            closedAt: null,
            openingBalance: 500.00,
            expectedAmount: 1250.00,
            countedAmount: 1248.50,
            difference: -1.50,
        );

        self::assertSame(1, $dto->cashRegisterId);
        self::assertSame('Caja 1', $dto->cashRegisterName);
        self::assertSame('open', $dto->status);
        self::assertSame(42, $dto->shiftId);
        self::assertSame('2026-04-30T08:00:00', $dto->openedAt);
        self::assertSame('juan', $dto->openedBy);
        self::assertNull($dto->closedAt);
        self::assertEqualsWithDelta(500.0, $dto->openingBalance ?? 0, 0.001);
        self::assertEqualsWithDelta(1250.0, $dto->expectedAmount ?? 0, 0.001);
        self::assertEqualsWithDelta(1248.5, $dto->countedAmount ?? 0, 0.001);
        self::assertEqualsWithDelta(-1.5, $dto->difference ?? 0, 0.001);
    }

    public function testCashRegisterStatusDtoNullableFields(): void
    {
        $dto = new CashRegisterStatusDto(
            cashRegisterId: 2,
            cashRegisterName: 'Caja 2',
            status: 'closed',
            shiftId: null,
            openedAt: null,
            openedBy: null,
            closedAt: null,
            openingBalance: null,
            expectedAmount: null,
            countedAmount: null,
            difference: null,
        );

        self::assertNull($dto->shiftId);
        self::assertNull($dto->openedAt);
        self::assertNull($dto->openedBy);
        self::assertNull($dto->openingBalance);
        self::assertNull($dto->expectedAmount);
        self::assertNull($dto->countedAmount);
        self::assertNull($dto->difference);
    }
}
