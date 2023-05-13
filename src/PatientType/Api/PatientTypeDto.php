<?php

namespace App\PatientType\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use App\PatientType\Domain\PatientType;

#[ApiResource(
    shortName: 'PatientType',
    provider: PatientTypeProvider::class,
    processor: PatientTypeProcessor::class,
)]
class PatientTypeDto extends PatientType
{
    #[ApiProperty(identifier: true)]
    public ?int $id;
}
