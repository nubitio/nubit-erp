<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Core\Doctrine\Filter\DevExtremeFilter;
use App\Repository\CustomerRepository;
use App\Validator as AppAssert;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CustomerRepository::class)]
#[ORM\Table(name: 'cliente')]
#[ApiResource(
    filters: [DevExtremeFilter::class],
)]
#[AppAssert\UniqueEntity(fields: ['ruc'], message: 'Ya existe un cliente con el mismo RUC/DNI.')]
class Customer
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^\d{8,11}$/', message: 'El RUC/DNI debe contener entre 8 y 11 dígitos.')]
    #[Groups(['sale:read', 'invoice:read', 'sale_record:read'])]
    #[ORM\Column(length: 11, unique: true)]
    private string $ruc;

    #[Assert\NotBlank]
    #[Groups(['sale:read', 'invoice:read', 'sale_record:read'])]
    #[ORM\Column(name: 'razon_social', length: 255)]
    private string $businessName;

    #[ORM\Column(name: 'domicilio_fiscal', nullable: true)]
    private ?string $fiscalAddress = null;

    #[Assert\Regex(pattern: '/^\+?[1-9]\d{1,2}[-.\s]?\d{6,14}$/', message: 'El teléfono ingresado no es válido.')]
    #[ORM\Column(name: 'telefono', length: 20, nullable: true)]
    private ?string $phone = null;

    #[Assert\Email]
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $email = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'price_list_id', referencedColumnName: 'id', nullable: true)]
    private ?PriceList $priceList = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRuc(): string
    {
        return $this->ruc;
    }

    public function setRuc(string $ruc): static
    {
        $this->ruc = $ruc;

        return $this;
    }

    public function getBusinessName(): string
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
