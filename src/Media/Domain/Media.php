<?php

namespace App\Media\Domain;

use App\Core\Domain\Annotation\IgnoreAutowire;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[IgnoreAutowire]
class Media
{
    public function __construct(
        #[SerializedName('id')]
        public ?string $id = null,
        #[SerializedName('path')]
        public ?string $path = null,
    ) {
    }
}
