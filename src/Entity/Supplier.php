<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Core\Infrastructure\Doctrine\Filter\DevExtremeFilter;
use App\Repository\SupplierRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: SupplierRepository::class)]
#[ORM\Table(name: 'proveedor')]
#[ApiResource(
    filters: [DevExtremeFilter::class],
)]
class Supplier
{
    #[Groups(['inbound:read', 'outbound:read'])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\Column(name: 'proveedor_id')]
    private ?string $id = null;

    #[ORM\Column(length: 11)]
    private ?string $ruc = null;

    #[Groups(['inbound:read', 'outbound:read'])]
    #[ORM\Column(name: 'razon_social', length: 255)]
    private ?string $businessName = null;

    #[ORM\Column(name: 'domicilio_fiscal', length: 255, nullable: true)]
    private ?string $fiscalAddress = null;

    #[ORM\Column(name: 'telefono', length: 14, nullable: true)]
    private ?string $phone = null;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getRuc(): ?string
    {
        return $this->ruc;
    }

    public function setRuc(string $ruc): static
    {
        $this->ruc = $ruc;

        return $this;
    }

    public function getBusinessName(): ?string
    {
        return $this->businessName;
    }

    public function setBusinessName(string $businessName): static
    {
        $this->businessName = $businessName;

        return $this;
    }

    public function getFiscalAddress(): ?string
    {
        return $this->fiscalAddress;
    }

    public function setFiscalAddress(string $fiscalAddress): static
    {
        $this->fiscalAddress = $fiscalAddress;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }
}
