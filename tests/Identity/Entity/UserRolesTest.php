<?php

declare(strict_types=1);

namespace App\Tests\Identity\Entity;

use App\Identity\Entity\Role;
use App\Identity\Entity\User;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class UserRolesTest extends TestCase
{
    private function makeUser(int $roleId): User
    {
        $role = new Role();
        new ReflectionProperty(Role::class, 'id')->setValue($role, $roleId);

        $user = new User();
        $user->setUsername('juan');
        $user->setRole($role);

        return $user;
    }

    public function testLegacyRootRoleIsSuperAdmin(): void
    {
        self::assertSame(['ROLE_SUPER_ADMIN', 'ROLE_USER'], $this->makeUser(0)->getRoles());
    }

    public function testRoleOneIsSuperAdmin(): void
    {
        self::assertSame(['ROLE_SUPER_ADMIN', 'ROLE_USER'], $this->makeUser(1)->getRoles());
    }

    public function testRoleTwoIsAdmin(): void
    {
        self::assertSame(['ROLE_ADMIN', 'ROLE_USER'], $this->makeUser(2)->getRoles());
    }

    public function testRoleThreeIsCashier(): void
    {
        self::assertSame(['ROLE_CASHIER', 'ROLE_USER'], $this->makeUser(3)->getRoles());
    }

    public function testUnmappedRoleOnlyGetsUserRole(): void
    {
        self::assertSame(['ROLE_USER'], $this->makeUser(42)->getRoles());
    }
}
