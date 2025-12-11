<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Core\Doctrine\Filter\DevExtremeFilter;
use App\Repository\UnitOfMeasureRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: UnitOfMeasureRepository::class)]
#[ORM\Table(name: 'unidad')]
#[ApiResource(
    filters: [DevExtremeFilter::class],
)]
class UnitOfMeasure
{
    #[Groups(['product:read'])]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'unidad_id')]
    private ?int $id = null;

    #[Groups(['product:read'])]
    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(length: 255)]
    private string $code;

    #[ORM\Column(name: 'type_id')]
    private int $type;

    #[ORM\Column]
    private float $ratio = 0;

    #[ORM\Column(name: 'is_reference')]
    private bool $reference = false;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getType(): int
    {
        return $this->type;
    }

    public function setType(int $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getRatio(): float
    {
        return $this->ratio;
    }

    public function setRatio(float $ratio): static
    {
        $this->ratio = $ratio;

        return $this;
    }

    public function isReference(): bool
    {
        return $this->reference;
    }

    public function setReference(bool $reference): static
    {
        $this->reference = $reference;

        return $this;
    }
}
