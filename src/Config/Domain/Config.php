<?php

namespace App\Config\Domain;

use App\Domain\Annotation\IgnoreAutowire;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[IgnoreAutowire]
class Config
{
    public function __construct(
        #[SerializedName('id')]
        public ?int $id,
        #[SerializedName('name')]
        public ?string $name,
        #[SerializedName('value')]
        public ?string $value,
    ) {
    }
}
