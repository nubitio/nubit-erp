<?php

declare(strict_types=1);

namespace App\Billing\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use App\Billing\Repository\TaxDocumentLineRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    uriTemplate: '/tax-documents/{id}/lines.{_format}',
    operations: [new GetCollection()],
    uriVariables: [
        'id' => new Link(
            fromProperty: 'lines',
            fromClass: TaxDocument::class,
        ),
    ],
    normalizationContext: ['groups' => ['tax_document_line:read']],
    paginationEnabled: false,
    mercure: true,
)]
#[ORM\Entity(repositoryClass: TaxDocumentLineRepository::class)]
#[ORM\Table(name: 'tax_document_line')]
#[ORM\UniqueConstraint(name: 'UNIQ_TAX_DOCUMENT_LINE', columns: ['tax_document_id', 'line_number'])]
#[ORM\Index(name: 'IDX_TAX_DOCUMENT_LINE_PRODUCT', columns: ['product_id'])]
class TaxDocumentLine
{
    #[Groups(['tax_document_line:read', 'creditnote:write'])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: TaxDocument::class, inversedBy: 'lines')]
    #[ORM\JoinColumn(
        name: 'tax_document_id',
        referencedColumnName: 'tax_document_id',
        nullable: false,
        onDelete: 'CASCADE'
    )]
    private TaxDocument $taxDocument;

    #[Groups(['tax_document_line:read', 'creditnote:write'])]
    #[ApiProperty(description: 'tax_document_line.line_number.label')]
    #[ORM\Column(name: 'line_number', type: Types::INTEGER)]
    private int $lineNumber;

    #[Groups(['tax_document_line:read', 'creditnote:write'])]
    #[ApiProperty(description: 'tax_document_line.product_id.label')]
    #[ORM\Column(name: 'product_id', type: Types::STRING, length: 255, nullable: true)]
    private ?string $productId = null;

    #[Groups(['tax_document_line:read', 'creditnote:write'])]
    #[ApiProperty(description: 'tax_document_line.product_code.label')]
    #[ORM\Column(name: 'product_code', type: Types::STRING, length: 255, nullable: true)]
    private ?string $productCode = null;

    #[Groups(['tax_document_line:read', 'creditnote:write'])]
    #[ApiProperty(description: 'tax_document_line.product_name.label')]
    #[ORM\Column(name: 'product_name', type: Types::STRING, length: 255)]
    private string $productName;

    #[Groups(['tax_document_line:read', 'creditnote:write'])]
    #[ApiProperty(description: 'tax_document_line.product_uom.label')]
    #[ORM\Column(name: 'product_uom', type: Types::STRING, length: 20, nullable: true)]
    private ?string $productUom = null;

    #[Groups(['tax_document_line:read', 'creditnote:write'])]
    #[ApiProperty(description: 'tax_document_line.quantity.label')]
    #[ORM\Column(name: 'quantity', type: Types::DECIMAL, precision: 10, scale: 6, options: ['default' => 0])]
    private string $quantity = '0.000000';

    #[Groups(['tax_document_line:read', 'creditnote:write'])]
    #[ApiProperty(description: 'tax_document_line.unit_price.label')]
    #[ORM\Column(name: 'unit_price', type: Types::DECIMAL, precision: 12, scale: 6, options: ['default' => 0])]
    private string $unitPrice = '0.000000';

    #[Groups(['tax_document_line:read'])]
    #[ApiProperty(description: 'tax_document_line.unit_value.label')]
    #[ORM\Column(name: 'unit_value', type: Types::DECIMAL, precision: 12, scale: 6, nullable: true)]
    private ?string $unitValue = null;

    #[Groups(['tax_document_line:read'])]
    #[ApiProperty(description: 'tax_document_line.discount_amount.label')]
    #[ORM\Column(name: 'discount_amount', type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $discountAmount = null;

    #[Groups(['tax_document_line:read'])]
    #[ApiProperty(description: 'tax_document_line.taxed_amount.label')]
    #[ORM\Column(name: 'taxed_amount', type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $taxedAmount = null;

    #[Groups(['tax_document_line:read'])]
    #[ApiProperty(description: 'tax_document_line.tax_amount.label')]
    #[ORM\Column(name: 'tax_amount', type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $taxAmount = null;

    #[Groups(['tax_document_line:read'])]
    #[ApiProperty(description: 'tax_document_line.tax_percentage.label')]
    #[ORM\Column(name: 'tax_percentage', type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $taxPercentage = null;

    #[Groups(['tax_document_line:read'])]
    #[ApiProperty(description: 'tax_document_line.tax_type.label')]
    #[ORM\Column(name: 'tax_type', type: Types::STRING, length: 4, nullable: true)]
    private ?string $taxType = null;

    #[Groups(['tax_document_line:read'])]
    #[ApiProperty(description: 'tax_document_line.total.label')]
    #[ORM\Column(name: 'total', type: Types::DECIMAL, precision: 10, scale: 2, options: ['default' => 0])]
    private string $total = '0.00';

    #[Groups(['tax_document_line:read'])]
    #[ApiProperty(description: 'tax_document_line.created_at.label')]
    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE, options: ['default' => 'CURRENT_TIMESTAMP'])]
    private DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTaxDocument(): TaxDocument
    {
        return $this->taxDocument;
    }

    public function setTaxDocument(TaxDocument $taxDocument): static
    {
        $this->taxDocument = $taxDocument;

        return $this;
    }

    public function getLineNumber(): int
    {
        return $this->lineNumber;
    }

    public function setLineNumber(int $lineNumber): static
    {
        $this->lineNumber = $lineNumber;

        return $this;
    }

    public function getProductId(): ?string
    {
        return $this->productId;
    }

    public function setProductId(?string $productId): static
    {
        $this->productId = $productId;

        return $this;
    }

    public function getProductCode(): ?string
    {
        return $this->productCode;
    }

    public function setProductCode(?string $productCode): static
    {
        $this->productCode = $productCode;

        return $this;
    }

    public function getProductName(): string
    {
        return $this->productName;
    }

    public function setProductName(string $productName): static
    {
        $this->productName = $productName;

        return $this;
    }

    public function getProductUom(): ?string
    {
        return $this->productUom;
    }

    public function setProductUom(?string $productUom): static
    {
        $this->productUom = $productUom;

        return $this;
    }

    public function getQuantity(): string
    {
        return $this->quantity;
    }

    public function setQuantity(string $quantity): static
    {
        $this->quantity = $quantity;

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

    public function getUnitValue(): ?string
    {
        return $this->unitValue;
    }

    public function setUnitValue(?string $unitValue): static
    {
        $this->unitValue = $unitValue;

        return $this;
    }

    public function getDiscountAmount(): ?string
    {
        return $this->discountAmount;
    }

    public function setDiscountAmount(?string $discountAmount): static
    {
        $this->discountAmount = $discountAmount;

        return $this;
    }

    public function getTaxedAmount(): ?string
    {
        return $this->taxedAmount;
    }

    public function setTaxedAmount(?string $taxedAmount): static
    {
        $this->taxedAmount = $taxedAmount;

        return $this;
    }

    public function getTaxAmount(): ?string
    {
        return $this->taxAmount;
    }

    public function setTaxAmount(?string $taxAmount): static
    {
        $this->taxAmount = $taxAmount;

        return $this;
    }

    public function getTaxPercentage(): ?string
    {
        return $this->taxPercentage;
    }

    public function setTaxPercentage(?string $taxPercentage): static
    {
        $this->taxPercentage = $taxPercentage;

        return $this;
    }

    public function getTaxType(): ?string
    {
        return $this->taxType;
    }

    public function setTaxType(?string $taxType): static
    {
        $this->taxType = $taxType;

        return $this;
    }

    public function getTotal(): string
    {
        return $this->total;
    }

    public function setTotal(string $total): static
    {
        $this->total = $total;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}
