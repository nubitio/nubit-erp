<?php

namespace App\VoucherSeries\Infrastructure;

use App\Core\Domain\Annotation\IgnoreAutowire;
use App\Entity\Branch;
use App\VoucherType\Infrastructure\VoucherTypeEntity;
use Doctrine\ORM\Mapping as ORM;

#[IgnoreAutowire]
#[ORM\Entity]
#[ORM\Table(name: 'local_comprobante')]
class VoucherSeriesEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\GeneratedValue]
        #[ORM\Column(name: 'id', type: 'integer')]
        public ?int $id = null,
        #[ORM\ManyToOne(targetEntity: Branch::class)]
        #[ORM\JoinColumn(name: 'local_id', referencedColumnName: 'local_id')]
        public ?Branch $local = null,
        #[ORM\ManyToOne(targetEntity: VoucherTypeEntity::class)]
        #[ORM\JoinColumn(name: 'tipo_comprobante_id', referencedColumnName: 'id')]
        public ?VoucherTypeEntity $tipo_comprobante = null,
        #[ORM\Column(name: 'serie', type: 'string')]
        public ?string $serie = null,
        #[ORM\Column(name: 'serie_asociada', type: 'string')]
        public ?string $serie_asociada = null,
        #[ORM\Column(name: 'active', type: 'boolean')]
        public ?bool $active = null,
    ) {
    }
}
