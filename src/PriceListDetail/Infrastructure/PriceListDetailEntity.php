<?php

namespace App\PriceListDetail\Infrastructure;

use App\Core\Domain\Annotation\IgnoreAutowire;
use Doctrine\ORM\Mapping as ORM;

#[IgnoreAutowire]
#[ORM\Entity]
#[ORM\Table(name: 'price_list_detail')]
class PriceListDetailEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\GeneratedValue]
        #[ORM\Column(name: 'id', type: 'integer')]
        public ?int $id = null,
        #[ORM\Column(name: 'price_list_id', type: 'integer')]
        public ?int $price_list_id = null,
        #[ORM\Column(name: 'product_id', type: 'string')]
        public ?string $product_id = null,
        #[ORM\Column(name: 'price', type: 'decimal')]
        public ?float $price = null,
    ) {
    }
}
