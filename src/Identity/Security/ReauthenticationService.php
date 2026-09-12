<?php

declare(strict_types=1);

namespace App\Identity\Security;

use SensitiveParameter;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Verifies the credentials of the already authenticated user before a
 * high-impact mutation. A bearer token alone is deliberately not sufficient.
 */
final readonly class ReauthenticationService
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher,
    ) {}

    public function isValid(?UserInterface $user, #[SensitiveParameter] string $plainPassword): bool
    {
        if (!$user instanceof PasswordAuthenticatedUserInterface || trim($plainPassword) === '') {
            return false;
        }

        return $this->passwordHasher->isPasswordValid($user, $plainPassword);
    }
}
