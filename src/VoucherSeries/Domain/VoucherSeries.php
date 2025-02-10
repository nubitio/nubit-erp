<?php

namespace App\VoucherSeries\Domain;

use App\Core\Domain\Annotation\IgnoreAutowire;
use App\VoucherType\Domain\VoucherType;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[IgnoreAutowire]
class VoucherSeries
{
    public function __construct(
        #[SerializedName('id')]
        public ?int $id = null,
        #[SerializedName('tipo_comprobante')]
        public ?VoucherType $tipo_comprobante = null,
        #[SerializedName('serie')]
        public ?string $serie = null,
        #[SerializedName('serie_asociada')]
        public ?string $serie_asociada = null,
        #[SerializedName('active')]
        public ?bool $active = false,
    ) {
    }
}
