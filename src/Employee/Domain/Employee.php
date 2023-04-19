<?php

namespace App\Employee\Domain;

use App\Core\Domain\Annotation\IgnoreAutowire;
use App\Position\Domain\Position;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[IgnoreAutowire]
class Employee
{
    public function __construct(
        #[SerializedName('id')]
        public ?int                $id = null,
        #[SerializedName('id_number')]
        public ?string             $id_number = null,
        #[SerializedName('first_name')]
        public ?string             $first_name = null,
        #[SerializedName('last_name')]
        public ?string             $last_name = null,
        #[SerializedName('gender')]
        public ?int                $gender = null,
        #[SerializedName('birthdate')]
        public ?\DateTimeInterface $birthdate = null,
        #[SerializedName('address')]
        public ?string             $address = null,
        #[SerializedName('phone')]
        public ?string             $phone = null,
        #[SerializedName('hire_date')]
        public ?\DateTimeInterface $hire_date = null,
        #[SerializedName('position')]
        public ?Position           $position = null,
        #[SerializedName('salary')]
        public ?float              $salary = null,
        #[SerializedName('active')]
        public ?bool               $active = false
    )
    {
    }
}