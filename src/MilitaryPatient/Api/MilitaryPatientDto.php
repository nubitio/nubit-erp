<?php

namespace App\MilitaryPatient\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use App\MilitaryPatient\Domain\MilitaryPatient;

#[ApiResource(
    shortName: 'MilitaryPatient',
    provider: MilitaryPatientProvider::class,
    processor: MilitaryPatientProcessor::class,
)]
class MilitaryPatientDto extends MilitaryPatient
{
    #[ApiProperty(identifier: true)]
    public ?int $id;
}
