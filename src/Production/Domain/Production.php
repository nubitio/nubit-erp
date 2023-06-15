<?php

namespace App\Production\Domain;

use App\ComboOption\Domain\ComboOption;
use App\Core\Domain\Annotation\IgnoreAutowire;
use App\Employee\Domain\Employee;
use App\Product\Domain\Product;
use App\ProductionDetail\Domain\ProductionDetail;
use App\Recipe\Domain\Recipe;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[IgnoreAutowire]
class Production
{
    public function __construct(
        #[SerializedName('id')]
        public ?int $id = null,
        #[SerializedName('product')]
        public ?Product $product = null,
        #[SerializedName('scheduled_date')]
        public ?\DateTimeInterface $scheduled_date = null,
        #[SerializedName('quantity')]
        public ?float $quantity = null,
        #[SerializedName('recipe')]
        public ?Recipe $recipe = null,
        #[SerializedName('employee')]
        public ?Employee $employee = null,
        #[SerializedName('production_line')]
        public ?ComboOption $production_line = null,
        #[SerializedName('status')]
        public ?int $status = null,
        /** @var ProductionDetail[] $details */
        #[SerializedName('details')]
        public array $details = []
    ) {
    }
}
