<?php

declare(strict_types=1);

namespace App\Payroll\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Organization\Entity\Company;
use App\Payroll\Dto\PayrollRunApproveDto;
use App\Payroll\Dto\PayrollRunCalculateDto;
use App\Payroll\Dto\PayrollRunPayDto;
use App\Payroll\Repository\PayrollRunRepository;
use App\Payroll\State\PayrollRunApproveProcessor;
use App\Payroll\State\PayrollRunCalculateProcessor;
use App\Payroll\State\PayrollRunPayProcessor;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Nubit\ApiPlatform\Doctrine\Filter\DataGridFilter;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A payroll processing batch for one Company and period. `type` decides
 * which formula PayrollCalculationService applies — regular monthly
 * salary, or one of the two seasonal benefits (gratificación, CTS) that
 * Peruvian labor law requires on fixed semesters. No `branch` relation,
 * same reasoning as FixedAsset/Employee.
 */
#[ORM\Entity(repositoryClass: PayrollRunRepository::class)]
#[ORM\Table(name: 'payroll_run')]
#[ORM\Index(name: 'idx_payroll_run_status', columns: ['status'])]
#[ORM\Index(name: 'idx_payroll_run_company', columns: ['company_id'])]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Post(),
        new Get(),
        new Patch(),
        new Delete(),
        new Post(
            uriTemplate: '/payroll-runs/{id}/calculate',
            normalizationContext: ['groups' => ['payroll_run:read']],
            // PayrollRunCalculateDto has no serialization groups of its own,
            // so this must override the class-level denormalizationContext
            // below — otherwise the 'payroll_run:write' group filter strips
            // every property before the DTO constructor runs.
            denormalizationContext: [],
            input: PayrollRunCalculateDto::class,
            output: PayrollRun::class,
            processor: PayrollRunCalculateProcessor::class,
        ),
        new Post(
            uriTemplate: '/payroll-runs/{id}/approve',
            normalizationContext: ['groups' => ['payroll_run:read']],
            denormalizationContext: [],
            input: PayrollRunApproveDto::class,
            output: PayrollRun::class,
            processor: PayrollRunApproveProcessor::class,
        ),
        new Post(
            uriTemplate: '/payroll-runs/{id}/pay',
            normalizationContext: ['groups' => ['payroll_run:read']],
            denormalizationContext: [],
            input: PayrollRunPayDto::class,
            output: PayrollRun::class,
            processor: PayrollRunPayProcessor::class,
        ),
    ],
    normalizationContext: ['groups' => ['payroll_run:read']],
    denormalizationContext: ['groups' => ['payroll_run:write']],
    filters: [DataGridFilter::class],
    mercure: true,
)]
class PayrollRun
{
    public const string DRAFT = 'DRAFT';

    public const string CALCULATED = 'CALCULATED';

    public const string APPROVED = 'APPROVED';

    public const string PAID = 'PAID';

    public const string REGULAR = 'REGULAR';

    public const string GRATIFICATION_JULY = 'GRATIFICATION_JULY';

    public const string GRATIFICATION_DECEMBER = 'GRATIFICATION_DECEMBER';

    public const string CTS_MAY = 'CTS_MAY';

    public const string CTS_NOVEMBER = 'CTS_NOVEMBER';

    #[Groups(['payroll_run:read'])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\Column(name: 'id', type: Types::STRING, length: 36)]
    private ?string $id = null;

    #[Assert\NotNull]
    #[Groups(['payroll_run:read', 'payroll_run:write'])]
    #[ApiProperty(description: 'payroll_run.company.label', readableLink: true)]
    #[ORM\ManyToOne(targetEntity: Company::class)]
    #[ORM\JoinColumn(name: 'company_id', referencedColumnName: 'empresa_id', nullable: false)]
    private Company $company;

    #[Assert\NotNull]
    #[Groups(['payroll_run:read', 'payroll_run:write'])]
    #[ApiProperty(description: 'payroll_run.period.label')]
    #[ORM\Column(name: 'period', type: Types::DATE_IMMUTABLE)]
    private DateTimeImmutable $period;

    #[Assert\NotBlank]
    #[Groups(['payroll_run:read', 'payroll_run:write'])]
    #[ApiProperty(description: 'payroll_run.type.label')]
    #[ORM\Column(name: 'type', length: 30, options: ['default' => self::REGULAR])]
    private string $type = self::REGULAR;

    #[Groups(['payroll_run:read'])]
    #[ApiProperty(description: 'payroll_run.status.label')]
    #[ORM\Column(name: 'status', length: 20, options: ['default' => self::DRAFT])]
    private string $status = self::DRAFT;

    #[Groups(['payroll_run:read'])]
    #[ApiProperty(description: 'payroll_run.calculated_at.label')]
    #[ORM\Column(name: 'calculated_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $calculatedAt = null;

    #[Groups(['payroll_run:read'])]
    #[ApiProperty(description: 'payroll_run.approved_at.label')]
    #[ORM\Column(name: 'approved_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $approvedAt = null;

    #[Groups(['payroll_run:read'])]
    #[ApiProperty(description: 'payroll_run.paid_at.label')]
    #[ORM\Column(name: 'paid_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $paidAt = null;

    #[Groups(['payroll_run:read'])]
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

    public function getCompany(): Company
    {
        return $this->company;
    }

    public function setCompany(Company $company): static
    {
        $this->company = $company;

        return $this;
    }

    public function getPeriod(): DateTimeImmutable
    {
        return $this->period;
    }

    public function setPeriod(DateTimeImmutable $period): static
    {
        $this->period = $period;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

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

    public function getCalculatedAt(): ?DateTimeImmutable
    {
        return $this->calculatedAt;
    }

    public function setCalculatedAt(?DateTimeImmutable $calculatedAt): static
    {
        $this->calculatedAt = $calculatedAt;

        return $this;
    }

    public function getApprovedAt(): ?DateTimeImmutable
    {
        return $this->approvedAt;
    }

    public function setApprovedAt(?DateTimeImmutable $approvedAt): static
    {
        $this->approvedAt = $approvedAt;

        return $this;
    }

    public function getPaidAt(): ?DateTimeImmutable
    {
        return $this->paidAt;
    }

    public function setPaidAt(?DateTimeImmutable $paidAt): static
    {
        $this->paidAt = $paidAt;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
