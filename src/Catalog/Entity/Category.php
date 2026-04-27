<?php

declare(strict_types=1);

namespace App\Catalog\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Efact\Doctrine\Filter\DevExtremeFilter;
use App\Catalog\Repository\CategoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CategoryRepository::class)]
#[ORM\Table(name: 'categoria')]
#[ApiResource(
    operations: [new GetCollection(), new Post(), new Get(), new Patch(), new Delete()],
    normalizationContext: ['groups' => ['category:read']],
    denormalizationContext: ['groups' => ['category:write']],
    filters: [DevExtremeFilter::class],
    mercure: true,
)]
class Category
{
    #[Groups([
        'category:read',
        'product:read',
    ])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\Column(name: 'categoria_id')]
    private ?string $id = null;

    #[Groups([
        'category:read',
        'product:read',
        'category:write',
    ])]
    #[Assert\NotBlank]
    #[ApiProperty(
        description: 'category.name.label',
        openapiContext: [
            'x-crud' => [
                'filterable' => true,
                'sortable'   => true,
                'order'      => 0,
            ],
        ],
    )]
    #[ORM\Column(name: 'descripcion')]
    private string $name;

    #[Groups(['category:read', 'category:write'])]
    #[ApiProperty(
        description: 'category.parent.label',
        openapiContext: [
            'x-crud' => [
                'filterable' => true,
                'sortable'   => false,
                'order'      => 1,
            ],
        ],
    )]
    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    #[ORM\JoinColumn(name: 'parent_id', referencedColumnName: 'categoria_id')]
    private ?self $parent = null;

    /**
     * @var Collection<int, self>
     */
    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parent')]
    private Collection $children;

    public function __construct()
    {
        $this->children = new ArrayCollection();
    }

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

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function setParent(?self $parent): static
    {
        $this->parent = $parent;

        return $this;
    }

    /**
     * @return Collection<int, self>
     */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    public function addChild(self $child): static
    {
        if (!$this->children->contains($child)) {
            $this->children->add($child);
            $child->setParent($this);
        }

        return $this;
    }

    public function removeChild(self $child): static
    {
        if ($this->children->removeElement($child) && $child->getParent() === $this) {
            $child->setParent(null);
        }

        return $this;
    }
}
