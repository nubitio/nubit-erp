<?php

declare(strict_types=1);

namespace App\Entity;

use App\Catalog\Entity\Category;
use App\Catalog\Entity\SunatCatalog;
use App\Catalog\Entity\UnitOfMeasure;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Efact\Doctrine\Filter\DevExtremeFilter;
use App\Inventory\Core\Enum\InventoryBehavior;
use App\Inventory\Core\Enum\LotTrackingStrategy;
use App\Inventory\Core\Enum\ProductKind;
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
    operations: [
        new GetCollection(),
        new Post(),
        new Get(),
        new Patch(),
        new Delete(),
        new Get(
            uriTemplate: 'products/{id}/stock/{warehouseId}',
            controller: ProductStockController::class,
            read: false,
        ),
    ],
    normalizationContext: ['groups' => ['product:read']],
    denormalizationContext: ['groups' => ['product:write']],
    filters: [DevExtremeFilter::class],
    mercure: true,
)]
#[AppAssert\UniqueEntity(fields: ['code'], message: 'Ya existe un producto con el mismo código.')]
#[AppAssert\UniqueEntity(fields: ['name'], message: 'Ya existe un producto con el mismo nombre.')]
class Product
{
    #[Groups(['product:read', 'sale_item:read', 'sale_item_record:read', 'purchase_item:read', 'stock_item:read'])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\Column(name: 'producto_id')]
    private ?string $id = null;

    #[Groups(['product:read', 'stock_item:read'])]
    #[ApiProperty(description: 'product.code.label')]
    #[ORM\Column(name: 'codigo', length: 255, nullable: true)]
    private ?string $code = null;

    #[Assert\NotBlank]
    #[Groups(['product:read', 'product:write'])]
    #[ApiProperty(description: 'product.type.label')]
    #[ORM\Column(name: 'tipo_id')]
    private int $type;

    /** @deprecated Use $productKind + $inventoryBehavior instead. Kept for backward compatibility. */
    #[Groups(['product:read'])]
    #[ApiProperty(description: 'product.product_type.label')]
    #[ORM\Column(name: 'product_type', length: 20, options: ['default' => ProductType::STOCKABLE->value])]
    private string $productType = ProductType::STOCKABLE->value;

    #[Groups(['product:read', 'product:write'])]
    #[ApiProperty(description: 'product.product_kind.label')]
    #[ORM\Column(name: 'product_kind', length: 10, options: ['default' => 'GOOD'])]
    private string $productKind = ProductKind::GOOD->value;

    #[Groups(['product:read', 'product:write'])]
    #[ApiProperty(description: 'product.inventory_behavior.label')]
    #[ORM\Column(name: 'inventory_behavior', length: 12, options: ['default' => 'TRACKED'])]
    private string $inventoryBehavior = InventoryBehavior::TRACKED->value;

    /** Transient flag: true when inventoryBehavior was explicitly set via v2 API — prevents setType() from overriding it. */
    private bool $inventoryBehaviorExplicitlySet = false;

    #[Groups(['product:read', 'product:write'])]
    #[Assert\Length(max: 64)]
    #[ApiProperty(description: 'product.sku.label')]
    #[ORM\Column(name: 'sku', length: 64, nullable: true, unique: true)]
    private ?string $sku = null;

    #[Groups(['product:read', 'product:write'])]
    #[Assert\Length(max: 64)]
    #[ApiProperty(description: 'product.barcode.label')]
    #[ORM\Column(name: 'barcode', length: 64, nullable: true)]
    private ?string $barcode = null;

    #[Groups(['product:read', 'product:write'])]
    #[ApiProperty(description: 'product.track_inventory.label')]
    #[ORM\Column(name: 'track_inventory', options: ['default' => true])]
    private bool $trackInventory = true;

    #[Groups(['product:read', 'product:write'])]
    #[ApiProperty(description: 'product.lot_tracking_strategy.label')]
    #[ORM\Column(name: 'lot_tracking_strategy', length: 10, options: ['default' => 'NONE'])]
    private string $lotTrackingStrategy = LotTrackingStrategy::NONE->value;

    #[Groups(['product:read'])]
    #[ApiProperty(description: 'product.base_unit.label')]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'base_unit_id', referencedColumnName: 'unidad_id', nullable: true)]
    private ?UnitOfMeasure $baseUnit = null;

    #[Groups(['product:read'])]
    #[ApiProperty(description: 'product.is_active.label')]
    #[ORM\Column(name: 'is_active', options: ['default' => true])]
    private bool $isActive = true;

    #[Assert\NotBlank]
    #[Groups(['product:read', 'product:write', 'sale_item:read', 'sale_item_record:read', 'purchase_item:read', 'stock_item:read'])]
    #[ApiProperty(description: 'product.name.label')]
    #[ORM\Column(name: 'descripcion', length: 255)]
    private string $name;

    #[Assert\NotBlank]
    #[Groups(['product:read', 'product:write'])]
    #[ApiProperty(description: 'product.category.label')]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'categoria_id', referencedColumnName: 'categoria_id', nullable: false)]
    private Category $category;

    #[Assert\NotBlank]
    #[Groups(['product:read', 'product:write'])]
    #[ApiProperty(description: 'product.unit_of_measure.label')]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'unidad_id', referencedColumnName: 'unidad_id', nullable: false)]
    private UnitOfMeasure $unitOfMeasure;

    #[Groups(['product:read'])]
    #[ApiProperty(description: 'product.purchase_price.label')]
    #[ORM\Column(name: 'precio_compra', type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $purchasePrice = null;

    #[Assert\NotBlank]
    #[Groups(['product:read', 'product:write'])]
    #[ApiProperty(description: 'product.sale_price.label')]
    #[ORM\Column(name: 'precio', type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $salePrice = '0.00';

    #[Groups(['product:read'])]
    #[ApiProperty(description: 'product.stock.label')]
    #[ORM\Column(name: 'stock', type: Types::DECIMAL, precision: 12, scale: 3, nullable: true)]
    private ?string $stock = null;

    #[Groups(['product:read', 'product:write'])]
    #[ApiProperty(description: 'product.min_stock.label')]
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

    #[Groups(['product:read', 'product:write'])]
    #[ApiProperty(description: 'product.photo.label')]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'imagen', referencedColumnName: 'id')]
    private ?Media $photo = null;

    #[ApiProperty(readableLink: true, description: 'product.detraction_catalog.label')]
    #[Groups(['product:read', 'product:write'])]
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
            $this->productKind = ProductKind::GOOD->value;
            if (!$this->inventoryBehaviorExplicitlySet) {
                $this->inventoryBehavior = InventoryBehavior::TRACKED->value;
                $this->trackInventory = true;
            }
        } else {
            $this->productType = ProductType::SERVICE->value;
            $this->productKind = ProductKind::SERVICE->value;
            if (!$this->inventoryBehaviorExplicitlySet) {
                $this->inventoryBehavior = InventoryBehavior::NOT_TRACKED->value;
                $this->trackInventory = false;
            }
        }

        return $this;
    }

    public function getProductType(): string
    {
        return $this->productType;
    }

    /** @deprecated Use setProductKind + setInventoryBehavior instead. */
    public function setProductType(string $productType): static
    {
        $normalized = strtoupper(trim($productType));
        $resolved = ProductType::tryFrom($normalized) ?? ProductType::STOCKABLE;
        $this->productType = $resolved->value;

        // Derive new fields from legacy value for backward compat.
        $this->productKind = ProductKind::fromProductType($resolved->value)->value;
        $this->inventoryBehavior = InventoryBehavior::fromProductType($resolved->value)->value;
        $this->trackInventory = InventoryBehavior::fromProductType($resolved->value)->tracksInventory();

        return $this;
    }

    public function getProductKind(): string
    {
        return $this->productKind;
    }

    public function getProductKindEnum(): ProductKind
    {
        return ProductKind::from($this->productKind);
    }

    public function setProductKind(ProductKind|string $kind): static
    {
        if (is_string($kind)) {
            $kind = ProductKind::from($kind);
        }
        $this->productKind = $kind->value;

        return $this;
    }

    public function getInventoryBehavior(): string
    {
        return $this->inventoryBehavior;
    }

    public function getInventoryBehaviorEnum(): InventoryBehavior
    {
        return InventoryBehavior::from($this->inventoryBehavior);
    }

    public function setInventoryBehavior(InventoryBehavior|string $behavior): static
    {
        if (is_string($behavior)) {
            $behavior = InventoryBehavior::from($behavior);
        }
        $this->inventoryBehavior = $behavior->value;
        $this->trackInventory = $behavior->tracksInventory();
        $this->inventoryBehaviorExplicitlySet = true;

        return $this;
    }

    public function getSku(): ?string
    {
        return $this->sku;
    }

    public function setSku(?string $sku): static
    {
        $this->sku = $sku !== null ? trim($sku) : null;

        return $this;
    }

    public function getBarcode(): ?string
    {
        return $this->barcode;
    }

    public function setBarcode(?string $barcode): static
    {
        $this->barcode = $barcode !== null ? trim($barcode) : null;

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

    public function getLotTrackingStrategy(): string
    {
        return $this->lotTrackingStrategy;
    }

    public function setLotTrackingStrategy(LotTrackingStrategy|string $strategy): static
    {
        $this->lotTrackingStrategy = $strategy instanceof LotTrackingStrategy ? $strategy->value : LotTrackingStrategy::from($strategy)->value;

        return $this;
    }

    public function getLotTrackingStrategyEnum(): LotTrackingStrategy
    {
        return LotTrackingStrategy::from($this->lotTrackingStrategy);
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
