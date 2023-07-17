<?php

namespace App\OrdenDetail\Infrastructure;

use App\Core\Domain\Annotation\IgnoreAutowire;
use App\Orden\Infrastructure\OrdenEntity;
use App\Product\Infrastructure\ProductEntity;
use Doctrine\ORM\Mapping as ORM;

#[IgnoreAutowire]
#[ORM\Entity]
#[ORM\Table(name: 'orden_detalle')]
class OrdenDetailEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\GeneratedValue]
        #[ORM\Column(name: 'id', type: 'integer')]
        public ?int $id = null,
        #[ORM\ManyToOne(targetEntity: OrdenEntity::class, inversedBy: 'details')]
        #[ORM\JoinColumn(name: 'orden_id', referencedColumnName: 'orden_id')]
        public ?OrdenEntity $orden = null,
        #[ORM\ManyToOne(targetEntity: ProductEntity::class)]
        #[ORM\JoinColumn(name: 'producto_id', referencedColumnName: 'producto_id')]
        public ?ProductEntity $producto = null,
        #[ORM\Column(name: 'cantidad', type: 'integer')]
        public ?int $cantidad = null,
        #[ORM\Column(name: 'cantidad_helper', type: 'integer')]
        public ?int $cantidad_helper = null,
        #[ORM\Column(name: 'precio_unitario', type: 'decimal')]
        public ?float $precio_unitario = null,
        #[ORM\Column(name: 'precio_total', type: 'decimal')]
        public ?float $precio_total = null,
        #[ORM\Column(name: 'nota', type: 'text')]
        public ?string $nota = null,
        #[ORM\Column(name: 'devolver', type: 'boolean')]
        public ?bool $devolver = null,
        #[ORM\Column(name: 'desechar', type: 'boolean')]
        public ?bool $desechar = null,
        #[ORM\Column(name: 'billed', type: 'boolean')]
        public ?bool $billed = null,
        #[ORM\Column(name: 'billed_quantity', type: 'integer')]
        public ?int $billed_quantity = null,
        #[ORM\Column(name: 'date_created', type: 'datetime')]
        public ?\DateTimeInterface $date_created = null,
        #[ORM\Column(name: 'date_updated', type: 'datetime')]
        public ?\DateTimeInterface $date_updated = null,
        #[ORM\Column(name: 'estado_cocina', type: 'boolean')]
        public ?bool $estado_cocina = null,
        #[ORM\Column(name: 'estado_barra', type: 'boolean')]
        public ?bool $estado_barra = null,
        #[ORM\Column(name: 'estado', type: 'integer')]
        public ?int $estado = null,
    ) {
    }
}
