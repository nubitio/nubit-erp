<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use App\Controller\Api\ConsultInvoice;
use App\Controller\Api\SendInvoiceEmail;
use App\Controller\Api\SendTaxDocument;
use App\Controller\Api\ValidateInvoice;
use App\Core\Doctrine\Filter\DevExtremeFilter;
use App\Core\Util\ReceiptPayload;
use App\Repository\TaxDocumentRepository;
use App\State\TaxDocument\TaxDocumentProcessor;
use App\State\TaxDocument\TaxDocumentProvider;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: TaxDocumentRepository::class)]
#[ORM\Table(name: 'tax_document')]
#[ApiResource(
    shortName: 'TaxDocument',
    normalizationContext: ['groups' => ['invoice:read', 'creditnote:read']],
    filters: [DevExtremeFilter::class],
    provider: TaxDocumentProvider::class,
    processor: TaxDocumentProcessor::class,
)]
#[ApiResource(
    shortName: 'TaxDocument',
    operations: [
        new Get(
            uriTemplate: '/tax-documents/{id}/consult',
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
    uriVariables: [
        'id' => new Link(
            fromProperty: 'id',
            fromClass: TaxDocument::class
        ),
    ]
)]
class TaxDocument
{
    #[ORM\Id]
    #[ORM\Column(name: 'tax_document_id', type: Types::STRING, length: 36)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[Groups(['invoice:read', 'creditnote:read'])]
    private ?string $id = null;

    #[ORM\OneToOne(targetEntity: Sale::class, inversedBy: 'taxDocument')]
    #[ORM\JoinColumn(name: 'movimiento_id', referencedColumnName: 'movimiento_id', nullable: false)]
    #[Groups(['creditnote:read'])]
    private Sale $sale;

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(name: 'related_tax_document_id', referencedColumnName: 'tax_document_id', nullable: true)]
    private ?self $relatedTaxDocument = null;

    #[ORM\ManyToOne(targetEntity: Branch::class)]
    #[ORM\JoinColumn(name: 'branch_id', referencedColumnName: 'local_id', nullable: true)]
    private ?Branch $branch = null;

    #[ORM\ManyToOne(targetEntity: Customer::class)]
    #[ORM\JoinColumn(name: 'customer_id', referencedColumnName: 'ruc', nullable: true)]
    #[Groups(['invoice:read'])]
    private ?Customer $customer = null;

    #[ORM\Column(name: 'document_type', type: Types::STRING, length: 10)]
    #[Groups(['creditnote:read'])]
    private string $documentType;

    #[ORM\Column(name: 'issue_date', type: Types::DATETIME_MUTABLE)]
    #[Groups(['creditnote:read'])]
    private DateTimeInterface $issueDate;

    #[ORM\Column(name: 'series', type: Types::STRING, length: 10)]
    #[Groups(['invoice:read', 'creditnote:read'])]
    private string $series;

    #[ORM\Column(name: 'number', type: Types::STRING, length: 20)]
    #[Groups(['invoice:read', 'creditnote:read'])]
    private string $number;

    #[ORM\Column(name: 'affected_document', type: Types::STRING, length: 50, nullable: true)]
    #[Groups(['creditnote:read'])]
    private ?string $affectedDocument = null;

    #[ORM\Column(name: 'reason_code', type: Types::STRING, length: 10, nullable: true)]
    #[Groups(['creditnote:read'])]
    private ?string $reasonCode = null;

    #[ORM\Column(name: 'reason_description', type: Types::STRING, length: 255, nullable: true)]
    #[Groups(['creditnote:read'])]
    private ?string $reasonDescription = null;

    #[ORM\Column(name: 'total', type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Groups(['invoice:read', 'creditnote:read'])]
    private string $total = '0.00';

    #[ORM\Column(name: 'void_ticket', type: Types::STRING, length: 255, nullable: true)]
    #[Groups(['invoice:read'])]
    private ?string $voidTicket = null;

    #[ORM\Column(name: 'void_correlation', type: Types::STRING, length: 255, nullable: true)]
    #[Groups(['invoice:read'])]
    private ?string $voidCorrelation = null;

    #[ORM\Column(name: 'sunat_status', type: Types::STRING, length: 50, nullable: true)]
    #[Groups(['invoice:read', 'creditnote:read'])]
    private ?string $sunatStatus = null;

    #[ORM\Column(name: 'sunat_message', type: Types::TEXT, nullable: true)]
    #[Groups(['invoice:read', 'creditnote:read'])]
    private ?string $sunatMessage = null;

    #[ORM\Column(name: 'digest', type: Types::TEXT, nullable: true)]
    #[Groups(['invoice:read', 'creditnote:read'])]
    private ?string $digest = null;

    /**
     * @var Collection<int, TaxDocumentLine>
     */
    #[ORM\OneToMany(targetEntity: TaxDocumentLine::class, mappedBy: 'taxDocument', orphanRemoval: true)]
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

    public function getSale(): Sale
    {
        return $this->sale;
    }

    public function setSale(Sale $sale): self
    {
        $this->sale = $sale;

        return $this;
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

    public function getIssueDate(): DateTimeInterface
    {
        return $this->issueDate;
    }

    public function setIssueDate(DateTimeInterface $issueDate): self
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
}
