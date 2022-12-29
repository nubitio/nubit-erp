<?php

namespace App\Role\Infrastructure;

use App\Domain\Annotation\IgnoreAutowire;
use App\Role\Domain\Role;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Table;

#[IgnoreAutowire]
#[Entity]
#[Table(name: 'rol')]
class RoleEntity
{
    #[Id]
    #[GeneratedValue]
    #[Column(name: 'rol_id', type: 'integer')]
    public ?int $rol_id;
    #[Column(name: 'descripcion', type: 'string')]
    public ?string $descripcion;

    public function mapToDomainModel(): Role
    {
        return new Role(
            $this->rol_id,
            $this->descripcion,
        );
    }

    public function mapFromDomainModel(Role $role): RoleEntity
    {
        $this->rol_id = $role->rol_id;
        $this->descripcion = $role->descripcion;

        return $this;
    }
}
