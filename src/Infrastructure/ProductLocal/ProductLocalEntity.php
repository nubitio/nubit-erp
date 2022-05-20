<?php

namespace App\Infrastructure\ProductLocal;

use App\Domain\Annotation\IgnoreAutowire;
use App\Domain\ProductLocal\ProductLocal;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Table;

#[IgnoreAutowire]
#[Entity]
#[Table(name: "producto_local")]
class ProductLocalEntity
{
    #[Id]
    #[Column(name: "local_id", type: "string")]
    private string $localId;
    #[Id]
    #[Column(name: "producto_id", type: "string")]
    private string $productId;
    #[Column(name: "precio", type: "decimal")]
    private float $price;
    #[Column(name: "activo", type: "boolean")]
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

    public function fromDomain(ProductLocal $productLocal): self
    {
        $this->localId = $productLocal->getLocalId();
        $this->productId = $productLocal->getProductId();
        $this->price = $productLocal->getPrice();
        $this->active = $productLocal->isActive();

        return $this;
    }
}