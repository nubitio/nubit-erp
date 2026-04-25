<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use App\Efact\Doctrine\Filter\DevExtremeFilter;
use App\Repository\LogisticOperationRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: LogisticOperationRepository::class)]
#[ORM\Table(name: 'operacion')]
#[ApiResource(
    filters: [DevExtremeFilter::class],
    mercure: true,
)]
class LogisticOperation
{
    #[Groups(['inbound:read', 'outbound:read'])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\Column(name: 'operacion_id')]
    private ?string $id = null;

    #[Groups(['inbound:read', 'outbound:read'])]
    #[ApiProperty(description: 'logistic_operation.name.label')]
    #[ORM\Column(name: 'descripcion')]
    private string $name;

    #[ApiProperty(description: 'logistic_operation.type.label')]
    #[ORM\Column(name: 'tipo_id')]
    private int $type;

    public function getId(): ?string
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

    public function getType(): int
    {
        return $this->type;
    }

    public function setType(int $type): static
    {
        $this->type = $type;

        return $this;
    }
}
