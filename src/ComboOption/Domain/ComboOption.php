<?php

namespace App\ComboOption\Domain;

use App\Core\Domain\Annotation\IgnoreAutowire;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[IgnoreAutowire]
class ComboOption
{
    public function __construct(
        #[SerializedName('id')]
        public ?int $id = null,
        #[SerializedName('_key')]
        public ?string $_key = null,
        #[SerializedName('_value')]
        public ?string $_value = null,
        #[SerializedName('active')]
        public ?bool $active = false,
    ) {
    }
}
