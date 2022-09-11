<?php

namespace App\Local\Domain;

use App\Domain\Annotation\IgnoreAutowire;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[IgnoreAutowire]
class Local
{
    public function __construct(
        #[SerializedName('local_id')]
        public ?string $local_id,
        #[SerializedName('empresa_id')]
        public ?string $empresa_id,
        #[SerializedName('descripcion')]
        public ?string $descripcion,
        #[SerializedName('direccion')]
        public ?string $direccion,
        #[SerializedName('telefono')]
        public ?string $telefono,
        #[SerializedName('estado')]
        public ?bool $estado,

    )
    {
    }
}