<?php

namespace App\VoucherSeries\Domain;

use App\Core\Domain\Annotation\IgnoreAutowire;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[IgnoreAutowire]
class VoucherSeries
{
    public function __construct(
        #[SerializedName('id')]
        public ?int $id = null,
        #[SerializedName('local_id')]
        public ?string $local_id = null,
        #[SerializedName('tipo_comprobante_id')]
        public ?string $tipo_comprobante_id = null,
        #[SerializedName('serie')]
        public ?string $serie = null,
        #[SerializedName('serie_asociada')]
        public ?string $serie_asociada = null,
        #[SerializedName('active')]
        public ?bool $active = false
    ) {
    }
}
