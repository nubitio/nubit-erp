<?php

namespace App\Customer\Domain;

use App\Domain\Annotation\IgnoreAutowire;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[IgnoreAutowire]
class Customer
{
    public function __construct(
        #[SerializedName('ruc')]
        public ?string $ruc,
        #[SerializedName('razon_social')]
        public ?string $razon_social,
        #[SerializedName('domicilio_fiscal')]
        public ?string $domicilio_fiscal,
        #[SerializedName('telefono')]
        public ?string $telefono,
        #[SerializedName('email')]
        public ?string $email,
        #[SerializedName('imagen')]
        public ?string $imagen,
        #[SerializedName('estado')]
        public ?bool $estado,

    )
    {
    }
}