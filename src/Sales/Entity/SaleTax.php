<?php

declare(strict_types=1);

namespace App\Sales\Entity;

use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * ORM entity mapping the sale_taxes table.
 */
#[ORM\Entity]
#[ORM\Table(name: 'sale_taxes')]
class SaleTax
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: SaleRecord::class, inversedBy: 'saleTaxes')]
    #[ORM\JoinColumn(name: 'sale_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private SaleRecord $saleRecord;

    /** Nullable FK — null means the tax applies at sale level; non-null means item-level. */
    #[ORM\ManyToOne(targetEntity: SaleItemRecord::class)]
    #[ORM\JoinColumn(name: 'sale_item_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?SaleItemRecord $saleItem = null;

    #[ORM\Column(name: 'tax_code', type: 'string', length: 20)]
    private string $taxCode = 'IGV';

    #[ORM\Column(name: 'tax_rate', type: Types::DECIMAL, precision: 8, scale: 4)]
    private string $taxRate = '0.0000';

    #[ORM\Column(name: 'taxable_base', type: Types::DECIMAL, precision: 12, scale: 2)]
    private string $taxableBase = '0.00';

    #[ORM\Column(name: 'tax_amount', type: Types::DECIMAL, precision: 12, scale: 2)]
    private string $taxAmount = '0.00';

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

    public function getTaxCode(): string
    {
        return $this->taxCode;
    }

    public function setTaxCode(string $taxCode): static
    {
        $this->taxCode = $taxCode;

        return $this;
    }

    public function getTaxRate(): string
    {
        return $this->taxRate;
    }

    public function setTaxRate(string $taxRate): static
    {
        $this->taxRate = $taxRate;

        return $this;
    }

    public function getTaxableBase(): string
    {
        return $this->taxableBase;
    }

    public function setTaxableBase(string $taxableBase): static
    {
        $this->taxableBase = $taxableBase;

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
