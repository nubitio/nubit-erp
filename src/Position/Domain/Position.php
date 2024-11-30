<?php

namespace App\Position\Domain;

use App\Core\Domain\Annotation\IgnoreAutowire;
use App\Department\Domain\Department;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[IgnoreAutowire]
class Position
{
    public function __construct(
        #[SerializedName('id')]
        public ?int $id = null,
        #[SerializedName('department')]
        public ?Department $department = null,
        #[SerializedName('name')]
        public ?string $name = null,
        #[SerializedName('description')]
        public ?string $description = null,
    ) {
    }
}
