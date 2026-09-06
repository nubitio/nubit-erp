<?php

declare(strict_types=1);

namespace App\Sales\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Catalog\Entity\PaymentMethod;
use App\Contacts\Entity\Customer;
use App\Contacts\Entity\Supplier;
use App\Inventory\Entity\Warehouse;
use App\Organization\Entity\Branch;
use App\Sales\Controller\Api\MakeSaleInvoice;
use App\Sales\Controller\Api\PrintSaleInvoice;
use App\Sales\Controller\Api\ReplaceRejectedSaleDocument;
use App\Sales\Controller\Api\VoidSale;
use App\Sales\Dto\SaleCollectPaymentDto;
use App\Sales\Dto\VoidSaleDto;
use App\Sales\Repository\SaleRecordRepository;
use App\Sales\State\SaleCollectPaymentProcessor;
use App\Sales\State\SaleProcessor;
use App\Sales\State\SaleV2Provider;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Nubit\ApiPlatform\Doctrine\Filter\DataGridFilter;
use Nubit\Platform\Exception\ServiceException;
use Nubit\Platform\Http\ApiResponse;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SaleRecordRepository::class)]
#[ORM\Table(name: 'sales')]
#[ORM\Index(name: 'idx_sales_branch_issued', columns: ['branch_id', 'issued_at'])]
#[ORM\Index(name: 'idx_sales_warehouse_issued', columns: ['warehouse_id', 'issued_at'])]
#[ORM\Index(name: 'idx_sales_cash_session_issued', columns: ['cash_session_id', 'issued_at'])]
#[ORM\Index(name: 'idx_sales_customer_issued', columns: ['customer_id', 'issued_at'])]
#[ORM\Index(name: 'idx_sales_status_created', columns: ['status', 'created_at'])]
#[ORM\Index(name: 'idx_sales_sync_status_synced', columns: ['sync_status', 'synced_at'])]
#[ORM\Index(name: 'idx_sales_device_synced', columns: ['device_id', 'synced_at'])]
#[ORM\Index(name: 'idx_sales_serie_numero', columns: ['series', 'number'])]
#[ORM\Index(name: 'idx_sales_issued_date_branch', columns: ['issued_date', 'branch_id', 'status'])]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    shortName: 'Sale',
    operations: [
        new GetCollection(security: "is_granted('perm:sales.view')"),
        new Get(security: "is_granted('perm:sales.view')"),
        new Get(
            uriTemplate: '/sales/{id}/payments',
            controller: \App\Sales\Controller\SalePayments::class,
            security: "is_granted('perm:sales.view')",
            name: 'sale_payment_history',
        ),
        new Post(security: "is_granted('perm:sales.create')"),
        new Post(
            uriTemplate: '/sales/{id}/invoice',
            controller: MakeSaleInvoice::class,
            security: "is_granted('perm:sales.create')",
            name: 'sale_make_invoice',
        ),
        new Post(
            uriTemplate: '/sales/{id}/void',
            controller: VoidSale::class,
            exceptionToStatus: [ServiceException::class => 400],
            input: VoidSaleDto::class,
            output: ApiResponse::class,
            deserialize: false,
            security: "is_granted('perm:sales.void')",
            name: 'sale_void',
        ),
        new Get(
            uriTemplate: '/sales/{id}/print-invoice',
            controller: PrintSaleInvoice::class,
            read: false,
            security: "is_granted('perm:sales.view')",
            name: 'sale_print_invoice',
        ),
        new Post(
            uriTemplate: '/sales/{id}/replace-rejected',
            controller: ReplaceRejectedSaleDocument::class,
            read: false,
            deserialize: false,
            security: "is_granted('perm:sales.create')",
            name: 'sale_replace_rejected',
        ),
        new Post(
            uriTemplate: '/sales/{id}/collect',
            normalizationContext: ['groups' => ['sale_record:read']],
            // SaleCollectPaymentDto has no serialization groups of its own —
            // without this override, the class-level denormalizationContext
            // below (sale_record:write group) strips every property before
            // the DTO constructor runs, same bug hit on
            // /supplier-bills/{id}/pay.
            denormalizationContext: [],
            input: SaleCollectPaymentDto::class,
            output: SaleRecord::class,
            processor: SaleCollectPaymentProcessor::class,
            security: "is_granted('perm:sales.collect')",
            name: 'sale_collect_payment',
        ),
    ],
    normalizationContext: ['groups' => ['sale_record:read']],
    denormalizationContext: ['groups' => ['sale_record:write']],
    filters: [DataGridFilter::class],
    provider: SaleV2Provider::class,
    processor: SaleProcessor::class,
    mercure: true,
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

    /**
     * Client-supplied `Idempotency-Key` header for offline-sale retries (see
     * SaleProcessor::process()) — nullable because only offline-sale
     * requests (X-Offline-Sale + Idempotency-Key headers) populate it.
     * Unique so a retry of the same outbox item can never create a second
     * row: the DB itself is the second line of defence after the
     * application-level lookup in SaleProcessor.
     *
     * Deliberately NOT named/reusing `idempotencyKey` below — that field is
     * an unrelated deterministic sync marker derived from this row's own id
     * (SalesWriteGateway::buildOfflineMeta(), 'idem:sale:' . $saleId) and is
     * overwritten on every write(), so it can never carry a client-supplied
     * value across a retry.
     */
    #[ORM\Column(name: 'offline_retry_key', type: 'string', length: 36, nullable: true, unique: true)]
    private ?string $offlineRetryKey = null;

    #[Groups(['sale:read', 'sale_record:read', 'sale_record:write'])]
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
    #[ORM\Column(name: 'exchange_rate', type: Types::DECIMAL, precision: 14, scale: 6, options: [
        'default' => '1.000000',
    ])]
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

    /**
     * Cash-only rounding granted to the customer.  It is kept separately from
     * a commercial discount so the payment, cash session and audit trail can
     * explain why the amount collected is lower than the merchandise total.
     */
    #[Groups(['sale:read', 'sale_record:read', 'sale_record:write'])]
    #[ORM\Column(name: 'cash_rounding_adjustment', type: Types::DECIMAL, precision: 12, scale: 2, options: [
        'default' => '0.00',
    ])]
    #[ApiProperty(description: 'sale_record.cash_rounding_adjustment.label')]
    private string $cashRoundingAdjustment = '0.00';

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

    /** Remains true after the debt is fully collected. */
    #[Groups(['sale_record:read'])]
    #[ORM\Column(name: 'receivable_account', options: ['default' => false])]
    private bool $receivableAccount = false;

    public function isReceivableAccount(): bool
    {
        return $this->receivableAccount;
    }

    #[Groups(['sale_record:read', 'sale_record:write'])]
    #[ORM\Column(name: 'issued_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    #[ApiProperty(description: 'sale_record.issued_at.label')]
    private ?DateTimeInterface $issuedAt = null;

    #[Groups(['sale_record:read', 'sale_record:write'])]
    #[ORM\Column(name: 'issued_date', type: Types::DATE_IMMUTABLE, nullable: true)]
    #[ApiProperty(description: 'sale_record.issued_date.label')]
    private ?\DateTimeImmutable $issuedDate = null;

    #[Groups(['sale_record:read', 'sale_record:write'])]
    #[ORM\Column(name: 'due_date', type: Types::DATE_IMMUTABLE, nullable: true)]
    #[ApiProperty(description: 'sale_record.due_date.label')]
    private ?\DateTimeImmutable $dueDate = null;

    // Plain FK id, not an ORM relation: Sales must not depend on Billing's
    // CustomerSubscription entity (see tests/Architecture/ContextBoundaryTest).
    #[Groups(['sale_record:read'])]
    #[ORM\Column(name: 'customer_subscription_id', type: Types::STRING, length: 255, nullable: true)]
    #[ApiProperty(description: 'sale_record.customer_subscription.label')]
    private ?string $customerSubscriptionId = null;

    #[Groups(['sale_record:read'])]
    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE, nullable: true, options: [
        'default' => 'CURRENT_TIMESTAMP',
    ])]
    #[ApiProperty(description: 'sale_record.created_at.label')]
    private ?DateTimeInterface $createdAt = null;

    #[Groups(['sale_record:read'])]
    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    #[ApiProperty(description: 'sale_record.updated_at.label')]
    private ?DateTimeInterface $updatedAt = null;

    #[ORM\Column(name: 'deleted_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTimeInterface $deletedAt = null;

    #[Groups(['sale_record:read'])]
    #[ORM\Column(name: 'sync_status', type: 'string', length: 20, options: ['default' => 'SYNCED'])]
    private string $syncStatus = 'SYNCED';

    #[Groups(['sale_record:read'])]
    #[ORM\Column(name: 'local_uuid', type: 'string', length: 36, nullable: true)]
    private ?string $localUuid = null;

    #[Groups(['sale_record:read'])]
    #[ORM\Column(name: 'idempotency_key', type: 'string', length: 36, nullable: true)]
    private ?string $idempotencyKey = null;

    #[Groups(['sale_record:read'])]
    #[ORM\Column(name: 'synced_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTimeInterface $syncedAt = null;

    #[ORM\Column(name: 'device_id', type: 'string', length: 64, nullable: true)]
    private ?string $deviceId = null;

    #[Groups(['sale_record:read'])]
    #[ORM\Column(name: 'version_no', type: 'integer', options: ['default' => 1])]
    private int $versionNo = 1;

    #[Groups(['sale_record:read'])]
    #[ORM\Column(name: 'last_modified_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTimeInterface $lastModifiedAt = null;

    /**
     * ORM-managed V2 items. Not in normalization group to avoid N+1.
     *
     * @var Collection<int, SaleItemRecord>
     */
    #[ORM\OneToMany(
        targetEntity: SaleItemRecord::class,
        mappedBy: 'saleRecord',
        cascade: ['persist', 'remove'],
        orphanRemoval: true,
    )]
    private Collection $saleItemRecords;

    /**
     * @var Collection<int, SalePayment>
     */
    #[ORM\OneToMany(
        targetEntity: SalePayment::class,
        mappedBy: 'saleRecord',
        cascade: ['persist', 'remove'],
        orphanRemoval: true,
    )]
    private Collection $salePayments;

    /**
     * @var Collection<int, SaleTax>
     */
    #[ORM\OneToMany(
        targetEntity: SaleTax::class,
        mappedBy: 'saleRecord',
        cascade: ['persist', 'remove'],
        orphanRemoval: true,
    )]
    private Collection $saleTaxes;

    /**
     * @var Collection<int, SaleDiscount>
     */
    #[ORM\OneToMany(
        targetEntity: SaleDiscount::class,
        mappedBy: 'saleRecord',
        cascade: ['persist', 'remove'],
        orphanRemoval: true,
    )]
    private Collection $saleDiscounts;

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

    #[ORM\Column(name: 'series', type: 'string', length: 10, nullable: true)]
    #[Groups(['sale:read', 'sale_record:read'])]
    #[ApiProperty(description: 'sale_record.series.label')]
    private ?string $series = null;

    #[ORM\Column(name: 'number', type: 'string', length: 20, nullable: true)]
    #[Groups(['sale:read', 'sale_record:read'])]
    #[ApiProperty(description: 'sale_record.number.label')]
    private ?string $number = null;

    // ── Write-only transient fields ───────────────────────────────────────────

    // no ORM annotation — transient
    #[Groups(['sale:read'])]
    #[ApiProperty(description: 'sale_record.tax_rate.label')]
    private ?string $taxRate = null;

    #[Groups(['sale_record:write'])]
    private ?bool $emitInvoice = null;

    #[Groups(['sale_record:read', 'sale_record:write'])]
    #[ApiProperty(description: 'sale_record.note.label')]
    #[Assert\Length(max: 2000)]
    #[ORM\Column(name: 'note', type: Types::TEXT, nullable: true)]
    private ?string $note = null;

    /**
     * On write: payment lines from the client. On read: resolved from
     * sale_payments by the provider (payment_method_id, method_name, amount).
     *
     * @var array<int, array<string, mixed>>
     */
    #[Groups(['sale_record:read', 'sale_record:write'])]
    #[ApiProperty(description: 'sale_record.payments.label')]
    private array $payments = [];

    /**
     * Write-only settlement contract. PAYMENT entries represent collected
     * money and RECEIVABLE entries represent customer financing.
     *
     * @var array<int, array<string, mixed>>
     */
    #[Groups(['sale_record:write'])]
    private array $settlements = [];

    /**
     * Items for write path (mapped to SaleItemRecord entities by processor).
     * Not in read group; use /api/sales/{id}/items sub-resource.
     *
     * @var array<int, mixed>
     */
    #[Groups(['sale_record:write'])]
    private array $writeItems = [];

    /**
     * Compatibility flag for API/legacy clients that submit collected payment
     * lines below the total. The POS derives it from its Crédito allocation.
     */
    #[Groups(['sale_record:write'])]
    private bool $creditSale = false;

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
        $this->salePayments = new ArrayCollection();
        $this->saleTaxes = new ArrayCollection();
        $this->saleDiscounts = new ArrayCollection();
    }

    #[ORM\PrePersist]
    public function setTimestampsOnPersist(): void
    {
        if ($this->createdAt === null) {
            $this->createdAt = new \DateTime();
        }
        if ($this->lastModifiedAt === null) {
            $this->lastModifiedAt = new \DateTime();
        }
    }

    #[ORM\PreUpdate]
    public function setTimestampsOnUpdate(): void
    {
        $this->updatedAt = new \DateTime();
    }

    /**
     * Compute discountPercent from the stored discount_total and subtotal so that
     * it is available for API reads without requiring a separate write cycle.
     *
     * Formula: discountPercent = discount_total / (subtotal + discount_total) * 100
     * Falls back to 0 when subtotal + discount_total is zero.
     */
    #[ORM\PostLoad]
    public function computeDiscountPercentOnLoad(): void
    {
        $discountTotal = (float) $this->discountTotal;
        $subtotal = (float) $this->subtotal;
        $base = $subtotal + $discountTotal;
        if ($base > 0 && $discountTotal > 0) {
            $this->discountPercent = (string) round(($discountTotal / $base) * 100, 4);
        } else {
            $this->discountPercent = '0';
        }
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

    public function getOfflineRetryKey(): ?string
    {
        return $this->offlineRetryKey;
    }

    public function setOfflineRetryKey(?string $offlineRetryKey): static
    {
        $this->offlineRetryKey = $offlineRetryKey;

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

    public function getCashRoundingAdjustment(): string
    {
        return $this->cashRoundingAdjustment;
    }

    public function setCashRoundingAdjustment(string $cashRoundingAdjustment): static
    {
        $this->cashRoundingAdjustment = $cashRoundingAdjustment;

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
        if (is_numeric($balanceDue) && (float) $balanceDue > 0) {
            $this->receivableAccount = true;
        }

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

    public function getIssuedDate(): ?\DateTimeImmutable
    {
        return $this->issuedDate;
    }

    public function setIssuedDate(?\DateTimeImmutable $issuedDate): static
    {
        $this->issuedDate = $issuedDate;

        return $this;
    }

    public function getDueDate(): ?\DateTimeImmutable
    {
        return $this->dueDate;
    }

    public function setDueDate(?\DateTimeImmutable $dueDate): static
    {
        $this->dueDate = $dueDate;

        return $this;
    }

    public function getCustomerSubscriptionId(): ?string
    {
        return $this->customerSubscriptionId;
    }

    public function setCustomerSubscriptionId(?string $customerSubscriptionId): static
    {
        $this->customerSubscriptionId = $customerSubscriptionId;

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

    public function addSaleItemRecord(SaleItemRecord $item): static
    {
        if (!$this->saleItemRecords->contains($item)) {
            $this->saleItemRecords->add($item);
            $item->setSaleRecord($this);
        }

        return $this;
    }

    // ── SalePayment collection ────────────────────────────────────────────────

    /**
     * @return Collection<int, SalePayment>
     */
    public function getSalePayments(): Collection
    {
        return $this->salePayments;
    }

    public function addSalePayment(SalePayment $payment): static
    {
        if (!$this->salePayments->contains($payment)) {
            $this->salePayments->add($payment);
            $payment->setSaleRecord($this);
        }

        return $this;
    }

    public function clearSalePayments(): static
    {
        $this->salePayments->clear();

        return $this;
    }

    // ── SaleTax collection ────────────────────────────────────────────────────

    /**
     * @return Collection<int, SaleTax>
     */
    public function getSaleTaxes(): Collection
    {
        return $this->saleTaxes;
    }

    public function addSaleTax(SaleTax $tax): static
    {
        if (!$this->saleTaxes->contains($tax)) {
            $this->saleTaxes->add($tax);
            $tax->setSaleRecord($this);
        }

        return $this;
    }

    public function clearSaleTaxes(): static
    {
        $this->saleTaxes->clear();

        return $this;
    }

    // ── SaleDiscount collection ───────────────────────────────────────────────

    /**
     * @return Collection<int, SaleDiscount>
     */
    public function getSaleDiscounts(): Collection
    {
        return $this->saleDiscounts;
    }

    public function addSaleDiscount(SaleDiscount $discount): static
    {
        if (!$this->saleDiscounts->contains($discount)) {
            $this->saleDiscounts->add($discount);
            $discount->setSaleRecord($this);
        }

        return $this;
    }

    public function clearSaleDiscounts(): static
    {
        $this->saleDiscounts->clear();

        return $this;
    }

    // ── Offline/sync field accessors ─────────────────────────────────────────

    public function getSyncStatus(): string
    {
        return $this->syncStatus;
    }

    public function setSyncStatus(string $syncStatus): static
    {
        $this->syncStatus = $syncStatus;

        return $this;
    }

    public function getLocalUuid(): ?string
    {
        return $this->localUuid;
    }

    public function setLocalUuid(?string $localUuid): static
    {
        $this->localUuid = $localUuid;

        return $this;
    }

    public function getIdempotencyKey(): ?string
    {
        return $this->idempotencyKey;
    }

    public function setIdempotencyKey(?string $idempotencyKey): static
    {
        $this->idempotencyKey = $idempotencyKey;

        return $this;
    }

    public function getSyncedAt(): ?DateTimeInterface
    {
        return $this->syncedAt;
    }

    public function setSyncedAt(?DateTimeInterface $syncedAt): static
    {
        $this->syncedAt = $syncedAt;

        return $this;
    }

    public function getDeviceId(): ?string
    {
        return $this->deviceId;
    }

    public function setDeviceId(?string $deviceId): static
    {
        $this->deviceId = $deviceId;

        return $this;
    }

    public function getVersionNo(): int
    {
        return $this->versionNo;
    }

    public function setVersionNo(int $versionNo): static
    {
        $this->versionNo = $versionNo;

        return $this;
    }

    public function getLastModifiedAt(): ?DateTimeInterface
    {
        return $this->lastModifiedAt;
    }

    public function setLastModifiedAt(?DateTimeInterface $lastModifiedAt): static
    {
        $this->lastModifiedAt = $lastModifiedAt;

        return $this;
    }

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
        return $this->series;
    }

    public function setSerie(?string $serie): static
    {
        $this->series = $serie;

        return $this;
    }

    public function getNumero(): ?string
    {
        return $this->number;
    }

    public function setNumero(?string $numero): static
    {
        $this->number = $numero;

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
     * @return array<int, array<string, mixed>>
     */
    public function getSettlements(): array
    {
        return $this->settlements;
    }

    /**
     * @param array<int, array<string, mixed>> $settlements
     */
    public function setSettlements(array $settlements): static
    {
        $this->settlements = $settlements;

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

    public function isCreditSale(): bool
    {
        return $this->creditSale;
    }

    public function setCreditSale(bool $creditSale): static
    {
        $this->creditSale = $creditSale;

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
