<?php

namespace App\CashboxDetail\Domain;

use App\Domain\Annotation\IgnoreAutowire;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[IgnoreAutowire]
class CashboxDetail
{
    public function __construct(
        #[SerializedName('id')]
        public ?int $id,
        #[SerializedName('uid')]
        public ?int $uid,
        #[SerializedName('caja_id')]
        public ?string $caja_id,
        #[SerializedName('tipo_id')]
        public ?int $tipo_id,
        #[SerializedName('descripcion')]
        public ?string $descripcion,
        #[SerializedName('referencia')]
        public ?string $referencia,
        #[SerializedName('monto')]
        public ?string $monto,
        public $cashbox,
    )
    {
    }
}