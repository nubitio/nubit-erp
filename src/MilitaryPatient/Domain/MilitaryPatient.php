<?php

namespace App\MilitaryPatient\Domain;

use App\Core\Domain\Annotation\IgnoreAutowire;
use App\Patient\Domain\Patient;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[IgnoreAutowire]
class MilitaryPatient extends Patient
{
    public function __construct(
        #[SerializedName('id')]
        public ?int     $id = null,
        #[SerializedName('patient')]
        public ?Patient $patient = null,
        #[SerializedName('cip_code')]
        public ?string  $cip_code = null,
        #[SerializedName('rank')]
        public ?string  $rank = null,
        #[SerializedName('weapon_code')]
        public ?string  $weapon_code = null
    )
    {
        parent::__construct();
    }
}