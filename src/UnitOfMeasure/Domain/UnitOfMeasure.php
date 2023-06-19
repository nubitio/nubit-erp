<?php

namespace App\UnitOfMeasure\Domain;

use App\Core\Domain\Annotation\IgnoreAutowire;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[IgnoreAutowire]
class UnitOfMeasure
{
    public function __construct(
        #[SerializedName('unidad_id')]
        public ?int $unidad_id = null,
        #[SerializedName('name')]
        public ?string $name = null,
        #[SerializedName('type_id')]
        public ?int $type_id = null,
        #[SerializedName('ratio')]
        public ?float $ratio = null,
        #[SerializedName('is_reference')]
        public ?bool $is_reference = false,
        #[SerializedName('active')]
        public ?bool $active = false
    ) {
    }
}
