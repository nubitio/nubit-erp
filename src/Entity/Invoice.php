<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeInterface;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\Core\Doctrine\Filter\DevExtremeFilter;
use App\Repository\InvoiceRepository;
use App\State\Invoice\InvoiceProvider;
use Doctrine\DBAL\Types\Types;
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
    provider: InvoiceProvider::class,
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
    #[ORM\OneToOne(targetEntity: Sale::class, inversedBy: 'invoiceEntity')]
    #[ORM\JoinColumn(
        name: 'movimiento_id',
        referencedColumnName: 'movimiento_id',
        nullable: false,
        onDelete: 'CASCADE'
    )]
    private Sale $sale;

    #[ORM\Column(name: 'raw_document_id', type: 'integer', nullable: true)]
    private ?int $rawDocumentId = null;

    #[Groups(['invoice:read'])]
    #[ORM\ManyToOne(targetEntity: Customer::class)]
    #[ORM\JoinColumn(name: 'cliente_id', referencedColumnName: 'ruc')]
    private ?Customer $customer = null;

    #[Groups(['invoice:read'])]
    #[ORM\ManyToOne(targetEntity: Branch::class)]
    #[ORM\JoinColumn(name: 'branch_id', referencedColumnName: 'local_id', nullable: true)]
    private ?Branch $branch = null;

    #[Groups(['invoice:read'])]
    #[ORM\Column(name: 'tipo_comprobante_id', type: 'string')]
    private string $voucherTypeId;

    #[Groups(['invoice:read'])]
    #[ORM\Column(name: 'fecha', type: 'datetime')]
    private DateTimeInterface $issuedAt;

    #[Groups(['invoice:read'])]
    #[ORM\Column(name: 'serie', type: 'string')]
    private string $series;

    #[Groups(['invoice:read'])]
    #[ORM\Column(name: 'numero', type: 'string')]
    private string $number;

    #[Groups(['invoice:read'])]
    #[ORM\Column(name: 'inafecto', type: 'boolean')]
    private bool $unaffected = false;

    #[Groups(['invoice:read'])]
    #[ORM\Column(name: 'subtotal', type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $subTotal = '0.00';

    #[Groups(['invoice:read'])]
    #[ORM\Column(name: 'gravada', type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $taxableAmount = '0.00';

    #[Groups(['invoice:read'])]
    #[ORM\Column(name: 'igv', type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $igv = '0.00';

    #[Groups(['invoice:read'])]
    #[ORM\Column(name: 'rc', type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $rc = '0.00';

    #[Groups(['invoice:read'])]
    #[ORM\Column(name: 'tipo_operacion', type: 'string', length: 4, nullable: true)]
    private ?string $operationType = null;

    #[Groups(['invoice:read'])]
    #[ORM\Column(name: 'detraccion_codigo', type: 'string', length: 10, nullable: true)]
    private ?string $detractionCode = null;

    #[Groups(['invoice:read'])]
    #[ORM\Column(name: 'detraccion_porcentaje', type: 'float', nullable: true)]
    private ?float $detractionPercent = null;

    #[Groups(['invoice:read'])]
    #[ORM\Column(name: 'detraccion_monto', type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $detractionAmount = null;

    #[Groups(['invoice:read'])]
    #[ORM\Column(name: 'detraccion_medio_pago', type: 'string', length: 10, nullable: true)]
    private ?string $detractionPaymentMethod = null;

    #[Groups(['invoice:read'])]
    #[ORM\Column(name: 'detraccion_valor_referencial', type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $detractionValueRef = null;

    #[Groups(['invoice:read'])]
    #[ORM\Column(name: 'total', type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $total = '0.00';

    #[Groups(['invoice:read'])]
    #[ORM\Column(name: 'discount_percent', type: 'integer')]
    private int $discountPercent = 0;

    #[Groups(['invoice:read'])]
    #[ORM\Column(name: 'discount_amount', type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $discountAmount = '0.00';

    #[Groups(['invoice:read'])]
    #[ORM\Column(name: 'voided', type: 'integer')]
    private int $voided = 0;

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
    private DateTimeInterface $createdAt;

    #[Groups(['invoice:read'])]
    #[ORM\Column(name: 'updated_at', type: 'datetime', nullable: true, options: ['default' => null])]
    private ?DateTimeInterface $updatedAt = null;

    public function getId(): ?string
    {
        return $this->id;
    }

    #[Groups(['invoice:read'])]
    public function getSaleId(): string
    {
        return $this->sale->getId();
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

    public function getBranch(): ?Branch
    {
        return $this->branch;
    }

    public function setBranch(?Branch $branch): self
    {
        $this->branch = $branch;

        return $this;
    }

    public function getVoucherTypeId(): string
    {
        return $this->voucherTypeId;
    }

    public function setVoucherTypeId(string $voucherTypeId): self
    {
        $this->voucherTypeId = $voucherTypeId;

        return $this;
    }

    public function getIssuedAt(): DateTimeInterface
    {
        return $this->issuedAt;
    }

    public function setIssuedAt(DateTimeInterface $issuedAt): self
    {
        $this->issuedAt = $issuedAt;

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

    public function isUnaffected(): bool
    {
        return $this->unaffected;
    }

    public function setUnaffected(bool $unaffected): self
    {
        $this->unaffected = $unaffected;

        return $this;
    }

    public function getSubTotal(): string
    {
        return $this->subTotal;
    }

    public function setSubTotal(string|float $subTotal): self
    {
        $this->subTotal = is_float($subTotal) ? number_format($subTotal, 2, '.', '') : $subTotal;

        return $this;
    }

    public function getTaxableAmount(): string
    {
        return $this->taxableAmount;
    }

    public function setTaxableAmount(string|float $taxableAmount): self
    {
        $this->taxableAmount = is_float($taxableAmount) ? number_format($taxableAmount, 2, '.', '') : $taxableAmount;

        return $this;
    }

    public function getIgv(): string
    {
        return $this->igv;
    }

    public function setIgv(string|float $igv): self
    {
        $this->igv = is_float($igv) ? number_format($igv, 2, '.', '') : $igv;

        return $this;
    }

    public function getRc(): string
    {
        return $this->rc;
    }

    public function setRc(string|float $rc): self
    {
        $this->rc = is_float($rc) ? number_format($rc, 2, '.', '') : $rc;

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

    public function getDetractionPercent(): ?float
    {
        return $this->detractionPercent;
    }

    public function setDetractionPercent(?float $detractionPercent): self
    {
        $this->detractionPercent = $detractionPercent;

        return $this;
    }

    public function getDetractionAmount(): ?string
    {
        return $this->detractionAmount;
    }

    public function setDetractionAmount(null|string|float $detractionAmount): self
    {
        $this->detractionAmount = is_float($detractionAmount)
            ? number_format($detractionAmount, 2, '.', '')
            : $detractionAmount;

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

    public function setDetractionValueRef(null|string|float $detractionValueRef): self
    {
        $this->detractionValueRef = is_float($detractionValueRef)
            ? number_format($detractionValueRef, 2, '.', '')
            : $detractionValueRef;

        return $this;
    }

    public function getTotal(): string
    {
        return $this->total;
    }

    public function setTotal(string|float $total): self
    {
        $this->total = is_float($total) ? number_format($total, 2, '.', '') : $total;

        return $this;
    }

    public function getDiscountPercent(): int
    {
        return $this->discountPercent;
    }

    public function setDiscountPercent(int $discountPercent): self
    {
        $this->discountPercent = $discountPercent;

        return $this;
    }

    public function getDiscountAmount(): string
    {
        return $this->discountAmount;
    }

    public function setDiscountAmount(string|float $discountAmount): self
    {
        $this->discountAmount = is_float($discountAmount)
            ? number_format($discountAmount, 2, '.', '')
            : $discountAmount;

        return $this;
    }

    public function getVoided(): int
    {
        return $this->voided;
    }

    public function setVoided(int $voided): self
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

    public function getCreatedAt(): DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }
}
