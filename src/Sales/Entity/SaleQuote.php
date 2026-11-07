<?php

declare(strict_types=1);

namespace App\Sales\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Contacts\Entity\Customer;
use App\Inventory\Entity\Warehouse;
use App\Organization\Entity\Branch;
use App\Sales\Dto\SaleQuoteConvertDto;
use App\Sales\Dto\SaleQuoteSendDto;
use App\Sales\Repository\SaleQuoteRepository;
use App\Sales\State\SaleQuoteConvertProcessor;
use App\Sales\State\SaleQuoteProcessor;
use App\Sales\State\SaleQuoteProvider;
use App\Sales\State\SaleQuoteSendProcessor;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Nubit\ApiPlatform\Doctrine\Filter\DataGridFilter;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Pre-sale quote: same shape as SaleRecord but with no SUNAT emission and
 * no payments. Converts into a real SaleRecord via SaleQuoteConvertProcessor
 * — never through SalesWriteGateway::write(), which always tries to
 * clean-up-and-reconstruct payments/taxes/discounts a quote doesn't have.
 */
#[ORM\Entity(repositoryClass: SaleQuoteRepository::class)]
#[ORM\Table(name: 'sale_quotes')]
#[ORM\Index(name: 'idx_sale_quotes_status_issued', columns: ['status', 'issued_at'])]
#[ORM\Index(name: 'idx_sale_quotes_customer', columns: ['customer_id'])]
#[ORM\Index(name: 'idx_sale_quotes_valid_until', columns: ['valid_until'])]
#[ORM\Index(name: 'idx_sale_quotes_branch', columns: ['branch_id'])]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new GetCollection(security: "is_granted('perm:sale_quotes.view')"),
        new Get(security: "is_granted('perm:sale_quotes.view')"),
        new Post(security: "is_granted('perm:sale_quotes.create')"),
        new Patch(security: "is_granted('perm:sale_quotes.edit')"),
        new Delete(security: "is_granted('perm:sale_quotes.void')"),
        new Post(
            uriTemplate: '/sale-quotes/{id}/convert',
            normalizationContext: ['groups' => ['sale_record:read']],
            security: "is_granted('perm:sale_quotes.convert') and is_granted('perm:sales.create')",
            // SaleQuoteConvertDto has no serialization groups of its own —
            // without this override, the class-level denormalizationContext
            // below (sale_quote:write group) strips every property before
            // the DTO constructor runs. Bug already hit on
            // /supplier-bills/{id}/pay, /sales/{id}/collect, and every
            // custom Post in Fase 1's bank reconciliation module.
            denormalizationContext: [],
            input: SaleQuoteConvertDto::class,
            output: SaleRecord::class,
            processor: SaleQuoteConvertProcessor::class,
            name: 'sale_quote_convert',
        ),
        new Post(
            uriTemplate: '/sale-quotes/{id}/send',
            denormalizationContext: [],
            security: "is_granted('perm:sale_quotes.send')",
            input: SaleQuoteSendDto::class,
            output: SaleQuote::class,
            processor: SaleQuoteSendProcessor::class,
            name: 'sale_quote_send',
        ),
    ],
    normalizationContext: ['groups' => ['sale_quote:read']],
    denormalizationContext: ['groups' => ['sale_quote:write']],
    filters: [DataGridFilter::class],
    provider: SaleQuoteProvider::class,
    processor: SaleQuoteProcessor::class,
    mercure: true,
)]
class SaleQuote
{
    public const string STATUS_DRAFT = 'draft';

    public const string STATUS_SENT = 'sent';

    public const string STATUS_ACCEPTED = 'accepted';

    public const string STATUS_EXPIRED = 'expired';

    public const string STATUS_CONVERTED = 'converted';

    #[Groups(['sale_quote:read'])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\Column(name: 'id', type: Types::STRING, length: 36)]
    private ?string $id = null;

    #[Groups(['sale_quote:read', 'sale_quote:write'])]
    #[ApiProperty(description: 'sale_quote.branch.label', readableLink: true)]
    #[ORM\ManyToOne(targetEntity: Branch::class)]
    #[ORM\JoinColumn(name: 'branch_id', referencedColumnName: 'local_id', nullable: true)]
    private ?Branch $branch = null;

    #[Groups(['sale_quote:read', 'sale_quote:write'])]
    #[ApiProperty(description: 'sale_quote.warehouse.label', readableLink: true)]
    #[ORM\ManyToOne(targetEntity: Warehouse::class)]
    #[ORM\JoinColumn(name: 'warehouse_id', referencedColumnName: 'almacen_id', nullable: true)]
    private ?Warehouse $warehouse = null;

    #[Groups(['sale_quote:read', 'sale_quote:write'])]
    #[ApiProperty(description: 'sale_quote.customer.label', readableLink: true)]
    #[ORM\ManyToOne(targetEntity: Customer::class)]
    #[ORM\JoinColumn(name: 'customer_id', referencedColumnName: 'id', nullable: true)]
    private ?Customer $customer = null;

    #[Groups(['sale_quote:read'])]
    #[ORM\Column(name: 'seller_user_id', nullable: true)]
    private ?int $sellerUserId = null;

    #[Groups(['sale_quote:read', 'sale_quote:write'])]
    #[ORM\Column(name: 'status', length: 20, options: ['default' => self::STATUS_DRAFT])]
    private string $status = self::STATUS_DRAFT;

    #[Groups(['sale_quote:read', 'sale_quote:write'])]
    #[ORM\Column(name: 'currency', length: 3, options: ['default' => 'PEN'])]
    private string $currency = 'PEN';

    #[Groups(['sale_quote:read'])]
    #[ORM\Column(name: 'subtotal', type: Types::DECIMAL, precision: 12, scale: 2, options: ['default' => '0.00'])]
    private string $subtotal = '0.00';

    #[Groups(['sale_quote:read'])]
    #[ORM\Column(name: 'discount_total', type: Types::DECIMAL, precision: 12, scale: 2, options: ['default' => '0.00'])]
    private string $discountTotal = '0.00';

    #[Groups(['sale_quote:read'])]
    #[ORM\Column(name: 'tax_total', type: Types::DECIMAL, precision: 12, scale: 2, options: ['default' => '0.00'])]
    private string $taxTotal = '0.00';

    #[Groups(['sale_quote:read'])]
    #[ORM\Column(name: 'grand_total', type: Types::DECIMAL, precision: 12, scale: 2, options: ['default' => '0.00'])]
    private string $grandTotal = '0.00';

    #[Groups(['sale_quote:read', 'sale_quote:write'])]
    #[ORM\Column(name: 'issued_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $issuedAt;

    #[Groups(['sale_quote:read', 'sale_quote:write'])]
    #[ORM\Column(name: 'valid_until', type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $validUntil = null;

    #[Groups(['sale_quote:read', 'sale_quote:write'])]
    #[ORM\Column(name: 'note', type: Types::TEXT, nullable: true)]
    private ?string $note = null;

    /**
     * Not exposed as a relation (avoids a bidirectional SaleRecord<->SaleQuote
     * coupling for a link that only ever gets set once, on convert) — plain
     * nullable string FK, same reasoning SaleRecord itself uses for
     * customerSubscriptionId.
     */
    #[Groups(['sale_quote:read'])]
    #[ORM\Column(name: 'converted_sale_id', length: 36, nullable: true, unique: true)]
    private ?string $convertedSaleId = null;

    #[Groups(['sale_quote:read'])]
    #[ORM\Column(name: 'converted_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $convertedAt = null;

    #[Groups(['sale_quote:read'])]
    #[ORM\Column(name: 'created_by', nullable: true)]
    private ?int $createdBy = null;

    #[Groups(['sale_quote:read'])]
    #[ORM\Column(name: 'updated_by', nullable: true)]
    private ?int $updatedBy = null;

    #[Groups(['sale_quote:read'])]
    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    #[Groups(['sale_quote:read'])]
    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $updatedAt = null;

    /** @var Collection<int, SaleQuoteItem> */
    #[Groups(['sale_quote:read'])]
    #[ORM\OneToMany(
        targetEntity: SaleQuoteItem::class,
        mappedBy: 'saleQuote',
        cascade: ['persist', 'remove'],
        orphanRemoval: true,
    )]
    private Collection $items;

    /**
     * Write-only transient payload — mirrors SaleRecord's own `writeItems`
     * property. SaleQuoteProcessor consumes this array and builds real
     * SaleQuoteItem entities from it (see SaleQuoteService::buildItemsFromPayload).
     *
     * @var array<int, array<string, mixed>>
     */
    #[Groups(['sale_quote:write'])]
    private array $writeItems = [];

    public function __construct()
    {
        $this->items = new ArrayCollection();
        $this->issuedAt = new DateTimeImmutable();
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): ?string
    {
        return $this->id;
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

    public function getIssuedAt(): DateTimeImmutable
    {
        return $this->issuedAt;
    }

    public function setIssuedAt(DateTimeImmutable $issuedAt): static
    {
        $this->issuedAt = $issuedAt;

        return $this;
    }

    public function getValidUntil(): ?DateTimeImmutable
    {
        return $this->validUntil;
    }

    public function setValidUntil(?DateTimeImmutable $validUntil): static
    {
        $this->validUntil = $validUntil;

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

    public function getConvertedSaleId(): ?string
    {
        return $this->convertedSaleId;
    }

    public function setConvertedSaleId(?string $convertedSaleId): static
    {
        $this->convertedSaleId = $convertedSaleId;

        return $this;
    }

    public function getConvertedAt(): ?DateTimeImmutable
    {
        return $this->convertedAt;
    }

    public function setConvertedAt(?DateTimeImmutable $convertedAt): static
    {
        $this->convertedAt = $convertedAt;

        return $this;
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

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /** @return Collection<int, SaleQuoteItem> */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(SaleQuoteItem $item): static
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setSaleQuote($this);
        }

        return $this;
    }

    public function clearItems(): static
    {
        foreach ($this->items as $item) {
            $this->items->removeElement($item);
        }

        return $this;
    }

    /** @return array<int, array<string, mixed>> */
    public function getWriteItems(): array
    {
        return $this->writeItems;
    }

    /** @param array<int, array<string, mixed>> $writeItems */
    public function setWriteItems(array $writeItems): static
    {
        $this->writeItems = $writeItems;

        return $this;
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }
}
