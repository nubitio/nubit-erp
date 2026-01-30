<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use App\Core\Doctrine\Filter\DevExtremeFilter;
use App\Enum\CashShiftStatus;
use App\Repository\CashShiftRepository;
use App\State\CashRegister\CashShiftClosureSummaryProvider;
use App\State\CashRegister\CashShiftMovementsProvider;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: CashShiftRepository::class)]
#[ApiResource(
    filters: [DevExtremeFilter::class],
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
)]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/cash-shifts/{id}/summary',
            provider: CashShiftClosureSummaryProvider::class,
        ),
    ],
    paginationEnabled: false
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
    private CashRegister $cashRegister;

    #[Groups(['cash_shift:read'])]
    #[ORM\Column(enumType: CashShiftStatus::class)]
    private CashShiftStatus $status;

    #[Groups(['cash_shift:read'])]
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $openingBalance = '0.00';

    #[Groups(['cash_shift:read'])]
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $closingBalance = null;

    #[Groups(['cash_shift:read'])]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'opened_by', referencedColumnName: 'usuario_id', nullable: false)]
    private User $openedBy;

    #[Groups(['cash_shift:read'])]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'closed_by', referencedColumnName: 'usuario_id')]
    private ?User $closedBy = null;

    #[Groups(['cash_shift:read'])]
    #[ORM\Column]
    private DateTimeImmutable $openedAt;

    #[Groups(['cash_shift:read'])]
    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $closedAt = null;

    #[Groups(['cash_shift:read'])]
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $expectedAmount = null;

    #[Groups(['cash_shift:read'])]
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $countedAmount = null;

    #[Groups(['cash_shift:read'])]
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $difference = null;

    #[Groups(['cash_shift:read'])]
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $note = null;

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
}
