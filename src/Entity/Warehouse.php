<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use App\Core\Infrastructure\Doctrine\Filter\DevExtremeFilter;
use App\Repository\WarehouseRepository;
use App\State\Warehouse\WarehousesByBranchProvider;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: WarehouseRepository::class)]
#[ORM\Table(name: 'almacen')]
#[ApiResource(
    normalizationContext: ['groups' => ['warehouse:read']],
    filters: [DevExtremeFilter::class],
)]
#[ApiResource(
    uriTemplate: '/branches/{id}/warehouses',
    operations: [new GetCollection()],
    uriVariables: [
        'id' => new Link(
            fromProperty: 'id',
            fromClass: Branch::class,
        ),
    ],
    provider: WarehousesByBranchProvider::class
)]
class Warehouse
{
    #[Groups([
        'warehouse:read',
        'inbound:read',
        'outbound:read',
    ])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\Column(name: 'almacen_id')]
    private ?string $id = null;

    #[Groups([
        'warehouse:read',
        'inbound:read',
        'outbound:read',
    ])]
    #[ORM\Column(name: 'descripcion', length: 255)]
    private ?string $name = null;

    #[Groups(['warehouse:read'])]
    #[ORM\ManyToOne(inversedBy: 'warehouses')]
    #[ORM\JoinColumn(name: 'local_id', referencedColumnName: 'local_id')]
    private ?Branch $branch = null;

    /**
     * @var Collection<int, ProductStock>
     */
    #[Groups(['warehouse:read'])]
    #[ORM\OneToMany(mappedBy: 'warehouse', targetEntity: ProductStock::class, cascade: [
        'persist',
        'remove',
    ], orphanRemoval: true)]
    private Collection $productStocks;

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

    public function getBranch(): ?Branch
    {
        return $this->branch;
    }

    public function setBranch(?Branch $branch): static
    {
        $this->branch = $branch;

        return $this;
    }

    public function __construct()
    {
        $this->productStocks = new ArrayCollection();
    }

    /**
     * @return Collection<int, ProductStock>
     */
    public function getProductStocks(): Collection
    {
        return $this->productStocks;
    }

    public function addProductStock(ProductStock $productStock): static
    {
        if (!$this->productStocks->contains($productStock)) {
            $this->productStocks->add($productStock);
            $productStock->setWarehouse($this);
        }

        return $this;
    }

    public function removeProductStock(ProductStock $productStock): static
    {
        if ($this->productStocks->removeElement($productStock)) {
            // set the owning side to null (unless already changed)
            if ($productStock->getWarehouse() === $this) {
                $productStock->setWarehouse(null);
            }
        }

        return $this;
    }

    public function getProductStock(Product $product): ?ProductStock
    {
        foreach ($this->productStocks as $productStock) {
            if ($productStock->getProduct() === $product) {
                return $productStock;
            }
        }

        return null;
    }
}
