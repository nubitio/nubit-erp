<?php

namespace App\CashboxNewDetail\Infrastructure;

use App\CashboxNew\Infrastructure\CashboxEntity;
use App\Core\Domain\Annotation\IgnoreAutowire;
use Doctrine\ORM\Mapping as ORM;

#[IgnoreAutowire]
#[ORM\Entity]
#[ORM\Table(name: 'caja_detalle')]
class CashboxDetailEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\GeneratedValue]
        #[ORM\Column(name: 'id', type: 'integer')]
        public ?int $id = null,
        #[ORM\Column(name: 'caja_id', type: 'string')]
        public ?string $caja_id = null,
        #[ORM\Column(name: 'uid', type: 'integer')]
        public ?int $uid = null,
        #[ORM\Column(name: 'tipo_id', type: 'integer')]
        public ?int $tipo_id = null,
        #[ORM\Column(name: 'descripcion', type: 'string')]
        public ?string $descripcion = null,
        #[ORM\Column(name: 'referencia', type: 'string')]
        public ?string $referencia = null,
        #[ORM\Column(name: 'monto', type: 'decimal')]
        public ?float $monto = null,
        #[ORM\ManyToOne(targetEntity: CashboxEntity::class, inversedBy: 'details')]
        #[ORM\JoinColumn(name: 'caja_id', referencedColumnName: 'caja_id')]
        public ?CashboxEntity $cashbox = null,
    ) {
    }
}
