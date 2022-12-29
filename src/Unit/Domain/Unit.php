<?php

namespace App\Unit\Domain;

use App\Domain\Annotation\IgnoreAutowire;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[IgnoreAutowire]
class Unit
{
    public function __construct(
        #[SerializedName('unidad_id')]
        public ?string $unidad_id,
        #[SerializedName('descripcion')]
        public ?string $descripcion,
        #[SerializedName('estado')]
        public ?bool $estado,
    ) {
    }
}
