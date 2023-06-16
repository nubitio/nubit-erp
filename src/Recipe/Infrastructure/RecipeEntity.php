<?php

namespace App\Recipe\Infrastructure;

use App\Core\Domain\Annotation\IgnoreAutowire;
use App\Product\Infrastructure\ProductEntity;
use App\RecipeDetail\Infrastructure\RecipeDetailEntity;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[IgnoreAutowire]
#[ORM\Entity]
#[ORM\Table(name: 'recipe')]
class RecipeEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\GeneratedValue]
        #[ORM\Column(name: 'id', type: 'integer')]
        public ?int $id = null,
        #[ORM\ManyToOne(targetEntity: ProductEntity::class)]
        #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'producto_id')]
        public ?ProductEntity $product = null,
        #[ORM\Column(name: 'type_id', type: 'integer')]
        public ?int $type_id = null,
        #[ORM\Column(name: 'reference', type: 'string')]
        public ?string $reference = null,
        #[ORM\Column(name: 'quantity', type: 'decimal')]
        public ?float $quantity = null,
        #[ORM\Column(name: 'active', type: 'boolean')]
        public ?bool $active = null,
        /**
         * @var RecipeDetailEntity[] $details
         *
         * @phpstan-var ArrayCollection<int , RecipeDetailEntity>|array<int , RecipeDetailEntity>
         */
        #[ORM\OneToMany(mappedBy: 'recipe', targetEntity: RecipeDetailEntity::class, cascade: ['persist', 'remove'])]
        public Collection|array $details = new ArrayCollection(),
    ) {
    }
}
