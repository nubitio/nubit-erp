<?php

namespace App\Role\Domain;

use App\Core\Domain\Annotation\IgnoreAutowire;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[IgnoreAutowire]
class Role
{
    public function __construct(
        #[SerializedName('rol_id')]
        public ?int $rol_id = null,
        #[SerializedName('descripcion')]
        public ?string $descripcion = null
    )
    {
    }
}