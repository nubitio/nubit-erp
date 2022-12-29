<?php

namespace App\Domain\Product;

use App\Domain\Annotation\IgnoreAutowire;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[IgnoreAutowire]
class Product
{
    #[SerializedName('producto_id')]
    private ?string $id = null;
    #[SerializedName('tipo_id')]
    private ?string $typeId = null;
    #[SerializedName('codigo')]
    private ?string $code = null;
    #[SerializedName('descripcion')]
    private ?string $name = null;
    #[SerializedName('categoria_id')]
    private ?string $categoryId = null;
    #[SerializedName('precio')]
    private float $price = 0.0;
    #[SerializedName('imagen')]
    private ?string $image = null;
    #[SerializedName('estado')]
    private bool $active = false;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(?string $id): void
    {
        $this->id = $id;
    }

    public function getTypeId(): ?string
    {
        return $this->typeId;
    }

    public function setTypeId(?string $typeId): void
    {
        $this->typeId = $typeId;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): void
    {
        if ('' === $code) {
            $this->code = null;
        } else {
            $this->code = $code;
        }
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getCategoryId(): ?string
    {
        return $this->categoryId;
    }

    public function setCategoryId(?string $categoryId): void
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
}
