<?php

namespace App\User\Infrastructure;

use App\Core\Domain\Annotation\IgnoreAutowire;
use App\Role\Infrastructure\RoleEntity;
use Doctrine\ORM\Mapping as ORM;

#[IgnoreAutowire]
#[ORM\Entity]
#[ORM\Table(name: 'usuario')]
class UserEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\GeneratedValue]
        #[ORM\Column(name: 'usuario_id', type: 'integer')]
        public ?int $usuario_id = null,
        #[ORM\ManyToOne(targetEntity: RoleEntity::class)]
        #[ORM\JoinColumn(name: 'rol_id', referencedColumnName: 'rol_id')]
        public ?RoleEntity $rol = null,
        #[ORM\Column(name: 'username', type: 'string')]
        public ?string $username = null,
        #[ORM\Column(name: 'password', type: 'string')]
        public ?string $password = null,
        #[ORM\Column(name: 'date_created', type: 'datetime')]
        public ?\DateTimeInterface $date_created = null,
        #[ORM\Column(name: 'date_updated', type: 'datetime')]
        public ?\DateTimeInterface $date_updated = null,
        #[ORM\Column(name: 'pin', type: 'string')]
        public ?string $pin = null,
        #[ORM\Column(name: 'estado', type: 'boolean')]
        public ?bool $estado = null,
    ) {
    }
}
