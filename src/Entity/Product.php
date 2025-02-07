<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Core\Infrastructure\Doctrine\Filter\DevExtremeFilter;
use App\Repository\ProductRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
#[ORM\Table(name: 'producto')]
#[ApiResource(
    normalizationContext: ['groups' => ['product:read']],
    filters: [DevExtremeFilter::class],
)]
class Product
{
    #[Groups(['product:read'])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\Column(name: 'producto_id')]
    private ?string $id = null;

    #[Groups(['product:read'])]
    #[ORM\Column(name: 'codigo', length: 255, nullable: true)]
    private ?string $code = null;

    #[Groups(['product:read'])]
    #[ORM\Column(name: 'tipo_id')]
    private ?int $type = null;

    #[Groups(['product:read'])]
    #[ORM\Column(name: 'descripcion', length: 255)]
    private ?string $name = null;

    #[Groups(['product:read'])]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'categoria_id', referencedColumnName: 'categoria_id', nullable: false)]
    private ?Category $category = null;

    #[Groups(['product:read'])]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'unidad_id', referencedColumnName: 'unidad_id', nullable: false)]
    private ?UnitOfMeasure $unitOfMeasure = null;

    #[Groups(['product:read'])]
    #[ORM\Column(name: 'precio_compra')]
    private ?float $purchasePrice = null;

    #[Groups(['product:read'])]
    #[ORM\Column(name: 'precio')]
    private ?float $salePrice = null;

    #[Groups(['product:read'])]
    #[ORM\Column(name: 'stock')]
    private ?int $stock = null;

    #[Groups(['product:read'])]
    #[ORM\Column(name: 'stock_minimo')]
    private ?int $minStock = null;

    #[Groups(['product:read'])]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'imagen', referencedColumnName: 'id')]
    private ?Media $photo = null;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getType(): ?int
    {
        return $this->type;
    }

    public function setType(int $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getUnitOfMeasure(): ?UnitOfMeasure
    {
        return $this->unitOfMeasure;
    }

    public function setUnitOfMeasure(?UnitOfMeasure $unitOfMeasure): static
    {
        $this->unitOfMeasure = $unitOfMeasure;

        return $this;
    }

    public function getPurchasePrice(): ?float
    {
        return $this->purchasePrice;
    }

    public function setPurchasePrice(float $purchasePrice): static
    {
        $this->purchasePrice = $purchasePrice;

        return $this;
    }

    public function getSalePrice(): ?float
    {
        return $this->salePrice;
    }

    public function setSalePrice(float $salePrice): static
    {
        $this->salePrice = $salePrice;

        return $this;
    }

    public function getStock(): ?int
    {
        return $this->stock;
    }

    public function setStock(?int $stock): static
    {
        $this->stock = $stock;

        return $this;
    }

    public function getMinStock(): ?int
    {
        return $this->minStock;
    }

    public function setMinStock(?int $minStock): static
    {
        $this->minStock = $minStock;

        return $this;
    }

    public function getPhoto(): ?Media
    {
        return $this->photo;
    }

    public function setPhoto(?Media $photo): static
    {
        $this->photo = $photo;

        return $this;
    }
}
