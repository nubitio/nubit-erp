<?php

declare(strict_types=1);

namespace App\Purchasing\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Contacts\Entity\Supplier;
use App\Identity\Entity\User;
use App\Inventory\Entity\Warehouse;
use App\Organization\Entity\Branch;
use App\Purchasing\Repository\PurchaseOrderRepository;
use App\Purchasing\State\PurchaseOrderCancelProcessor;
use App\Purchasing\State\PurchaseOrderProcessor;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Nubit\ApiPlatform\Doctrine\Filter\DataGridFilter;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * A supplier purchase order requesting goods before they're received.
 * Receiving against it creates a Purchase (src/Purchasing/Entity/Purchase.php)
 * the same way direct-intake purchases are created today — this entity
 * never touches inventory itself, only tracks ordered/received quantities.
 */
#[ORM\Entity(repositoryClass: PurchaseOrderRepository::class)]
#[ORM\Table(name: 'purchase_orders')]
#[ORM\Index(name: 'idx_purchase_orders_status_issued', columns: ['status', 'issued_at'])]
#[ORM\Index(name: 'idx_purchase_orders_supplier', columns: ['supplier_id'])]
#[ORM\Index(name: 'idx_purchase_orders_branch', columns: ['branch_id'])]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Post(),
        new Patch(),
        new Delete(processor: PurchaseOrderCancelProcessor::class, output: false),
    ],
    normalizationContext: ['groups' => ['purchase_order:read']],
    denormalizationContext: ['groups' => ['purchase_order:write']],
    filters: [DataGridFilter::class],
    processor: PurchaseOrderProcessor::class,
    mercure: true,
)]
class PurchaseOrder
{
    public const string STATUS_DRAFT = 'draft';

    public const string STATUS_SENT = 'sent';

    public const string STATUS_PARTIALLY_RECEIVED = 'partially_received';

    public const string STATUS_RECEIVED = 'received';

    public const string STATUS_CANCELLED = 'cancelled';

    #[Groups(['purchase_order:read'])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\Column(name: 'id', type: Types::STRING, length: 36)]
    private ?string $id = null;

    #[Groups(['purchase_order:read'])]
    #[ORM\Column(name: 'order_no', nullable: true)]
    private ?int $orderNo = null;

    #[Groups(['purchase_order:read', 'purchase_order:write'])]
    #[ApiProperty(description: 'purchase_order.branch.label', readableLink: true)]
    #[ORM\ManyToOne(targetEntity: Branch::class)]
    #[ORM\JoinColumn(name: 'branch_id', referencedColumnName: 'local_id', nullable: true)]
    private ?Branch $branch = null;

    #[Groups(['purchase_order:read', 'purchase_order:write'])]
    #[ApiProperty(description: 'purchase_order.warehouse.label', readableLink: true)]
    #[ORM\ManyToOne(targetEntity: Warehouse::class)]
    #[ORM\JoinColumn(name: 'warehouse_id', referencedColumnName: 'almacen_id', nullable: true)]
    private ?Warehouse $warehouse = null;

    #[Groups(['purchase_order:read', 'purchase_order:write'])]
    #[ApiProperty(description: 'purchase_order.supplier.label', readableLink: true)]
    #[ORM\ManyToOne(targetEntity: Supplier::class)]
    #[ORM\JoinColumn(name: 'supplier_id', referencedColumnName: 'proveedor_id', nullable: false)]
    private Supplier $supplier;

    #[Groups(['purchase_order:read'])]
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'buyer_user_id', referencedColumnName: 'usuario_id', nullable: true)]
    private ?User $buyerUser = null;

    #[Groups(['purchase_order:read', 'purchase_order:write'])]
    #[ORM\Column(name: 'status', length: 20, options: ['default' => self::STATUS_DRAFT])]
    private string $status = self::STATUS_DRAFT;

    #[Groups(['purchase_order:read', 'purchase_order:write'])]
    #[ORM\Column(name: 'currency', length: 3, options: ['default' => 'PEN'])]
    private string $currency = 'PEN';

    #[Groups(['purchase_order:read'])]
    #[ORM\Column(name: 'subtotal', type: Types::DECIMAL, precision: 12, scale: 2, options: ['default' => '0.00'])]
    private string $subtotal = '0.00';

    #[Groups(['purchase_order:read'])]
    #[ORM\Column(name: 'tax_total', type: Types::DECIMAL, precision: 12, scale: 2, options: ['default' => '0.00'])]
    private string $taxTotal = '0.00';

    #[Groups(['purchase_order:read'])]
    #[ORM\Column(name: 'grand_total', type: Types::DECIMAL, precision: 12, scale: 2, options: ['default' => '0.00'])]
    private string $grandTotal = '0.00';

    #[Groups(['purchase_order:read', 'purchase_order:write'])]
    #[ORM\Column(name: 'note', type: Types::TEXT, nullable: true)]
    private ?string $note = null;

    #[Groups(['purchase_order:read', 'purchase_order:write'])]
    #[ORM\Column(name: 'expected_date', type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $expectedDate = null;

    #[Groups(['purchase_order:read'])]
    #[ORM\Column(name: 'issued_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $issuedAt;

    #[Groups(['purchase_order:read'])]
    #[ORM\Column(name: 'sent_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $sentAt = null;

    #[Groups(['purchase_order:read'])]
    #[ORM\Column(name: 'cancelled_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $cancelledAt = null;

    #[Groups(['purchase_order:read'])]
    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    #[Groups(['purchase_order:read'])]
    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $updatedAt = null;

    /** @var Collection<int, PurchaseOrderItem> */
    #[Groups(['purchase_order:read'])]
    #[ORM\OneToMany(
        targetEntity: PurchaseOrderItem::class,
        mappedBy: 'purchaseOrder',
        cascade: ['persist', 'remove'],
        orphanRemoval: true,
    )]
    private Collection $items;

    /**
     * Write-only transient payload, mirrors SaleQuote::$writeItems.
     *
     * @var array<int, array<string, mixed>>
     */
    #[Groups(['purchase_order:write'])]
    private array $writeItems = [];

    public function __construct()
    {
        $this->items = new ArrayCollection();
        $this->issuedAt = new DateTimeImmutable();
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getOrderNo(): ?int
    {
        return $this->orderNo;
    }

    public function setOrderNo(?int $orderNo): static
    {
        $this->orderNo = $orderNo;

        return $this;
    }

    public function getBranch(): ?Branch
    {
        return $this->branch;
    }

    public function setBranch(?Branch $branch): static
    {
        $this->branch = $branch;

        return $this;
    }

    public function getWarehouse(): ?Warehouse
    {
        return $this->warehouse;
    }

    public function setWarehouse(?Warehouse $warehouse): static
    {
        $this->warehouse = $warehouse;

        return $this;
    }

    public function getSupplier(): Supplier
    {
        return $this->supplier;
    }

    public function setSupplier(Supplier $supplier): static
    {
        $this->supplier = $supplier;

        return $this;
    }

    public function getBuyerUser(): ?User
    {
        return $this->buyerUser;
    }

    public function setBuyerUser(?User $buyerUser): static
    {
        $this->buyerUser = $buyerUser;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): static
    {
        $this->currency = $currency;

        return $this;
    }

    public function getSubtotal(): string
    {
        return $this->subtotal;
    }

    public function setSubtotal(string $subtotal): static
    {
        $this->subtotal = $subtotal;

        return $this;
    }

    public function getTaxTotal(): string
    {
        return $this->taxTotal;
    }

    public function setTaxTotal(string $taxTotal): static
    {
        $this->taxTotal = $taxTotal;

        return $this;
    }

    public function getGrandTotal(): string
    {
        return $this->grandTotal;
    }

    public function setGrandTotal(string $grandTotal): static
    {
        $this->grandTotal = $grandTotal;

        return $this;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): static
    {
        $this->note = $note;

        return $this;
    }

    public function getExpectedDate(): ?DateTimeImmutable
    {
        return $this->expectedDate;
    }

    public function setExpectedDate(?DateTimeImmutable $expectedDate): static
    {
        $this->expectedDate = $expectedDate;

        return $this;
    }

    public function getIssuedAt(): DateTimeImmutable
    {
        return $this->issuedAt;
    }

    public function getSentAt(): ?DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function setSentAt(?DateTimeImmutable $sentAt): static
    {
        $this->sentAt = $sentAt;

        return $this;
    }

    public function getCancelledAt(): ?DateTimeImmutable
    {
        return $this->cancelledAt;
    }

    public function setCancelledAt(?DateTimeImmutable $cancelledAt): static
    {
        $this->cancelledAt = $cancelledAt;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /** @return Collection<int, PurchaseOrderItem> */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(PurchaseOrderItem $item): static
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setPurchaseOrder($this);
        }

        return $this;
    }

    public function clearItems(): static
    {
        foreach ($this->items as $item) {
            $this->items->removeElement($item);
        }

        return $this;
    }

    /** @return array<int, array<string, mixed>> */
    public function getWriteItems(): array
    {
        return $this->writeItems;
    }

    /** @param array<int, array<string, mixed>> $writeItems */
    public function setWriteItems(array $writeItems): static
    {
        $this->writeItems = $writeItems;

        return $this;
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }
}
