<?php

namespace App\Recipe\Domain;

use App\Core\Domain\Annotation\IgnoreAutowire;
use App\Product\Domain\Product;
use App\RecipeDetail\Domain\RecipeDetail;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[IgnoreAutowire]
class Recipe
{
    public function __construct(
        #[SerializedName('id')]
        public ?int $id = null,
        #[SerializedName('product')]
        public ?Product $product = null,
        #[SerializedName('type_id')]
        public ?int $type_id = null,
        #[SerializedName('reference')]
        public ?string $reference = null,
        #[SerializedName('quantity')]
        public ?float $quantity = null,
        #[SerializedName('active')]
        public ?bool $active = false,
        /** @var RecipeDetail[] $details */
        #[SerializedName('details')]
        public array $details = [],
    ) {
    }

    public function getName(): string
    {
        if ($this->reference) {
            return $this->reference.' - '.$this->product?->descripcion;
        } else {
            return $this->product?->descripcion ?: '';
        }
    }
}
