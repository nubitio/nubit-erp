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

    public function getLocalId(): string
    {
        return $this->localId;
    }

    public function setLocalId(string $localId): void
    {
        $this->localId = $localId;
    }

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function setProductId(string $productId): void
    {
        $this->productId = $productId;
    }

    public function getPrice(): float
    {
        return $this->price;
    }

    public function setPrice(float $price): void
    {
        $this->price = $price;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }
}
