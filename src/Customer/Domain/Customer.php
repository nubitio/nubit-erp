<?php

namespace App\Customer\Domain;

use App\Core\Domain\Annotation\IgnoreAutowire;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[IgnoreAutowire]
class Customer
{
    public function __construct(
        #[SerializedName('ruc')]
        public ?string $ruc = null,
        #[SerializedName('razon_social')]
        public ?string $razon_social = null,
        #[SerializedName('domicilio_fiscal')]
        public ?string $domicilio_fiscal = null,
        #[SerializedName('telefono')]
        public ?string $telefono = null,
        #[SerializedName('email')]
        public ?string $email = null,
        #[SerializedName('imagen')]
        public ?string $imagen = null,
        #[SerializedName('estado')]
        public ?bool $estado = null
    )
    {
    }
}