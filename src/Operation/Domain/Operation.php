<?php

namespace App\Operation\Domain;

use App\Domain\Annotation\IgnoreAutowire;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[IgnoreAutowire]
class Operation
{
    public function __construct(
        #[SerializedName('operacion_id')]
        public ?string $operacion_id,
        #[SerializedName('descripcion')]
        public ?string $descripcion,
        #[SerializedName('tipo_id')]
        public ?int $tipo_id,
        #[SerializedName('estado')]
        public ?bool $estado,
    ) {
    }
}
