<?php

namespace App\Infrastructure\Product;

use App\Domain\Annotation\IgnoreAutowire;
use App\Domain\Product\Product;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Table;
use Symfony\Component\Validator\Constraints\NotBlank;

#[IgnoreAutowire]
#[Entity]
#[Table(name: "producto")]
class ProductEntity
{
    #[Id]
    #[Column(name: "producto_id", type: "string")]
    private string $id;
    #[NotBlank]
    #[Column(name: "codigo", type: "string")]
    private ?string $code = null;
    #[Column(name: "descripcion", type: "string")]
    private string $name;
    #[Column(name: "categoria_id", type: "string")]
    private string $categoryId;
    #[Column(name: "precio", type: "decimal")]
    private float $price;
    #[Column(name: "imagen", type: "string")]
    private ?string $image = null;
    #[Column(name: "estado", type: "boolean")]
    private bool $active;

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @param string $id
     */
    public function setId(string $id): void
    {
        $this->id = $id;
    }

    /**
     * @return string|null
     */
    public function getCode(): ?string
    {
        return $this->code;
    }

    /**
     * @param string|null $code
     */
    public function setCode(?string $code): void
    {
        $this->code = $code;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getCategoryId(): string
    {
        return $this->categoryId;
    }

    /**
     * @param string $categoryId
     */
    public function setCategoryId(string $categoryId): void
    {
        $this->categoryId = $categoryId;
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
     * @return string|null
     */
    public function getImage(): ?string
    {
        return $this->image;
    }

    /**
     * @param string|null $image
     */
    public function setImage(?string $image): void
    {
        $this->image = $image;
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

    public function __toString()
    {
        return $this->getName();
    }

    public function fromDomain(Product $product): self
    {
        $this->setId($product->getId());
        $this->setCode($product->getCode());
        $this->setName($product->getName());
        $this->setCategoryId($product->getCategoryId());
        $this->setPrice($product->getPrice());
        $this->setImage($product->getImage());
        $this->setActive($product->isActive());

        return $this;
    }

    public function toDomain(): Product
    {
        $product = new Product();
        $product->setId($this->getId());
        $product->setCode($this->getCode());
        $product->setName($this->getName());
        $product->setCategoryId($this->getCategoryId());
        $product->setPrice($this->getPrice());
        $product->setImage($this->getImage());
        $product->setActive($this->isActive());

        return $product;
    }
}