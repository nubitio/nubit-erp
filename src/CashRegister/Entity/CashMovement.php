<?php

declare(strict_types=1);

namespace App\CashRegister\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\CashRegister\Entity\CashMovementCategory;
use App\CashRegister\Entity\CashSession;
use App\CashRegister\Enum\CashMovementType;
use App\CashRegister\State\CashMovementCollectionProvider;
use App\CashRegister\State\CashMovementItemProvider;
use App\CashRegister\State\CashMovementWriteProcessor;
use App\Organization\Entity\Branch;
use DateTimeImmutable;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Not a Doctrine entity — a read/write shape for the v2 `cash_movements`
 * table, always built by `CashMovementHydrator` from a raw DBAL row (reads)
 * or written by `CashMovementWriteProcessor` via a raw DBAL INSERT (the
 * table is append-only: no PATCH/DELETE operation exists below). It used to
 * be an ORM entity mapped to a legacy singular `cash_movement` table that
 * nothing wrote to after the CashRegister v2 cutover; that table (and
 * `cash_shift`) were dropped 2026-08-19 once every remaining reader was
 * confirmed to go through the v2-aware paths below — see ADR-004's
 * amendment (docs/adr/ADR-004-cashregister-strangler-fig-decisions.md).
 */
#[ApiResource(
    normalizationContext: ['groups' => ['cash_movement:read']],
    operations: [
        new GetCollection(provider: CashMovementCollectionProvider::class, security: "is_granted('perm:cash.view')"),
        new Get(provider: CashMovementItemProvider::class, security: "is_granted('perm:cash.view')"),
        new Post(processor: CashMovementWriteProcessor::class, security: "is_granted('perm:cash.movement')"),
    ],
    mercure: true,
)]
class CashMovement
{
    #[Groups(['cash_movement:read'])]
    // @phpstan-ignore property.unusedType (set via ReflectionProperty by CashMovementHydrator/CashMovementWriteProcessor)
    private ?int $id = null;

    #[Groups(['cash_movement:read'])]
    #[ApiProperty(description: 'cash_movement.cash_session.label')]
    private ?CashSession $cashSession = null;

    #[Groups(['cash_movement:read'])]
    #[ApiProperty(description: 'cash_movement.category.label')]
    private ?CashMovementCategory $category = null;

    #[Groups(['cash_movement:read'])]
    #[ApiProperty(description: 'cash_movement.type.label')]
    private CashMovementType $type;

    #[Groups(['cash_movement:read'])]
    #[ApiProperty(description: 'cash_movement.amount.label')]
    private string $amount = '0.00';

    #[Groups(['cash_movement:read'])]
    #[ApiProperty(description: 'cash_movement.description.label')]
    private string $description;

    private DateTimeImmutable $createdAt;

    #[Groups(['cash_movement:read'])]
    #[ApiProperty(description: 'cash_movement.branch.label')]
    private ?Branch $branch = null;

    public function getId(): ?int
    {
        return $this->id;
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
