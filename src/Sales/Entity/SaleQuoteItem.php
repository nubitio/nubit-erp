<?php

declare(strict_types=1);

namespace App\Sales\Entity;

use ApiPlatform\Metadata\ApiProperty;
use App\Catalog\Entity\Product;
use App\Sales\Repository\SaleQuoteItemRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Mirrors SaleItemRecord but stripped of the sync/offline machinery
 * (syncStatus, localUuid, idempotencyKey, deviceId, versionNo) since
 * quotes are never offline-synced. Written only through the parent
 * SaleQuote's writeItems payload — no ApiResource of its own.
 */
#[ORM\Entity(repositoryClass: SaleQuoteItemRepository::class)]
#[ORM\Table(name: 'sale_quote_items')]
#[ORM\Index(name: 'idx_sale_quote_items_quote', columns: ['sale_quote_id'])]
class SaleQuoteItem
{
    #[Groups(['sale_quote:read'])]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: SaleQuote::class, inversedBy: 'items')]
    #[ORM\JoinColumn(name: 'sale_quote_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private SaleQuote $saleQuote;

    #[Groups(['sale_quote:read'])]
    #[ORM\Column(name: 'line_no')]
    private int $lineNo = 0;

    #[Groups(['sale_quote:read'])]
    #[ApiProperty(description: 'sale_quote_item.product.label', readableLink: true)]
    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'producto_id', nullable: true)]
    private ?Product $product = null;

    #[Groups(['sale_quote:read'])]
    #[ORM\Column(name: 'qty', type: Types::DECIMAL, precision: 14, scale: 3)]
    private string $qty = '0.000';

    #[Groups(['sale_quote:read'])]
    #[ORM\Column(name: 'unit_price', type: Types::DECIMAL, precision: 12, scale: 4)]
    private string $unitPrice = '0.0000';

    #[Groups(['sale_quote:read'])]
    #[ORM\Column(name: 'discount_percent', type: Types::DECIMAL, precision: 8, scale: 4, nullable: true)]
    private ?string $discountPercent = null;

    #[Groups(['sale_quote:read'])]
    #[ORM\Column(name: 'gross_amount', type: Types::DECIMAL, precision: 12, scale: 2, options: ['default' => '0.00'])]
    private string $grossAmount = '0.00';

    #[Groups(['sale_quote:read'])]
    #[ORM\Column(name: 'discount_amount', type: Types::DECIMAL, precision: 12, scale: 2, options: [
        'default' => '0.00',
    ])]
    private string $discountAmount = '0.00';

    #[Groups(['sale_quote:read'])]
    #[ORM\Column(name: 'tax_amount', type: Types::DECIMAL, precision: 12, scale: 2, options: ['default' => '0.00'])]
    private string $taxAmount = '0.00';

    #[Groups(['sale_quote:read'])]
    #[ORM\Column(name: 'net_amount', type: Types::DECIMAL, precision: 12, scale: 2, options: ['default' => '0.00'])]
    private string $netAmount = '0.00';

    #[Groups(['sale_quote:read'])]
    #[ORM\Column(name: 'name_snapshot', length: 255)]
    private string $nameSnapshot = '';

    #[Groups(['sale_quote:read'])]
    #[ORM\Column(name: 'sku_snapshot', length: 64, nullable: true)]
    private ?string $skuSnapshot = null;

    #[Groups(['sale_quote:read'])]
    #[ORM\Column(name: 'uom_snapshot', length: 20, nullable: true)]
    private ?string $uomSnapshot = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSaleQuote(): SaleQuote
    {
        return $this->saleQuote;
    }

    public function setSaleQuote(SaleQuote $saleQuote): static
    {
        $this->saleQuote = $saleQuote;

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

    public function getDiscountPercent(): ?string
    {
        return $this->discountPercent;
    }

    public function setDiscountPercent(?string $discountPercent): static
    {
        $this->discountPercent = $discountPercent;

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
}
