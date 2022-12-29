<?php

namespace App\VoucherType\Domain;

use App\Domain\Annotation\IgnoreAutowire;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[IgnoreAutowire]
class VoucherType
{
    public function __construct(
        #[SerializedName('codigo')]
        public ?string $codigo,
        #[SerializedName('descripcion')]
        public ?string $descripcion,
        #[SerializedName('estado')]
        public ?bool $estado,
    ) {
    }
}
