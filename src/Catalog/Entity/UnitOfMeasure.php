<?php

declare(strict_types=1);

namespace App\Catalog\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use App\Efact\Doctrine\Filter\DevExtremeFilter;
use App\Catalog\Repository\UnitOfMeasureRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: UnitOfMeasureRepository::class)]
#[ORM\Table(name: 'unidad')]
#[ApiResource(
    normalizationContext: ['groups' => ['unit-of-measure:read']],
    denormalizationContext: ['groups' => ['unit-of-measure:write']],
    filters: [DevExtremeFilter::class],
    mercure: true,
)]
class UnitOfMeasure
{
    #[Groups(['unit-of-measure:read', 'product:read'])]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'unidad_id')]
    private ?int $id = null;

    #[Groups(['unit-of-measure:read', 'product:read'])]
    #[ApiProperty(description: 'unit_of_measure.name.label')]
    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(length: 255)]
    private string $code;

    #[ORM\Column(name: 'type_id')]
    private int $type;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $ratio = '1.00';

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

    public function getRatio(): string
    {
        return $this->ratio;
    }

    public function setRatio(string $ratio): static
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
