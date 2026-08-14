<?php

declare(strict_types=1);

namespace App\AccountsPayable\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use App\AccountsPayable\Repository\ExpenseCategoryRepository;
use Doctrine\ORM\Mapping as ORM;
use RuntimeException;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: ExpenseCategoryRepository::class)]
#[ORM\Table(name: 'expense_category')]
#[ORM\UniqueConstraint(columns: ['code'])]
#[ApiResource(exceptionToStatus: [
    RuntimeException::class => 400,
], mercure: true)]
#[ORM\HasLifecycleCallbacks]
class ExpenseCategory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['Default', 'supplier_bill:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    #[Groups(['Default', 'supplier_bill:read'])]
    #[ApiProperty(description: 'expense_category.code.label')]
    private string $code;

    #[ORM\Column(length: 255)]
    #[Groups(['Default', 'supplier_bill:read'])]
    #[ApiProperty(description: 'expense_category.name.label')]
    private string $name;

    #[ORM\Column(name: 'is_system', options: ['default' => true])]
    private bool $isSystem = true;

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
            throw new RuntimeException('No se puede eliminar una categoría del sistema.');
        }
    }
}
