<?php

namespace App\User\Domain;

use App\Core\Domain\Annotation\IgnoreAutowire;
use App\Role\Domain\Role;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[IgnoreAutowire]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    public function __construct(
        #[SerializedName('usuario_id')]
        public ?int $usuario_id = null,
        #[SerializedName('rol')]
        public ?Role $rol = null,
        #[SerializedName('username')]
        public ?string $username = null,
        #[SerializedName('password')]
        public ?string $password = null,
        #[SerializedName('date_created')]
        public ?\DateTimeInterface $date_created = null,
        #[SerializedName('date_updated')]
        public ?\DateTimeInterface $date_updated = null,
        #[SerializedName('pin')]
        public ?string $pin = null,
        #[SerializedName('estado')]
        public ?bool $estado = false
    ) {
    }

    public function getRoles(): array
    {
        if ('root' === $this->username) {
            return ['ROLE_SUPER_ADMIN'];
        }

        return ['ROLE_USER'];
    }

    public function eraseCredentials(): void
    {
        // TODO: Implement eraseCredentials() method.
    }

    public function getUserIdentifier(): string
    {
        return $this->username ?: '';
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }
}
