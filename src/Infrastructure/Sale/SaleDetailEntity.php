<?php

namespace App\Infrastructure\Sale;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Id;

class SaleDetailEntity
{
    #[Id]
    #[Column(name: 'movimiento_id', type: 'string')]
    private string $saleId;
    #[Column(name: 'producto_id', type: 'string')]
    private string $productId;
    #[Column(name: 'cantidad', type: 'float')]
    private float $quantity;
    #[Column(name: 'precio_unitario', type: 'decimal')]
    private ?float $unitPrice = null;
    #[Column(name: 'descuento', type: 'decimal')]
    private ?float $discount = null;
    #[Column(name: 'precio_total', type: 'decimal')]
    private ?float $totalPrice = null;

    public function getSaleId(): string
    {
        return $this->saleId;
    }

    public function setSaleId(string $saleId): void
    {
        $this->saleId = $saleId;
    }

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function setProductId(string $productId): void
    {
        $this->productId = $productId;
    }

    public function getQuantity(): float
    {
        return $this->quantity;
    }

    public function setQuantity(float $quantity): void
    {
        $this->quantity = $quantity;
    }

    public function getUnitPrice(): ?float
    {
        return $this->unitPrice;
    }

    public function setUnitPrice(?float $unitPrice): void
    {
        $this->unitPrice = $unitPrice;
    }

    public function getDiscount(): ?float
    {
        return $this->discount;
    }

    public function setDiscount(?float $discount): void
    {
        $this->discount = $discount;
    }

    public function getTotalPrice(): ?float
    {
        return $this->totalPrice;
    }

    public function setTotalPrice(?float $totalPrice): void
    {
        $this->totalPrice = $totalPrice;
    }
}
