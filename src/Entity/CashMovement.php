<?php

declare(strict_types=1);

namespace App\Entity;

use App\Organization\Entity\Branch;
use DateTimeImmutable;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Efact\Doctrine\Filter\DevExtremeFilter;
use App\Enum\CashMovementType;
use App\Entity\CashMovementCategory;
use App\Entity\CashSession;
use App\Repository\CashMovementRepository;
use App\State\CashRegister\CashMovementItemProvider;
use App\State\CashRegister\CashMovementWriteProcessor;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: CashMovementRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    normalizationContext: ['groups' => ['cash_movement:read']],
    filters: [DevExtremeFilter::class],
    operations: [
        new GetCollection(),
        new Get(provider: CashMovementItemProvider::class),
        new Post(processor: CashMovementWriteProcessor::class),
        new Patch(
            provider: CashMovementItemProvider::class,
            processor: CashMovementWriteProcessor::class,
        ),
        new Delete(
            provider: CashMovementItemProvider::class,
            processor: CashMovementWriteProcessor::class,
        ),
    ],
    mercure: true,
)]
class CashMovement
{
    #[Groups(['cash_movement:read'])]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Groups(['cash_movement:read'])]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    #[ApiProperty(description: 'cash_movement.cash_shift.label')]
    private ?CashShift $cashShift = null;

    /**
     * V2 FK to cash_sessions. The DB column (cash_session_id) does NOT yet exist
     * on the legacy cash_movement table — it will be added in T-CASH-07.
     * This property is intentionally kept PHP-only (no ORM column mapping) for now.
     */
    #[Groups(['cash_movement:read'])]
    #[ApiProperty(description: 'cash_movement.cash_session.label')]
    private ?CashSession $cashSession = null;

    #[Groups(['cash_movement:read'])]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    #[ApiProperty(description: 'cash_movement.category.label')]
    private ?CashMovementCategory $category = null;

    #[Groups(['cash_movement:read'])]
    #[ORM\Column(enumType: CashMovementType::class)]
    #[ApiProperty(description: 'cash_movement.type.label')]
    private CashMovementType $type;

    #[Groups(['cash_movement:read'])]
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[ApiProperty(description: 'cash_movement.amount.label')]
    private string $amount = '0.00';

    #[Groups(['cash_movement:read'])]
    #[ORM\Column(length: 255)]
    #[ApiProperty(description: 'cash_movement.description.label')]
    private string $description;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    #[Groups(['cash_movement:read'])]
    #[ORM\ManyToOne(targetEntity: Branch::class)]
    #[ORM\JoinColumn(name: 'branch_id', referencedColumnName: 'local_id', nullable: true)]
    #[ApiProperty(description: 'cash_movement.branch.label')]
    private ?Branch $branch = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCashShift(): ?CashShift
    {
        return $this->cashShift;
    }

    public function setCashShift(?CashShift $cashShift): static
    {
        $this->cashShift = $cashShift;

        return $this;
    }

    public function getCashSession(): ?CashSession
    {
        return $this->cashSession;
    }

    public function setCashSession(?CashSession $cashSession): static
    {
        $this->cashSession = $cashSession;

        return $this;
    }

    public function getCategory(): ?CashMovementCategory
    {
        return $this->category;
    }

    public function setCategory(CashMovementCategory $category): static
    {
        $this->category = $category;
        $this->type = $category->getType();

        return $this;
    }

    public function getType(): CashMovementType
    {
        return $this->type;
    }

    public function setType(CashMovementType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getAmount(): ?string
    {
        return $this->amount;
    }

    public function setAmount(string $amount): static
    {
        $this->amount = $amount;

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

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new DateTimeImmutable();
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function syncBranchFromCashShift(): void
    {
        if ($this->branch === null) {
            if ($this->cashSession !== null) {
                $this->branch = $this->cashSession->getBranch();
            } elseif ($this->cashShift !== null) {
                $this->branch = $this->cashShift->getBranch();
            }
        }
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
