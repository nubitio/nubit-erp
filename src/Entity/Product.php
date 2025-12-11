<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Core\Doctrine\Filter\DevExtremeFilter;
use App\Repository\ProductRepository;
use App\State\Product\ProductStockController;
use App\Validator as AppAssert;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
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
    #[ORM\Column(name: 'precio_compra', nullable: true)]
    private ?float $purchasePrice = null;

    #[Assert\NotBlank]
    #[Groups(['product:read'])]
    #[ORM\Column(name: 'precio')]
    private float $salePrice = 0;

    #[Groups(['product:read'])]
    #[ORM\Column(name: 'stock', nullable: true)]
    private ?int $stock = null;

    #[Groups(['product:read'])]
    #[ORM\Column(name: 'stock_minimo', nullable: true)]
    private ?int $minStock = null;

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

    public function getPurchasePrice(): ?float
    {
        return $this->purchasePrice;
    }

    public function setPurchasePrice(float $purchasePrice): static
    {
        $this->purchasePrice = $purchasePrice;

        return $this;
    }

    public function getSalePrice(): float
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

    public function getStockByWarehouse(Warehouse $warehouse): ?int
    {
        foreach ($this->stocks as $stock) {
            if ($stock->getWarehouse() === $warehouse) {
                return $stock->getStock();
            }
        }

        return 0;
    }
}
