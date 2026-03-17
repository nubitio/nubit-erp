<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use App\Repository\SaleItemRecordRepository;
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
)]
#[ORM\Entity(repositoryClass: SaleItemRecordRepository::class)]
#[ORM\Table(name: 'sale_items')]
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

    #[ORM\Column(name: 'name_snapshot', type: 'string', length: 255)]
    private string $nameSnapshot = '';

    #[ORM\Column(name: 'sku_snapshot', type: 'string', length: 64, nullable: true)]
    private ?string $skuSnapshot = null;

    #[ORM\Column(name: 'uom_snapshot', type: 'string', length: 20, nullable: true)]
    private ?string $uomSnapshot = null;

    #[ORM\Column(name: 'margin_amount', type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
    private ?string $marginAmount = null;

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
}
