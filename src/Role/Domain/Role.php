<?php

namespace App\Role\Domain;

use App\Domain\Annotation\IgnoreAutowire;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[IgnoreAutowire]
class Role
{
    public function __construct(
        #[SerializedName('rol_id')]
        public ?int $rol_id,
        #[SerializedName('descripcion')]
        public ?string $descripcion,
    ) {
    }
}
