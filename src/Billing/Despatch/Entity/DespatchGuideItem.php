<?php

declare(strict_types=1);

namespace App\Billing\Despatch\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Ítem (bien trasladado) de una Guía de Remisión Electrónica.
 */
#[ORM\Entity]
#[ORM\Table(name: 'despatch_guide_line')]
class DespatchGuideItem
{
    #[ORM\Id]
    #[ORM\Column(name: 'id', type: Types::STRING, length: 36)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[Groups(['despatch:read'])]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: DespatchGuide::class, inversedBy: 'lines')]
    #[ORM\JoinColumn(name: 'despatch_guide_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private DespatchGuide $despatchGuide;

    #[ORM\Column(name: 'product_code', type: Types::STRING, length: 50, nullable: true)]
    #[Groups(['despatch:read', 'despatch:write'])]
    private ?string $productCode = null;

    #[ORM\Column(name: 'sunat_product_code', type: Types::STRING, length: 10, nullable: true)]
    #[Groups(['despatch:read', 'despatch:write'])]
    private ?string $sunatProductCode = null;

    #[ORM\Column(name: 'description', type: Types::STRING, length: 500)]
    #[Groups(['despatch:read', 'despatch:write'])]
    private string $description = '';

    #[ORM\Column(name: 'unit', type: Types::STRING, length: 10, options: ['default' => 'NIU'])]
    #[Groups(['despatch:read', 'despatch:write'])]
    private string $unit = 'NIU';

    #[ORM\Column(name: 'quantity', type: Types::DECIMAL, precision: 12, scale: 3)]
    #[Groups(['despatch:read', 'despatch:write'])]
    private string $quantity = '0.000';

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getDespatchGuide(): DespatchGuide
    {
        return $this->despatchGuide;
    }

    public function setDespatchGuide(DespatchGuide $despatchGuide): self
    {
        $this->despatchGuide = $despatchGuide;

        return $this;
    }

    public function getProductCode(): ?string
    {
        return $this->productCode;
    }

    public function setProductCode(?string $productCode): self
    {
        $this->productCode = $productCode;

        return $this;
    }

    public function getSunatProductCode(): ?string
    {
        return $this->sunatProductCode;
    }

    public function setSunatProductCode(?string $sunatProductCode): self
    {
        $this->sunatProductCode = $sunatProductCode;

        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getUnit(): string
    {
        return $this->unit;
    }

    public function setUnit(string $unit): self
    {
        $this->unit = $unit;

        return $this;
    }

    public function getQuantity(): string
    {
        return $this->quantity;
    }

    public function setQuantity(string $quantity): self
    {
        $this->quantity = $quantity;

        return $this;
    }
}
