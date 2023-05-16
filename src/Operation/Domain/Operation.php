<?php

namespace App\Operation\Domain;

use App\Core\Domain\Annotation\IgnoreAutowire;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[IgnoreAutowire]
class Operation
{
    public function __construct(
        #[SerializedName('operacion_id')]
        public ?string $operacion_id = null,
        #[SerializedName('descripcion')]
        public ?string $descripcion = null,
        #[SerializedName('tipo_id')]
        public ?int $tipo_id = null,
        #[SerializedName('estado')]
        public ?bool $estado = false
    ) {
    }
}
