<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use App\Core\Doctrine\Filter\DevExtremeFilter;
use App\Enum\CashShiftStatus;
use App\Repository\CashShiftRepository;
use App\Dto\CashRegister\CashShiftXReportDto;
use App\State\CashRegister\CashShiftClosureSummaryProvider;
use App\State\CashRegister\CashShiftMovementsProvider;
use App\State\CashRegister\CashShiftXReportProvider;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: CashShiftRepository::class)]
#[ApiResource(
    filters: [DevExtremeFilter::class],
    mercure: true,
)]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/cash-shifts/{id}/movements',
            uriVariables: [
                'id' => new Link(
                    fromProperty: 'id',
                    fromClass: CashShift::class,
                ),
            ],
            output: CashMovement::class,
            provider: CashShiftMovementsProvider::class,
        ),
    ],
    normalizationContext: ['groups' => ['cash_movement:read']],
    paginationEnabled: false,
    mercure: true,
)]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/cash-shifts/{id}/summary',
            provider: CashShiftClosureSummaryProvider::class,
        ),
    ],
    paginationEnabled: false,
    mercure: true,
)]
#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/cash-shifts/{id}/x-report',
            provider: CashShiftXReportProvider::class,
            output: CashShiftXReportDto::class,
        ),
    ],
    paginationEnabled: false,
    mercure: true,
)]
class CashShift
{
    #[Groups(['cash_shift:read'])]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Groups(['cash_shift:read'])]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[ApiProperty(description: 'cash_shift.cash_register.label')]
    private CashRegister $cashRegister;

    #[Groups(['cash_shift:read'])]
    #[ORM\Column(enumType: CashShiftStatus::class)]
    #[ApiProperty(description: 'cash_shift.status.label')]
    private CashShiftStatus $status;

    #[Groups(['cash_shift:read'])]
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[ApiProperty(description: 'cash_shift.opening_balance.label')]
    private string $openingBalance = '0.00';

    #[Groups(['cash_shift:read'])]
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    #[ApiProperty(description: 'cash_shift.closing_balance.label')]
    private ?string $closingBalance = null;

    #[Groups(['cash_shift:read'])]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'opened_by', referencedColumnName: 'usuario_id', nullable: false)]
    #[ApiProperty(description: 'cash_shift.opened_by.label')]
    private User $openedBy;

    #[Groups(['cash_shift:read'])]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'closed_by', referencedColumnName: 'usuario_id')]
    #[ApiProperty(description: 'cash_shift.closed_by.label')]
    private ?User $closedBy = null;

    #[Groups(['cash_shift:read'])]
    #[ORM\Column]
    #[ApiProperty(description: 'cash_shift.opened_at.label')]
    private DateTimeImmutable $openedAt;

    #[Groups(['cash_shift:read'])]
    #[ORM\Column(nullable: true)]
    #[ApiProperty(description: 'cash_shift.closed_at.label')]
    private ?DateTimeImmutable $closedAt = null;

    #[Groups(['cash_shift:read'])]
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    #[ApiProperty(description: 'cash_shift.expected_amount.label')]
    private ?string $expectedAmount = null;

    #[Groups(['cash_shift:read'])]
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    #[ApiProperty(description: 'cash_shift.counted_amount.label')]
    private ?string $countedAmount = null;

    #[Groups(['cash_shift:read'])]
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    #[ApiProperty(description: 'cash_shift.difference.label')]
    private ?string $difference = null;

    #[Groups(['cash_shift:read'])]
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[ApiProperty(description: 'cash_shift.note.label')]
    private ?string $note = null;

    #[Groups(['cash_shift:read'])]
    #[ORM\ManyToOne(targetEntity: Branch::class)]
    #[ORM\JoinColumn(name: 'branch_id', referencedColumnName: 'local_id', nullable: true)]
    #[ApiProperty(description: 'cash_shift.branch.label')]
    private ?Branch $branch = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCashRegister(): CashRegister
    {
        return $this->cashRegister;
    }

    public function setCashRegister(CashRegister $cashRegister): static
    {
        $this->cashRegister = $cashRegister;

        return $this;
    }

    public function getStatus(): CashShiftStatus
    {
        return $this->status;
    }

    public function setStatus(CashShiftStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getOpeningBalance(): string
    {
        return $this->openingBalance;
    }

    public function setOpeningBalance(string $openingBalance): static
    {
        $this->openingBalance = $openingBalance;

        return $this;
    }

    public function getClosingBalance(): ?string
    {
        return $this->closingBalance;
    }

    public function setClosingBalance(string $closingBalance): static
    {
        $this->closingBalance = $closingBalance;

        return $this;
    }

    public function getOpenedBy(): User
    {
        return $this->openedBy;
    }

    public function setOpenedBy(User $openedBy): static
    {
        $this->openedBy = $openedBy;

        return $this;
    }

    public function getClosedBy(): ?User
    {
        return $this->closedBy;
    }

    public function setClosedBy(User $closedBy): static
    {
        $this->closedBy = $closedBy;

        return $this;
    }

    public function getOpenedAt(): DateTimeImmutable
    {
        return $this->openedAt;
    }

    public function setOpenedAt(DateTimeImmutable $openedAt): static
    {
        $this->openedAt = $openedAt;

        return $this;
    }

    public function getClosedAt(): ?DateTimeImmutable
    {
        return $this->closedAt;
    }

    public function setClosedAt(DateTimeImmutable $closedAt): static
    {
        $this->closedAt = $closedAt;

        return $this;
    }

    public function getExpectedAmount(): ?string
    {
        return $this->expectedAmount;
    }

    public function setExpectedAmount(?string $expected_amount): static
    {
        $this->expectedAmount = $expected_amount;

        return $this;
    }

    public function getCountedAmount(): ?string
    {
        return $this->countedAmount;
    }

    public function setCountedAmount(?string $counted_amount): static
    {
        $this->countedAmount = $counted_amount;

        return $this;
    }

    public function getDifference(): ?string
    {
        return $this->difference;
    }

    public function setDifference(?string $difference): static
    {
        $this->difference = $difference;

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

    public function getBranch(): ?Branch
    {
        return $this->branch;
    }

    public function setBranch(?Branch $branch): static
    {
        $this->branch = $branch;

        return $this;
    }
}
