<?php

namespace App\Category\Domain;

use App\Core\Domain\Annotation\IgnoreAutowire;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[IgnoreAutowire]
class Category
{
    public function __construct(
        #[SerializedName('categoria_id')]
        public ?string $categoria_id = null,
        #[SerializedName('descripcion')]
        public ?string $descripcion = null,
        #[SerializedName('parent_id')]
        public ?string $parent_id = null,
        #[SerializedName('imagen')]
        public ?string $imagen = null,
        #[SerializedName('estado')]
        public ?bool   $estado = null,
    )
    {
    }
}
