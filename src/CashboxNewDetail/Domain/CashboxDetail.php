<?php

namespace App\CashboxNewDetail\Domain;

use App\Core\Domain\Annotation\IgnoreAutowire;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[IgnoreAutowire]
class CashboxDetail
{
    public function __construct(
        #[SerializedName('id')]
        public ?int $id = null,
        #[SerializedName('caja_id')]
        public ?string $caja_id = null,
        #[SerializedName('uid')]
        public ?int $uid = null,
        #[SerializedName('tipo_id')]
        public ?int $tipo_id = null,
        #[SerializedName('descripcion')]
        public ?string $descripcion = null,
        #[SerializedName('referencia')]
        public ?string $referencia = null,
        #[SerializedName('monto')]
        public ?float $monto = null
    ) {
    }
}
