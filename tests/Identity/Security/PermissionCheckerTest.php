<?php

declare(strict_types=1);

namespace App\Tests\Identity\Security;

use App\Identity\Entity\Role;
use App\Identity\Entity\User;
use App\Identity\Repository\RolePermissionRepository;
use App\Identity\Security\PermissionChecker;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Bundle\SecurityBundle\Security as SecurityComponent;

final class PermissionCheckerTest extends TestCase
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

    /**
     * @param list<string> $grants
     */
    private function makeChecker(?User $user, array $grants = [], int $expectedLookups = 0): PermissionChecker
    {
        $security = $this->createStub(SecurityComponent::class);
        $security->method('getUser')->willReturn($user);

        $repository = $this->createMock(RolePermissionRepository::class);
        $repository
            ->expects(self::exactly($expectedLookups))
            ->method('findPermissionKeysByRoleId')
            ->willReturn($grants);

        return new PermissionChecker($security, $repository);
    }

    public function testAnonymousHasNoPermissions(): void
    {
        $checker = $this->makeChecker(null);

        static::assertFalse($checker->hasPermission('sales.view'));
    }

    public function testSuperAdminHasEveryPermissionWithoutLookup(): void
    {
        $superAdmin = $this->makeUser(1);
        $checker = $this->makeChecker($superAdmin, expectedLookups: 0);

        static::assertTrue($checker->hasPermission('sales.void'));
        static::assertSame(['*'], $checker->permissionsFor($superAdmin));
    }

    public function testGrantsAreCheckedAndCachedPerRole(): void
    {
        $cashier = $this->makeUser(3);
        $checker = $this->makeChecker($cashier, grants: ['sales.view', 'pos.access'], expectedLookups: 1);

        static::assertTrue($checker->hasPermission('sales.view'));
        static::assertFalse($checker->hasPermission('sales.void'));
        static::assertSame(['sales.view', 'pos.access'], $checker->permissionsFor($cashier));
    }

    /**
     * Only the tenant administrator role is seeded with settings.edit. A
     * manager or cashier must not be able to mutate SUNAT credentials.
     */
    public function testOnlyTenantAdministratorCanEditSettings(): void
    {
        foreach ([3, 4] as $ordinaryRoleId) {
            $checker = $this->makeChecker($this->makeUser($ordinaryRoleId), expectedLookups: 1);

            static::assertFalse($checker->hasPermission('settings.edit'));
        }

        $adminChecker = $this->makeChecker(
            $this->makeUser(2),
            grants: ['settings.edit'],
            expectedLookups: 1,
        );

        static::assertTrue($adminChecker->hasPermission('settings.edit'));
    }
}
