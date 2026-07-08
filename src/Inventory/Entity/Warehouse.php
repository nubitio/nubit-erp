<?php

declare(strict_types=1);

namespace App\Inventory\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Catalog\Entity\Product;
use App\Efact\Domain\ActivatableInterface;
use App\Inventory\Repository\WarehouseRepository;
use App\Inventory\State\WarehouseCreateProcessor;
use App\Inventory\State\WarehousesByBranchProvider;
use App\Organization\Entity\Branch;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Nubit\ApiPlatform\Doctrine\Filter\DataGridFilter;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: WarehouseRepository::class)]
#[ORM\Table(name: 'almacen')]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Post(processor: WarehouseCreateProcessor::class),
        new Get(),
        new Patch(),
        new Delete(),
        new GetCollection(
            uriTemplate: '/branches/{id}/warehouses',
            uriVariables: [
                'id' => new Link(fromProperty: 'id', fromClass: Branch::class),
            ],
            provider: WarehousesByBranchProvider::class,
        ),
    ],
    normalizationContext: ['groups' => ['warehouse:read']],
    filters: [DataGridFilter::class],
    mercure: true,
)]
class Warehouse implements ActivatableInterface
{
    #[Groups([
        'warehouse:read',
        'inbound:read',
        'outbound:read',
        'purchase:read',
        'stock_item:read',
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
        'purchase:read',
        'stock_item:read',
    ])]
    #[ApiProperty(description: 'warehouse.name.label')]
    #[ORM\Column(name: 'descripcion', length: 255)]
    private string $name;

    #[Groups(['warehouse:read'])]
    #[ApiProperty(description: 'warehouse.branch.label')]
    #[ORM\ManyToOne(inversedBy: 'warehouses')]
    #[ORM\JoinColumn(name: 'local_id', referencedColumnName: 'local_id')]
    private ?Branch $branch = null;

    #[Groups(['warehouse:read'])]
    #[ApiProperty(description: 'warehouse.is_active.label')]
    #[ORM\Column(name: 'is_active', options: ['default' => true])]
    private bool $isActive = true;

    /**
     * @var Collection<int, ProductStock>
     */
    #[Groups(['warehouse:read'])]
    #[ORM\OneToMany(
        targetEntity: ProductStock::class,
        mappedBy: 'warehouse',
        cascade: [
            'persist',
            'remove',
        ],
        orphanRemoval: true,
    )]
    private Collection $productStocks;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getName(): string
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

    public function getIsActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

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
        $this->productStocks->removeElement($productStock);

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
