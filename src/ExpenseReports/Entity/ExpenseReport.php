<?php

declare(strict_types=1);

namespace App\ExpenseReports\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\AccountsPayable\Entity\ExpenseCategory;
use App\ExpenseReports\Dto\ExpenseReportReimburseDto;
use App\ExpenseReports\Dto\ExpenseReportReviewDto;
use App\ExpenseReports\Repository\ExpenseReportRepository;
use App\ExpenseReports\State\ExpenseReportApproveProcessor;
use App\ExpenseReports\State\ExpenseReportReimburseProcessor;
use App\ExpenseReports\State\ExpenseReportRejectProcessor;
use App\Identity\Entity\User;
use App\Organization\Entity\Branch;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Nubit\AdminBundle\Media\Entity\Media;
use Nubit\ApiPlatform\Doctrine\Filter\DataGridFilter;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * An employee's out-of-pocket expense claim (viáticos) — distinct from
 * SupplierBill, which is a supplier's formal invoice with a RUC. The
 * "employee" is the existing User entity; there's no dedicated Employee
 * entity yet (see Fase 7 / Planilla in the roadmap).
 */
#[ORM\Entity(repositoryClass: ExpenseReportRepository::class)]
#[ORM\Table(name: 'expense_report')]
#[ORM\Index(name: 'idx_expense_report_status', columns: ['status'])]
#[ORM\Index(name: 'idx_expense_report_employee', columns: ['employee_id'])]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Post(),
        new Get(),
        new Patch(),
        new Delete(),
        new Post(
            uriTemplate: '/expense-reports/{id}/approve',
            normalizationContext: ['groups' => ['expense_report:read']],
            // ExpenseReportReviewDto has no serialization groups of its own,
            // so this must override the class-level denormalizationContext
            // below — otherwise the 'expense_report:write' group filter
            // strips every property before the DTO constructor runs.
            denormalizationContext: [],
            input: ExpenseReportReviewDto::class,
            output: ExpenseReport::class,
            processor: ExpenseReportApproveProcessor::class,
        ),
        new Post(
            uriTemplate: '/expense-reports/{id}/reject',
            normalizationContext: ['groups' => ['expense_report:read']],
            denormalizationContext: [],
            input: ExpenseReportReviewDto::class,
            output: ExpenseReport::class,
            processor: ExpenseReportRejectProcessor::class,
        ),
        new Post(
            uriTemplate: '/expense-reports/{id}/reimburse',
            normalizationContext: ['groups' => ['expense_report:read']],
            denormalizationContext: [],
            input: ExpenseReportReimburseDto::class,
            output: ExpenseReport::class,
            processor: ExpenseReportReimburseProcessor::class,
        ),
    ],
    normalizationContext: ['groups' => ['expense_report:read']],
    denormalizationContext: ['groups' => ['expense_report:write']],
    filters: [DataGridFilter::class],
    mercure: true,
)]
class ExpenseReport
{
    public const string PENDING = 'PENDING';

    public const string APPROVED = 'APPROVED';

    public const string REJECTED = 'REJECTED';

    public const string REIMBURSED = 'REIMBURSED';

    #[Groups(['expense_report:read'])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\Column(name: 'id', type: Types::STRING, length: 36)]
    private ?string $id = null;

    #[Assert\NotNull]
    #[Groups(['expense_report:read', 'expense_report:write'])]
    #[ApiProperty(description: 'expense_report.employee.label', readableLink: true)]
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'employee_id', referencedColumnName: 'usuario_id', nullable: false)]
    private User $employee;

    #[Assert\NotNull]
    #[Groups(['expense_report:read', 'expense_report:write'])]
    #[ApiProperty(description: 'expense_report.category.label', readableLink: true)]
    #[ORM\ManyToOne(targetEntity: ExpenseCategory::class)]
    #[ORM\JoinColumn(name: 'category_id', referencedColumnName: 'id', nullable: false)]
    private ExpenseCategory $category;

    #[Assert\NotNull]
    #[Groups(['expense_report:read', 'expense_report:write'])]
    #[ApiProperty(description: 'expense_report.branch.label', readableLink: true)]
    #[ORM\ManyToOne(targetEntity: Branch::class)]
    #[ORM\JoinColumn(name: 'branch_id', referencedColumnName: 'local_id', nullable: false)]
    private Branch $branch;

    #[Assert\NotBlank]
    #[Groups(['expense_report:read', 'expense_report:write'])]
    #[ApiProperty(description: 'expense_report.description.label')]
    #[ORM\Column(name: 'description', length: 255)]
    private string $description;

    #[Assert\NotNull]
    #[Assert\Positive]
    #[Groups(['expense_report:read', 'expense_report:write'])]
    #[ApiProperty(description: 'expense_report.amount.label')]
    #[ORM\Column(name: 'amount', type: Types::DECIMAL, precision: 12, scale: 2)]
    private string $amount;

    #[Groups(['expense_report:read', 'expense_report:write'])]
    #[ApiProperty(description: 'expense_report.currency.label')]
    #[ORM\Column(name: 'currency', length: 3, options: ['default' => 'PEN'])]
    private string $currency = 'PEN';

    #[Assert\NotNull]
    #[Groups(['expense_report:read', 'expense_report:write'])]
    #[ApiProperty(description: 'expense_report.expense_date.label')]
    #[ORM\Column(name: 'expense_date', type: Types::DATE_IMMUTABLE)]
    private DateTimeImmutable $expenseDate;

    #[Groups(['expense_report:read', 'expense_report:write'])]
    #[ApiProperty(description: 'expense_report.receipt_media.label', readableLink: true)]
    #[ORM\ManyToOne(targetEntity: Media::class)]
    #[ORM\JoinColumn(name: 'receipt_media_id', referencedColumnName: 'id', nullable: true)]
    private ?Media $receiptMedia = null;

    #[Groups(['expense_report:read'])]
    #[ApiProperty(description: 'expense_report.status.label')]
    #[ORM\Column(name: 'status', length: 20, options: ['default' => self::PENDING])]
    private string $status = self::PENDING;

    #[Groups(['expense_report:read'])]
    #[ApiProperty(description: 'expense_report.reviewed_by.label', readableLink: true)]
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'reviewed_by_id', referencedColumnName: 'usuario_id', nullable: true)]
    private ?User $reviewedBy = null;

    #[Groups(['expense_report:read'])]
    #[ApiProperty(description: 'expense_report.reviewed_at.label')]
    #[ORM\Column(name: 'reviewed_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $reviewedAt = null;

    #[Groups(['expense_report:read'])]
    #[ApiProperty(description: 'expense_report.review_note.label')]
    #[ORM\Column(name: 'review_note', type: Types::TEXT, nullable: true)]
    private ?string $reviewNote = null;

    #[Groups(['expense_report:read'])]
    #[ApiProperty(description: 'expense_report.reimbursed_at.label')]
    #[ORM\Column(name: 'reimbursed_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $reimbursedAt = null;

    #[Groups(['expense_report:read', 'expense_report:write'])]
    #[ApiProperty(description: 'expense_report.note.label')]
    #[ORM\Column(name: 'note', type: Types::TEXT, nullable: true)]
    private ?string $note = null;

    #[Groups(['expense_report:read'])]
    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getEmployee(): User
    {
        return $this->employee;
    }

    public function setEmployee(User $employee): static
    {
        $this->employee = $employee;

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

    public function getBranch(): Branch
    {
        return $this->branch;
    }

    public function setBranch(Branch $branch): static
    {
        $this->branch = $branch;

        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

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

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): static
    {
        $this->currency = $currency;

        return $this;
    }

    public function getExpenseDate(): DateTimeImmutable
    {
        return $this->expenseDate;
    }

    public function setExpenseDate(DateTimeImmutable $expenseDate): static
    {
        $this->expenseDate = $expenseDate;

        return $this;
    }

    public function getReceiptMedia(): ?Media
    {
        return $this->receiptMedia;
    }

    public function setReceiptMedia(?Media $receiptMedia): static
    {
        $this->receiptMedia = $receiptMedia;

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

    public function getReviewedBy(): ?User
    {
        return $this->reviewedBy;
    }

    public function setReviewedBy(?User $reviewedBy): static
    {
        $this->reviewedBy = $reviewedBy;

        return $this;
    }

    public function getReviewedAt(): ?DateTimeImmutable
    {
        return $this->reviewedAt;
    }

    public function setReviewedAt(?DateTimeImmutable $reviewedAt): static
    {
        $this->reviewedAt = $reviewedAt;

        return $this;
    }

    public function getReviewNote(): ?string
    {
        return $this->reviewNote;
    }

    public function setReviewNote(?string $reviewNote): static
    {
        $this->reviewNote = $reviewNote;

        return $this;
    }

    public function getReimbursedAt(): ?DateTimeImmutable
    {
        return $this->reimbursedAt;
    }

    public function setReimbursedAt(?DateTimeImmutable $reimbursedAt): static
    {
        $this->reimbursedAt = $reimbursedAt;

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
}
