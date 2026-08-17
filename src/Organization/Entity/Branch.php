<?php

declare(strict_types=1);

namespace App\Organization\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use App\Efact\Domain\ActivatableInterface;
use App\Inventory\Entity\Warehouse;
use App\Organization\Repository\BranchRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Nubit\ApiPlatform\Doctrine\Filter\DataGridFilter;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: BranchRepository::class)]
#[ORM\Table(name: 'local')]
#[ApiResource(normalizationContext: ['groups' => ['branch:read']], filters: [DataGridFilter::class], mercure: true)]
class Branch implements ActivatableInterface
{
    #[Groups([
        'branch:read',
        'warehouse:read',
        'voucher_series:read',
        'cash_register:read',
        'sale_quote:read',
        'purchase_order:read',
        'expense_report:read',
    ])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\Column(name: 'local_id')]
    private ?string $id = null;

    #[Groups([
        'branch:read',
        'warehouse:read',
        'voucher_series:read',
        'cash_register:read',
        'sale_quote:read',
        'purchase_order:read',
        'expense_report:read',
    ])]
    #[ApiProperty(description: 'branch.name.label')]
    #[ORM\Column(name: 'descripcion', length: 255)]
    private string $name;

    #[Groups(['branch:read'])]
    #[ApiProperty(description: 'branch.address.label')]
    #[ORM\Column(name: 'direccion', length: 255, nullable: true)]
    private ?string $address = null;

    #[Groups(['branch:read'])]
    #[ApiProperty(description: 'branch.phone.label')]
    #[ORM\Column(name: 'telefono', length: 255, nullable: true)]
    private ?string $phone = null;

    #[Groups(['branch:read'])]
    #[ApiProperty(description: 'branch.is_active.label')]
    #[ORM\Column(name: 'is_active', options: ['default' => true])]
    private bool $isActive = true;

    #[Groups(['branch:read'])]
    #[ApiProperty(description: 'branch.company.label')]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'empresa_id', referencedColumnName: 'empresa_id', nullable: false)]
    private Company $company;

    /**
     * @var Collection<int, Warehouse>
     */
    #[ORM\OneToMany(targetEntity: Warehouse::class, mappedBy: 'branch', orphanRemoval: true)]
    private Collection $warehouses;

    public function __construct()
    {
        $this->warehouses = new ArrayCollection();
    }

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

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): static
    {
        $this->address = $address;

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

    public function getIsActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getCompany(): ?Company
    {
        return $this->company;
    }

    public function setCompany(?Company $company): static
    {
        $this->company = $company;

        return $this;
    }

    /**
     * @return Collection<int, Warehouse>
     */
    public function getWarehouses(): Collection
    {
        return $this->warehouses;
    }

    public function addWarehouse(Warehouse $warehouse): static
    {
        if (!$this->warehouses->contains($warehouse)) {
            $this->warehouses->add($warehouse);
            $warehouse->setBranch($this);
        }

        return $this;
    }

    public function removeWarehouse(Warehouse $warehouse): static
    {
        if ($this->warehouses->removeElement($warehouse) && $warehouse->getBranch() === $this) {
            $warehouse->setBranch(null);
        }

        return $this;
    }
}
