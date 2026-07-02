<?php

declare(strict_types=1);

namespace App\Catalog\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use App\Catalog\Repository\SunatCatalogRepository;
use Doctrine\ORM\Mapping as ORM;
use Nubit\ApiPlatform\Doctrine\Filter\DataGridFilter;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: SunatCatalogRepository::class)]
#[ORM\Table(name: 'sunat_catalog')]
#[ORM\UniqueConstraint(name: 'uniq_sunat_catalog_code', columns: ['catalog', 'code'])]
#[ApiResource(filters: [DataGridFilter::class], mercure: true)]
class SunatCatalog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[Groups(['product:read', 'sunat_catalog:read'])]
    #[ORM\Column]
    private ?int $id = null;

    #[Groups(['product:read', 'sunat_catalog:read'])]
    #[ApiProperty(description: 'sunat_catalog.catalog.label')]
    #[ORM\Column(name: 'catalog', type: 'integer')]
    private int $catalog;

    #[Groups(['product:read', 'sunat_catalog:read'])]
    #[ApiProperty(description: 'sunat_catalog.code.label')]
    #[ORM\Column(name: 'code', length: 10)]
    private string $code;

    #[Groups(['product:read', 'sunat_catalog:read'])]
    #[ApiProperty(description: 'sunat_catalog.description.label')]
    #[ORM\Column(name: 'description', length: 255)]
    private string $description;

    #[Groups(['product:read', 'sunat_catalog:read'])]
    #[ApiProperty(description: 'sunat_catalog.percentage.label')]
    #[ORM\Column(name: 'percentage', type: 'float', nullable: true)]
    private ?float $percentage = null;

    #[Groups(['product:read', 'sunat_catalog:read'])]
    #[ApiProperty(description: 'sunat_catalog.active.label')]
    #[ORM\Column(name: 'active', type: 'boolean', options: ['default' => true])]
    private bool $active = true;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCatalog(): int
    {
        return $this->catalog;
    }

    public function setCatalog(int $catalog): static
    {
        $this->catalog = $catalog;

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

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getPercentage(): ?float
    {
        return $this->percentage;
    }

    public function setPercentage(?float $percentage): static
    {
        $this->percentage = $percentage;

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
