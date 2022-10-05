<?php

namespace App\RoleMenu\Domain;

use App\Domain\Annotation\IgnoreAutowire;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[IgnoreAutowire]
class RoleMenu
{
    public function __construct(
        #[SerializedName('id')]
        public ?int $id = null,
        #[SerializedName('menu_id')]
        public ?int $menu_id = null,
        #[SerializedName('rol_id')]
        public ?int $rol_id = null,
        #[SerializedName('estado')]
        public ?bool $estado = null,
    )
    {
    }
}