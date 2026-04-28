<?php

declare(strict_types=1);

namespace App\Inventory\Entity;

use App\Catalog\Entity\Product;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Efact\Doctrine\Filter\DevExtremeFilter;
use App\Inventory\Repository\StockItemRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: StockItemRepository::class)]
#[ORM\Table(name: 'stock_items')]
#[ApiResource(
    operations: [new GetCollection(), new Get()],
    normalizationContext: ['groups' => ['stock_item:read']],
    filters: [DevExtremeFilter::class],
    mercure: true,
)]
class StockItem
{
    #[Groups(['stock_item:read'])]
    #[ApiProperty(identifier: true, description: 'stock_item.id.label')]
    public function getId(): string
    {
        return sprintf('%s:%s', $this->warehouse->getId(), $this->product->getId());
    }

    #[Groups(['stock_item:read'])]
    #[ApiProperty(description: 'stock_item.available_qty.label')]
    public function getQtyAvailable(): string
    {
        return number_format((float) $this->qtyOnHand - (float) $this->qtyReserved, 6, '.', '');
    }

    #[Groups(['stock_item:read'])]
    #[ApiProperty(identifier: false, description: 'stock_item.warehouse.label')]
    #[ORM\Id]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'warehouse_id', referencedColumnName: 'almacen_id', nullable: false)]
    private Warehouse $warehouse;

    #[Groups(['stock_item:read'])]
    #[ApiProperty(identifier: false, description: 'stock_item.product.label')]
    #[ORM\Id]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'producto_id', nullable: false)]
    private Product $product;

    #[Groups(['stock_item:read'])]
    #[ApiProperty(description: 'stock_item.qty_on_hand.label')]
    #[ORM\Column(name: 'qty_on_hand', type: Types::DECIMAL, precision: 18, scale: 6)]
    private string $qtyOnHand = '0.000000';

    #[Groups(['stock_item:read'])]
    #[ApiProperty(description: 'stock_item.qty_reserved.label')]
    #[ORM\Column(name: 'qty_reserved', type: Types::DECIMAL, precision: 18, scale: 6)]
    private string $qtyReserved = '0.000000';

    #[Groups(['stock_item:read'])]
    #[ApiProperty(description: 'stock_item.updated_at.label')]
    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $updatedAt;

    /** References the last stock_movements.id that was applied to this read model. */
    #[ORM\Column(name: 'last_movement_id', type: Types::GUID, nullable: true)]
    private ?string $lastMovementId = null;

    /** Optimistic concurrency version counter. Incremented on every write. */
    #[ORM\Column(name: 'version', type: Types::INTEGER, options: ['default' => 0, 'unsigned' => true])]
    private int $version = 0;

    public function __construct()
    {
        $this->updatedAt = new \DateTimeImmutable();
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

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function setProduct(Product $product): static
    {
        $this->product = $product;

        return $this;
    }

    public function getQtyOnHand(): string
    {
        return $this->qtyOnHand;
    }

    public function setQtyOnHand(string $qtyOnHand): static
    {
        $this->qtyOnHand = $qtyOnHand;

        return $this;
    }

    public function getQtyReserved(): string
    {
        return $this->qtyReserved;
    }

    public function setQtyReserved(string $qtyReserved): static
    {
        $this->qtyReserved = $qtyReserved;

        return $this;
    }

    public function getUpdatedAt(): \DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getLastMovementId(): ?string
    {
        return $this->lastMovementId;
    }

    public function setLastMovementId(?string $lastMovementId): static
    {
        $this->lastMovementId = $lastMovementId;

        return $this;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function incrementVersion(): static
    {
        ++$this->version;

        return $this;
    }
}
