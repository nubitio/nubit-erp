<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Enum\CashMovementType;
use App\Repository\CashMovementCategoryRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: CashMovementCategoryRepository::class)]
#[ORM\Table(name: 'cash_movement_category', uniqueConstraints: [new ORM\UniqueConstraint(columns: ['code'])])]
#[ApiResource(
    exceptionToStatus: [
        \RuntimeException::class => 400,
    ],
)]
#[ORM\HasLifecycleCallbacks]
class CashMovementCategory
{
    public const CASH_SALE = 'cash_sale';
    public const CREDIT_PAYMENT = 'credit_payment';
    public const CASH_REPLENISHMENT = 'cash_replenishment';
    public const OTHER_INCOME = 'other_income';
    public const OVERAGE_ADJUSTMENT = 'overage_adjustment';
    public const PETTY_CASH_EXPENSE = 'petty_cash_expense';
    public const CUSTOMER_REFUND = 'customer_refund';
    public const MANAGER_WITHDRAWAL = 'manager_withdrawal';
    public const BANK_DEPOSIT = 'bank_deposit';
    public const SHORTAGE_ADJUSTMENT = 'shortage_adjustment';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['Default', 'cash_movement:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    #[Groups(['Default', 'cash_movement:read'])]
    private ?string $code = null;

    #[ORM\Column(length: 255)]
    #[Groups(['Default', 'cash_movement:read'])]
    private ?string $name = null;

    #[ORM\Column(length: 20, enumType: CashMovementType::class)]
    #[Groups(['Default', 'cash_movement:read'])]
    private ?CashMovementType $type = null;

    #[ORM\Column(name: 'is_system', options: ['default' => true])]
    private bool $isSystem = true;

    #[ORM\Column(options: ['default' => true])]
    private bool $active = true;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getType(): ?CashMovementType
    {
        return $this->type;
    }

    public function setType(CashMovementType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function isSystem(): bool
    {
        return $this->isSystem;
    }

    public function setIsSystem(bool $isSystem): static
    {
        $this->isSystem = $isSystem;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }

    #[ORM\PreRemove]
    public function preventDeletionForSystem(): void
    {
        if ($this->isSystem) {
            throw new \RuntimeException('No se puede eliminar una categoría del sistema.');
        }
    }

    #[ORM\PreUpdate]
    public function preventUpdateForSystem(): void
    {
        if ($this->isSystem) {
            throw new \RuntimeException('No se puede modificar una categoría del sistema.');
        }
    }
}
