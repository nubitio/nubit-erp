<?php

namespace App\RecipeDetail\Infrastructure;

use App\Core\Domain\Annotation\IgnoreAutowire;
use App\Recipe\Infrastructure\RecipeEntity;
use Doctrine\ORM\Mapping as ORM;

#[IgnoreAutowire]
#[ORM\Entity]
#[ORM\Table(name: "recipe_detail")]
class RecipeDetailEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\GeneratedValue]
        #[ORM\Column(name: 'id', type: 'integer')]
        public ?int $id = null,
        #[ORM\Column(name: 'recipe_id', type: 'integer')]
        public ?int $recipe_id = null,
        #[ORM\Column(name: 'material_id', type: 'integer')]
        public ?int $material_id = null,
        #[ORM\Column(name: 'quantity', type: 'decimal')]
        public ?float $quantity = null,
        #[ORM\ManyToOne(targetEntity: RecipeEntity::class, inversedBy: 'details')]
        #[ORM\JoinColumn(name: 'recipe_id', referencedColumnName: 'id')]
        public ?RecipeEntity $recipe = null
    )
    {
    }
}