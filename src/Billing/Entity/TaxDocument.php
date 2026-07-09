<?php

declare(strict_types=1);

namespace App\Billing\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use App\Billing\Controller\Api\ConsultInvoice;
use App\Billing\Controller\Api\SendInvoiceEmail;
use App\Billing\Controller\Api\SendTaxDocument;
use App\Billing\Controller\Api\ValidateInvoice;
use App\Billing\Receipt\ReceiptPayload;
use App\Billing\Repository\TaxDocumentRepository;
use App\Billing\State\TaxDocumentProcessor;
use App\Billing\State\TaxDocumentProvider;
use App\Contacts\Entity\Customer;
use App\Organization\Entity\Branch;
use App\Sales\Entity\SaleRecord;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Nubit\ApiPlatform\Doctrine\Filter\DataGridFilter;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: TaxDocumentRepository::class)]
#[ORM\Table(name: 'tax_document')]
#[ORM\UniqueConstraint(name: 'uq_tax_document_number_branch', columns: [
    'document_type',
    'branch_id',
    'series',
    'number',
])]
#[ORM\Index(name: 'IDX_TAX_DOCUMENT_BRANCH', columns: ['branch_id'])]
#[ORM\Index(name: 'IDX_TAX_DOCUMENT_CUSTOMER', columns: ['customer_id'])]
#[ORM\Index(name: 'IDX_TAX_DOCUMENT_SALE_ID', columns: ['sale_id'])]
#[ORM\Index(name: 'idx_tax_document_sunat_status', columns: ['sunat_status'])]
#[ApiResource(
    operations: [
        new GetCollection(provider: TaxDocumentProvider::class),
        new GetCollection(
            uriTemplate: '/sales/{saleId}/tax-documents',
            uriVariables: [
                'saleId' => new Link(fromClass: \App\Sales\Entity\SaleRecord::class, toProperty: 'saleRecord'),
            ],
            provider: TaxDocumentProvider::class,
            normalizationContext: ['groups' => ['invoice:read']],
        ),
        new Post(processor: TaxDocumentProcessor::class),
        new Get(uriVariables: ['id' => new Link(fromClass: TaxDocument::class, identifiers: [
            'id',
        ])], provider: TaxDocumentProvider::class),
        new Get(
            uriTemplate: '/tax-documents/{id}/consult',
            uriVariables: ['id' => new Link(fromClass: TaxDocument::class, identifiers: ['id'])],
            controller: ConsultInvoice::class,
            read: false,
            deserialize: false,
        ),
        new Post(
            uriTemplate: '/tax-documents/{id}/validate',
            controller: ValidateInvoice::class,
            read: false,
            deserialize: false,
        ),
        new Post(
            uriTemplate: '/tax-documents/{id}/send',
            controller: SendTaxDocument::class,
            read: false,
            deserialize: false,
        ),
        new Post(
            uriTemplate: '/tax-documents/{id}/email',
            controller: SendInvoiceEmail::class,
            read: false,
            deserialize: false,
        ),
    ],
    normalizationContext: ['groups' => ['invoice:read', 'creditnote:read']],
    denormalizationContext: ['groups' => ['invoice:write', 'creditnote:write']],
    filters: [DataGridFilter::class],
    mercure: true,
)]
class TaxDocument
{
    #[ORM\Id]
    #[ORM\Column(name: 'tax_document_id', type: Types::STRING, length: 36)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[Groups(['invoice:read', 'creditnote:read'])]
    private ?string $id = null;

    /**
     * V2 relation to the sales table. Populated when sale_id column is set.
     */
    #[ORM\ManyToOne(targetEntity: SaleRecord::class)]
    #[ORM\JoinColumn(name: 'sale_id', referencedColumnName: 'id', nullable: true)]
    private ?SaleRecord $saleRecord = null;

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(name: 'related_tax_document_id', referencedColumnName: 'tax_document_id', nullable: true)]
    #[Groups(['creditnote:write'])]
    private ?self $relatedTaxDocument = null;

    #[ORM\ManyToOne(targetEntity: Branch::class)]
    #[ORM\JoinColumn(name: 'branch_id', referencedColumnName: 'local_id', nullable: true)]
    private ?Branch $branch = null;

    #[ORM\ManyToOne(targetEntity: Customer::class)]
    #[ORM\JoinColumn(name: 'customer_id', referencedColumnName: 'id', nullable: true)]
    #[Groups(['invoice:read'])]
    #[ApiProperty(description: 'tax_document.customer.label')]
    private ?Customer $customer = null;

    #[ORM\Column(name: 'document_type', type: Types::STRING, length: 10)]
    #[Groups(['creditnote:read'])]
    #[ApiProperty(description: 'tax_document.document_type.label')]
    private string $documentType;

    #[ORM\Column(name: 'issue_date', type: Types::DATE_IMMUTABLE)]
    #[Groups(['creditnote:read', 'creditnote:write'])]
    #[ApiProperty(description: 'tax_document.issue_date.label')]
    private \DateTimeImmutable $issueDate;

    #[ORM\Column(name: 'series', type: Types::STRING, length: 10)]
    #[Groups(['invoice:read', 'creditnote:read'])]
    #[ApiProperty(description: 'tax_document.series.label')]
    private string $series;

    #[ORM\Column(name: 'number', type: Types::STRING, length: 20)]
    #[Groups(['invoice:read', 'creditnote:read'])]
    #[ApiProperty(description: 'tax_document.number.label')]
    private string $number;

    #[ORM\Column(name: 'payment_method', type: Types::INTEGER, nullable: true)]
    private ?int $paymentMethod = null;

    #[Groups(['invoice:read', 'creditnote:read'])]
    #[ORM\Column(name: 'currency', type: Types::STRING, length: 3, nullable: true)]
    #[ApiProperty(description: 'tax_document.currency.label')]
    private ?string $currency = null;

    #[ORM\Column(name: 'exchange_rate', type: Types::DECIMAL, precision: 14, scale: 6, nullable: true)]
    private ?string $exchangeRate = null;

    #[ORM\Column(name: 'subtotal', type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $subtotal = null;

    #[ORM\Column(name: 'taxable_amount', type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $taxableAmount = null;

    #[ORM\Column(name: 'igv_amount', type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $igvAmount = null;

    #[ORM\Column(name: 'rc_amount', type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $rcAmount = null;

    #[ORM\Column(name: 'discount_percent', type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $discountPercent = null;

    #[ORM\Column(name: 'discount_amount', type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $discountAmount = null;

    #[ORM\Column(name: 'operation_type', type: Types::STRING, length: 4, nullable: true)]
    private ?string $operationType = null;

    #[ORM\Column(name: 'detraction_code', type: Types::STRING, length: 10, nullable: true)]
    private ?string $detractionCode = null;

    #[ORM\Column(name: 'detraction_percent', type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $detractionPercent = null;

    #[ORM\Column(name: 'detraction_amount', type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $detractionAmount = null;

    #[ORM\Column(name: 'detraction_payment_method', type: Types::STRING, length: 10, nullable: true)]
    private ?string $detractionPaymentMethod = null;

    #[ORM\Column(name: 'detraction_value_ref', type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $detractionValueRef = null;

    #[ORM\Column(name: 'affected_document', type: Types::STRING, length: 50, nullable: true)]
    #[Groups(['creditnote:read'])]
    #[ApiProperty(description: 'tax_document.affected_document.label')]
    private ?string $affectedDocument = null;

    #[ORM\Column(name: 'reason_code', type: Types::STRING, length: 10, nullable: true)]
    #[Groups(['creditnote:read', 'creditnote:write'])]
    #[ApiProperty(description: 'tax_document.reason_code.label')]
    private ?string $reasonCode = null;

    #[ORM\Column(name: 'reason_description', type: Types::STRING, length: 255, nullable: true)]
    #[Groups(['creditnote:read', 'creditnote:write'])]
    #[ApiProperty(description: 'tax_document.reason_description.label')]
    private ?string $reasonDescription = null;

    #[ORM\Column(name: 'total', type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Groups(['invoice:read', 'creditnote:read'])]
    #[ApiProperty(description: 'tax_document.total.label')]
    private string $total = '0.00';

    #[ORM\Column(name: 'void_ticket', type: Types::STRING, length: 255, nullable: true)]
    #[Groups(['invoice:read'])]
    #[ApiProperty(description: 'tax_document.void_ticket.label')]
    private ?string $voidTicket = null;

    #[ORM\Column(name: 'void_correlation', type: Types::STRING, length: 255, nullable: true)]
    #[Groups(['invoice:read'])]
    #[ApiProperty(description: 'tax_document.void_correlation.label')]
    private ?string $voidCorrelation = null;

    #[ORM\Column(name: 'sunat_status', type: Types::STRING, length: 50, nullable: true)]
    #[Groups(['invoice:read', 'creditnote:read'])]
    #[ApiProperty(description: 'tax_document.sunat_status.label')]
    private ?string $sunatStatus = null;

    #[ORM\Column(name: 'sunat_message', type: Types::TEXT, nullable: true)]
    #[Groups(['invoice:read', 'creditnote:read'])]
    #[ApiProperty(description: 'tax_document.sunat_message.label')]
    private ?string $sunatMessage = null;

    #[ORM\Column(name: 'digest', type: Types::TEXT, nullable: true)]
    #[Groups(['invoice:read', 'creditnote:read'])]
    #[ApiProperty(description: 'tax_document.digest.label')]
    private ?string $digest = null;

    #[ORM\Column(name: 'cdr_path', type: Types::STRING, length: 255, nullable: true)]
    #[Groups(['invoice:read', 'creditnote:read'])]
    #[ApiProperty(description: 'tax_document.cdr_path.label')]
    private ?string $cdrPath = null;

    /**
     * @var Collection<int, TaxDocumentLine>
     */
    #[ORM\OneToMany(
        targetEntity: TaxDocumentLine::class,
        mappedBy: 'taxDocument',
        cascade: ['persist', 'remove'],
        orphanRemoval: true,
    )]
    #[Groups(['creditnote:write'])]
    private Collection $lines;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE, options: ['default' => 'CURRENT_TIMESTAMP'])]
    private DateTimeInterface $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_MUTABLE)]
    private DateTimeInterface $updatedAt;

    public function __construct()
    {
        $this->lines = new ArrayCollection();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getSaleRecord(): ?SaleRecord
    {
        return $this->saleRecord;
    }

    public function setSaleRecord(?SaleRecord $saleRecord): self
    {
        $this->saleRecord = $saleRecord;

        return $this;
    }

    /**
     * Returns the canonical sale ID. Uses the V2 saleRecord (sales table).
     */
    public function getCanonicalSaleId(): ?string
    {
        return $this->saleRecord?->getId();
    }

    public function getRelatedTaxDocument(): ?self
    {
        return $this->relatedTaxDocument;
    }

    public function setRelatedTaxDocument(?self $relatedTaxDocument): self
    {
        $this->relatedTaxDocument = $relatedTaxDocument;

        return $this;
    }

    /**
     * @return Collection<int, TaxDocumentLine>
     */
    public function getLines(): Collection
    {
        return $this->lines;
    }

    public function addLine(TaxDocumentLine $line): self
    {
        if (!$this->lines->contains($line)) {
            $this->lines->add($line);
            $line->setTaxDocument($this);
        }

        return $this;
    }

    public function removeLine(TaxDocumentLine $line): self
    {
        $this->lines->removeElement($line);

        return $this;
    }

    public function getBranch(): ?Branch
    {
        return $this->branch;
    }

    public function setBranch(?Branch $branch): self
    {
        $this->branch = $branch;

        return $this;
    }

    public function getCustomer(): ?Customer
    {
        return $this->customer;
    }

    public function setCustomer(?Customer $customer): self
    {
        $this->customer = $customer;

        return $this;
    }

    public function getDocumentType(): string
    {
        return $this->documentType;
    }

    public function setDocumentType(string $documentType): self
    {
        $this->documentType = $documentType;

        return $this;
    }

    public function getIssueDate(): \DateTimeImmutable
    {
        return $this->issueDate;
    }

    public function setIssueDate(\DateTimeImmutable $issueDate): self
    {
        $this->issueDate = $issueDate;

        return $this;
    }

    public function getSeries(): string
    {
        return $this->series;
    }

    public function setSeries(string $series): self
    {
        $this->series = $series;

        return $this;
    }

    public function getNumber(): string
    {
        return $this->number;
    }

    public function setNumber(string $number): self
    {
        $this->number = $number;

        return $this;
    }

    public function getPaymentMethod(): ?int
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(?int $paymentMethod): self
    {
        $this->paymentMethod = $paymentMethod;

        return $this;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function setCurrency(?string $currency): self
    {
        $this->currency = $currency;

        return $this;
    }

    public function getExchangeRate(): ?string
    {
        return $this->exchangeRate;
    }

    public function setExchangeRate(?string $exchangeRate): self
    {
        $this->exchangeRate = $exchangeRate;

        return $this;
    }

    public function getSubtotal(): ?string
    {
        return $this->subtotal;
    }

    public function setSubtotal(?string $subtotal): self
    {
        $this->subtotal = $subtotal;

        return $this;
    }

    public function getTaxableAmount(): ?string
    {
        return $this->taxableAmount;
    }

    public function setTaxableAmount(?string $taxableAmount): self
    {
        $this->taxableAmount = $taxableAmount;

        return $this;
    }

    public function getIgvAmount(): ?string
    {
        return $this->igvAmount;
    }

    public function setIgvAmount(?string $igvAmount): self
    {
        $this->igvAmount = $igvAmount;

        return $this;
    }

    public function getRcAmount(): ?string
    {
        return $this->rcAmount;
    }

    public function setRcAmount(?string $rcAmount): self
    {
        $this->rcAmount = $rcAmount;

        return $this;
    }

    public function getDiscountPercent(): ?string
    {
        return $this->discountPercent;
    }

    public function setDiscountPercent(?string $discountPercent): self
    {
        $this->discountPercent = $discountPercent;

        return $this;
    }

    public function getDiscountAmount(): ?string
    {
        return $this->discountAmount;
    }

    public function setDiscountAmount(?string $discountAmount): self
    {
        $this->discountAmount = $discountAmount;

        return $this;
    }

    public function getOperationType(): ?string
    {
        return $this->operationType;
    }

    public function setOperationType(?string $operationType): self
    {
        $this->operationType = $operationType;

        return $this;
    }

    public function getDetractionCode(): ?string
    {
        return $this->detractionCode;
    }

    public function setDetractionCode(?string $detractionCode): self
    {
        $this->detractionCode = $detractionCode;

        return $this;
    }

    public function getDetractionPercent(): ?string
    {
        return $this->detractionPercent;
    }

    public function setDetractionPercent(?string $detractionPercent): self
    {
        $this->detractionPercent = $detractionPercent;

        return $this;
    }

    public function getDetractionAmount(): ?string
    {
        return $this->detractionAmount;
    }

    public function setDetractionAmount(?string $detractionAmount): self
    {
        $this->detractionAmount = $detractionAmount;

        return $this;
    }

    public function getDetractionPaymentMethod(): ?string
    {
        return $this->detractionPaymentMethod;
    }

    public function setDetractionPaymentMethod(?string $detractionPaymentMethod): self
    {
        $this->detractionPaymentMethod = $detractionPaymentMethod;

        return $this;
    }

    public function getDetractionValueRef(): ?string
    {
        return $this->detractionValueRef;
    }

    public function setDetractionValueRef(?string $detractionValueRef): self
    {
        $this->detractionValueRef = $detractionValueRef;

        return $this;
    }

    public function getAffectedDocument(): ?string
    {
        return $this->affectedDocument;
    }

    public function setAffectedDocument(?string $affectedDocument): self
    {
        $this->affectedDocument = $affectedDocument;

        return $this;
    }

    public function getReasonCode(): ?string
    {
        return $this->reasonCode;
    }

    public function setReasonCode(?string $reasonCode): self
    {
        $this->reasonCode = $reasonCode;

        return $this;
    }

    public function getReasonDescription(): ?string
    {
        return $this->reasonDescription;
    }

    public function setReasonDescription(?string $reasonDescription): self
    {
        $this->reasonDescription = $reasonDescription;

        return $this;
    }

    public function getTotal(): string
    {
        return $this->total;
    }

    public function setTotal(string $total): self
    {
        $this->total = $total;

        return $this;
    }

    public function getVoidTicket(): ?string
    {
        return $this->voidTicket;
    }

    public function setVoidTicket(?string $voidTicket): self
    {
        $this->voidTicket = $voidTicket;

        return $this;
    }

    public function getVoidCorrelation(): ?string
    {
        return $this->voidCorrelation;
    }

    public function setVoidCorrelation(?string $voidCorrelation): self
    {
        $this->voidCorrelation = $voidCorrelation;

        return $this;
    }

    public function getSunatStatus(): ?string
    {
        return $this->sunatStatus;
    }

    public function setSunatStatus(?string $sunatStatus): self
    {
        $this->sunatStatus = $sunatStatus;

        return $this;
    }

    public function getSunatMessage(): ?string
    {
        return $this->sunatMessage;
    }

    public function setSunatMessage(?string $sunatMessage): self
    {
        $this->sunatMessage = $sunatMessage;

        return $this;
    }

    public function getDigest(): ?string
    {
        return $this->digest;
    }

    public function setDigest(?string $digest): self
    {
        $this->digest = $digest;

        return $this;
    }

    public function getCdrPath(): ?string
    {
        return $this->cdrPath;
    }

    public function setCdrPath(?string $cdrPath): self
    {
        $this->cdrPath = $cdrPath;

        return $this;
    }

    public function getCreatedAt(): DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    #[Groups(['invoice:read', 'creditnote:read'])]
    public function getFullNumber(): string
    {
        return sprintf('%s-%s', $this->series, $this->number);
    }

    public function isProcessed(): bool
    {
        return $this->sunatStatus !== null;
    }

    #[Groups(['invoice:read', 'creditnote:read'])]
    public function getPublicReceiptUrl(): string
    {
        return ReceiptPayload::publicUrl($this->getDocumentType(), $this->getId());
    }

    #[Groups(['invoice:read'])]
    public function isVoided(): bool
    {
        return $this->voidTicket !== null || $this->voidCorrelation !== null;
    }
}
