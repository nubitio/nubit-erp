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
#[Table(name: 'producto')]
class ProductEntity
{
    #[Id]
    #[Column(name: 'producto_id', type: 'string')]
    private string $id;
    #[Column(name: 'tipo_id', type: 'integer')]
    private int $typeId;
    #[NotBlank]
    #[Column(name: 'codigo', type: 'string')]
    private ?string $code = null;
    #[Column(name: 'descripcion', type: 'string')]
    private string $name;
    #[Column(name: 'categoria_id', type: 'string')]
    private string $categoryId;
    #[Column(name: 'precio', type: 'decimal')]
    private float $price;
    #[Column(name: 'imagen', type: 'string')]
    private ?string $image = null;
    #[Column(name: 'estado', type: 'boolean')]
    private bool $active;

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function getTypeId(): int
    {
        return $this->typeId;
    }

    public function setTypeId(int $typeId): void
    {
        $this->typeId = $typeId;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): void
    {
        $this->code = $code;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getCategoryId(): string
    {
        return $this->categoryId;
    }

    public function setCategoryId(string $categoryId): void
    {
        $this->categoryId = $categoryId;
    }

    public function getPrice(): float
    {
        return $this->price;
    }

    public function setPrice(float $price): void
    {
        $this->price = $price;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): void
    {
        $this->image = $image;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

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
        if (null !== $product->getCode()) {
            $this->setCode($product->getCode());
        }
        if (null !== $product->getTypeId()) {
            $this->setTypeId($product->getTypeId());
        }
        if (null !== $product->getName()) {
            $this->setName($product->getName());
        }
        if (null !== $product->getCategoryId()) {
            $this->setCategoryId($product->getCategoryId());
        }
        if (null !== $product->getPrice() && 0.0 !== $product->getPrice()) {
            $this->setPrice($product->getPrice());
        }
        if (null !== $product->getImage()) {
            $this->setImage($product->getImage());
        }
        if (null !== $product->isActive()) {
            $this->setActive($product->isActive());
        }

        return $this;
    }

    public function toDomain(): Product
    {
        $product = new Product();
        $product->setId($this->getId());
        $product->setTypeId($this->getTypeId());
        $product->setCode($this->getCode());
        $product->setName($this->getName());
        $product->setCategoryId($this->getCategoryId());
        $product->setPrice($this->getPrice());
        $product->setImage($this->getImage());
        $product->setActive($this->isActive());

        return $product;
    }
}
