<?php

declare(strict_types=1);

namespace App\Tests\CashRegister\Enum;

use App\CashRegister\Enum\CashShiftStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CashShiftStatus::class)]
final class CashShiftStatusTest extends TestCase
{
    public function testOpenValue(): void
    {
        self::assertSame('open', CashShiftStatus::OPEN->value);
    }

    public function testClosedValue(): void
    {
        self::assertSame('closed', CashShiftStatus::CLOSED->value);
    }

    public function testFromOpen(): void
    {
        self::assertSame(CashShiftStatus::OPEN, CashShiftStatus::from('open'));
    }

    public function testFromClosed(): void
    {
        self::assertSame(CashShiftStatus::CLOSED, CashShiftStatus::from('closed'));
    }

    public function testTryFromUnknownReturnsNull(): void
    {
        $value = 'unknown';
        // @phpstan-ignore staticMethod.alreadyNarrowedType
        self::assertNull(CashShiftStatus::tryFrom($value));
    }
}
