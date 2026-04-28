<?php

declare(strict_types=1);

namespace App\Tests\Identity\Entity;

use App\Identity\Entity\Role;
use App\Identity\Entity\User;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Security\Core\User\UserInterface;

#[CoversClass(User::class)]
final class UserTest extends TestCase
{
    public function testGetUserIdentifierReturnsUsername(): void
    {
        $user = new User();
        $user->setUsername('johndoe');

        self::assertSame('johndoe', $user->getUserIdentifier());
    }

    public function testEraseCredentialsSetsPlainPasswordToNull(): void
    {
        $user = new User();
        $user->setPlainPassword('secret');
        $user->eraseCredentials();

        self::assertNull($user->getPlainPassword());
    }

    public function testGetAndSetPassword(): void
    {
        $user = new User();
        $result = $user->setPassword('hashed-pw');

        self::assertSame('hashed-pw', $user->getPassword());
        self::assertSame($user, $result);
    }

    public function testGetDeletedAtReturnsNullByDefault(): void
    {
        $user = new User();

        self::assertNull($user->getDeletedAt());
    }

    public function testSetAndGetDeletedAt(): void
    {
        $user = new User();
        $dt = new DateTimeImmutable('2024-01-15 10:00:00');
        $result = $user->setDeletedAt($dt);

        self::assertSame($dt, $user->getDeletedAt());
        self::assertSame($user, $result);
    }

    public function testSetDeletedAtAcceptsNull(): void
    {
        $user = new User();
        $user->setDeletedAt(new DateTimeImmutable());
        $user->setDeletedAt(null);

        self::assertNull($user->getDeletedAt());
    }

    public function testSetAndGetRole(): void
    {
        $role = new Role();
        $role->setName('Admin');

        $user = new User();
        $result = $user->setRole($role);

        self::assertSame($role, $user->getRole());
        self::assertSame($user, $result);
    }

    public function testGetRolesForRoleId1ReturnsSuperAdmin(): void
    {
        $user = $this->createUserWithRoleId(1);

        self::assertSame(['ROLE_SUPER_ADMIN', 'ROLE_USER'], $user->getRoles());
    }

    public function testGetRolesForRoleId2ReturnsAdmin(): void
    {
        $user = $this->createUserWithRoleId(2);

        self::assertSame(['ROLE_ADMIN', 'ROLE_USER'], $user->getRoles());
    }

    public function testGetRolesForRoleId3ReturnsCashier(): void
    {
        $user = $this->createUserWithRoleId(3);

        self::assertSame(['ROLE_CASHIER', 'ROLE_USER'], $user->getRoles());
    }

    public function testGetRolesForNullRoleIdReturnsOnlyRoleUser(): void
    {
        $role = new Role(); // id is null (not persisted)
        $user = new User();
        $user->setRole($role);

        self::assertSame(['ROLE_USER'], $user->getRoles());
    }

    public function testGetRolesAlwaysUnique(): void
    {
        $user = $this->createUserWithRoleId(1);
        $roles = $user->getRoles();

        self::assertSame(array_unique($roles), $roles);
    }

    public function testIsEqualToReturnsTrueForSameUsername(): void
    {
        $user1 = new User();
        $user1->setUsername('alice');

        $user2 = new User();
        $user2->setUsername('alice');

        self::assertTrue($user1->isEqualTo($user2));
    }

    public function testIsEqualToReturnsFalseForDifferentUsername(): void
    {
        $user1 = new User();
        $user1->setUsername('alice');

        $user2 = new User();
        $user2->setUsername('bob');

        self::assertFalse($user1->isEqualTo($user2));
    }

    public function testIsEqualToWorksWithAnyUserInterface(): void
    {
        $user = new User();
        $user->setUsername('alice');

        $other = new class ('alice') implements UserInterface {
            /** @param non-empty-string $id */
            public function __construct(private string $id)
            {
            }
            public function getRoles(): array
            {
                return [];
            }
            public function eraseCredentials(): void
            {
            }
            /** @return non-empty-string */
            public function getUserIdentifier(): string
            {
                return $this->id;
            }
        };

        self::assertTrue($user->isEqualTo($other));
    }

    private function createUserWithRoleId(int $id): User
    {
        $role = new Role();
        // Use reflection to set the private id field
        $ref = new \ReflectionProperty(Role::class, 'id');
        $ref->setValue($role, $id);

        $user = new User();
        $user->setRole($role);

        return $user;
    }
}
