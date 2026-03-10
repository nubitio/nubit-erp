<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use ApiPlatform\Metadata\ApiResource;
use App\Core\Doctrine\Filter\DevExtremeFilter;
use App\Repository\CashSessionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: CashSessionRepository::class)]
#[ORM\Table(name: 'cash_sessions')]
#[ApiResource(
    normalizationContext: ['groups' => ['cash_session:read']],
    filters: [DevExtremeFilter::class],
)]
class CashSession
{
    #[Groups(['cash_session:read'])]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Plain int FK to cash_shift.id — no Doctrine relation (legacy reference only).
     */
    #[Groups(['cash_session:read'])]
    #[ORM\Column(nullable: true)]
    private ?int $legacyCashShiftId = null;

    #[Groups(['cash_session:read'])]
    #[ORM\ManyToOne(targetEntity: CashRegister::class)]
    #[ORM\JoinColumn(name: 'cash_register_id', referencedColumnName: 'id', nullable: false)]
    private CashRegister $cashRegister;

    #[Groups(['cash_session:read'])]
    #[ORM\ManyToOne(targetEntity: Branch::class)]
    #[ORM\JoinColumn(name: 'branch_id', referencedColumnName: 'local_id', nullable: false)]
    private Branch $branch;

    #[Groups(['cash_session:read'])]
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'opened_by', referencedColumnName: 'usuario_id', nullable: false)]
    private User $openedBy;

    #[Groups(['cash_session:read'])]
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'closed_by', referencedColumnName: 'usuario_id', nullable: true)]
    private ?User $closedBy = null;

    /**
     * Status: OPEN | CLOSED | RECONCILING
     */
    #[Groups(['cash_session:read'])]
    #[ORM\Column(length: 20)]
    private string $status = 'OPEN';

    #[Groups(['cash_session:read'])]
    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    private string $openingAmount = '0.00';

    #[Groups(['cash_session:read'])]
    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
    private ?string $expectedAmount = null;

    #[Groups(['cash_session:read'])]
    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
    private ?string $countedAmount = null;

    #[Groups(['cash_session:read'])]
    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
    private ?string $differenceAmount = null;

    #[Groups(['cash_session:read'])]
    #[ORM\Column]
    private DateTimeImmutable $openedAt;

    #[Groups(['cash_session:read'])]
    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $closedAt = null;

    #[Groups(['cash_session:read'])]
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $closeNote = null;

    #[Groups(['cash_session:read'])]
    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    #[Groups(['cash_session:read'])]
    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $updatedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLegacyCashShiftId(): ?int
    {
        return $this->legacyCashShiftId;
    }

    public function setLegacyCashShiftId(?int $legacyCashShiftId): static
    {
        $this->legacyCashShiftId = $legacyCashShiftId;

        return $this;
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

    public function getBranch(): Branch
    {
        return $this->branch;
    }

    public function setBranch(Branch $branch): static
    {
        $this->branch = $branch;

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

    public function setClosedBy(?User $closedBy): static
    {
        $this->closedBy = $closedBy;

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

    public function getOpeningAmount(): string
    {
        return $this->openingAmount;
    }

    public function setOpeningAmount(string $openingAmount): static
    {
        $this->openingAmount = $openingAmount;

        return $this;
    }

    public function getExpectedAmount(): ?string
    {
        return $this->expectedAmount;
    }

    public function setExpectedAmount(?string $expectedAmount): static
    {
        $this->expectedAmount = $expectedAmount;

        return $this;
    }

    public function getCountedAmount(): ?string
    {
        return $this->countedAmount;
    }

    public function setCountedAmount(?string $countedAmount): static
    {
        $this->countedAmount = $countedAmount;

        return $this;
    }

    public function getDifferenceAmount(): ?string
    {
        return $this->differenceAmount;
    }

    public function setDifferenceAmount(?string $differenceAmount): static
    {
        $this->differenceAmount = $differenceAmount;

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

    public function setClosedAt(?DateTimeImmutable $closedAt): static
    {
        $this->closedAt = $closedAt;

        return $this;
    }

    public function getCloseNote(): ?string
    {
        return $this->closeNote;
    }

    public function setCloseNote(?string $closeNote): static
    {
        $this->closeNote = $closeNote;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
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
}
