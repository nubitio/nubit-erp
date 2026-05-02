<?php

declare(strict_types=1);

namespace App\Inventory\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Efact\Doctrine\Filter\DevExtremeFilter;
use App\Identity\Entity\User;
use App\Inventory\Core\Enum\StockEntryType;
use App\Inventory\Repository\StockEntryRepository;
use App\Inventory\State\StockEntryProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Unified manual stock entry document (standard ERP pattern).
 *
 * Covers four operations via the `type` field:
 *   RECEIPT    — manual inbound (production, donation, customer return without NC)
 *   ISSUE      — manual outbound (sample, internal use, documented waste)
 *   TRANSFER   — inter-warehouse stock movement (requires targetWarehouse)
 *   ADJUSTMENT — stock correction to match physical count (requires reason)
 *
 * All types write to the stock_movements ledger through InventoryService.
 * Documents are immutable after creation.
 */
#[ORM\Entity(repositoryClass: StockEntryRepository::class)]
#[ORM\Table(name: 'stock_entries')]
#[ApiResource(
    shortName: 'StockEntry',
    operations: [
        new GetCollection(),
        new Get(),
        new Get(
            uriTemplate: '/stock-entries/{id}/items',
            normalizationContext: ['groups' => ['stock_entry_items:read']],
        ),
        new Post(processor: StockEntryProcessor::class),
    ],
    normalizationContext:   ['groups' => ['stock_entry:read']],
    denormalizationContext: ['groups' => ['stock_entry:write']],
    filters: [DevExtremeFilter::class],
    security: "is_granted('ROLE_ADMIN')",
    mercure: true,
)]
class StockEntry
{
    /** @see StockEntryType for valid values */
    public const array ADJUSTMENT_REASONS = [
        'INITIAL_STOCK',
        'COUNT_CORRECTION',
        'LOSS',
        'DAMAGE',
        'THEFT',
        'EXPIRY',
        'OTHER',
    ];

    #[Groups(['stock_entry:read'])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\Column(name: 'id', type: 'string', length: 36)]
    private ?string $id = null;

    #[Groups(['stock_entry:read', 'stock_entry:write'])]
    #[ApiProperty(description: 'stock_entry.type.label')]
    #[ORM\Column(name: 'type', length: 20, enumType: StockEntryType::class)]
    #[Assert\NotNull]
    /** @phpstan-ignore-next-line doctrine.columnType */
    private ?StockEntryType $type = null;

    /** Source warehouse (always required). */
    #[Groups(['stock_entry:read', 'stock_entry:write'])]
    #[ApiProperty(description: 'stock_entry.warehouse.label')]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'warehouse_id', referencedColumnName: 'almacen_id', nullable: false)]
    #[Assert\NotNull]
    /** @phpstan-ignore-next-line doctrine.associationType */
    private ?Warehouse $warehouse = null;

    /** Destination warehouse — required only for TRANSFER. */
    #[Groups(['stock_entry:read', 'stock_entry:write'])]
    #[ApiProperty(description: 'stock_entry.target_warehouse.label')]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'target_warehouse_id', referencedColumnName: 'almacen_id', nullable: true)]
    private ?Warehouse $targetWarehouse = null;

    /** Optional operation category — applicable to RECEIPT and ISSUE. */
    #[Groups(['stock_entry:read', 'stock_entry:write'])]
    #[ApiProperty(description: 'stock_entry.operation.label')]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'operation_id', referencedColumnName: 'operacion_id', nullable: true)]
    private ?LogisticOperation $operation = null;

    /** Reason code — required for ADJUSTMENT, null otherwise. */
    #[Groups(['stock_entry:read', 'stock_entry:write'])]
    #[ApiProperty(description: 'stock_entry.reason.label')]
    #[ORM\Column(name: 'reason', length: 30, nullable: true)]
    #[Assert\Choice(choices: self::ADJUSTMENT_REASONS, message: 'Invalid reason code.')]
    private ?string $reason = null;

    #[Groups(['stock_entry:read', 'stock_entry:write'])]
    #[ApiProperty(description: 'stock_entry.note.label')]
    #[ORM\Column(name: 'note', type: Types::TEXT, nullable: true)]
    private ?string $note = null;

    #[Groups(['stock_entry:read'])]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'created_by', referencedColumnName: 'usuario_id', nullable: false)]
    /** @phpstan-ignore-next-line doctrine.associationType */
    private ?User $createdBy = null;

    #[Groups(['stock_entry:read'])]
    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, StockEntryItem> */
    #[Groups(['stock_entry:read', 'stock_entry:write', 'stock_entry_items:read'])]
    #[ORM\OneToMany(
        targetEntity: StockEntryItem::class,
        mappedBy: 'entry',
        cascade: ['persist', 'remove'],
        orphanRemoval: true,
    )]
    #[Assert\Count(min: 1, minMessage: 'Al menos una línea de producto es requerida.')]
    private Collection $items;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->items     = new ArrayCollection();
    }

    // ─── Accessors ────────────────────────────────────────────────────────────

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getType(): ?StockEntryType
    {
        return $this->type;
    }
    public function setType(?StockEntryType $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getTypeLabel(): ?string
    {
        return $this->type?->label();
    }

    public function getWarehouse(): ?Warehouse
    {
        return $this->warehouse;
    }
    public function setWarehouse(?Warehouse $w): static
    {
        $this->warehouse = $w;
        return $this;
    }

    public function getTargetWarehouse(): ?Warehouse
    {
        return $this->targetWarehouse;
    }
    public function setTargetWarehouse(?Warehouse $w): static
    {
        $this->targetWarehouse = $w;
        return $this;
    }

    public function getOperation(): ?LogisticOperation
    {
        return $this->operation;
    }
    public function setOperation(?LogisticOperation $op): static
    {
        $this->operation = $op;
        return $this;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }
    public function setReason(?string $reason): static
    {
        $this->reason = $reason;
        return $this;
    }

    public function getReasonLabel(): ?string
    {
        return match ($this->reason) {
            'INITIAL_STOCK'    => 'Carga inicial',
            'COUNT_CORRECTION' => 'Corrección por conteo',
            'LOSS'             => 'Pérdida / merma',
            'DAMAGE'           => 'Producto dañado',
            'THEFT'            => 'Robo / sustracción',
            'EXPIRY'           => 'Producto vencido',
            'OTHER'            => 'Otro',
            default            => $this->reason,
        };
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

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }
    public function setCreatedBy(?User $user): static
    {
        $this->createdBy = $user;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return Collection<int, StockEntryItem> */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(StockEntryItem $item): static
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setEntry($this);
        }
        return $this;
    }
}
