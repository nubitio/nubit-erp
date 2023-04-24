<?php

namespace App\Patient\Domain;

use App\Core\Domain\Annotation\IgnoreAutowire;
use App\Customer\Domain\Customer;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[IgnoreAutowire]
class Patient extends Customer
{
    public function __construct(
        #[SerializedName('id')]
        public ?int                $id = null,
        #[SerializedName('customer')]
        public ?Customer           $customer = null,
        #[SerializedName('type_id')]
        public ?int                $type_id = null,
        #[SerializedName('insurance_provider')]
        public ?string             $insurance_provider = null,
        #[SerializedName('medical_record_number')]
        public ?string             $medical_record_number = null,
        #[SerializedName('created_by')]
        public ?int                $created_by = null,
        #[SerializedName('updated_by')]
        public ?int                $updated_by = null,
        #[SerializedName('created_at')]
        public ?\DateTimeInterface $created_at = null,
        #[SerializedName('updated_at')]
        public ?\DateTimeInterface $updated_at = null
    )
    {
        parent::__construct();
    }
}