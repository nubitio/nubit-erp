<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Core\Doctrine\Filter\DevExtremeFilter;
use App\Inventory\Core\Enum\ProductType;
use App\Repository\ProductRepository;
use App\State\Product\ProductStockController;
use App\Validator as AppAssert;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
#[ORM\Table(name: 'producto')]
#[ApiResource(
    normalizationContext: ['groups' => ['product:read']],
    filters: [DevExtremeFilter::class],
)]
#[ApiResource(
    uriTemplate: 'products/{id}/stock/{warehouseId}',
    operations: [
        new Get(
            controller: ProductStockController::class,
            read: false
        ),
    ],
)]
#[AppAssert\UniqueEntity(fields: ['code'], message: 'Ya existe un producto con el mismo código.')]
#[AppAssert\UniqueEntity(fields: ['name'], message: 'Ya existe un producto con el mismo nombre.')]
class Product
{
    #[Groups(['product:read', 'sale_item:read'])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\Column(name: 'producto_id')]
    private ?string $id = null;

    #[Groups(['product:read'])]
    #[ORM\Column(name: 'codigo', length: 255, nullable: true)]
    private ?string $code = null;

    #[Assert\NotBlank]
    #[Groups(['product:read'])]
    #[ORM\Column(name: 'tipo_id')]
    private int $type;

    #[Groups(['product:read'])]
    #[ORM\Column(name: 'product_type', length: 20, options: ['default' => ProductType::STOCKABLE->value])]
    private string $productType = ProductType::STOCKABLE->value;

    #[Groups(['product:read'])]
    #[ORM\Column(name: 'track_inventory', options: ['default' => true])]
    private bool $trackInventory = true;

    #[Groups(['product:read'])]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'base_unit_id', referencedColumnName: 'unidad_id', nullable: true)]
    private ?UnitOfMeasure $baseUnit = null;

    #[Groups(['product:read'])]
    #[ORM\Column(name: 'is_active', options: ['default' => true])]
    private bool $isActive = true;

    #[Assert\NotBlank]
    #[Groups(['product:read', 'sale_item:read'])]
    #[ORM\Column(name: 'descripcion', length: 255)]
    private string $name;

    #[Assert\NotBlank]
    #[Groups(['product:read'])]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'categoria_id', referencedColumnName: 'categoria_id', nullable: false)]
    private Category $category;

    #[Assert\NotBlank]
    #[Groups(['product:read'])]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'unidad_id', referencedColumnName: 'unidad_id', nullable: false)]
    private UnitOfMeasure $unitOfMeasure;

    #[Groups(['product:read'])]
    #[ORM\Column(name: 'precio_compra', type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $purchasePrice = null;

    #[Assert\NotBlank]
    #[Groups(['product:read'])]
    #[ORM\Column(name: 'precio', type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $salePrice = '0.00';

    #[Groups(['product:read'])]
    #[ORM\Column(name: 'stock', type: Types::DECIMAL, precision: 12, scale: 3, nullable: true)]
    private ?string $stock = null;

    #[Groups(['product:read'])]
    #[ORM\Column(name: 'stock_minimo', type: Types::DECIMAL, precision: 12, scale: 3, nullable: true)]
    private ?string $minStock = null;

    /**
     * @var Collection<int, ProductStock>
     */
    #[Groups(['product:read'])]
    #[ORM\OneToMany(targetEntity: ProductStock::class, mappedBy: 'product', cascade: [
        'persist',
        'remove',
    ], orphanRemoval: true)]
    private Collection $stocks;

    #[Groups(['product:read'])]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'imagen', referencedColumnName: 'id')]
    private ?Media $photo = null;

    #[ApiProperty(readableLink: true)]
    #[Groups(['product:read'])]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'detraccion_catalog_id', referencedColumnName: 'id', nullable: true)]
    private ?SunatCatalog $detractionCatalog = null;

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

    public function getType(): int
    {
        return $this->type;
    }

    public function setType(int $type): static
    {
        $this->type = $type;
        if ($type === 1) {
            $this->productType = ProductType::STOCKABLE->value;
            $this->trackInventory = true;
        } else {
            $this->productType = ProductType::SERVICE->value;
            $this->trackInventory = false;
        }

        return $this;
    }

    public function getProductType(): string
    {
        return $this->productType;
    }

    public function setProductType(string $productType): static
    {
        $normalized = strtoupper(trim($productType));
        $resolved = ProductType::tryFrom($normalized) ?? ProductType::STOCKABLE;
        $this->productType = $resolved->value;

        return $this;
    }

    public function getTrackInventory(): bool
    {
        return $this->trackInventory;
    }

    public function setTrackInventory(bool $trackInventory): static
    {
        $this->trackInventory = $trackInventory;

        return $this;
    }

    public function getBaseUnit(): ?UnitOfMeasure
    {
        return $this->baseUnit;
    }

    public function setBaseUnit(?UnitOfMeasure $baseUnit): static
    {
        $this->baseUnit = $baseUnit;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getCategory(): Category
    {
        return $this->category;
    }

    public function setCategory(Category $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getUnitOfMeasure(): UnitOfMeasure
    {
        return $this->unitOfMeasure;
    }

    public function setUnitOfMeasure(UnitOfMeasure $unitOfMeasure): static
    {
        $this->unitOfMeasure = $unitOfMeasure;

        return $this;
    }

    public function getPurchasePrice(): ?string
    {
        return $this->purchasePrice;
    }

    public function setPurchasePrice(?string $purchasePrice): static
    {
        $this->purchasePrice = $purchasePrice;

        return $this;
    }

    public function getSalePrice(): string
    {
        return $this->salePrice;
    }

    public function setSalePrice(string $salePrice): static
    {
        $this->salePrice = $salePrice;

        return $this;
    }

    public function getStock(): ?string
    {
        return $this->stock;
    }

    public function setStock(?string $stock): static
    {
        $this->stock = $stock;

        return $this;
    }

    public function getDetractionCatalog(): ?SunatCatalog
    {
        return $this->detractionCatalog;
    }

    public function setDetractionCatalog(?SunatCatalog $detractionCatalog): static
    {
        $this->detractionCatalog = $detractionCatalog;

        return $this;
    }

    public function getMinStock(): ?string
    {
        return $this->minStock;
    }

    public function setMinStock(?string $minStock): static
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

    public function __construct()
    {
        $this->stocks = new ArrayCollection();
    }

    /**
     * @return Collection<int, ProductStock>
     */
    public function getStocks(): Collection
    {
        return $this->stocks;
    }

    public function addStock(ProductStock $stock): static
    {
        if (!$this->stocks->contains($stock)) {
            $this->stocks->add($stock);
            $stock->setProduct($this);
        }

        return $this;
    }

    public function removeStock(ProductStock $stock): static
    {
        // set the owning side to null (unless already changed)
        $this->stocks->removeElement($stock);

        return $this;
    }

    public function getStockByWarehouse(Warehouse $warehouse): ?float
    {
        foreach ($this->stocks as $stock) {
            if ($stock->getWarehouse() === $warehouse) {
                return $stock->getStock();
            }
        }

        return 0;
    }
}
