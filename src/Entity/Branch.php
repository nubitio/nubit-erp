<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Core\Doctrine\Filter\DevExtremeFilter;
use App\Repository\BranchRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: BranchRepository::class)]
#[ORM\Table(name: 'local')]
#[ApiResource(
    normalizationContext: ['groups' => ['branch:read']],
    filters: [DevExtremeFilter::class],
)]
class Branch
{
    #[Groups([
        'branch:read',
        'warehouse:read',
        'voucher_series:read',
        'cash_register:read',
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
    ])]
    #[ORM\Column(name: 'descripcion', length: 255)]
    private string $name;

    #[Groups(['branch:read'])]
    #[ORM\Column(name: 'direccion', length: 255, nullable: true)]
    private ?string $address = null;

    #[Groups(['branch:read'])]
    #[ORM\Column(name: 'telefono', length: 255, nullable: true)]
    private ?string $phone = null;

    #[Groups(['branch:read'])]
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
        // set the owning side to null (unless already changed)
        if ($this->warehouses->removeElement($warehouse) && $warehouse->getBranch() === $this) {
            $warehouse->setBranch(null);
        }

        return $this;
    }
}
