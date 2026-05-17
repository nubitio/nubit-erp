<?php

declare(strict_types=1);

namespace App\Sales\Entity;

use App\Catalog\Entity\Product;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use App\Sales\Repository\SaleItemRecordRepository;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    uriTemplate: '/sales/{id}/items.{_format}',
    operations: [new GetCollection()],
    uriVariables: [
        'id' => new Link(
            fromProperty: 'saleItemRecords',
            fromClass: SaleRecord::class,
        ),
    ],
    normalizationContext: ['groups' => ['sale_item_record:read']],
    paginationEnabled: false,
    mercure: true,
)]
#[ORM\Entity(repositoryClass: SaleItemRecordRepository::class)]
#[ORM\Table(name: 'sale_items')]
#[ORM\HasLifecycleCallbacks]
class SaleItemRecord
{
    #[Groups(['sale_item_record:read'])]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: SaleRecord::class, inversedBy: 'saleItemRecords')]
    #[ORM\JoinColumn(name: 'sale_id', referencedColumnName: 'id')]
    private ?SaleRecord $saleRecord = null;

    #[Groups(['sale_item_record:read'])]
    #[ORM\Column(name: 'legacy_movimiento_detalle_id', type: 'integer', nullable: true, unique: true)]
    #[ApiProperty(description: 'sale_item_record.legacy_movimiento_detalle_id.label')]
    private ?int $legacyMovimientoDetalleId = null;

    #[Groups(['sale_item_record:read'])]
    #[ORM\Column(name: 'line_no', type: 'integer')]
    #[ApiProperty(description: 'sale_item_record.line_no.label')]
    private int $lineNo = 0;

    #[Groups(['sale_item_record:read'])]
    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'producto_id', nullable: true)]
    #[ApiProperty(description: 'sale_item_record.product.label')]
    private ?Product $product = null;

    #[Groups(['sale_item_record:read'])]
    #[ORM\Column(name: 'qty', type: Types::DECIMAL, precision: 14, scale: 3)]
    #[ApiProperty(description: 'sale_item_record.qty.label')]
    private string $qty = '0.000';

    #[Groups(['sale_item_record:read'])]
    #[ORM\Column(name: 'unit_price', type: Types::DECIMAL, precision: 12, scale: 4)]
    #[ApiProperty(description: 'sale_item_record.unit_price.label')]
    private string $unitPrice = '0.0000';

    #[Groups(['sale_item_record:read'])]
    #[ORM\Column(name: 'gross_amount', type: Types::DECIMAL, precision: 12, scale: 2)]
    #[ApiProperty(description: 'sale_item_record.gross_amount.label')]
    private string $grossAmount = '0.00';

    #[Groups(['sale_item_record:read'])]
    #[ORM\Column(
        name: 'discount_amount',
        type: Types::DECIMAL,
        precision: 12,
        scale: 2,
        options: ['default' => '0.00'],
    )]
    #[ApiProperty(description: 'sale_item_record.discount_amount.label')]
    private string $discountAmount = '0.00';

    #[Groups(['sale_item_record:read'])]
    #[ORM\Column(name: 'tax_amount', type: Types::DECIMAL, precision: 12, scale: 2, options: ['default' => '0.00'])]
    #[ApiProperty(description: 'sale_item_record.tax_amount.label')]
    private string $taxAmount = '0.00';

    #[Groups(['sale_item_record:read'])]
    #[ORM\Column(name: 'net_amount', type: Types::DECIMAL, precision: 12, scale: 2)]
    #[ApiProperty(description: 'sale_item_record.net_amount.label')]
    private string $netAmount = '0.00';

    #[ORM\Column(name: 'unit_cost_snapshot', type: Types::DECIMAL, precision: 12, scale: 4, nullable: true)]
    private ?string $unitCostSnapshot = null;

    #[Groups(['sale_item_record:read'])]
    #[ORM\Column(name: 'name_snapshot', type: 'string', length: 255)]
    private string $nameSnapshot = '';

    #[Groups(['sale_item_record:read'])]
    #[ORM\Column(name: 'sku_snapshot', type: 'string', length: 64, nullable: true)]
    private ?string $skuSnapshot = null;

    #[ORM\Column(name: 'uom_snapshot', type: 'string', length: 20, nullable: true)]
    private ?string $uomSnapshot = null;

    #[ORM\Column(name: 'margin_amount', type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
    private ?string $marginAmount = null;

    #[ORM\Column(name: 'variant_id', type: 'integer', nullable: true)]
    private ?int $variantId = null;

    #[ORM\Column(name: 'descuento_porcentaje', type: Types::DECIMAL, precision: 8, scale: 4, nullable: true)]
    private ?string $discountPercent = null;

    #[Groups(['sale_item_record:read'])]
    #[ORM\Column(name: 'sync_status', type: 'string', length: 20, options: ['default' => 'SYNCED'])]
    private string $syncStatus = 'SYNCED';

    #[Groups(['sale_item_record:read'])]
    #[ORM\Column(name: 'local_uuid', type: 'string', length: 36, nullable: true)]
    private ?string $localUuid = null;

    #[Groups(['sale_item_record:read'])]
    #[ORM\Column(name: 'idempotency_key', type: 'string', length: 36, nullable: true)]
    private ?string $idempotencyKey = null;

    #[Groups(['sale_item_record:read'])]
    #[ORM\Column(name: 'synced_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTimeInterface $syncedAt = null;

    #[ORM\Column(name: 'device_id', type: 'string', length: 64, nullable: true)]
    private ?string $deviceId = null;

    #[Groups(['sale_item_record:read'])]
    #[ORM\Column(name: 'version_no', type: 'integer', options: ['default' => 1])]
    private int $versionNo = 1;

    #[Groups(['sale_item_record:read'])]
    #[ORM\Column(name: 'last_modified_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTimeInterface $lastModifiedAt = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTimeInterface $createdAt = null;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTimeInterface $updatedAt = null;

    #[ORM\PrePersist]
    public function setTimestampsOnPersist(): void
    {
        $now = new \DateTime();
        if ($this->createdAt === null) {
            $this->createdAt = $now;
        }
        if ($this->lastModifiedAt === null) {
            $this->lastModifiedAt = $now;
        }
        if ($this->syncedAt === null) {
            $this->syncedAt = $now;
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSaleRecord(): ?SaleRecord
    {
        return $this->saleRecord;
    }

    public function setSaleRecord(?SaleRecord $saleRecord): static
    {
        $this->saleRecord = $saleRecord;

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

    public function getQty(): string
    {
        return $this->qty;
    }

    public function setQty(string $qty): static
    {
        $this->qty = $qty;

        return $this;
    }

    public function getUnitPrice(): string
    {
        return $this->unitPrice;
    }

    public function setUnitPrice(string $unitPrice): static
    {
        $this->unitPrice = $unitPrice;

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

    public function getDiscountAmount(): string
    {
        return $this->discountAmount;
    }

    public function setDiscountAmount(string $discountAmount): static
    {
        $this->discountAmount = $discountAmount;

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

    public function getNetAmount(): string
    {
        return $this->netAmount;
    }

    public function setNetAmount(string $netAmount): static
    {
        $this->netAmount = $netAmount;

        return $this;
    }

    public function getUnitCostSnapshot(): ?string
    {
        return $this->unitCostSnapshot;
    }

    public function setUnitCostSnapshot(?string $unitCostSnapshot): static
    {
        $this->unitCostSnapshot = $unitCostSnapshot;

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

    public function getSkuSnapshot(): ?string
    {
        return $this->skuSnapshot;
    }

    public function setSkuSnapshot(?string $skuSnapshot): static
    {
        $this->skuSnapshot = $skuSnapshot;

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

    public function getMarginAmount(): ?string
    {
        return $this->marginAmount;
    }

    public function getDiscountPercent(): ?string
    {
        return $this->discountPercent;
    }

    public function setDiscountPercent(?string $discountPercent): static
    {
        $this->discountPercent = $discountPercent;

        return $this;
    }

    public function getVariantId(): ?int
    {
        return $this->variantId;
    }

    public function setVariantId(?int $variantId): static
    {
        $this->variantId = $variantId;

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
}
