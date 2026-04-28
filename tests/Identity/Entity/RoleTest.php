<?php

declare(strict_types=1);

namespace App\Tests\Identity\Entity;

use App\Identity\Entity\Role;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Role::class)]
final class RoleTest extends TestCase
{
    public function testGetIdReturnsNullBeforePersistence(): void
    {
        $role = new Role();

        self::assertNull($role->getId());
    }

    public function testSetAndGetName(): void
    {
        $role = new Role();
        $result = $role->setName('Admin');

        self::assertSame('Admin', $role->getName());
        self::assertSame($role, $result);
    }

    public function testSetNameReturnsStatic(): void
    {
        $role = new Role();

        self::assertInstanceOf(Role::class, $role->setName('Cashier'));
    }
}
