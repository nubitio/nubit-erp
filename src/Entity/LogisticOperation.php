<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Core\Doctrine\Filter\DevExtremeFilter;
use App\Repository\LogisticOperationRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: LogisticOperationRepository::class)]
#[ORM\Table(name: 'operacion')]
#[ApiResource(
    filters: [DevExtremeFilter::class],
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
    #[ORM\Column(name: 'descripcion')]
    private ?string $name = null;

    #[ORM\Column(name: 'tipo_id')]
    private ?int $type = null;

    public function getId(): ?string
    {
        return $this->id;
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

    public function getType(): ?int
    {
        return $this->type;
    }

    public function setType(int $type): static
    {
        $this->type = $type;

        return $this;
    }
}
