<?php

declare(strict_types=1);

namespace App\Tests\CashRegister\Enum;

use App\CashRegister\Enum\CashMovementType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CashMovementType::class)]
final class CashMovementTypeTest extends TestCase
{
    public function testIncomeValue(): void
    {
        self::assertSame('income', CashMovementType::INCOME->value);
    }

    public function testExpenseValue(): void
    {
        self::assertSame('expense', CashMovementType::EXPENSE->value);
    }

    public function testFromIncome(): void
    {
        self::assertSame(CashMovementType::INCOME, CashMovementType::from('income'));
    }

    public function testFromExpense(): void
    {
        self::assertSame(CashMovementType::EXPENSE, CashMovementType::from('expense'));
    }

    public function testTryFromUnknownReturnsNull(): void
    {
        $value = 'unknown';
        // @phpstan-ignore staticMethod.alreadyNarrowedType
        self::assertNull(CashMovementType::tryFrom($value));
    }

    public function testCasesAreUnique(): void
    {
        $cases = CashMovementType::cases();
        $values = array_map(static fn (CashMovementType $c) => $c->value, $cases);

        self::assertCount(count(array_unique($values)), $cases);
    }
}
