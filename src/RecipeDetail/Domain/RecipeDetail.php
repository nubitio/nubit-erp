<?php

namespace App\RecipeDetail\Domain;

use App\Core\Domain\Annotation\IgnoreAutowire;
use App\Product\Domain\Product;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[IgnoreAutowire]
class RecipeDetail
{
    public function __construct(
        #[SerializedName('id')]
        public ?int $id = null,
        #[SerializedName('recipe_id')]
        public ?int $recipe_id = null,
        #[SerializedName('material')]
        public ?Product $material = null,
        #[SerializedName('quantity')]
        public ?float $quantity = null,
    ) {
    }
}
