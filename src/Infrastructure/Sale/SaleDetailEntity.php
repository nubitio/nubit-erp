<?php

namespace App\Infrastructure\Sale;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Id;

class SaleDetailEntity
{
    #[Id]
    #[Column(name: 'movimiento_id', type: "string")]
    private string $saleId;
    #[Column(name: 'producto_id', type: "string")]
    private string $productId;
    #[Column(name: 'cantidad', type: "float")]
    private float $quantity;
    #[Column(name: 'precio_unitario', type: "decimal")]
    private ?float $unitPrice = null;
    #[Column(name: 'descuento', type: "decimal")]
    private ?float $discount = null;
    #[Column(name: 'precio_total', type: "decimal")]
    private ?float $totalPrice = null;

    /**
     * @return string
     */
    public function getSaleId(): string
    {
        return $this->saleId;
    }

    /**
     * @param string $saleId
     */
    public function setSaleId(string $saleId): void
    {
        $this->saleId = $saleId;
    }

    /**
     * @return string
     */
    public function getProductId(): string
    {
        return $this->productId;
    }

    /**
     * @param string $productId
     */
    public function setProductId(string $productId): void
    {
        $this->productId = $productId;
    }

    /**
     * @return float
     */
    public function getQuantity(): float
    {
        return $this->quantity;
    }

    /**
     * @param float $quantity
     */
    public function setQuantity(float $quantity): void
    {
        $this->quantity = $quantity;
    }

    /**
     * @return float|null
     */
    public function getUnitPrice(): ?float
    {
        return $this->unitPrice;
    }

    /**
     * @param float|null $unitPrice
     */
    public function setUnitPrice(?float $unitPrice): void
    {
        $this->unitPrice = $unitPrice;
    }

    /**
     * @return float|null
     */
    public function getDiscount(): ?float
    {
        return $this->discount;
    }

    /**
     * @param float|null $discount
     */
    public function setDiscount(?float $discount): void
    {
        $this->discount = $discount;
    }

    /**
     * @return float|null
     */
    public function getTotalPrice(): ?float
    {
        return $this->totalPrice;
    }

    /**
     * @param float|null $totalPrice
     */
    public function setTotalPrice(?float $totalPrice): void
    {
        $this->totalPrice = $totalPrice;
    }

}