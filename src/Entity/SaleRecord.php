<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Core\Doctrine\Filter\DevExtremeFilter;
use App\Core\Util\ReceiptPayload;
use App\Repository\SaleRecordRepository;
use App\State\Sale\SaleV2Processor;
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
#[ApiResource(
    shortName: 'Sale',
    normalizationContext: ['groups' => ['sale_record:read']],
    denormalizationContext: ['groups' => ['sale_record:write']],
    filters: [DevExtremeFilter::class],
    operations: [
        new GetCollection(),
        new Get(),
        new Post(),
        new Patch(),
    ],
    provider: SaleV2Provider::class,
    processor: SaleV2Processor::class,
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
    private ?Branch $branch = null;

    #[Groups(['sale_record:read', 'sale_record:write'])]
    #[ORM\ManyToOne(targetEntity: Warehouse::class)]
    #[ORM\JoinColumn(name: 'warehouse_id', referencedColumnName: 'almacen_id', nullable: true)]
    private ?Warehouse $warehouse = null;

    #[ORM\Column(name: 'cash_register_id', type: 'integer', nullable: true)]
    private ?int $cashRegisterId = null;

    #[ORM\Column(name: 'cash_session_id', type: 'integer', nullable: true)]
    private ?int $cashSessionId = null;

    #[Groups(['sale_record:read', 'sale_record:write'])]
    #[ORM\ManyToOne(targetEntity: Customer::class)]
    #[ORM\JoinColumn(name: 'customer_id', referencedColumnName: 'id', nullable: true)]
    private ?Customer $customer = null;

    #[Groups(['sale_record:read', 'sale_record:write'])]
    #[ORM\Column(name: 'seller_user_id', type: 'integer', nullable: true)]
    private ?int $sellerUserId = null;

    #[Groups(['sale_record:read'])]
    #[ORM\Column(name: 'status', type: 'string', length: 30, options: ['default' => 'COMPLETED'])]
    private string $status = 'COMPLETED';

    #[Groups(['sale_record:read', 'sale_record:write'])]
    #[ORM\Column(name: 'currency', type: 'string', length: 3, options: ['default' => 'PEN'])]
    private string $currency = 'PEN';

    #[Groups(['sale_record:read', 'sale_record:write'])]
    #[ORM\Column(name: 'exchange_rate', type: Types::DECIMAL, precision: 14, scale: 6, options: ['default' => '1.000000'])]
    private string $exchangeRate = '1.000000';

    #[Groups(['sale_record:read'])]
    #[ORM\Column(name: 'subtotal', type: Types::DECIMAL, precision: 12, scale: 2, options: ['default' => '0.00'])]
    private string $subtotal = '0.00';

    #[Groups(['sale_record:read'])]
    #[ORM\Column(name: 'discount_total', type: Types::DECIMAL, precision: 12, scale: 2, options: ['default' => '0.00'])]
    private string $discountTotal = '0.00';

    #[Groups(['sale_record:read'])]
    #[ORM\Column(name: 'tax_total', type: Types::DECIMAL, precision: 12, scale: 2, options: ['default' => '0.00'])]
    private string $taxTotal = '0.00';

    #[Groups(['sale_record:read', 'sale_record:write'])]
    #[ORM\Column(name: 'grand_total', type: Types::DECIMAL, precision: 12, scale: 2, options: ['default' => '0.00'])]
    private string $grandTotal = '0.00';

    #[Groups(['sale_record:read'])]
    #[ORM\Column(name: 'paid_total', type: Types::DECIMAL, precision: 12, scale: 2, options: ['default' => '0.00'])]
    private string $paidTotal = '0.00';

    #[Groups(['sale_record:read'])]
    #[ORM\Column(name: 'change_total', type: Types::DECIMAL, precision: 12, scale: 2, options: ['default' => '0.00'])]
    private string $changeTotal = '0.00';

    #[Groups(['sale_record:read'])]
    #[ORM\Column(name: 'balance_due', type: Types::DECIMAL, precision: 12, scale: 2, options: ['default' => '0.00'])]
    private string $balanceDue = '0.00';

    #[Groups(['sale_record:read', 'sale_record:write'])]
    #[ORM\Column(name: 'issued_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTimeInterface $issuedAt = null;

    #[Groups(['sale_record:read'])]
    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE, nullable: true, options: ['default' => 'CURRENT_TIMESTAMP'])]
    private ?DateTimeInterface $createdAt = null;

    #[Groups(['sale_record:read'])]
    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTimeInterface $updatedAt = null;

    #[ORM\Column(name: 'deleted_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTimeInterface $deletedAt = null;

    /**
     * Not in normalization group to avoid N+1; use /api/sales/{id}/items sub-resource.
     *
     * @var Collection<int, SaleItemRecord>
     */
    #[ORM\OneToMany(targetEntity: SaleItemRecord::class, mappedBy: 'saleRecord')]
    private Collection $items;

    // ── Transient fields enriched by SaleV2Provider ──────────────────────────

    #[Groups(['sale_record:read'])]
    private ?string $taxDocumentId = null;

    #[Groups(['sale_record:read'])]
    private bool $hasInvoice = false;

    #[Groups(['sale_record:read'])]
    private ?string $invoiceFullNumber = null;

    #[Groups(['sale_record:read'])]
    private ?string $sunatStatus = null;

    #[Groups(['sale_record:read'])]
    private ?string $publicReceiptUrl = null;

    /** Resolved from sale_payments by provider */
    #[Groups(['sale_record:read', 'sale_record:write'])]
    private ?PaymentMethod $paymentMethod = null;

    /** Computed: discount_total / (subtotal + discount_total) * 100 */
    #[Groups(['sale_record:read'])]
    private ?string $discountPercent = null;

    // ── Write-only transient fields ───────────────────────────────────────────

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

    public function __construct()
    {
        $this->items = new ArrayCollection();
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
     * @return Collection<int, SaleItemRecord>
     */
    public function getItems(): Collection
    {
        return $this->items;
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
}
