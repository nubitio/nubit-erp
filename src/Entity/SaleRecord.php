<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Controller\Api\MakeInvoice;
use App\Controller\Api\VoidSale;
use App\Controller\PrintESCPOSController;
use App\Core\Doctrine\Filter\DevExtremeFilter;
use App\Core\Exception\ServiceException;
use App\Core\Http\ApiResponse;
use App\Dto\Sale\VoidSaleDto;
use App\Repository\SaleRecordRepository;
use App\State\Sale\SaleProcessor;
use App\State\Sale\SaleV2Provider;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: SaleRecordRepository::class)]
#[ORM\Table(name: 'sales')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    shortName: 'Sale',
    operations: [
        new GetCollection(),
        new Get(),
        new Post(),
        new Patch(),
        new Post(
            uriTemplate: '/sales/{id}/invoice',
            controller: MakeInvoice::class,
            name: 'sale_make_invoice'
        ),
        new Post(
            uriTemplate: '/sales/{id}/void',
            controller: VoidSale::class,
            exceptionToStatus: [ServiceException::class => 400],
            input: VoidSaleDto::class,
            output: ApiResponse::class,
            deserialize: false,
            name: 'sale_void',
        ),
        new Get(
            uriTemplate: '/sales/{id}/print-invoice',
            controller: PrintESCPOSController::class,
            read: false,
            name: 'sale_print_invoice',
        ),
    ],
    normalizationContext: ['groups' => ['sale_record:read']],
    denormalizationContext: ['groups' => ['sale_record:write']],
    filters: [DevExtremeFilter::class],
    provider: SaleV2Provider::class,
    processor: SaleProcessor::class,
)]
class SaleRecord
{
    #[Groups(['sale_record:read'])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\Column(name: 'id', type: 'string', length: 36)]
    private ?string $id = null;

    #[ORM\Column(name: 'legacy_movimiento_id', type: 'string', length: 255, nullable: true, unique: true)]
    private ?string $legacyMovimientoId = null;

    #[Groups(['sale_record:read', 'sale_record:write'])]
    #[ORM\ManyToOne(targetEntity: Branch::class)]
    #[ORM\JoinColumn(name: 'branch_id', referencedColumnName: 'local_id', nullable: true)]
    #[ApiProperty(description: 'sale_record.branch.label')]
    private ?Branch $branch = null;

    #[Groups(['sale_record:read', 'sale_record:write'])]
    #[ORM\ManyToOne(targetEntity: Warehouse::class)]
    #[ORM\JoinColumn(name: 'warehouse_id', referencedColumnName: 'almacen_id', nullable: true)]
    #[ApiProperty(description: 'sale_record.warehouse.label')]
    private ?Warehouse $warehouse = null;

    #[ORM\Column(name: 'cash_register_id', type: 'integer', nullable: true)]
    private ?int $cashRegisterId = null;

    #[ORM\Column(name: 'cash_session_id', type: 'integer', nullable: true)]
    private ?int $cashSessionId = null;

    #[Groups(['sale_record:read', 'sale_record:write'])]
    #[ORM\ManyToOne(targetEntity: Customer::class)]
    #[ORM\JoinColumn(name: 'customer_id', referencedColumnName: 'id', nullable: true)]
    #[ApiProperty(description: 'sale_record.customer.label')]
    private ?Customer $customer = null;

    #[Groups(['sale_record:read', 'sale_record:write'])]
    #[ORM\Column(name: 'seller_user_id', type: 'integer', nullable: true)]
    #[ApiProperty(description: 'sale_record.seller_user_id.label')]
    private ?int $sellerUserId = null;

    #[Groups(['sale_record:read'])]
    #[ORM\Column(name: 'status', type: 'string', length: 30, options: ['default' => 'COMPLETED'])]
    #[ApiProperty(description: 'sale_record.status.label')]
    private string $status = 'COMPLETED';

    #[Groups(['sale_record:read', 'sale_record:write'])]
    #[ORM\Column(name: 'currency', type: 'string', length: 3, options: ['default' => 'PEN'])]
    #[ApiProperty(description: 'sale_record.currency.label')]
    private string $currency = 'PEN';

    #[Groups(['sale_record:read', 'sale_record:write'])]
    #[ORM\Column(
        name: 'exchange_rate',
        type: Types::DECIMAL,
        precision: 14,
        scale: 6,
        options: ['default' => '1.000000'],
    )]
    #[ApiProperty(description: 'sale_record.exchange_rate.label')]
    private string $exchangeRate = '1.000000';

    #[Groups(['sale_record:read'])]
    #[ORM\Column(name: 'subtotal', type: Types::DECIMAL, precision: 12, scale: 2, options: ['default' => '0.00'])]
    #[ApiProperty(description: 'sale_record.subtotal.label')]
    private string $subtotal = '0.00';

    #[Groups(['sale_record:read'])]
    #[ORM\Column(name: 'discount_total', type: Types::DECIMAL, precision: 12, scale: 2, options: ['default' => '0.00'])]
    #[ApiProperty(description: 'sale_record.discount_total.label')]
    private string $discountTotal = '0.00';

    #[Groups(['sale_record:read'])]
    #[ORM\Column(name: 'tax_total', type: Types::DECIMAL, precision: 12, scale: 2, options: ['default' => '0.00'])]
    #[ApiProperty(description: 'sale_record.tax_total.label')]
    private string $taxTotal = '0.00';

    #[Groups(['sale_record:read', 'sale_record:write'])]
    #[ORM\Column(name: 'grand_total', type: Types::DECIMAL, precision: 12, scale: 2, options: ['default' => '0.00'])]
    #[ApiProperty(description: 'sale_record.grand_total.label')]
    private string $grandTotal = '0.00';

    #[Groups(['sale_record:read'])]
    #[ORM\Column(name: 'paid_total', type: Types::DECIMAL, precision: 12, scale: 2, options: ['default' => '0.00'])]
    #[ApiProperty(description: 'sale_record.paid_total.label')]
    private string $paidTotal = '0.00';

    #[Groups(['sale_record:read'])]
    #[ORM\Column(name: 'change_total', type: Types::DECIMAL, precision: 12, scale: 2, options: ['default' => '0.00'])]
    #[ApiProperty(description: 'sale_record.change_total.label')]
    private string $changeTotal = '0.00';

    #[Groups(['sale_record:read'])]
    #[ORM\Column(name: 'balance_due', type: Types::DECIMAL, precision: 12, scale: 2, options: ['default' => '0.00'])]
    #[ApiProperty(description: 'sale_record.balance_due.label')]
    private string $balanceDue = '0.00';

    #[Groups(['sale_record:read', 'sale_record:write'])]
    #[ORM\Column(name: 'issued_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    #[ApiProperty(description: 'sale_record.issued_at.label')]
    private ?DateTimeInterface $issuedAt = null;

    #[Groups(['sale_record:read'])]
    #[ORM\Column(
        name: 'created_at',
        type: Types::DATETIME_MUTABLE,
        nullable: true,
        options: ['default' => 'CURRENT_TIMESTAMP'],
    )]
    #[ApiProperty(description: 'sale_record.created_at.label')]
    private ?DateTimeInterface $createdAt = null;

    #[Groups(['sale_record:read'])]
    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    #[ApiProperty(description: 'sale_record.updated_at.label')]
    private ?DateTimeInterface $updatedAt = null;

    #[ORM\Column(name: 'deleted_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTimeInterface $deletedAt = null;

    /**
     * ORM-managed V2 items. Not in normalization group to avoid N+1.
     *
     * @var Collection<int, SaleItemRecord>
     */
    #[ORM\OneToMany(targetEntity: SaleItemRecord::class, mappedBy: 'saleRecord')]
    private Collection $saleItemRecords;

    // ── Legacy pipeline items (transient, not ORM-mapped) ────────────────────

    /**
     * Pipeline-only SaleItem objects used by the write pipeline.
     *
     * @var Collection<int, SaleItem>
     */
    private Collection $legacyItems;

    // ── Transient fields enriched by SaleV2Provider ──────────────────────────

    #[Groups(['sale_record:read'])]
    #[ApiProperty(description: 'sale_record.tax_document_id.label')]
    private ?string $taxDocumentId = null;

    #[Groups(['sale_record:read'])]
    #[ApiProperty(description: 'sale_record.has_invoice.label')]
    private bool $hasInvoice = false;

    #[Groups(['sale_record:read'])]
    #[ApiProperty(description: 'sale_record.invoice_full_number.label')]
    private ?string $invoiceFullNumber = null;

    #[Groups(['sale_record:read'])]
    #[ApiProperty(description: 'sale_record.sunat_status.label')]
    private ?string $sunatStatus = null;

    #[Groups(['sale_record:read'])]
    #[ApiProperty(description: 'sale_record.public_receipt_url.label')]
    private ?string $publicReceiptUrl = null;

    #[Groups(['sale_record:read'])]
    #[ApiProperty(description: 'sale_record.document_type.label')]
    private ?string $documentType = null;

    /** Resolved from sale_payments by provider */
    #[Groups(['sale_record:read', 'sale_record:write'])]
    #[ApiProperty(description: 'sale_record.payment_method.label')]
    private ?PaymentMethod $paymentMethod = null;

    /** Computed on read: discount_total / (subtotal + discount_total) * 100; accepted on write. */
    #[Groups(['sale_record:read', 'sale_record:write'])]
    #[ApiProperty(description: 'sale_record.discount_percent.label')]
    private ?string $discountPercent = null;

    #[ORM\Column(name: 'serie', type: 'string', length: 10, nullable: true)]
    #[Groups(['sale:read'])]
    #[ApiProperty(description: 'sale_record.serie.label')]
    private ?string $serie = null;

    #[ORM\Column(name: 'numero', type: 'string', length: 20, nullable: true)]
    #[Groups(['sale:read'])]
    #[ApiProperty(description: 'sale_record.numero.label')]
    private ?string $numero = null;

    // ── Write-only transient fields ───────────────────────────────────────────

    // no ORM annotation — transient
    #[Groups(['sale:read'])]
    #[ApiProperty(description: 'sale_record.tax_rate.label')]
    private ?string $taxRate = null;

    #[Groups(['sale_record:write'])]
    private ?bool $emitInvoice = null;

    #[Groups(['sale_record:write'])]
    private ?string $note = null;

    /**
     * @var array<int, array<string, mixed>>
     */
    #[Groups(['sale_record:write'])]
    private array $payments = [];

    /**
     * Items for write path (mapped to SaleItem entities by processor).
     * Not in read group; use /api/sales/{id}/items sub-resource.
     *
     * @var array<int, mixed>
     */
    #[Groups(['sale_record:write'])]
    private array $writeItems = [];

    // ── Legacy-pipeline transient fields (no ORM mapping) ────────────────────

    private int $tipoId = 2;

    private ?int $formaPago = null;

    private ?int $updatedBy = null;

    private ?int $createdBy = null;

    private ?int $recargoConsumo = null;

    private ?string $tipoOperacion = null;

    private ?string $invoice = null;

    private ?string $detraccionCodigo = null;

    private ?float $detraccionPorcentaje = null;

    private ?float $detraccionMonto = null;

    private ?string $detraccionMedioPago = null;

    private ?float $detraccionValorReferencial = null;

    private ?Supplier $proveedor = null;

    private ?\DateTimeImmutable $dateCreated = null;

    private ?\DateTimeImmutable $dateUpdated = null;

    public function __construct()
    {
        $this->saleItemRecords = new ArrayCollection();
        $this->legacyItems = new ArrayCollection();
    }

    #[ORM\PrePersist]
    public function setTimestampsOnPersist(): void
    {
        if ($this->createdAt === null) {
            $this->createdAt = new \DateTime();
        }
    }

    #[ORM\PreUpdate]
    public function setTimestampsOnUpdate(): void
    {
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(?string $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getLegacyMovimientoId(): ?string
    {
        return $this->legacyMovimientoId;
    }

    public function setLegacyMovimientoId(?string $legacyMovimientoId): static
    {
        $this->legacyMovimientoId = $legacyMovimientoId;

        return $this;
    }

    public function getBranch(): ?Branch
    {
        return $this->branch;
    }

    public function setBranch(?Branch $branch): static
    {
        $this->branch = $branch;

        return $this;
    }

    public function getWarehouse(): ?Warehouse
    {
        return $this->warehouse;
    }

    public function setWarehouse(?Warehouse $warehouse): static
    {
        $this->warehouse = $warehouse;

        return $this;
    }

    public function getCashRegisterId(): ?int
    {
        return $this->cashRegisterId;
    }

    public function setCashRegisterId(?int $cashRegisterId): static
    {
        $this->cashRegisterId = $cashRegisterId;

        return $this;
    }

    public function getCashSessionId(): ?int
    {
        return $this->cashSessionId;
    }

    public function setCashSessionId(?int $cashSessionId): static
    {
        $this->cashSessionId = $cashSessionId;

        return $this;
    }

    public function getCustomer(): ?Customer
    {
        return $this->customer;
    }

    public function setCustomer(?Customer $customer): static
    {
        $this->customer = $customer;

        return $this;
    }

    public function getSellerUserId(): ?int
    {
        return $this->sellerUserId;
    }

    public function setSellerUserId(?int $sellerUserId): static
    {
        $this->sellerUserId = $sellerUserId;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): static
    {
        $this->currency = $currency;

        return $this;
    }

    public function getExchangeRate(): string
    {
        return $this->exchangeRate;
    }

    public function setExchangeRate(string $exchangeRate): static
    {
        $this->exchangeRate = $exchangeRate;

        return $this;
    }

    public function getSubtotal(): string
    {
        return $this->subtotal;
    }

    public function setSubtotal(string $subtotal): static
    {
        $this->subtotal = $subtotal;

        return $this;
    }

    public function getDiscountTotal(): string
    {
        return $this->discountTotal;
    }

    public function setDiscountTotal(string $discountTotal): static
    {
        $this->discountTotal = $discountTotal;

        return $this;
    }

    public function getTaxTotal(): string
    {
        return $this->taxTotal;
    }

    public function setTaxTotal(string $taxTotal): static
    {
        $this->taxTotal = $taxTotal;

        return $this;
    }

    public function getGrandTotal(): string
    {
        return $this->grandTotal;
    }

    public function setGrandTotal(string $grandTotal): static
    {
        $this->grandTotal = $grandTotal;

        return $this;
    }

    public function getPaidTotal(): string
    {
        return $this->paidTotal;
    }

    public function setPaidTotal(string $paidTotal): static
    {
        $this->paidTotal = $paidTotal;

        return $this;
    }

    public function getChangeTotal(): string
    {
        return $this->changeTotal;
    }

    public function setChangeTotal(string $changeTotal): static
    {
        $this->changeTotal = $changeTotal;

        return $this;
    }

    public function getBalanceDue(): string
    {
        return $this->balanceDue;
    }

    public function setBalanceDue(string $balanceDue): static
    {
        $this->balanceDue = $balanceDue;

        return $this;
    }

    public function getIssuedAt(): ?DateTimeInterface
    {
        return $this->issuedAt;
    }

    public function setIssuedAt(?DateTimeInterface $issuedAt): static
    {
        $this->issuedAt = $issuedAt;

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

    public function getDeletedAt(): ?DateTimeInterface
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?DateTimeInterface $deletedAt): static
    {
        $this->deletedAt = $deletedAt;

        return $this;
    }

    /**
     * ORM-managed V2 items.
     *
     * @return Collection<int, SaleItemRecord>
     */
    public function getSaleItemRecords(): Collection
    {
        return $this->saleItemRecords;
    }

    /**
     * Pipeline-only legacy SaleItem objects (transient).
     *
     * @return Collection<int, SaleItem>
     */
    public function getItems(): Collection
    {
        return $this->legacyItems;
    }

    /** Bridge alias for addLegacyItem() — keeps legacy call-sites working during Wave 2. */
    public function addItem(SaleItem $item): static
    {
        return $this->addLegacyItem($item);
    }

    public function addLegacyItem(SaleItem $item): static
    {
        $this->legacyItems->add($item);

        return $this;
    }

    /**
     * @param Collection<int, SaleItem> $items
     */
    public function setLegacyItems(Collection $items): static
    {
        $this->legacyItems = $items;

        return $this;
    }

    // ── Transient getters/setters ─────────────────────────────────────────────

    public function getTaxDocumentId(): ?string
    {
        return $this->taxDocumentId;
    }

    public function setTaxDocumentId(?string $taxDocumentId): static
    {
        $this->taxDocumentId = $taxDocumentId;

        return $this;
    }

    public function isHasInvoice(): bool
    {
        return $this->hasInvoice;
    }

    public function setHasInvoice(bool $hasInvoice): static
    {
        $this->hasInvoice = $hasInvoice;

        return $this;
    }

    public function getInvoiceFullNumber(): ?string
    {
        return $this->invoiceFullNumber;
    }

    public function setInvoiceFullNumber(?string $invoiceFullNumber): static
    {
        $this->invoiceFullNumber = $invoiceFullNumber;

        return $this;
    }

    public function getSunatStatus(): ?string
    {
        return $this->sunatStatus;
    }

    public function setSunatStatus(?string $sunatStatus): static
    {
        $this->sunatStatus = $sunatStatus;

        return $this;
    }

    public function getPublicReceiptUrl(): ?string
    {
        return $this->publicReceiptUrl;
    }

    public function setPublicReceiptUrl(?string $publicReceiptUrl): static
    {
        $this->publicReceiptUrl = $publicReceiptUrl;

        return $this;
    }

    public function getDocumentType(): ?string
    {
        return $this->documentType;
    }

    public function setDocumentType(?string $documentType): static
    {
        $this->documentType = $documentType;

        return $this;
    }

    public function getPaymentMethod(): ?PaymentMethod
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(?PaymentMethod $paymentMethod): static
    {
        $this->paymentMethod = $paymentMethod;

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

    public function getSerie(): ?string
    {
        return $this->serie;
    }

    public function setSerie(?string $serie): static
    {
        $this->serie = $serie;

        return $this;
    }

    public function getNumero(): ?string
    {
        return $this->numero;
    }

    public function setNumero(?string $numero): static
    {
        $this->numero = $numero;

        return $this;
    }

    public function getTaxRate(): ?string
    {
        return $this->taxRate;
    }

    public function setTaxRate(?string $taxRate): static
    {
        $this->taxRate = $taxRate;

        return $this;
    }

    public function getEmitInvoice(): ?bool
    {
        return $this->emitInvoice;
    }

    public function setEmitInvoice(?bool $emitInvoice): static
    {
        $this->emitInvoice = $emitInvoice;

        return $this;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): static
    {
        $this->note = $note;

        return $this;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getPayments(): array
    {
        return $this->payments;
    }

    /**
     * @param array<int, array<string, mixed>> $payments
     */
    public function setPayments(array $payments): static
    {
        $this->payments = $payments;

        return $this;
    }

    /**
     * @return array<int, mixed>
     */
    public function getWriteItems(): array
    {
        return $this->writeItems;
    }

    /**
     * @param array<int, mixed> $writeItems
     */
    public function setWriteItems(array $writeItems): static
    {
        $this->writeItems = $writeItems;

        return $this;
    }

    // ── Bridge alias methods (legacy-pipeline compatibility) ─────────────────

    public function getAlmacen(): ?Warehouse
    {
        return $this->getWarehouse();
    }

    public function setAlmacen(?Warehouse $almacen): static
    {
        return $this->setWarehouse($almacen);
    }

    public function getCliente(): ?Customer
    {
        return $this->getCustomer();
    }

    public function setCliente(?Customer $cliente): static
    {
        return $this->setCustomer($cliente);
    }

    public function getFecha(): ?DateTimeInterface
    {
        return $this->getIssuedAt();
    }

    public function setFecha(?DateTimeInterface $fecha): static
    {
        return $this->setIssuedAt($fecha);
    }

    public function getTotal(): ?string
    {
        return $this->grandTotal;
    }

    public function setTotal(string $total): static
    {
        return $this->setGrandTotal($total);
    }

    public function getTipoPago(): ?PaymentMethod
    {
        return $this->getPaymentMethod();
    }

    public function setTipoPago(?PaymentMethod $tipoPago): static
    {
        return $this->setPaymentMethod($tipoPago);
    }

    public function getGlosa(): ?string
    {
        return $this->getNote();
    }

    public function setGlosa(?string $glosa): static
    {
        return $this->setNote($glosa);
    }

    public function getDescuento(): ?string
    {
        return $this->getDiscountPercent();
    }

    public function setDescuento(?string $descuento): static
    {
        return $this->setDiscountPercent($descuento);
    }

    public function getIgv(): ?string
    {
        return $this->getTaxRate();
    }

    public function setIgv(?string $igv): static
    {
        return $this->setTaxRate($igv);
    }

    public function getCajaId(): ?int
    {
        return $this->getCashRegisterId();
    }

    public function setCajaId(?int $cajaId): static
    {
        return $this->setCashRegisterId($cajaId);
    }

    /** Returns 0 for VOIDED, 1 for everything else. */
    public function getEstado(): int
    {
        return $this->status === 'VOIDED' ? 0 : 1;
    }

    public function setEstado(int $estado): static
    {
        return $this->setStatus($estado === 0 ? 'VOIDED' : 'COMPLETED');
    }

    public function getCreatedBy(): ?int
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?int $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getUpdatedBy(): ?int
    {
        return $this->updatedBy;
    }

    public function setUpdatedBy(?int $updatedBy): static
    {
        $this->updatedBy = $updatedBy;

        return $this;
    }

    public function getDateCreated(): ?\DateTimeImmutable
    {
        return $this->dateCreated;
    }

    public function setDateCreated(?\DateTimeImmutable $dateCreated): static
    {
        $this->dateCreated = $dateCreated;

        return $this;
    }

    public function getDateUpdated(): ?\DateTimeImmutable
    {
        return $this->dateUpdated;
    }

    public function setDateUpdated(?\DateTimeImmutable $dateUpdated): static
    {
        $this->dateUpdated = $dateUpdated;

        return $this;
    }

    public function getTipoId(): int
    {
        return $this->tipoId;
    }

    public function setTipoId(int $tipoId): static
    {
        $this->tipoId = $tipoId;

        return $this;
    }

    public function getFormaPago(): ?int
    {
        return $this->formaPago;
    }

    public function setFormaPago(?int $formaPago): static
    {
        $this->formaPago = $formaPago;

        return $this;
    }

    public function getRecargoConsumo(): ?int
    {
        return $this->recargoConsumo;
    }

    public function setRecargoConsumo(?int $recargoConsumo): static
    {
        $this->recargoConsumo = $recargoConsumo;

        return $this;
    }

    public function getTipoOperacion(): ?string
    {
        return $this->tipoOperacion;
    }

    public function setTipoOperacion(?string $tipoOperacion): static
    {
        $this->tipoOperacion = $tipoOperacion;

        return $this;
    }

    public function getInvoice(): ?string
    {
        return $this->invoice;
    }

    public function setInvoice(?string $invoice): static
    {
        $this->invoice = $invoice;

        return $this;
    }

    public function getDetraccionCodigo(): ?string
    {
        return $this->detraccionCodigo;
    }

    public function setDetraccionCodigo(?string $detraccionCodigo): static
    {
        $this->detraccionCodigo = $detraccionCodigo;

        return $this;
    }

    public function getDetraccionPorcentaje(): ?float
    {
        return $this->detraccionPorcentaje;
    }

    public function setDetraccionPorcentaje(?float $detraccionPorcentaje): static
    {
        $this->detraccionPorcentaje = $detraccionPorcentaje;

        return $this;
    }

    public function getDetraccionMonto(): ?float
    {
        return $this->detraccionMonto;
    }

    public function setDetraccionMonto(?float $detraccionMonto): static
    {
        $this->detraccionMonto = $detraccionMonto;

        return $this;
    }

    public function getDetraccionMedioPago(): ?string
    {
        return $this->detraccionMedioPago;
    }

    public function setDetraccionMedioPago(?string $detraccionMedioPago): static
    {
        $this->detraccionMedioPago = $detraccionMedioPago;

        return $this;
    }

    public function getDetraccionValorReferencial(): ?float
    {
        return $this->detraccionValorReferencial;
    }

    public function setDetraccionValorReferencial(?float $detraccionValorReferencial): static
    {
        $this->detraccionValorReferencial = $detraccionValorReferencial;

        return $this;
    }

    public function getProveedor(): ?Supplier
    {
        return $this->proveedor;
    }

    public function setProveedor(?Supplier $proveedor): static
    {
        $this->proveedor = $proveedor;

        return $this;
    }
}
