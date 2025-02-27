<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use App\Core\Infrastructure\Doctrine\Filter\DevExtremeFilter;
use App\Enum\CashShiftStatus;
use App\Repository\CashShiftRepository;
use App\State\CashRegister\CashShiftClosureSummaryProvider;
use App\State\CashRegister\CashShiftMovementsProvider;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

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
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?CashRegister $cashRegister = null;

    #[ORM\Column(enumType: CashShiftStatus::class)]
    private ?CashShiftStatus $status = null;

    #[ORM\Column]
    private ?float $openingBalance = null;

    #[ORM\Column]
    private ?float $closingBalance = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'opened_by', referencedColumnName: 'usuario_id', nullable: false)]
    private ?User $openedBy = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'closed_by', referencedColumnName: 'usuario_id')]
    private ?User $closedBy = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $openedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $closedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCashRegister(): ?CashRegister
    {
        return $this->cashRegister;
    }

    public function setCashRegister(?CashRegister $cashRegister): static
    {
        $this->cashRegister = $cashRegister;

        return $this;
    }

    public function getStatus(): ?CashShiftStatus
    {
        return $this->status;
    }

    public function setStatus(CashShiftStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getOpeningBalance(): ?float
    {
        return $this->openingBalance;
    }

    public function setOpeningBalance(float $openingBalance): static
    {
        $this->openingBalance = $openingBalance;

        return $this;
    }

    public function getClosingBalance(): ?float
    {
        return $this->closingBalance;
    }

    public function setClosingBalance(?float $closingBalance): static
    {
        $this->closingBalance = $closingBalance;

        return $this;
    }

    public function getOpenedBy(): ?User
    {
        return $this->openedBy;
    }

    public function setOpenedBy(?User $openedBy): static
    {
        $this->openedBy = $openedBy;

        return $this;
    }

    public function getClosedBy(): ?User
    {
        return $this->closedBy;
    }

    public function setClosedBy(?User $closedBy): static
    {
        $this->closedBy = $closedBy;

        return $this;
    }

    public function getOpenedAt(): ?\DateTimeImmutable
    {
        return $this->openedAt;
    }

    public function setOpenedAt(\DateTimeImmutable $openedAt): static
    {
        $this->openedAt = $openedAt;

        return $this;
    }

    public function getClosedAt(): ?\DateTimeImmutable
    {
        return $this->closedAt;
    }

    public function setClosedAt(?\DateTimeImmutable $closedAt): static
    {
        $this->closedAt = $closedAt;

        return $this;
    }
}
