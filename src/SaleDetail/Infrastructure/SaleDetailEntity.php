<?php

namespace App\SaleDetail\Infrastructure;

use App\Core\Domain\Annotation\IgnoreAutowire;
use App\Sale\Infrastructure\SaleEntity;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\Table;

#[IgnoreAutowire]
#[Entity]
#[Table(name: "movimiento_detalle")]
class SaleDetailEntity
{
    public function __construct(
        #[Id]
        #[GeneratedValue]
        #[Column(name: "movimiento_id", type: "string")]
        public ?string     $movimiento_id = null,
        #[Column(name: "producto_id", type: "string")]
        public ?string     $producto_id = null,
        #[Column(name: "cantidad", type: "decimal")]
        public ?string     $cantidad = null,
        #[Column(name: "precio_unitario", type: "decimal")]
        public ?string     $precio_unitario = null,
        #[Column(name: "descuento", type: "decimal")]
        public ?string     $descuento = null,
        #[Column(name: "precio_total", type: "decimal")]
        public ?string     $precio_total = null,
        #[ManyToOne(targetEntity: SaleEntity::class, fetch: 'EAGER', inversedBy: 'details')]
        #[JoinColumn(name: 'movimiento_id', referencedColumnName: 'movimiento_id')]
        public ?SaleEntity $movimiento = null
    )
    {
    }
}