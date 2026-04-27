<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Organization\Entity\Branch;
use App\Entity\CashRegister;
use App\Entity\CashShift;
use App\Entity\Role;
use App\Entity\User;
use App\Enum\CashShiftStatus;
use PHPUnit\Framework\TestCase;

final class CashShiftTest extends TestCase
{
    public function testCashShiftAccessors(): void
    {
        $branch = (new Branch())->setName('Main');
        $register = (new CashRegister())->setName('Caja 1')->setBranch($branch);
        $role = (new Role())->setName('Cajero');
        $openedBy = (new User())->setUsername('open')->setPassword('x')->setRole($role);
        $closedBy = (new User())->setUsername('close')->setPassword('y')->setRole($role);
        $openedAt = new \DateTimeImmutable('2026-02-01 08:00:00');
        $closedAt = new \DateTimeImmutable('2026-02-01 18:00:00');

        $entity = new CashShift();
        $entity->setCashRegister($register)
            ->setStatus(CashShiftStatus::OPEN)
            ->setOpeningBalance('100.00')
            ->setClosingBalance('150.00')
            ->setOpenedBy($openedBy)
            ->setClosedBy($closedBy)
            ->setOpenedAt($openedAt)
            ->setClosedAt($closedAt)
            ->setExpectedAmount('160.00')
            ->setCountedAmount('150.00')
            ->setDifference('-10.00')
            ->setNote('Cierre con diferencia')
            ->setBranch($branch);

        self::assertSame($register, $entity->getCashRegister());
        self::assertSame(CashShiftStatus::OPEN, $entity->getStatus());
        self::assertSame('100.00', $entity->getOpeningBalance());
        self::assertSame('150.00', $entity->getClosingBalance());
        self::assertSame($openedBy, $entity->getOpenedBy());
        self::assertSame($closedBy, $entity->getClosedBy());
        self::assertSame($openedAt, $entity->getOpenedAt());
        self::assertSame($closedAt, $entity->getClosedAt());
        self::assertSame('160.00', $entity->getExpectedAmount());
        self::assertSame('150.00', $entity->getCountedAmount());
        self::assertSame('-10.00', $entity->getDifference());
        self::assertSame('Cierre con diferencia', $entity->getNote());
        self::assertSame($branch, $entity->getBranch());
    }
}
