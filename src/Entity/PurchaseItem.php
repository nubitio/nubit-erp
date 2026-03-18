<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use App\Repository\PurchaseItemRepository;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: PurchaseItemRepository::class)]
#[ORM\Table(name: 'goods_receipt_items')]
#[ApiResource(
    uriTemplate: '/purchases/{id}/items.{_format}',
    operations: [new GetCollection()],
    uriVariables: [
        'id' => new Link(fromProperty: 'items', fromClass: Purchase::class),
    ],
    normalizationContext: ['groups' => ['purchase_item:read']],
    paginationEnabled: false,
)]
class PurchaseItem
{
    #[Groups(['purchase_item:read'])]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: 'bigint')]
    #[ApiProperty(description: 'purchase_item.id.label')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Purchase::class, inversedBy: 'items')]
    #[ORM\JoinColumn(name: 'goods_receipt_id', referencedColumnName: 'id', nullable: true)]
    private ?Purchase $purchase = null;

    #[Groups(['purchase_item:read'])]
    #[ORM\Column(name: 'legacy_movimiento_detalle_id', type: 'integer', nullable: true)]
    #[ApiProperty(description: 'purchase_item.legacy_movimiento_detalle_id.label')]
    private ?int $legacyMovimientoDetalleId = null;

    #[Groups(['purchase_item:read', 'purchase:write'])]
    #[ORM\Column(name: 'line_no', type: 'integer')]
    #[ApiProperty(description: 'purchase_item.line_no.label')]
    private int $lineNo = 0;

    #[Groups(['purchase_item:read', 'purchase:write'])]
    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'producto_id', nullable: true)]
    #[ApiProperty(description: 'purchase_item.product.label')]
    private ?Product $product = null;

    #[Groups(['purchase_item:read'])]
    #[ORM\Column(name: 'variant_id', type: 'string', length: 255, nullable: true)]
    #[ApiProperty(description: 'purchase_item.variant_id.label')]
    private ?string $variantId = null;

    #[Groups(['purchase_item:read'])]
    #[ORM\Column(name: 'sku_snapshot', type: 'string', length: 64, nullable: true)]
    #[ApiProperty(description: 'purchase_item.sku_snapshot.label')]
    private ?string $skuSnapshot = null;

    #[Groups(['purchase_item:read'])]
    #[ORM\Column(name: 'name_snapshot', type: 'string', length: 255)]
    #[ApiProperty(description: 'purchase_item.name_snapshot.label')]
    private string $nameSnapshot = '';

    #[Groups(['purchase_item:read'])]
    #[ORM\Column(name: 'uom_snapshot', type: 'string', length: 20, nullable: true)]
    #[ApiProperty(description: 'purchase_item.uom_snapshot.label')]
    private ?string $uomSnapshot = null;

    #[Groups(['purchase_item:read', 'purchase:write'])]
    #[ORM\Column(name: 'qty', type: Types::DECIMAL, precision: 14, scale: 3)]
    #[ApiProperty(description: 'purchase_item.qty.label')]
    private string|int|float $qty = '0.000';

    #[Groups(['purchase_item:read', 'purchase:write'])]
    #[ORM\Column(name: 'unit_cost', type: Types::DECIMAL, precision: 12, scale: 4)]
    #[ApiProperty(description: 'purchase_item.unit_cost.label')]
    private string $unitCost = '0.0000';

    #[Groups(['purchase_item:read', 'purchase:write'])]
    #[ORM\Column(name: 'gross_amount', type: Types::DECIMAL, precision: 12, scale: 2)]
    #[ApiProperty(description: 'purchase_item.gross_amount.label')]
    private string $grossAmount = '0.00';

    #[Groups(['purchase_item:read', 'purchase:write'])]
    #[ORM\Column(name: 'tax_amount', type: Types::DECIMAL, precision: 12, scale: 2, options: ['default' => '0.00'])]
    #[ApiProperty(description: 'purchase_item.tax_amount.label')]
    private string $taxAmount = '0.00';

    #[Groups(['purchase_item:read', 'purchase:write'])]
    #[ORM\Column(name: 'total_amount', type: Types::DECIMAL, precision: 12, scale: 2)]
    #[ApiProperty(description: 'purchase_item.total_amount.label')]
    private string $totalAmount = '0.00';

    #[Groups(['purchase_item:read'])]
    #[ORM\Column(
        name: 'created_at',
        type: Types::DATETIME_MUTABLE,
        nullable: true,
        options: ['default' => 'CURRENT_TIMESTAMP'],
    )]
    #[ApiProperty(description: 'purchase_item.created_at.label')]
    private ?DateTimeInterface $createdAt = null;

    #[Groups(['purchase_item:read'])]
    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    #[ApiProperty(description: 'purchase_item.updated_at.label')]
    private ?DateTimeInterface $updatedAt = null;

    #[Groups(['purchase_item:read'])]
    #[ORM\Column(name: 'local_uuid', type: 'string', length: 36, nullable: true)]
    #[ApiProperty(description: 'purchase_item.local_uuid.label')]
    private ?string $localUuid = null;

    #[Groups(['purchase_item:read'])]
    #[ORM\Column(name: 'idempotency_key', type: 'string', length: 36, nullable: true)]
    #[ApiProperty(description: 'purchase_item.idempotency_key.label')]
    private ?string $idempotencyKey = null;

    #[Groups(['purchase_item:read'])]
    #[ORM\Column(name: 'sync_status', type: 'string', length: 20, options: ['default' => 'SYNCED'])]
    #[ApiProperty(description: 'purchase_item.sync_status.label')]
    private string $syncStatus = 'SYNCED';

    #[Groups(['purchase_item:read'])]
    #[ORM\Column(name: 'synced_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    #[ApiProperty(description: 'purchase_item.synced_at.label')]
    private ?DateTimeInterface $syncedAt = null;

    #[Groups(['purchase_item:read'])]
    #[ORM\Column(name: 'device_id', type: 'string', length: 100, nullable: true)]
    #[ApiProperty(description: 'purchase_item.device_id.label')]
    private ?string $deviceId = null;

    #[Groups(['purchase_item:read'])]
    #[ORM\Column(name: 'version_no', type: 'integer', options: ['default' => 1])]
    #[ApiProperty(description: 'purchase_item.version_no.label')]
    private int $versionNo = 1;

    #[Groups(['purchase_item:read'])]
    #[ORM\Column(
        name: 'last_modified_at',
        type: Types::DATETIME_MUTABLE,
        nullable: true,
        options: ['default' => 'CURRENT_TIMESTAMP'],
    )]
    #[ApiProperty(description: 'purchase_item.last_modified_at.label')]
    private ?DateTimeInterface $lastModifiedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPurchase(): ?Purchase
    {
        return $this->purchase;
    }

    public function setPurchase(?Purchase $purchase): static
    {
        $this->purchase = $purchase;

        return $this;
    }

    public function getLegacyMovimientoDetalleId(): ?int
    {
        return $this->legacyMovimientoDetalleId;
    }

    public function setLegacyMovimientoDetalleId(?int $legacyMovimientoDetalleId): static
    {
        $this->legacyMovimientoDetalleId = $legacyMovimientoDetalleId;

        return $this;
    }

    public function getLineNo(): int
    {
        return $this->lineNo;
    }

    public function setLineNo(int $lineNo): static
    {
        $this->lineNo = $lineNo;

        return $this;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): static
    {
        $this->product = $product;

        return $this;
    }

    public function getVariantId(): ?string
    {
        return $this->variantId;
    }

    public function setVariantId(?string $variantId): static
    {
        $this->variantId = $variantId;

        return $this;
    }

    public function getSkuSnapshot(): ?string
    {
        return $this->skuSnapshot;
    }

    public function setSkuSnapshot(?string $skuSnapshot): static
    {
        $this->skuSnapshot = $skuSnapshot;

        return $this;
    }

    public function getNameSnapshot(): string
    {
        return $this->nameSnapshot;
    }

    public function setNameSnapshot(string $nameSnapshot): static
    {
        $this->nameSnapshot = $nameSnapshot;

        return $this;
    }

    public function getUomSnapshot(): ?string
    {
        return $this->uomSnapshot;
    }

    public function setUomSnapshot(?string $uomSnapshot): static
    {
        $this->uomSnapshot = $uomSnapshot;

        return $this;
    }

    public function getQty(): string
    {
        return (string) $this->qty;
    }

    public function setQty(int|float|string $qty): static
    {
        $this->qty = (string) $qty;

        return $this;
    }

    public function getUnitCost(): string
    {
        return $this->unitCost;
    }

    public function setUnitCost(string $unitCost): static
    {
        $this->unitCost = $unitCost;

        return $this;
    }

    public function getGrossAmount(): string
    {
        return $this->grossAmount;
    }

    public function setGrossAmount(string $grossAmount): static
    {
        $this->grossAmount = $grossAmount;

        return $this;
    }

    public function getTaxAmount(): string
    {
        return $this->taxAmount;
    }

    public function setTaxAmount(string $taxAmount): static
    {
        $this->taxAmount = $taxAmount;

        return $this;
    }

    public function getTotalAmount(): string
    {
        return $this->totalAmount;
    }

    public function setTotalAmount(string $totalAmount): static
    {
        $this->totalAmount = $totalAmount;

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
}
