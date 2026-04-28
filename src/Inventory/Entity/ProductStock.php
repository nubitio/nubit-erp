<?php

declare(strict_types=1);

namespace App\Inventory\Entity;

use App\Catalog\Entity\Product;
use DateTimeImmutable;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use App\Inventory\Repository\ProductStockRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: ProductStockRepository::class)]
#[ORM\Table(name: 'product_stock')]
#[ORM\UniqueConstraint(name: 'product_warehouse_unique', columns: ['product_id', 'warehouse_id'])]
#[ApiResource(
    normalizationContext: ['groups' => ['product_stock:read']],
    mercure: true,
)]
class ProductStock
{
    #[Groups(['product_stock:read'])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column]
    private ?int $id = null;

    #[ApiProperty(description: 'product_stock.product.label')]
    #[Groups(['product_stock:read'])]
    #[ORM\ManyToOne(inversedBy: 'stocks')]
    #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'producto_id', nullable: false)]
    private Product $product;

    #[ApiProperty(description: 'product_stock.warehouse.label')]
    #[Groups(['product_stock:read'])]
    #[ORM\ManyToOne(inversedBy: 'productStocks')]
    #[ORM\JoinColumn(name: 'warehouse_id', referencedColumnName: 'almacen_id', nullable: false)]
    private Warehouse $warehouse;

    #[ApiProperty(description: 'product_stock.stock.label')]
    #[Groups(['product_stock:read'])]
    #[ORM\Column(type: 'decimal', precision: 12, scale: 3, options: ['comment' => 'float'])]
    private string $stock = '0.000';

    #[ApiProperty(description: 'product_stock.min_stock.label')]
    #[Groups(['product_stock:read'])]
    #[ORM\Column(name: 'min_stock', type: 'decimal', precision: 12, scale: 3, options: ['comment' => 'float'])]
    private string $minStock = '0.000';

    #[ApiProperty(description: 'product_stock.created_at.label')]
    #[Groups(['product_stock:read'])]
    #[ORM\Column(name: 'created_at')]
    private DateTimeImmutable $createdAt;

    #[ApiProperty(description: 'product_stock.updated_at.label')]
    #[Groups(['product_stock:read'])]
    #[ORM\Column(name: 'updated_at', nullable: true)]
    private ?DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
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

    public function getWarehouse(): Warehouse
    {
        return $this->warehouse;
    }

    public function setWarehouse(Warehouse $warehouse): static
    {
        $this->warehouse = $warehouse;

        return $this;
    }

    public function getStock(): float
    {
        return (float)$this->stock;
    }

    public function setStock(float $stock): static
    {
        $this->stock = (string)$stock;

        return $this;
    }

    public function getMinStock(): float
    {
        return (float)$this->minStock;
    }

    public function setMinStock(float $minStock): static
    {
        $this->minStock = (string)$minStock;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function updateTimestamps(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }
}
