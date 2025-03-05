<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Core\Infrastructure\Doctrine\Filter\DevExtremeFilter;
use App\Repository\CustomerRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: CustomerRepository::class)]
#[ORM\Table(name: 'cliente')]
#[ApiResource(
    filters: [DevExtremeFilter::class],
)]
class Customer
{
    #[Groups(['sale:read', 'invoice:read'])]
    #[ORM\Id]
    #[ORM\Column(length: 11)]
    private ?string $ruc = null;

    #[Groups(['sale:read', 'invoice:read'])]
    #[ORM\Column(name: 'razon_social', length: 255)]
    private ?string $businessName = null;

    #[ORM\Column(name: 'domicilio_fiscal', nullable: true)]
    private ?string $fiscalAddress = null;

    #[ORM\Column(name: 'telefono', length: 20, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $email = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'price_list_id', referencedColumnName: 'id', nullable: true)]
    private ?PriceList $priceList = null;

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

    public function setFiscalAddress(?string $fiscalAddress): static
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

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getPriceList(): ?PriceList
    {
        return $this->priceList;
    }

    public function setPriceList(?PriceList $priceList): static
    {
        $this->priceList = $priceList;

        return $this;
    }
}
