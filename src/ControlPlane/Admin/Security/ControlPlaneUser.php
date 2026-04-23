<?php

declare(strict_types=1);

namespace App\ControlPlane\Admin\Security;

use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final readonly class ControlPlaneUser implements UserInterface, PasswordAuthenticatedUserInterface
{
    /**
     * @param array<int, string> $roles
     */
    public function __construct(
        private int $id,
        private string $username,
        private string $password,
        private array $roles,
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getUserIdentifier(): string
    {
        return $this->username;
    }

    /**
     * @return array<int, string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';

        return array_values(array_unique($roles));
    }

    public function eraseCredentials(): void
    {
    }

    public function getPassword(): string
    {
        return $this->password;
    }
}
