<?php

declare(strict_types=1);

namespace App\Contacts\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Contacts\Repository\CustomerRepository;
use App\Efact\Domain\ActivatableInterface;
use App\Efact\Validator as AppAssert;
use App\Inventory\Entity\PriceList;
use Doctrine\ORM\Mapping as ORM;
use Nubit\ApiPlatform\Attribute\Exportable;
use Nubit\ApiPlatform\Doctrine\Filter\DataGridFilter;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[Exportable]
#[ORM\Entity(repositoryClass: CustomerRepository::class)]
#[ORM\Table(name: 'cliente')]
#[ApiResource(
    operations: [
        new GetCollection(security: "is_granted('perm:customers.view')"),
        new Post(security: "is_granted('perm:customers.create')"),
        new Get(security: "is_granted('perm:customers.view')"),
        new Patch(security: "is_granted('perm:customers.edit')"),
        new Delete(security: "is_granted('perm:customers.delete')"),
    ],
    filters: [DataGridFilter::class],
    mercure: true,
)]
#[AppAssert\UniqueEntity(fields: ['ruc'], message: 'Ya existe un cliente con el mismo RUC/DNI.')]
class Customer implements ActivatableInterface
{
    #[Groups(['sale:read', 'invoice:read', 'sale_record:read', 'sale_quote:read'])]
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^\d{8,11}$/', message: 'El RUC/DNI debe contener entre 8 y 11 dígitos.')]
    #[Groups(['sale:read', 'invoice:read', 'sale_record:read'])]
    #[ApiProperty(description: 'customer.ruc.label')]
    #[ORM\Column(length: 11, unique: true)]
    private string $ruc;

    #[Assert\NotBlank]
    #[Groups(['sale:read', 'invoice:read', 'sale_record:read', 'customer_subscription:read', 'sale_quote:read'])]
    #[ApiProperty(description: 'customer.business_name.label')]
    #[ORM\Column(name: 'razon_social', length: 255)]
    private string $businessName;

    #[ORM\Column(name: 'domicilio_fiscal', nullable: true)]
    #[ApiProperty(description: 'customer.fiscal_address.label')]
    private ?string $fiscalAddress = null;

    #[Assert\Regex(pattern: '/^\+?[1-9]\d{1,2}[-.\s]?\d{6,14}$/', message: 'El teléfono ingresado no es válido.')]
    #[Groups(['sale:read', 'sale_record:read'])]
    #[ApiProperty(description: 'customer.phone.label')]
    #[ORM\Column(name: 'telefono', length: 20, nullable: true)]
    private ?string $phone = null;

    #[Assert\Email]
    #[ApiProperty(description: 'customer.email.label')]
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $email = null;

    #[ORM\ManyToOne]
    #[ApiProperty(description: 'customer.price_list.label')]
    #[ORM\JoinColumn(name: 'price_list_id', referencedColumnName: 'id', nullable: true)]
    private ?PriceList $priceList = null;

    #[ApiProperty(description: 'customer.is_active.label')]
    #[ORM\Column(name: 'is_active', options: ['default' => true])]
    private bool $isActive = true;

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

    public function getIsActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }
}
