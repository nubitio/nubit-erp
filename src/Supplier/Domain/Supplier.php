<?php

namespace App\Supplier\Domain;

use App\Core\Domain\Annotation\IgnoreAutowire;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[IgnoreAutowire]
class Supplier
{
    public function __construct(
        #[SerializedName('proveedor_id')]
        public ?string $proveedor_id,
        #[SerializedName('ruc')]
        public ?string $ruc,
        #[SerializedName('razon_social')]
        public ?string $razon_social,
        #[SerializedName('domicilio_fiscal')]
        public ?string $domicilio_fiscal,
        #[SerializedName('telefono')]
        public ?string $telefono,
        #[SerializedName('estado')]
        public ?bool $estado,
    ) {
    }
}
