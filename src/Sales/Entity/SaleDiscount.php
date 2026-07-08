<?php

declare(strict_types=1);

namespace App\Sales\Entity;

use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * ORM entity mapping the sale_discounts table.
 */
#[ORM\Entity]
#[ORM\Table(name: 'sale_discounts')]
class SaleDiscount
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: SaleRecord::class, inversedBy: 'saleDiscounts')]
    #[ORM\JoinColumn(name: 'sale_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private SaleRecord $saleRecord;

    /** Nullable FK — null means sale-level discount; non-null means item-level. */
    #[ORM\ManyToOne(targetEntity: SaleItemRecord::class)]
    #[ORM\JoinColumn(name: 'sale_item_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?SaleItemRecord $saleItem = null;

    #[ORM\Column(name: 'discount_type', type: 'string', length: 20)]
    private string $discountType = 'PERCENT';

    #[ORM\Column(name: 'source', type: 'string', length: 20, options: ['default' => 'MANUAL'])]
    private string $source = 'MANUAL';

    #[ORM\Column(name: 'value', type: Types::DECIMAL, precision: 12, scale: 4)]
    private string $value = '0.0000';

    #[ORM\Column(name: 'amount_applied', type: Types::DECIMAL, precision: 12, scale: 2)]
    private string $amountApplied = '0.00';

    #[ORM\Column(name: 'reason_code', type: 'string', length: 50, nullable: true)]
    private ?string $reasonCode = null;

    #[ORM\Column(name: 'reason_text', type: 'string', length: 255, nullable: true)]
    private ?string $reasonText = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTimeInterface $createdAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSaleRecord(): SaleRecord
    {
        return $this->saleRecord;
    }

    public function setSaleRecord(SaleRecord $saleRecord): static
    {
        $this->saleRecord = $saleRecord;

        return $this;
    }

    public function getSaleItem(): ?SaleItemRecord
    {
        return $this->saleItem;
    }

    public function setSaleItem(?SaleItemRecord $saleItem): static
    {
        $this->saleItem = $saleItem;

        return $this;
    }

    public function getDiscountType(): string
    {
        return $this->discountType;
    }

    public function setDiscountType(string $discountType): static
    {
        $this->discountType = $discountType;

        return $this;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function setSource(string $source): static
    {
        $this->source = $source;

        return $this;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function setValue(string $value): static
    {
        $this->value = $value;

        return $this;
    }

    public function getAmountApplied(): string
    {
        return $this->amountApplied;
    }

    public function setAmountApplied(string $amountApplied): static
    {
        $this->amountApplied = $amountApplied;

        return $this;
    }

    public function getReasonCode(): ?string
    {
        return $this->reasonCode;
    }

    public function setReasonCode(?string $reasonCode): static
    {
        $this->reasonCode = $reasonCode;

        return $this;
    }

    public function getReasonText(): ?string
    {
        return $this->reasonText;
    }

    public function setReasonText(?string $reasonText): static
    {
        $this->reasonText = $reasonText;

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
}
