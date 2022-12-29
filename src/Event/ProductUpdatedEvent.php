<?php

namespace App\Event;

use App\Domain\Annotation\IgnoreAutowire;
use App\Domain\Product\Product;
use Symfony\Contracts\EventDispatcher\Event;

#[IgnoreAutowire]
class ProductUpdatedEvent extends Event
{
    public const NAME = 'product.updated';

    private Product $product;

    public function __construct(Product $product)
    {
        $this->product = $product;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }
}
