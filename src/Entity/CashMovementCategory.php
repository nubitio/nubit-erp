<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Enum\CashMovementType;
use App\Repository\CashMovementCategoryRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CashMovementCategoryRepository::class)]
#[ORM\Table(name: 'cash_movement_category', uniqueConstraints: [new ORM\UniqueConstraint(columns: ['code'])])]
#[ApiResource]
class CashMovementCategory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    private ?string $code = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 20, enumType: CashMovementType::class)]
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
}
