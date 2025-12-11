<?php

namespace App\Entity;

use DateTimeImmutable;
use ApiPlatform\Metadata\ApiResource;
use App\Core\Doctrine\Filter\DevExtremeFilter;
use App\Enum\CashMovementType;
use App\Entity\CashMovementCategory;
use App\Repository\CashMovementRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: CashMovementRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    normalizationContext: ['groups' => ['cash_movement:read']],
    filters: [DevExtremeFilter::class],
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
    #[ORM\JoinColumn(nullable: false)]
    private CashShift $cashShift;

    #[Groups(['cash_movement:read'])]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?CashMovementCategory $category = null;

    #[Groups(['cash_movement:read'])]
    #[ORM\Column(enumType: CashMovementType::class)]
    private CashMovementType $type;

    #[Groups(['cash_movement:read'])]
    #[ORM\Column]
    private float $amount = 0;

    #[Groups(['cash_movement:read'])]
    #[ORM\Column(length: 255)]
    private string $description;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCashShift(): CashShift
    {
        return $this->cashShift;
    }

    public function setCashShift(CashShift $cashShift): static
    {
        $this->cashShift = $cashShift;

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

    public function getAmount(): ?float
    {
        return $this->amount;
    }

    public function setAmount(float $amount): static
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
}
