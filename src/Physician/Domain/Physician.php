<?php

namespace App\Physician\Domain;

use App\Core\Domain\Annotation\IgnoreAutowire;
use App\Employee\Domain\Employee;
use App\Specialty\Domain\Specialty;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[IgnoreAutowire]
class Physician
{
    public function __construct(
        #[SerializedName('id')]
        public ?int       $id = null,
        #[SerializedName('employee')]
        public ?Employee  $employee = null,
        #[SerializedName('specialty')]
        public ?Specialty $specialty = null,
        #[SerializedName('licence')]
        public ?string    $licence = null
    )
    {
    }

    #[SerializedName('full_name')]
    public function getFullName(): ?string
    {
        return $this->employee?->getFullName();
    }
}