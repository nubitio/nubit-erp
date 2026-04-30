<?php

declare(strict_types=1);

namespace App\Tests\ControlPlane\Admin\Security;

use App\ControlPlane\Admin\Security\ControlPlaneUser;
use PHPUnit\Framework\TestCase;

final class ControlPlaneUserTest extends TestCase
{
    /** @param list<string> $roles */
    private function makeUser(array $roles = ['ROLE_ADMIN']): ControlPlaneUser
    {
        return new ControlPlaneUser(
            id: 1,
            username: 'superadmin',
            password: '$argon2id$...',
            roles: $roles,
        );
    }

    public function testGetUserIdentifierReturnsUsername(): void
    {
        self::assertSame('superadmin', $this->makeUser()->getUserIdentifier());
    }

    public function testGetIdReturnsId(): void
    {
        self::assertSame(1, $this->makeUser()->getId());
    }

    public function testGetPasswordReturnsHash(): void
    {
        self::assertSame('$argon2id$...', $this->makeUser()->getPassword());
    }

    public function testGetRolesAlwaysContainsRoleUser(): void
    {
        $roles = $this->makeUser(['ROLE_ADMIN'])->getRoles();
        self::assertContains('ROLE_USER', $roles);
        self::assertContains('ROLE_ADMIN', $roles);
    }

    public function testGetRolesDeduplicates(): void
    {
        $user = new ControlPlaneUser(1, 'u', 'p', ['ROLE_USER', 'ROLE_USER', 'ROLE_ADMIN']);
        $roles = $user->getRoles();
        self::assertCount(array_count_values($roles)['ROLE_USER'], [1 => 1]); // exactly once
        self::assertSame(count($roles), count(array_unique($roles)));
    }

    public function testEraseCredentialsIsNoOp(): void
    {
        $user = $this->makeUser();
        $user->eraseCredentials();
        // After erasing, password should still work (this impl is a no-op)
        self::assertNotEmpty($user->getPassword());
    }

    public function testEmptyRolesStillGetsRoleUser(): void
    {
        $user = new ControlPlaneUser(1, 'u', 'p', []);
        self::assertContains('ROLE_USER', $user->getRoles());
    }
}
