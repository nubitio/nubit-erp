<?php

declare(strict_types=1);

namespace App\Tests\CashRegister\Entity;

use App\CashRegister\Entity\CashMovement;
use App\CashRegister\Entity\CashMovementCategory;
use App\CashRegister\Entity\CashSession;
use App\CashRegister\Entity\CashShift;
use App\CashRegister\Enum\CashMovementType;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(CashMovement::class)]
final class CashMovementTest extends TestCase
{
    public function testGetIdReturnsNullBeforePersistence(): void
    {
        $entity = new CashMovement();
        $entity->setType(CashMovementType::INCOME);
        $entity->setAmount('0.00');
        $entity->setDescription('');

        self::assertNull($entity->getId());
    }

    public function testSetAndGetTypeIncome(): void
    {
        $entity = new CashMovement();
        $entity->setType(CashMovementType::INCOME);
        $entity->setAmount('0.00');
        $entity->setDescription('');

        self::assertSame(CashMovementType::INCOME, $entity->getType());
    }

    public function testSetAndGetTypeExpense(): void
    {
        $entity = new CashMovement();
        $entity->setType(CashMovementType::EXPENSE);
        $entity->setAmount('0.00');
        $entity->setDescription('');

        self::assertSame(CashMovementType::EXPENSE, $entity->getType());
    }

    public function testSetAndGetAmount(): void
    {
        $entity = new CashMovement();
        $entity->setType(CashMovementType::INCOME);
        $entity->setAmount('123.45');
        $entity->setDescription('');

        self::assertSame('123.45', $entity->getAmount());
    }

    public function testSetAndGetDescription(): void
    {
        $entity = new CashMovement();
        $entity->setType(CashMovementType::INCOME);
        $entity->setAmount('0.00');
        $entity->setDescription('Test description');

        self::assertSame('Test description', $entity->getDescription());
    }

    public function testSetAndGetCashShift(): void
    {
        /** @var CashShift&MockObject */
        $cashShift = $this->createStub(CashShift::class);

        $entity = new CashMovement();
        $entity->setType(CashMovementType::INCOME);
        $entity->setAmount('0.00');
        $entity->setDescription('');
        $entity->setCashShift($cashShift);

        self::assertSame($cashShift, $entity->getCashShift());
    }

    public function testSetCashShiftToNull(): void
    {
        /** @var CashShift&MockObject */
        $cashShift = $this->createStub(CashShift::class);

        $entity = new CashMovement();
        $entity->setType(CashMovementType::INCOME);
        $entity->setAmount('0.00');
        $entity->setDescription('');
        $entity->setCashShift($cashShift);
        $entity->setCashShift(null);

        self::assertNull($entity->getCashShift());
    }

    public function testSetAndGetCashSession(): void
    {
        /** @var CashSession&MockObject */
        $cashSession = $this->createStub(CashSession::class);

        $entity = new CashMovement();
        $entity->setType(CashMovementType::INCOME);
        $entity->setAmount('0.00');
        $entity->setDescription('');
        $entity->setCashSession($cashSession);

        self::assertSame($cashSession, $entity->getCashSession());
    }

    public function testSetCashSessionToNull(): void
    {
        /** @var CashSession&MockObject */
        $cashSession = $this->createStub(CashSession::class);

        $entity = new CashMovement();
        $entity->setType(CashMovementType::INCOME);
        $entity->setAmount('0.00');
        $entity->setDescription('');
        $entity->setCashSession($cashSession);
        $entity->setCashSession(null);

        self::assertNull($entity->getCashSession());
    }

    public function testSetCategoryAlsoSetsType(): void
    {
        /** @var CashMovementCategory&MockObject */
        $category = $this->createStub(CashMovementCategory::class);
        $category->method('getType')->willReturn(CashMovementType::EXPENSE);

        $entity = new CashMovement();
        $entity->setType(CashMovementType::INCOME);
        $entity->setAmount('0.00');
        $entity->setDescription('');
        $entity->setCategory($category);

        self::assertSame($category, $entity->getCategory());
        self::assertSame(CashMovementType::EXPENSE, $entity->getType());
    }

    public function testSetAndGetCreatedAt(): void
    {
        $createdAt = new DateTimeImmutable('2026-01-15 10:00:00');

        $entity = new CashMovement();
        $entity->setType(CashMovementType::INCOME);
        $entity->setAmount('0.00');
        $entity->setDescription('');
        $entity->setCreatedAt($createdAt);

        self::assertSame($createdAt, $entity->getCreatedAt());
    }

    public function testPrePersistSetsCreatedAt(): void
    {
        $before = new DateTimeImmutable();

        $entity = new CashMovement();
        $entity->setType(CashMovementType::INCOME);
        $entity->setAmount('0.00');
        $entity->setDescription('');
        $entity->setCreatedAtValue();

        $after = new DateTimeImmutable();

        self::assertGreaterThanOrEqual($before, $entity->getCreatedAt());
        self::assertLessThanOrEqual($after, $entity->getCreatedAt());
    }
}
