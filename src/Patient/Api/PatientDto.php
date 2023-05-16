<?php

namespace App\Patient\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use App\Patient\Domain\Patient;

#[ApiResource(
    shortName: 'Patient',
    provider: PatientProvider::class,
    processor: PatientProcessor::class,
)]
class PatientDto extends Patient
{
    #[ApiProperty(identifier: true)]
    public ?int $id;
}
