<?php

namespace App\Operation\Domain;

use App\Core\Domain\Annotation\IgnoreAutowire;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[IgnoreAutowire]
class Operation
{
    public function __construct(
        #[SerializedName('id')]
        public ?string $operacion_id = null,
        #[SerializedName('name')]
        public ?string $descripcion = null,
        #[SerializedName('tipo_id')]
        public ?int $tipo_id = null,
        #[SerializedName('estado')]
        public ?bool $estado = false,
    ) {
    }
}
