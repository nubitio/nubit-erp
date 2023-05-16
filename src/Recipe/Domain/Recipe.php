<?php

namespace App\Recipe\Domain;

use App\Core\Domain\Annotation\IgnoreAutowire;
use App\RecipeDetail\Domain\RecipeDetail;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[IgnoreAutowire]
class Recipe
{
    public function __construct(
        #[SerializedName('id')]
        public ?int $id = null,
        #[SerializedName('name')]
        public ?string $name = null,
        #[SerializedName('active')]
        public ?bool $active = false,
        /** @var RecipeDetail[] $details */
        #[SerializedName('details')]
        public array $details = []
    ) {
    }
}
