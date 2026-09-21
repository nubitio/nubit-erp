<?php

declare(strict_types=1);

namespace App\AccountsPayable\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\AccountsPayable\Dto\SupplierBillPayDto;
use App\AccountsPayable\Dto\SupplierBillReversePaymentDto;
use App\AccountsPayable\Repository\SupplierBillRepository;
use App\AccountsPayable\State\SupplierBillCreateProcessor;
use App\AccountsPayable\State\SupplierBillPayProcessor;
use App\AccountsPayable\State\SupplierBillReversePaymentProcessor;
use App\AccountsPayable\State\SupplierBillVoidProcessor;
use App\Contacts\Entity\Supplier;
use App\Organization\Entity\Branch;
use App\Purchasing\Entity\Purchase;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Nubit\ApiPlatform\Doctrine\Filter\DataGridFilter;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SupplierBillRepository::class)]
#[ORM\Table(name: 'supplier_bill')]
#[ORM\Index(name: 'idx_supplier_bill_status_due_date', columns: ['status', 'due_date'])]
#[ORM\Index(name: 'idx_supplier_bill_supplier', columns: ['supplier_id'])]
#[ApiResource(
    operations: [
        new GetCollection(security: "is_granted('perm:accounts_payable.view')"),
        new Post(security: "is_granted('perm:accounts_payable.create')", processor: SupplierBillCreateProcessor::class),
        new Get(security: "is_granted('perm:accounts_payable.view')"),
        new Patch(security: "is_granted('perm:accounts_payable.create')"),
        new Delete(
            // Voids in place instead of deleting the row — keeps the audit
            // trail (paid bills must stay visible in reports/statements).
            // Blocked in SupplierBillVoidProcessor when payments were
            // already applied, mirroring Sales' SaleVoidUseCase guard.
            processor: SupplierBillVoidProcessor::class,
            security: "is_granted('perm:accounts_payable.void')",
        ),
        new Post(
            uriTemplate: '/supplier-bills/{id}/pay',
            normalizationContext: ['groups' => ['supplier_bill:read']],
            // SupplierBillPayDto has no serialization groups of its own, so
            // this must override the class-level denormalizationContext
            // below — otherwise the 'supplier_bill:write' group filter
            // strips every property before the DTO constructor runs.
            denormalizationContext: [],
            input: SupplierBillPayDto::class,
            output: SupplierBill::class,
            processor: SupplierBillPayProcessor::class,
            security: "is_granted('perm:accounts_payable.pay')",
        ),
        new Post(
            uriTemplate: '/supplier-bills/{id}/reverse-payment',
            normalizationContext: ['groups' => ['supplier_bill:read']],
            denormalizationContext: [],
            input: SupplierBillReversePaymentDto::class,
            output: SupplierBill::class,
            processor: SupplierBillReversePaymentProcessor::class,
            security: "is_granted('perm:accounts_payable.pay')",
        ),
    ],
    normalizationContext: ['groups' => ['supplier_bill:read']],
    denormalizationContext: ['groups' => ['supplier_bill:write']],
    filters: [DataGridFilter::class],
    mercure: true,
)]
#[ORM\HasLifecycleCallbacks]
class SupplierBill
{
    public const string PENDING = 'PENDING';

    public const string PARTIAL = 'PARTIAL';

    public const string PAID = 'PAID';

    public const string VOID = 'VOID';

    #[Groups(['supplier_bill:read'])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\Column(name: 'id', type: Types::STRING, length: 36)]
    private ?string $id = null;

    #[Assert\NotNull]
    #[Groups(['supplier_bill:read', 'supplier_bill:write'])]
    #[ApiProperty(description: 'supplier_bill.supplier.label', readableLink: true)]
    #[ORM\ManyToOne(targetEntity: Supplier::class)]
    #[ORM\JoinColumn(name: 'supplier_id', referencedColumnName: 'proveedor_id', nullable: false)]
    private Supplier $supplier;

    #[Assert\NotNull]
    #[Groups(['supplier_bill:read', 'supplier_bill:write'])]
    #[ApiProperty(description: 'supplier_bill.branch.label', readableLink: true)]
    #[ORM\ManyToOne(targetEntity: Branch::class)]
    #[ORM\JoinColumn(name: 'branch_id', referencedColumnName: 'local_id', nullable: false)]
    private Branch $branch;

    #[Groups(['supplier_bill:read', 'supplier_bill:write'])]
    #[ApiProperty(description: 'supplier_bill.purchase.label', readableLink: true)]
    #[ORM\ManyToOne(targetEntity: Purchase::class)]
    #[ORM\JoinColumn(name: 'purchase_id', referencedColumnName: 'id', nullable: true)]
    private ?Purchase $purchase = null;

    #[Assert\NotNull]
    #[Groups(['supplier_bill:read', 'supplier_bill:write'])]
    #[ApiProperty(description: 'supplier_bill.category.label', readableLink: true)]
    #[ORM\ManyToOne(targetEntity: ExpenseCategory::class)]
    #[ORM\JoinColumn(name: 'category_id', referencedColumnName: 'id', nullable: false)]
    private ExpenseCategory $category;

    #[Groups(['supplier_bill:read', 'supplier_bill:write'])]
    #[ApiProperty(description: 'supplier_bill.document_type.label')]
    #[ORM\Column(name: 'document_type', length: 50, nullable: true)]
    private ?string $documentType = null;

    #[Groups(['supplier_bill:read', 'supplier_bill:write'])]
    #[ApiProperty(description: 'supplier_bill.series.label')]
    #[ORM\Column(name: 'series', length: 20, nullable: true)]
    private ?string $series = null;

    #[Groups(['supplier_bill:read', 'supplier_bill:write'])]
    #[ApiProperty(description: 'supplier_bill.number.label')]
    #[ORM\Column(name: 'number', length: 20, nullable: true)]
    private ?string $number = null;

    #[Assert\NotNull]
    #[Groups(['supplier_bill:read', 'supplier_bill:write'])]
    #[ApiProperty(description: 'supplier_bill.issue_date.label')]
    #[ORM\Column(name: 'issue_date', type: Types::DATE_IMMUTABLE)]
    private DateTimeImmutable $issueDate;

    #[Assert\NotNull]
    #[Groups(['supplier_bill:read', 'supplier_bill:write'])]
    #[ApiProperty(description: 'supplier_bill.due_date.label')]
    #[ORM\Column(name: 'due_date', type: Types::DATE_IMMUTABLE)]
    private DateTimeImmutable $dueDate;

    #[Groups(['supplier_bill:read', 'supplier_bill:write'])]
    #[ApiProperty(description: 'supplier_bill.currency.label')]
    #[ORM\Column(name: 'currency', length: 3, options: ['default' => 'PEN'])]
    private string $currency = 'PEN';

    #[Assert\NotNull]
    #[Assert\Positive]
    #[Groups(['supplier_bill:read', 'supplier_bill:write'])]
    #[ApiProperty(description: 'supplier_bill.amount.label')]
    #[ORM\Column(name: 'amount', type: Types::DECIMAL, precision: 12, scale: 2)]
    private string $amount;

    #[Groups(['supplier_bill:read'])]
    #[ApiProperty(description: 'supplier_bill.paid_total.label')]
    #[ORM\Column(name: 'paid_total', type: Types::DECIMAL, precision: 12, scale: 2, options: ['default' => '0.00'])]
    private string $paidTotal = '0.00';

    #[Groups(['supplier_bill:read'])]
    #[ApiProperty(description: 'supplier_bill.balance_due.label')]
    #[ORM\Column(name: 'balance_due', type: Types::DECIMAL, precision: 12, scale: 2)]
    private string $balanceDue;

    #[Groups(['supplier_bill:read'])]
    #[ApiProperty(description: 'supplier_bill.status.label')]
    #[ORM\Column(name: 'status', length: 20, options: ['default' => self::PENDING])]
    private string $status = self::PENDING;

    /** @var Collection<int, SupplierPayment> */
    #[Groups(['supplier_bill:read'])]
    #[ORM\OneToMany(mappedBy: 'supplierBill', targetEntity: SupplierPayment::class)]
    private Collection $payments;

    #[Groups(['supplier_bill:read', 'supplier_bill:write'])]
    #[ApiProperty(description: 'supplier_bill.note.label')]
    #[ORM\Column(name: 'note', type: Types::TEXT, nullable: true)]
    private ?string $note = null;

    #[Groups(['supplier_bill:read'])]
    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
        $this->payments = new ArrayCollection();
    }

    /** @return Collection<int, SupplierPayment> */
    public function getPayments(): Collection
    {
        return $this->payments;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getSupplier(): Supplier
    {
        return $this->supplier;
    }

    public function setSupplier(Supplier $supplier): static
    {
        $this->supplier = $supplier;

        return $this;
    }

    public function getBranch(): Branch
    {
        return $this->branch;
    }

    public function setBranch(Branch $branch): static
    {
        $this->branch = $branch;

        return $this;
    }

    public function getPurchase(): ?Purchase
    {
        return $this->purchase;
    }

    public function setPurchase(?Purchase $purchase): static
    {
        $this->purchase = $purchase;

        return $this;
    }

    public function getCategory(): ExpenseCategory
    {
        return $this->category;
    }

    public function setCategory(ExpenseCategory $category): static
    {
        $this->category = $category;

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

    public function getSeries(): ?string
    {
        return $this->series;
    }

    public function setSeries(?string $series): static
    {
        $this->series = $series;

        return $this;
    }

    public function getNumber(): ?string
    {
        return $this->number;
    }

    public function setNumber(?string $number): static
    {
        $this->number = $number;

        return $this;
    }

    public function getIssueDate(): DateTimeImmutable
    {
        return $this->issueDate;
    }

    public function setIssueDate(DateTimeImmutable $issueDate): static
    {
        $this->issueDate = $issueDate;

        return $this;
    }

    public function getDueDate(): DateTimeImmutable
    {
        return $this->dueDate;
    }

    public function setDueDate(DateTimeImmutable $dueDate): static
    {
        $this->dueDate = $dueDate;

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

    public function getAmount(): string
    {
        return $this->amount;
    }

    public function setAmount(string $amount): static
    {
        $this->amount = $amount;

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

    public function getBalanceDue(): string
    {
        return $this->balanceDue;
    }

    public function setBalanceDue(string $balanceDue): static
    {
        $this->balanceDue = $balanceDue;

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

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): static
    {
        $this->note = $note;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * balanceDue starts equal to amount. Not set in setAmount() because
     * amount can also be edited later without wanting to reset a balance
     * that payments have already reduced — only the very first persist
     * should seed it.
     */
    #[ORM\PrePersist]
    public function initializeBalanceDue(): void
    {
        $this->balanceDue = $this->amount;
    }
}
