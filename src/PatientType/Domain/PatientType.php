<?php

namespace App\PatientType\Domain;

use App\Core\Domain\Annotation\IgnoreAutowire;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[IgnoreAutowire]
class PatientType
{
    public function __construct(
        #[SerializedName('id')]
        public ?int $id = null,
        #[SerializedName('name')]
        public ?string $name = null,
        #[SerializedName('description')]
        public ?string $description = null
    )
    {
    }
}