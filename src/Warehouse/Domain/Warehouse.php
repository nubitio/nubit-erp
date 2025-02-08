<?php

namespace App\Warehouse\Domain;

use App\Core\Domain\Annotation\IgnoreAutowire;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[IgnoreAutowire]
class Warehouse
{
    public function __construct(
        #[SerializedName('almacen_id')]
        public ?string $almacen_id = null,
        #[SerializedName('descripcion')]
        public ?string $descripcion = null,
        #[SerializedName('tipo_id')]
        public ?int $tipo_id = null,
        #[SerializedName('is_default')]
        public ?bool $is_default = false,
        #[SerializedName('estado')]
        public ?bool $estado = false,
    ) {
    }
}
