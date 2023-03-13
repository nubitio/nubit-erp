<?php

namespace App\PriceListDetail\Domain;

use App\Core\Domain\Annotation\IgnoreAutowire;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[IgnoreAutowire]
class PriceListDetail
{
    public function __construct(
        #[SerializedName('id')]
        public ?int    $id = null,
        #[SerializedName('price_list_id')]
        public ?int    $price_list_id = null,
        #[SerializedName('product_id')]
        public ?string $product_id = null,
        #[SerializedName('price')]
        public ?float  $price = null
    )
    {
    }
}