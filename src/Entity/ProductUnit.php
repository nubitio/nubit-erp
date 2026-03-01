<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ProductUnitRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductUnitRepository::class)]
#[ORM\Table(name: 'product_units')]
#[ORM\UniqueConstraint(name: 'uq_product_units_product_unit', columns: ['product_id', 'unit_id'])]
class ProductUnit
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'producto_id', nullable: false, onDelete: 'CASCADE')]
    private Product $product;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'unit_id', referencedColumnName: 'unidad_id', nullable: false)]
    private UnitOfMeasure $unit;

    #[ORM\Column(name: 'conversion_to_base', type: Types::DECIMAL, precision: 18, scale: 6)]
    private string $conversionToBase = '1.000000';

    #[ORM\Column(name: 'is_default_sale_unit', options: ['default' => false])]
    private bool $defaultSaleUnit = false;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'purchase_unit_id', referencedColumnName: 'unidad_id', nullable: true)]
    private ?UnitOfMeasure $purchaseUnit = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getUnit(): UnitOfMeasure
    {
        return $this->unit;
    }

    public function setUnit(UnitOfMeasure $unit): static
    {
        $this->unit = $unit;

        return $this;
    }

    public function getConversionToBase(): string
    {
        return $this->conversionToBase;
    }

    public function setConversionToBase(string $conversionToBase): static
    {
        $this->conversionToBase = $conversionToBase;

        return $this;
    }

    public function isDefaultSaleUnit(): bool
    {
        return $this->defaultSaleUnit;
    }

    public function setDefaultSaleUnit(bool $defaultSaleUnit): static
    {
        $this->defaultSaleUnit = $defaultSaleUnit;

        return $this;
    }

    public function getPurchaseUnit(): ?UnitOfMeasure
    {
        return $this->purchaseUnit;
    }

    public function setPurchaseUnit(?UnitOfMeasure $purchaseUnit): static
    {
        $this->purchaseUnit = $purchaseUnit;

        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }
}
