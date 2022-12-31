<?php

namespace App\VoucherSeries\Domain;

use App\Core\Domain\Annotation\IgnoreAutowire;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[IgnoreAutowire]
class VoucherSeries
{
    public function __construct(
        #[SerializedName('id')]
        public ?int $id,
        #[SerializedName('local_id')]
        public ?string $local_id,
        #[SerializedName('tipo_comprobante_id')]
        public ?string $tipo_comprobante_id,
        #[SerializedName('serie')]
        public ?string $serie,
        #[SerializedName('serie_asociada')]
        public ?string $serie_asociada,
        #[SerializedName('active')]
        public ?bool $active,
    ) {
    }
}
