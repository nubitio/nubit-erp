<?php

namespace App\SaleDetail\Infrastructure;

use App\Core\Domain\Annotation\IgnoreAutowire;
use App\Product\Infrastructure\ProductEntity;
use App\Sale\Infrastructure\SaleEntity;
use Doctrine\ORM\Mapping as ORM;

#[IgnoreAutowire]
#[ORM\Entity]
#[ORM\Table(name: "movimiento_detalle")]
class SaleDetailEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\GeneratedValue]
        #[ORM\Column(name: 'id', type: 'integer')]
        public ?int           $id = null,
        #[ORM\ManyToOne(targetEntity: SaleEntity::class, inversedBy: 'details')]
        #[ORM\JoinColumn(name: 'movimiento_id', referencedColumnName: 'movimiento_id')]
        public ?SaleEntity    $movimiento = null,
        #[ORM\ManyToOne(targetEntity: ProductEntity::class)]
        #[ORM\JoinColumn(name: 'producto_id', referencedColumnName: 'producto_id')]
        public ?ProductEntity $producto = null,
        #[ORM\Column(name: 'cantidad', type: 'decimal')]
        public ?float         $cantidad = null,
        #[ORM\Column(name: 'precio_unitario', type: 'decimal')]
        public ?float         $precio_unitario = null,
        #[ORM\Column(name: 'descuento', type: 'decimal')]
        public ?float         $descuento = null,
        #[ORM\Column(name: 'precio_total', type: 'decimal')]
        public ?float         $precio_total = null
    )
    {
    }
}