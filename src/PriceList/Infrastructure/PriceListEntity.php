<?php

namespace App\PriceList\Infrastructure;

use App\Core\Domain\Annotation\IgnoreAutowire;
use Doctrine\ORM\Mapping as ORM;

#[IgnoreAutowire]
#[ORM\Entity]
#[ORM\Table(name: 'price_list')]
class PriceListEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\GeneratedValue]
        #[ORM\Column(name: 'id', type: 'integer')]
        public ?int $id = null,
        #[ORM\Column(name: 'name', type: 'string')]
        public ?string $name = null,
        #[ORM\Column(name: 'active', type: 'boolean')]
        public ?bool $active = null,
    ) {
    }
}
