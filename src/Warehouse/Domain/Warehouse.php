<?php

namespace App\Warehouse\Domain;

use App\Domain\Annotation\IgnoreAutowire;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[IgnoreAutowire]
class Warehouse
{
    public function __construct(
        #[SerializedName('almacen_id')]
        public ?string $almacen_id,
        #[SerializedName('local_id')]
        public ?string $local_id,
        #[SerializedName('descripcion')]
        public ?string $descripcion,
        #[SerializedName('tipo_id')]
        public ?int $tipo_id,
        #[SerializedName('is_default')]
        public ?bool $is_default,
        #[SerializedName('estado')]
        public ?bool $estado,

    )
    {
    }
}