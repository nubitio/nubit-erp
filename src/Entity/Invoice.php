<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\Core\Doctrine\Filter\DevExtremeFilter;
use App\Repository\InvoiceRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    shortName: 'Invoice',
    operations: [
        new GetCollection(),
    ],
    normalizationContext: ['groups' => ['invoice:read']],
    filters: [DevExtremeFilter::class],
)]
#[ORM\Entity(repositoryClass: InvoiceRepository::class)]
#[ORM\Table(name: 'comprobante')]
class Invoice
{
    #[Groups(['invoice:read'])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\Column(name: 'comprobante_id', type: 'string')]
    private ?string $id = null;

    #[Groups(['invoice:read'])]
    #[ORM\ManyToOne(targetEntity: Sale::class)]
    #[ORM\JoinColumn(
        name: 'movimiento_id',
        referencedColumnName: 'movimiento_id',
        nullable: false,
        onDelete: 'CASCADE'
    )]
    private ?Sale $sale = null;

    #[ORM\Column(name: 'raw_document_id', type: 'integer', nullable: true)]
    private ?int $rawDocumentId = null;

    #[Groups(['invoice:read'])]
    #[ORM\ManyToOne(targetEntity: Customer::class)]
    #[ORM\JoinColumn(name: 'cliente_id', referencedColumnName: 'ruc')]
    private ?Customer $customer = null;

    #[Groups(['invoice:read'])]
    #[ORM\Column(name: 'tipo_comprobante_id', type: 'string')]
    private ?string $voucherTypeId = null;

    #[Groups(['invoice:read'])]
    #[ORM\Column(name: 'fecha', type: 'datetime')]
    private ?\DateTimeInterface $issuedAt = null;

    #[Groups(['invoice:read'])]
    #[ORM\Column(name: 'serie', type: 'string')]
    private ?string $series = null;

    #[Groups(['invoice:read'])]
    #[ORM\Column(name: 'numero', type: 'string')]
    private ?string $number = null;

    #[Groups(['invoice:read'])]
    #[ORM\Column(name: 'inafecto', type: 'boolean')]
    private ?bool $unaffected = null;

    #[Groups(['invoice:read'])]
    #[ORM\Column(name: 'subtotal', type: 'float')]
    private ?float $subTotal = null;

    #[Groups(['invoice:read'])]
    #[ORM\Column(name: 'gravada', type: 'float')]
    private ?float $taxableAmount = null;

    #[Groups(['invoice:read'])]
    #[ORM\Column(name: 'igv', type: 'float')]
    private ?float $igv = null;

    #[Groups(['invoice:read'])]
    #[ORM\Column(name: 'rc', type: 'float')]
    private ?float $rc = null;

    #[Groups(['invoice:read'])]
    #[ORM\Column(name: 'total', type: 'float')]
    private ?float $total = null;

    #[Groups(['invoice:read'])]
    #[ORM\Column(name: 'discount_percent', type: 'integer')]
    private ?int $discountPercent = null;

    #[Groups(['invoice:read'])]
    #[ORM\Column(name: 'discount_amount', type: 'float')]
    private ?float $discountAmount = null;

    #[Groups(['invoice:read'])]
    #[ORM\Column(name: 'voided', type: 'integer')]
    private ?int $voided = null;

    #[Groups(['invoice:read'])]
    #[ORM\Column(name: 'digest', type: 'string', nullable: true, options: ['default' => null])]
    private ?string $digest = null;

    #[Groups(['invoice:read'])]
    #[ORM\Column(name: 'ticket_baja', type: 'string', nullable: true, options: ['default' => null])]
    private ?string $voidTicket = null;

    #[Groups(['invoice:read'])]
    #[ORM\Column(name: 'correlativo_baja', type: 'string', nullable: true, options: ['default' => null])]
    private ?string $voidCorrelation = null;

    #[Groups(['invoice:read'])]
    #[ORM\Column(name: 'envio_sunat', type: 'string', nullable: true, options: ['default' => null])]
    private ?string $sunatStatus = null;

    #[Groups(['invoice:read'])]
    #[ORM\Column(name: 'mensaje_sunat', type: 'text', nullable: true, options: ['default' => null])]
    private ?string $sunatMessage = null;

    #[Groups(['invoice:read'])]
    #[ORM\Column(name: 'created_at', type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    private ?\DateTimeInterface $createdAt = null;

    #[Groups(['invoice:read'])]
    #[ORM\Column(name: 'updated_at', type: 'datetime', nullable: true, options: ['default' => null])]
    private ?\DateTimeInterface $updatedAt = null;

    public function getId(): ?string
    {
        return $this->id;
    }

    #[Groups(['invoice:read'])]
    public function getSaleId(): ?string
    {
        return $this->sale?->getId();
    }

    public function getSale(): ?Sale
    {
        return $this->sale;
    }

    public function setSale(?Sale $sale): self
    {
        $this->sale = $sale;

        return $this;
    }

    public function getRawDocumentId(): ?int
    {
        return $this->rawDocumentId;
    }

    public function setRawDocumentId(?int $rawDocumentId): self
    {
        $this->rawDocumentId = $rawDocumentId;

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

    public function getVoucherTypeId(): ?string
    {
        return $this->voucherTypeId;
    }

    public function setVoucherTypeId(?string $voucherTypeId): self
    {
        $this->voucherTypeId = $voucherTypeId;

        return $this;
    }

    public function getIssuedAt(): ?\DateTimeInterface
    {
        return $this->issuedAt;
    }

    public function setIssuedAt(?\DateTimeInterface $issuedAt): self
    {
        $this->issuedAt = $issuedAt;

        return $this;
    }

    public function getSeries(): ?string
    {
        return $this->series;
    }

    public function setSeries(?string $series): self
    {
        $this->series = $series;

        return $this;
    }

    public function getNumber(): ?string
    {
        return $this->number;
    }

    public function setNumber(?string $number): self
    {
        $this->number = $number;

        return $this;
    }

    public function isUnaffected(): ?bool
    {
        return $this->unaffected;
    }

    public function setUnaffected(?bool $unaffected): self
    {
        $this->unaffected = $unaffected;

        return $this;
    }

    public function getSubTotal(): ?float
    {
        return $this->subTotal;
    }

    public function setSubTotal(?float $subTotal): self
    {
        $this->subTotal = $subTotal;

        return $this;
    }

    public function getTaxableAmount(): ?float
    {
        return $this->taxableAmount;
    }

    public function setTaxableAmount(?float $taxableAmount): self
    {
        $this->taxableAmount = $taxableAmount;

        return $this;
    }

    public function getIgv(): ?float
    {
        return $this->igv;
    }

    public function setIgv(?float $igv): self
    {
        $this->igv = $igv;

        return $this;
    }

    public function getRc(): ?float
    {
        return $this->rc;
    }

    public function setRc(?float $rc): self
    {
        $this->rc = $rc;

        return $this;
    }

    public function getTotal(): ?float
    {
        return $this->total;
    }

    public function setTotal(?float $total): self
    {
        $this->total = $total;

        return $this;
    }

    public function getDiscountPercent(): ?int
    {
        return $this->discountPercent;
    }

    public function setDiscountPercent(?int $discountPercent): self
    {
        $this->discountPercent = $discountPercent;

        return $this;
    }

    public function getDiscountAmount(): ?float
    {
        return $this->discountAmount;
    }

    public function setDiscountAmount(?float $discountAmount): self
    {
        $this->discountAmount = $discountAmount;

        return $this;
    }

    public function getVoided(): ?int
    {
        return $this->voided;
    }

    public function setVoided(?int $voided): self
    {
        $this->voided = $voided;

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

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }
}
