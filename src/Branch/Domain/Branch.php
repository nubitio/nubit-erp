<?php

namespace App\Branch\Domain;

use App\Company\Domain\Company;
use App\Core\Domain\Annotation\IgnoreAutowire;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[IgnoreAutowire]
class Branch
{
    public function __construct(
        #[SerializedName('local_id')]
        public ?string $local_id = null,
        #[SerializedName('empresa')]
        public ?Company $empresa = null,
        #[SerializedName('descripcion')]
        public ?string $descripcion = null,
        #[SerializedName('direccion')]
        public ?string $direccion = null,
        #[SerializedName('telefono')]
        public ?string $telefono = null,
        #[SerializedName('estado')]
        public ?bool $estado = false
    )
    {
    }
}