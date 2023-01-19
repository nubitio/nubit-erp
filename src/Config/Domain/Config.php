<?php

namespace App\Config\Domain;

use App\Core\Domain\Annotation\IgnoreAutowire;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[IgnoreAutowire]
class Config
{
    public function __construct(
        #[SerializedName('id')]
        public ?int $id = null,
        #[SerializedName('name')]
        public ?string $name = null,
        #[SerializedName('value')]
        public ?string $value = null
    )
    {
    }
}