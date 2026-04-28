<?php

declare(strict_types=1);

namespace App\Tests\CashRegister\Entity;

use App\CashRegister\Entity\CashMovementCategory;
use App\CashRegister\Enum\CashMovementType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(CashMovementCategory::class)]
final class CashMovementCategoryTest extends TestCase
{
    public function testGetIdReturnsNullBeforePersistence(): void
    {
        $entity = new CashMovementCategory();

        self::assertNull($entity->getId());
    }

    public function testSetAndGetCode(): void
    {
        $entity = new CashMovementCategory();
        $entity->setCode(CashMovementCategory::CASH_SALE);

        self::assertSame(CashMovementCategory::CASH_SALE, $entity->getCode());
    }

    public function testSetAndGetName(): void
    {
        $entity = new CashMovementCategory();
        $entity->setCode('other_income');
        $entity->setName('Other Income');

        self::assertSame('Other Income', $entity->getName());
    }

    public function testSetAndGetTypeIncome(): void
    {
        $entity = new CashMovementCategory();
        $entity->setCode('cash_sale');
        $entity->setName('Cash Sale');
        $entity->setType(CashMovementType::INCOME);

        self::assertSame(CashMovementType::INCOME, $entity->getType());
    }

    public function testSetAndGetTypeExpense(): void
    {
        $entity = new CashMovementCategory();
        $entity->setCode('petty_cash_expense');
        $entity->setName('Petty Cash Expense');
        $entity->setType(CashMovementType::EXPENSE);

        self::assertSame(CashMovementType::EXPENSE, $entity->getType());
    }

    public function testIsSystemDefaultsToTrue(): void
    {
        $entity = new CashMovementCategory();

        self::assertTrue($entity->isSystem());
    }

    public function testSetIsSystem(): void
    {
        $entity = new CashMovementCategory();
        $entity->setIsSystem(false);

        self::assertFalse($entity->isSystem());
    }

    public function testIsActiveDefaultsToTrue(): void
    {
        $entity = new CashMovementCategory();

        self::assertTrue($entity->isActive());
    }

    public function testSetActive(): void
    {
        $entity = new CashMovementCategory();
        $entity->setActive(false);

        self::assertFalse($entity->isActive());
    }

    public function testPreventDeletionForSystemThrows(): void
    {
        $entity = new CashMovementCategory();
        $entity->setIsSystem(true);

        $this->expectException(RuntimeException::class);
        $entity->preventDeletionForSystem();
    }

    public function testPreventDeletionForNonSystemDoesNotThrow(): void
    {
        $entity = new CashMovementCategory();
        $entity->setIsSystem(false);

        // Should not throw
        $entity->preventDeletionForSystem();
        $this->addToAssertionCount(1);
    }

    public function testPreventUpdateForSystemThrows(): void
    {
        $entity = new CashMovementCategory();
        $entity->setIsSystem(true);

        $this->expectException(RuntimeException::class);
        $entity->preventUpdateForSystem();
    }

    public function testPreventUpdateForNonSystemDoesNotThrow(): void
    {
        $entity = new CashMovementCategory();
        $entity->setIsSystem(false);

        // Should not throw
        $entity->preventUpdateForSystem();
        $this->addToAssertionCount(1);
    }

    public function testSystemConstants(): void
    {
        self::assertSame('cash_sale', CashMovementCategory::CASH_SALE);
        self::assertSame('credit_payment', CashMovementCategory::CREDIT_PAYMENT);
        self::assertSame('cash_replenishment', CashMovementCategory::CASH_REPLENISHMENT);
        self::assertSame('other_income', CashMovementCategory::OTHER_INCOME);
        self::assertSame('overage_adjustment', CashMovementCategory::OVERAGE_ADJUSTMENT);
        self::assertSame('petty_cash_expense', CashMovementCategory::PETTY_CASH_EXPENSE);
        self::assertSame('customer_refund', CashMovementCategory::CUSTOMER_REFUND);
        self::assertSame('manager_withdrawal', CashMovementCategory::MANAGER_WITHDRAWAL);
        self::assertSame('bank_deposit', CashMovementCategory::BANK_DEPOSIT);
        self::assertSame('shortage_adjustment', CashMovementCategory::SHORTAGE_ADJUSTMENT);
    }
}
