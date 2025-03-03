<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    uriTemplate: '/movements/{id}/items.{_format}',
    operations: [new GetCollection()],
    uriVariables: [
        'id' => new Link(
            fromProperty: 'items',
            fromClass: SaleEntity::class,
        ),
    ],
    normalizationContext: ['groups' => ['sale_item:read']],
    paginationEnabled: false,
)]
#[ORM\Entity]
#[ORM\Table(name: 'movimiento_detalle')]
class SaleDetailEntity
{
    public function __construct(
        #[Groups(['sale_item:read'])]
        #[ORM\Id]
        #[ORM\GeneratedValue]
        #[ORM\Column(name: 'id', type: 'integer')]
        public ?int $id = null,
        #[ORM\ManyToOne(cascade: ['persist', 'remove'], inversedBy: 'items')]
        #[ORM\JoinColumn(name: 'movimiento_id', referencedColumnName: 'movimiento_id')]
        public ?SaleEntity $movimiento = null,
        #[Groups(['sale_item:read'])]
        #[ORM\ManyToOne(targetEntity: Product::class)]
        #[ORM\JoinColumn(name: 'producto_id', referencedColumnName: 'producto_id')]
        public ?Product $producto = null,
        #[Groups(['sale_item:read'])]
        #[ORM\Column(name: 'cantidad')]
        public ?float $cantidad = null,
        #[Groups(['sale_item:read'])]
        #[ORM\Column(name: 'precio_unitario')]
        public ?float $precio_unitario = null,
        #[Groups(['sale_item:read'])]
        #[ORM\Column(name: 'descuento')]
        public ?float $descuento = null,
        #[Groups(['sale_item:read'])]
        #[ORM\Column(name: 'precio_total')]
        public ?float $precio_total = null,
    ) {
    }
}
