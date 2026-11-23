<?php

declare(strict_types=1);

namespace App\CashRegister\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use App\CashRegister\Enum\CashMovementType;
use App\CashRegister\Repository\CashMovementCategoryRepository;
use Doctrine\ORM\Mapping as ORM;
use RuntimeException;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: CashMovementCategoryRepository::class)]
#[ORM\Table(name: 'cash_movement_category')]
#[ORM\UniqueConstraint(columns: ['code'])]
#[ApiResource(exceptionToStatus: [
    RuntimeException::class => 400,
], mercure: true)]
#[ORM\HasLifecycleCallbacks]
class CashMovementCategory
{
    public const string CASH_SALE = 'cash_sale';

    public const string CREDIT_PAYMENT = 'credit_payment';

    public const string CASH_REPLENISHMENT = 'cash_replenishment';

    public const string OTHER_INCOME = 'other_income';

    public const string OVERAGE_ADJUSTMENT = 'overage_adjustment';

    public const string PETTY_CASH_EXPENSE = 'petty_cash_expense';

    public const string CUSTOMER_REFUND = 'customer_refund';

    public const string MANAGER_WITHDRAWAL = 'manager_withdrawal';

    public const string BANK_DEPOSIT = 'bank_deposit';

    public const string SHORTAGE_ADJUSTMENT = 'shortage_adjustment';

    public const string SUPPLIER_PAYMENT = 'supplier_payment';

    /**
     * The reasons a person decides on: change from the supervisor, other income, a small expense, a supplier paid
     * at the door, cash taken to the safe. Seeded as manual; the others are what the system records itself.
     *
     * @var list<string>
     */
    public const array MANUAL_CODES = [
        self::CASH_REPLENISHMENT,
        self::OTHER_INCOME,
        self::PETTY_CASH_EXPENSE,
        self::SUPPLIER_PAYMENT,
        self::MANAGER_WITHDRAWAL,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['Default', 'cash_movement:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    #[Groups(['Default', 'cash_movement:read'])]
    #[ApiProperty(description: 'cash_movement_category.code.label')]
    private string $code;

    #[ORM\Column(length: 255)]
    #[Groups(['Default', 'cash_movement:read'])]
    #[ApiProperty(description: 'cash_movement_category.name.label')]
    private string $name;

    #[ORM\Column(length: 20, enumType: CashMovementType::class)]
    #[Groups(['Default', 'cash_movement:read'])]
    #[ApiProperty(description: 'cash_movement_category.type.label')]
    private CashMovementType $type;

    #[ORM\Column(name: 'is_system', options: ['default' => true])]
    private bool $isSystem = true;

    /**
     * Whether a person may pick it when registering cash that came in or went out by hand. The others describe
     * what the system itself records (a sale, a collection, a refund, a count difference, a treasury transfer):
     * offering them would count the same money twice.
     */
    #[ORM\Column(name: 'is_manual', options: ['default' => false])]
    private bool $manual = false;

    #[ORM\Column(options: ['default' => true])]
    private bool $active = true;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

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

    public function isSystem(): bool
    {
        return $this->isSystem;
    }

    public function setIsSystem(bool $isSystem): static
    {
        $this->isSystem = $isSystem;

        return $this;
    }

    public function isManual(): bool
    {
        return $this->manual;
    }

    public function setManual(bool $manual): static
    {
        $this->manual = $manual;

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
            throw new RuntimeException('No se puede eliminar una categoría del sistema.');
        }
    }

    #[ORM\PreUpdate]
    public function preventUpdateForSystem(): void
    {
        if ($this->isSystem) {
            throw new RuntimeException('No se puede modificar una categoría del sistema.');
        }
    }
}
