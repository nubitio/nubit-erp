<?php

namespace App\PriceList\Domain;

use App\Core\Domain\Annotation\IgnoreAutowire;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[IgnoreAutowire]
class PriceList
{
    public function __construct(
        #[SerializedName('id')]
        public ?int $id = null,
        #[SerializedName('name')]
        public ?string $name = null,
        #[SerializedName('active')]
        public ?bool $active = false,
    ) {
    }
}
