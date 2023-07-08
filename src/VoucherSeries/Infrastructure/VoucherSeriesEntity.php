<?php

namespace App\VoucherSeries\Infrastructure;

use App\Core\Domain\Annotation\IgnoreAutowire;
use Doctrine\ORM\Mapping as ORM;

#[IgnoreAutowire]
#[ORM\Entity]
#[ORM\Table(name: "local_comprobante")]
class VoucherSeriesEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\GeneratedValue]
        #[ORM\Column(name: 'id', type: 'integer')]
        public ?int $id = null,
        #[ORM\Column(name: 'local_id', type: 'string')]
        public ?string $local_id = null,
        #[ORM\Column(name: 'tipo_comprobante_id', type: 'string')]
        public ?string $tipo_comprobante_id = null,
        #[ORM\Column(name: 'serie', type: 'string')]
        public ?string $serie = null,
        #[ORM\Column(name: 'serie_asociada', type: 'string')]
        public ?string $serie_asociada = null,
        #[ORM\Column(name: 'active', type: 'boolean')]
        public ?bool $active = null
    )
    {
    }
}