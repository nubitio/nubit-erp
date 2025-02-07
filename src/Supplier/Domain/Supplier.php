<?php

namespace App\Supplier\Domain;

use App\Core\Domain\Annotation\IgnoreAutowire;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[IgnoreAutowire]
class Supplier
{
    public function __construct(
        #[SerializedName('id')]
        public ?string $proveedor_id = null,
        #[SerializedName('ruc')]
        public ?string $ruc = null,
        #[SerializedName('businessName')]
        public ?string $razon_social = null,
        #[SerializedName('domicilio_fiscal')]
        public ?string $domicilio_fiscal = null,
        #[SerializedName('telefono')]
        public ?string $telefono = null,
        #[SerializedName('estado')]
        public ?bool $estado = false,
    ) {
    }
}
