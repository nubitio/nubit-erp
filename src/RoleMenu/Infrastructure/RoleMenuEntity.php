<?php

namespace App\RoleMenu\Infrastructure;

use App\Domain\Annotation\IgnoreAutowire;
use App\RoleMenu\Domain\RoleMenu;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Table;

#[IgnoreAutowire]
#[Entity]
#[Table(name: 'menu_rol')]
class RoleMenuEntity
{
    #[Id]
    #[GeneratedValue]
    #[Column(name: 'id', type: 'integer')]
    public ?int $id;
    #[Column(name: 'menu_id', type: 'integer')]
    public ?int $menu_id;
    #[Column(name: 'rol_id', type: 'integer')]
    public ?int $rol_id;
    #[Column(name: 'estado', type: 'boolean')]
    public ?bool $estado;

    public function mapToDomainModel(): RoleMenu
    {
        return new RoleMenu(
            $this->id,
            $this->menu_id,
            $this->rol_id,
            $this->estado,
        );
    }

    public function mapFromDomainModel(RoleMenu $roleMenu): RoleMenuEntity
    {
        $this->id = $roleMenu->id;
        $this->menu_id = $roleMenu->menu_id;
        $this->rol_id = $roleMenu->rol_id;
        $this->estado = $roleMenu->estado;

        return $this;
    }
}
