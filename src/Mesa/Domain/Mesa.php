<?php

namespace App\Mesa\Domain;

use App\Ambiente\Domain\Ambiente;
use App\Core\Domain\Annotation\IgnoreAutowire;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[IgnoreAutowire]
class Mesa
{
    public function __construct(
        #[SerializedName('mesa_id')]
        public ?int $mesa_id = null,
        #[SerializedName('ambiente')]
        public ?Ambiente $ambiente = null,
        #[SerializedName('descripcion')]
        public ?string $descripcion = null,
        #[SerializedName('position')]
        public ?int $position = null,
        #[SerializedName('capacity')]
        public ?int $capacity = null,
        #[SerializedName('estado')]
        public ?bool $estado = false
    ) {
    }
}
