<?php

declare(strict_types=1);

namespace App\Entity;

use App\Inventory\Core\Enum\LotTrackingStrategy;
use App\Repository\StockLotRepository;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: StockLotRepository::class)]
#[ORM\Table(name: 'stock_lots')]
#[ORM\UniqueConstraint(name: 'uq_lot_product_warehouse_number', columns: ['product_id', 'warehouse_id', 'lot_number'])]
#[ORM\Index(name: 'idx_lot_product_warehouse', columns: ['product_id', 'warehouse_id'])]
#[ORM\Index(name: 'idx_lot_expiration', columns: ['expiration_date'])]
class StockLot
{
    #[Groups(['stock_lot:read'])]
    #[ORM\Id]
    #[ORM\Column(type: Types::GUID, length: 36)]
    private string $id;

    #[Groups(['stock_lot:read'])]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'producto_id', nullable: false)]
    private Product $product;

    #[Groups(['stock_lot:read'])]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'warehouse_id', referencedColumnName: 'almacen_id', nullable: false)]
    private Warehouse $warehouse;

    #[Groups(['stock_lot:read'])]
    #[ORM\Column(name: 'lot_number', length: 100)]
    private string $lotNumber;

    #[Groups(['stock_lot:read'])]
    #[ORM\Column(name: 'expiration_date', type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $expirationDate = null;

    #[Groups(['stock_lot:read'])]
    #[ORM\Column(name: 'manufacture_date', type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $manufactureDate = null;

    #[Groups(['stock_lot:read'])]
    #[ORM\Column(name: 'qty_on_hand', type: Types::DECIMAL, precision: 18, scale: 6)]
    private string $qtyOnHand = '0.000000';

    #[Groups(['stock_lot:read'])]
    #[ORM\Column(name: 'cost', type: Types::DECIMAL, precision: 12, scale: 4, nullable: true)]
    private ?string $cost = null;

    #[Groups(['stock_lot:read'])]
    #[ORM\Column(name: 'received_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $receivedAt;

    #[Groups(['stock_lot:read'])]
    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->id = Uuid::v7()->toRfc4122();
        $this->receivedAt = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $this->createdAt = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    public function getId(): string
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

    public function getLotNumber(): string
    {
        return $this->lotNumber;
    }

    public function setLotNumber(string $lotNumber): static
    {
        $this->lotNumber = $lotNumber;

        return $this;
    }

    public function getExpirationDate(): ?DateTimeImmutable
    {
        return $this->expirationDate;
    }

    public function setExpirationDate(?DateTimeImmutable $expirationDate): static
    {
        $this->expirationDate = $expirationDate;

        return $this;
    }

    public function getManufactureDate(): ?DateTimeImmutable
    {
        return $this->manufactureDate;
    }

    public function setManufactureDate(?DateTimeImmutable $manufactureDate): static
    {
        $this->manufactureDate = $manufactureDate;

        return $this;
    }

    public function getQtyOnHand(): string
    {
        return $this->qtyOnHand;
    }

    public function setQtyOnHand(string $qtyOnHand): static
    {
        // Normalise to 6 decimal places without float coercion.
        $this->qtyOnHand = bcadd($qtyOnHand, '0', 6);

        return $this;
    }

    public function getCost(): ?string
    {
        return $this->cost;
    }

    public function setCost(?string $cost): static
    {
        $this->cost = $cost !== null ? bcadd($cost, '0', 4) : null;

        return $this;
    }

    public function getReceivedAt(): DateTimeImmutable
    {
        return $this->receivedAt;
    }

    public function setReceivedAt(DateTimeImmutable $receivedAt): static
    {
        $this->receivedAt = $receivedAt;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getLotTrackingStrategy(): LotTrackingStrategy
    {
        return LotTrackingStrategy::from($this->product->getLotTrackingStrategy());
    }

    public function isExpired(): bool
    {
        if ($this->expirationDate === null) {
            return false;
        }

        return $this->expirationDate < new DateTimeImmutable('today', new DateTimeZone('America/Lima'));
    }
}
