<?php

declare(strict_types=1);

namespace App\Entity;

use App\Identity\Entity\User;
use App\Organization\Entity\Branch;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Efact\Doctrine\Filter\DevExtremeFilter;
use App\Repository\PurchaseRepository;
use App\State\Purchase\PurchaseProcessor;
use App\State\Purchase\PurchaseProvider;
use App\State\Purchase\PurchaseSoftDeleteProcessor;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: PurchaseRepository::class)]
#[ORM\Table(name: 'goods_receipts')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    shortName: 'Purchase',
    operations: [
        new GetCollection(),
        new Get(),
        new Post(),
        new Put(),
        new Patch(),
        new Delete(processor: PurchaseSoftDeleteProcessor::class, output: false),
    ],
    normalizationContext:   ['groups' => ['purchase:read']],
    denormalizationContext: ['groups' => ['purchase:write']],
    filters: [DevExtremeFilter::class],
    provider:  PurchaseProvider::class,
    processor: PurchaseProcessor::class,
    mercure: true,
)]
class Purchase
{
    #[Groups(['purchase:read'])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\Column(name: 'id', type: 'string', length: 36)]
    #[ApiProperty(description: 'purchase.id.label')]
    private ?string $id = null;

    #[Groups(['purchase:read'])]
    #[ORM\Column(name: 'legacy_movimiento_id', type: 'string', length: 255, nullable: true)]
    #[ApiProperty(description: 'purchase.legacy_movimiento_id.label')]
    private ?string $legacyMovimientoId = null;

    #[Groups(['purchase:read', 'purchase:write'])]
    #[ORM\ManyToOne(targetEntity: Branch::class)]
    #[ORM\JoinColumn(name: 'branch_id', referencedColumnName: 'local_id', nullable: true)]
    #[ApiProperty(description: 'purchase.branch.label')]
    private ?Branch $branch = null;

    #[Groups(['purchase:read', 'purchase:write'])]
    #[ORM\ManyToOne(targetEntity: Warehouse::class)]
    #[ORM\JoinColumn(name: 'warehouse_id', referencedColumnName: 'almacen_id', nullable: true)]
    #[ApiProperty(description: 'purchase.warehouse.label')]
    private ?Warehouse $warehouse = null;

    #[Groups(['purchase:read', 'purchase:write'])]
    #[ORM\ManyToOne(targetEntity: Supplier::class)]
    #[ORM\JoinColumn(name: 'supplier_id', referencedColumnName: 'proveedor_id', nullable: true)]
    #[ApiProperty(description: 'purchase.supplier.label')]
    private ?Supplier $supplier = null;

    #[Groups(['purchase:read', 'purchase:write'])]
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'buyer_user_id', referencedColumnName: 'usuario_id', nullable: true)]
    #[ApiProperty(description: 'purchase.buyer_user.label')]
    private ?User $buyerUser = null;

    #[Groups(['purchase:read', 'purchase:write'])]
    #[ORM\Column(name: 'status', type: 'string', length: 30, options: ['default' => 'POSTED'])]
    #[ApiProperty(description: 'purchase.status.label')]
    private string $status = 'POSTED';

    #[Groups(['purchase:read', 'purchase:write'])]
    #[ORM\Column(name: 'currency', type: 'string', length: 3, options: ['default' => 'PEN'])]
    #[ApiProperty(description: 'purchase.currency.label')]
    private string $currency = 'PEN';

    #[Groups(['purchase:read', 'purchase:write'])]
    #[ORM\Column(
        name: 'exchange_rate',
        type: Types::DECIMAL,
        precision: 14,
        scale: 6,
        options: ['default' => '1.000000'],
    )]
    #[ApiProperty(description: 'purchase.exchange_rate.label')]
    private string $exchangeRate = '1.000000';

    #[Groups(['purchase:read', 'purchase:write'])]
    #[ORM\Column(name: 'subtotal', type: Types::DECIMAL, precision: 12, scale: 2)]
    #[ApiProperty(description: 'purchase.subtotal.label')]
    private string $subtotal = '0.00';

    #[Groups(['purchase:read', 'purchase:write'])]
    #[ORM\Column(name: 'tax_total', type: Types::DECIMAL, precision: 12, scale: 2)]
    #[ApiProperty(description: 'purchase.tax_total.label')]
    private string $taxTotal = '0.00';

    #[Groups(['purchase:read', 'purchase:write'])]
    #[ORM\Column(name: 'grand_total', type: Types::DECIMAL, precision: 12, scale: 2)]
    #[ApiProperty(description: 'purchase.grand_total.label')]
    private string $grandTotal = '0.00';

    #[Groups(['purchase:read', 'purchase:write'])]
    #[ORM\Column(name: 'note', type: 'string', length: 255, nullable: true)]
    #[ApiProperty(description: 'purchase.note.label')]
    private ?string $note = null;

    #[Groups(['purchase:read', 'purchase:write'])]
    #[ORM\Column(name: 'issued_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    #[ApiProperty(description: 'purchase.issued_at.label')]
    private ?DateTimeInterface $issuedAt = null;

    #[Groups(['purchase:read'])]
    #[ORM\Column(
        name: 'created_at',
        type: Types::DATETIME_MUTABLE,
        nullable: true,
        options: ['default' => 'CURRENT_TIMESTAMP'],
    )]
    #[ApiProperty(description: 'purchase.created_at.label')]
    private ?DateTimeInterface $createdAt = null;

    #[Groups(['purchase:read'])]
    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    #[ApiProperty(description: 'purchase.updated_at.label')]
    private ?DateTimeInterface $updatedAt = null;

    #[ORM\Column(name: 'deleted_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTimeInterface $deletedAt = null;

    #[Groups(['purchase:read', 'purchase:write'])]
    #[ORM\Column(name: 'local_uuid', type: 'string', length: 36, nullable: true)]
    #[ApiProperty(description: 'purchase.local_uuid.label')]
    private ?string $localUuid = null;

    #[Groups(['purchase:read', 'purchase:write'])]
    #[ORM\Column(name: 'idempotency_key', type: 'string', length: 36, nullable: true)]
    #[ApiProperty(description: 'purchase.idempotency_key.label')]
    private ?string $idempotencyKey = null;

    #[Groups(['purchase:read', 'purchase:write'])]
    #[ORM\Column(name: 'sync_status', type: 'string', length: 20, options: ['default' => 'SYNCED'])]
    #[ApiProperty(description: 'purchase.sync_status.label')]
    private string $syncStatus = 'SYNCED';

    #[Groups(['purchase:read', 'purchase:write'])]
    #[ORM\Column(name: 'synced_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    #[ApiProperty(description: 'purchase.synced_at.label')]
    private ?DateTimeInterface $syncedAt = null;

    #[Groups(['purchase:read', 'purchase:write'])]
    #[ORM\Column(name: 'device_id', type: 'string', length: 100, nullable: true)]
    #[ApiProperty(description: 'purchase.device_id.label')]
    private ?string $deviceId = null;

    #[Groups(['purchase:read', 'purchase:write'])]
    #[ORM\Column(name: 'version_no', type: 'integer', options: ['default' => 1])]
    #[ApiProperty(description: 'purchase.version_no.label')]
    private int $versionNo = 1;

    #[Groups(['purchase:read', 'purchase:write'])]
    #[ORM\Column(
        name: 'last_modified_at',
        type: Types::DATETIME_MUTABLE,
        nullable: true,
        options: ['default' => 'CURRENT_TIMESTAMP'],
    )]
    #[ApiProperty(description: 'purchase.last_modified_at.label')]
    private ?DateTimeInterface $lastModifiedAt = null;

    /**
     * @var Collection<int, PurchaseItem>
     */
    #[Groups(['purchase:read', 'purchase:write'])]
    #[ORM\OneToMany(
        targetEntity: PurchaseItem::class,
        mappedBy: 'purchase',
        cascade: ['persist', 'remove'],
        orphanRemoval: true,
    )]
    private Collection $items;

    public function __construct()
    {
        $this->items = new ArrayCollection();
    }

    #[ORM\PrePersist]
    public function setTimestampsOnPersist(): void
    {
        if ($this->createdAt === null) {
            $this->createdAt = new \DateTime();
        }
        if ($this->lastModifiedAt === null) {
            $this->lastModifiedAt = new \DateTime();
        }
        if ($this->issuedAt === null) {
            $this->issuedAt = new \DateTime();
        }
    }

    #[ORM\PreUpdate]
    public function setTimestampsOnUpdate(): void
    {
        $this->updatedAt = new \DateTime();
        $this->lastModifiedAt = new \DateTime();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(?string $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getLegacyMovimientoId(): ?string
    {
        return $this->legacyMovimientoId;
    }

    public function setLegacyMovimientoId(?string $legacyMovimientoId): static
    {
        $this->legacyMovimientoId = $legacyMovimientoId;

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

    public function getSupplier(): ?Supplier
    {
        return $this->supplier;
    }

    public function setSupplier(?Supplier $supplier): static
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

    public function getExchangeRate(): string
    {
        return $this->exchangeRate;
    }

    public function setExchangeRate(string $exchangeRate): static
    {
        $this->exchangeRate = $exchangeRate;

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

    public function getIssuedAt(): ?DateTimeInterface
    {
        return $this->issuedAt;
    }

    public function setIssuedAt(?DateTimeInterface $issuedAt): static
    {
        $this->issuedAt = $issuedAt;

        return $this;
    }

    public function getCreatedAt(): ?DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getDeletedAt(): ?DateTimeInterface
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?DateTimeInterface $deletedAt): static
    {
        $this->deletedAt = $deletedAt;

        return $this;
    }

    public function getLocalUuid(): ?string
    {
        return $this->localUuid;
    }

    public function setLocalUuid(?string $localUuid): static
    {
        $this->localUuid = $localUuid;

        return $this;
    }

    public function getIdempotencyKey(): ?string
    {
        return $this->idempotencyKey;
    }

    public function setIdempotencyKey(?string $idempotencyKey): static
    {
        $this->idempotencyKey = $idempotencyKey;

        return $this;
    }

    public function getSyncStatus(): string
    {
        return $this->syncStatus;
    }

    public function setSyncStatus(string $syncStatus): static
    {
        $this->syncStatus = $syncStatus;

        return $this;
    }

    public function getSyncedAt(): ?DateTimeInterface
    {
        return $this->syncedAt;
    }

    public function setSyncedAt(?DateTimeInterface $syncedAt): static
    {
        $this->syncedAt = $syncedAt;

        return $this;
    }

    public function getDeviceId(): ?string
    {
        return $this->deviceId;
    }

    public function setDeviceId(?string $deviceId): static
    {
        $this->deviceId = $deviceId;

        return $this;
    }

    public function getVersionNo(): int
    {
        return $this->versionNo;
    }

    public function setVersionNo(int $versionNo): static
    {
        $this->versionNo = $versionNo;

        return $this;
    }

    public function getLastModifiedAt(): ?DateTimeInterface
    {
        return $this->lastModifiedAt;
    }

    public function setLastModifiedAt(?DateTimeInterface $lastModifiedAt): static
    {
        $this->lastModifiedAt = $lastModifiedAt;

        return $this;
    }

    /**
     * @return Collection<int, PurchaseItem>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(PurchaseItem $item): static
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setPurchase($this);
        }

        return $this;
    }

    public function removeItem(PurchaseItem $item): static
    {
        if ($this->items->removeElement($item)) {
            if ($item->getPurchase() === $this) {
                $item->setPurchase(null);
            }
        }

        return $this;
    }
}
