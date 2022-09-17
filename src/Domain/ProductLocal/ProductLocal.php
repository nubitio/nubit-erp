<?php

namespace App\Domain\ProductLocal;

use App\Domain\Annotation\IgnoreAutowire;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[IgnoreAutowire]
class ProductLocal
{
    #[SerializedName('local_id')]
    private string $localId;
    #[SerializedName('producto_id')]
    private string $productId;
    #[SerializedName('precio')]
    private float $price;
    #[SerializedName('activo')]
    private bool $active;

    /**
     * @return string
     */
    public function getLocalId(): string
    {
        return $this->localId;
    }

    /**
     * @param string $localId
     */
    public function setLocalId(string $localId): void
    {
        $this->localId = $localId;
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
    public function getPrice(): float
    {
        return $this->price;
    }

    /**
     * @param float $price
     */
    public function setPrice(float $price): void
    {
        $this->price = $price;
    }

    /**
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->active;
    }

    /**
     * @param bool $active
     */
    public function setActive(bool $active): void
    {
        $this->active = $active;
    }
}