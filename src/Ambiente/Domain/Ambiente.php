<?php

namespace App\Ambiente\Domain;

use App\Branch\Domain\Branch;
use App\Core\Domain\Annotation\IgnoreAutowire;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[IgnoreAutowire]
class Ambiente
{
    public function __construct(
        #[SerializedName('ambiente_id')]
        public ?int $ambiente_id = null,
        #[SerializedName('local')]
        public ?Branch $local = null,
        #[SerializedName('descripcion')]
        public ?string $descripcion = null,
        #[SerializedName('estado')]
        public ?bool $estado = false
    ) {
    }
}
