<?php

namespace App\Performance\Domain;

use App\ComboOption\Domain\ComboOption;
use App\Core\Domain\Annotation\IgnoreAutowire;
use App\Product\Domain\Product;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[IgnoreAutowire]
class Performance
{
    public function __construct(
        #[SerializedName('id')]
        public ?int $id = null,
        #[SerializedName('lines')]
        public ?ComboOption $lines = null,
        #[SerializedName('products')]
        public ?Product $products = null,
        #[SerializedName('types')]
        public ?ComboOption $types = null,
        #[SerializedName('date')]
        public ?\DateTimeInterface $date = null,
        #[SerializedName('start')]
        public ?\DateTimeInterface $start = null,
        #[SerializedName('end')]
        public ?\DateTimeInterface $end = null,
        #[SerializedName('quantity')]
        public ?float $quantity = null,
        #[SerializedName('obs')]
        public ?string $obs = null
    )
    {
    }
}