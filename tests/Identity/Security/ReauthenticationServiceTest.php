<?php

declare(strict_types=1);

namespace App\Tests\Identity\Security;

use App\Identity\Entity\User;
use App\Identity\Security\ReauthenticationService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class ReauthenticationServiceTest extends TestCase
{
    public function testItRequiresAnAuthenticatedUserAndNonEmptyPassword(): void
    {
        $hasher = $this->createMock(UserPasswordHasherInterface::class);
        $hasher->expects(self::never())->method('isPasswordValid');

        $service = new ReauthenticationService($hasher);

        self::assertFalse($service->isValid(null, 'secret'));
        self::assertFalse($service->isValid($this->user(), ''));
    }

    public function testItDelegatesToThePasswordHasherForTheCurrentUser(): void
    {
        $user = $this->user();
        $hasher = $this->createMock(UserPasswordHasherInterface::class);
        $hasher->expects(self::once())->method('isPasswordValid')->with($user, 'secret')->willReturn(true);

        self::assertTrue(new ReauthenticationService($hasher)->isValid($user, 'secret'));
    }

    private function user(): User
    {
        return new User()
            ->setUsername('admin')
            ->setPassword('hash');
    }
}
