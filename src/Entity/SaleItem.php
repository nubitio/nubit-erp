<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use App\Repository\SaleItemRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    uriTemplate: '/movements/{id}/items.{_format}',
    operations: [new GetCollection()],
    uriVariables: [
        'id' => new Link(
            fromProperty: 'items',
            fromClass: Sale::class,
        ),
    ],
    normalizationContext: ['groups' => ['sale_item:read']],
    paginationEnabled: false,
)]
#[ORM\Entity(repositoryClass: SaleItemRepository::class)]
#[ORM\Table(name: 'movimiento_detalle')]
class SaleItem
{
    #[Groups(['sale_item:read'])]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(cascade: ['persist', 'remove'], inversedBy: 'items')]
    #[ORM\JoinColumn(name: 'movimiento_id', referencedColumnName: 'movimiento_id')]
    private ?Sale $movimiento = null;

    #[Groups(['sale_item:read', 'sale:write'])]
    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(name: 'producto_id', referencedColumnName: 'producto_id')]
    private ?Product $producto = null;

    #[Groups(['sale_item:read', 'sale:write'])]
    #[ORM\Column(name: 'cantidad')]
    private ?float $cantidad = null;

    #[Groups(['sale_item:read', 'sale:write'])]
    #[ORM\Column(name: 'precio_unitario', nullable: true)]
    private ?float $precio_unitario = null;

    #[Groups(['sale_item:read', 'sale:write'])]
    #[ORM\Column(name: 'descuento')]
    private ?float $descuento = null;

    #[Groups(['sale_item:read', 'sale:write'])]
    #[ORM\Column(name: 'precio_total', nullable: true)]
    private ?float $precio_total = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMovimiento(): ?Sale
    {
        return $this->movimiento;
    }

    public function setMovimiento(?Sale $movimiento): static
    {
        $this->movimiento = $movimiento;

        return $this;
    }

    public function getProducto(): ?Product
    {
        return $this->producto;
    }

    public function setProducto(?Product $producto): static
    {
        $this->producto = $producto;

        return $this;
    }

    public function getCantidad(): ?float
    {
        return $this->cantidad;
    }

    public function setCantidad(?float $cantidad): static
    {
        $this->cantidad = $cantidad;

        return $this;
    }

    public function getPrecioUnitario(): ?float
    {
        return $this->precio_unitario;
    }

    public function setPrecioUnitario(?float $precio_unitario): static
    {
        $this->precio_unitario = $precio_unitario;

        return $this;
    }

    public function getDescuento(): ?float
    {
        return $this->descuento;
    }

    public function setDescuento(?float $descuento): static
    {
        $this->descuento = $descuento;

        return $this;
    }

    public function getPrecioTotal(): ?float
    {
        return $this->precio_total;
    }

    public function setPrecioTotal(?float $precio_total): static
    {
        $this->precio_total = $precio_total;

        return $this;
    }
}
