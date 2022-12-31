<?php

namespace App\User\Domain;

use App\Core\Domain\Annotation\IgnoreAutowire;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[IgnoreAutowire]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    public function __construct(
        #[SerializedName('usuario_id')]
        public ?int $usuario_id,
        #[SerializedName('username')]
        public ?string $username,
        #[SerializedName('password')]
        public ?string $password,
        #[SerializedName('estado')]
        public ?bool $estado,
        #[SerializedName('date_created')]
        public ?\DateTime $date_created,
        #[SerializedName('date_updated')]
        public ?\DateTime $date_updated,
        #[SerializedName('pin')]
        public ?string $pin,
        #[SerializedName('rol_id')]
        public ?int $rol_id,
    ) {
    }

    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    public function eraseCredentials()
    {
        // TODO: Implement eraseCredentials() method.
    }

    public function getUserIdentifier(): string
    {
        return $this->username;
    }

    public function getPassword(): string
    {
        return $this->password;
    }
}
