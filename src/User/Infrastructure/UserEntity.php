<?php

namespace App\User\Infrastructure;

use App\Domain\Annotation\IgnoreAutowire;
use App\User\Domain\User;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Table;

#[IgnoreAutowire]
#[Entity]
#[Table(name: 'usuario')]
class UserEntity
{
    #[Id]
    #[GeneratedValue]
    #[Column(name: 'usuario_id', type: 'integer')]
    public ?int $usuario_id;
    #[Column(name: 'username', type: 'string')]
    public ?string $username;
    #[Column(name: 'password', type: 'string')]
    public ?string $password;
    #[Column(name: 'estado', type: 'boolean')]
    public ?bool $estado;
    #[Column(name: 'date_created', type: 'datetime')]
    public ?\DateTime $date_created;
    #[Column(name: 'date_updated', type: 'datetime')]
    public ?\DateTime $date_updated;
    #[Column(name: 'pin', type: 'string')]
    public ?string $pin;
    #[Column(name: 'rol_id', type: 'integer')]
    public ?int $rol_id;

    public function mapToDomainModel(): User
    {
        return new User(
            $this->usuario_id,
            $this->username,
            $this->password,
            $this->estado,
            $this->date_created,
            $this->date_updated,
            $this->pin,
            $this->rol_id,
        );
    }

    public function mapFromDomainModel(User $user): UserEntity
    {
        $this->usuario_id = $user->usuario_id;
        $this->username = $user->username;
        $this->password = $user->password;
        $this->estado = $user->estado;
        $this->date_created = $user->date_created;
        $this->date_updated = $user->date_updated;
        $this->pin = '' != $user->pin ? $user->pin : null;
        $this->rol_id = $user->rol_id;

        return $this;
    }
}
