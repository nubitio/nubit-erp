<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use App\Core\Doctrine\Filter\DevExtremeFilter;
use App\Repository\ConfigRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ConfigRepository::class)]
#[ApiResource(
    filters: [DevExtremeFilter::class],
    mercure: true,
)]
class Config
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[ApiProperty(description: 'config.name.label')]
    private string $name;

    #[ORM\Column(length: 255)]
    #[ApiProperty(description: 'config.value.label')]
    private string $value;

    #[ORM\Column(enumType: ConfigType::class)]
    #[ApiProperty(description: 'config.type.label')]
    private ConfigType $type;

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

    public function getValue(): string
    {
        return $this->value;
    }

    public function setValue(string $value): static
    {
        $this->value = $value;

        return $this;
    }

    public function getType(): ConfigType
    {
        return $this->type;
    }

    public function setType(ConfigType $type): static
    {
        $this->type = $type;

        return $this;
    }
}
