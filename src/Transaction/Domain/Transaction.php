<?php

namespace App\Transaction\Domain;

use App\Domain\Annotation\IgnoreAutowire;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[IgnoreAutowire]
class Transaction
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

    )
    {
    }
}