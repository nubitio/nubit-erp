<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\StockItemRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StockItemRepository::class)]
#[ORM\Table(name: 'stock_items')]
class StockItem
{
    #[ORM\Id]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'warehouse_id', referencedColumnName: 'almacen_id', nullable: false)]
    private Warehouse $warehouse;

    #[ORM\Id]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'producto_id', nullable: false)]
    private Product $product;

    #[ORM\Column(name: 'qty_on_hand', type: Types::DECIMAL, precision: 18, scale: 6)]
    private string $qtyOnHand = '0.000000';

    #[ORM\Column(name: 'qty_reserved', type: Types::DECIMAL, precision: 18, scale: 6)]
    private string $qtyReserved = '0.000000';

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $updatedAt;

    public function __construct()
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getWarehouse(): Warehouse
    {
        return $this->warehouse;
    }

    public function setWarehouse(Warehouse $warehouse): static
    {
        $this->warehouse = $warehouse;

        return $this;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function setProduct(Product $product): static
    {
        $this->product = $product;

        return $this;
    }

    public function getQtyOnHand(): string
    {
        return $this->qtyOnHand;
    }

    public function setQtyOnHand(string $qtyOnHand): static
    {
        $this->qtyOnHand = $qtyOnHand;

        return $this;
    }

    public function getQtyReserved(): string
    {
        return $this->qtyReserved;
    }

    public function setQtyReserved(string $qtyReserved): static
    {
        $this->qtyReserved = $qtyReserved;

        return $this;
    }

    public function getUpdatedAt(): \DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }
}
