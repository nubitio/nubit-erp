<?php

namespace App\Category\Domain;

use App\Domain\Annotation\IgnoreAutowire;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[IgnoreAutowire]
class Category
{
    public function __construct(
        #[SerializedName('categoria_id')]
        public ?string $categoria_id,
        #[SerializedName('descripcion')]
        public ?string $descripcion,
        #[SerializedName('parent_id')]
        public ?string $parent_id,
        #[SerializedName('imagen')]
        public ?string $imagen,
        #[SerializedName('estado')]
        public ?bool $estado,
    ) {
    }
}
