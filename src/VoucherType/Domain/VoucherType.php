<?php

namespace App\VoucherType\Domain;

use App\Core\Domain\Annotation\IgnoreAutowire;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[IgnoreAutowire]
class VoucherType
{
    public function __construct(
        #[SerializedName('id')]
        public ?int $id = null,
        #[SerializedName('codigo')]
        public ?string $codigo = null,
        #[SerializedName('descripcion')]
        public ?string $descripcion = null,
        #[SerializedName('estado')]
        public ?bool $estado = false
    ) {
    }
}
