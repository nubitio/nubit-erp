<?php

namespace App\Specialty\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use App\Specialty\Domain\Specialty;

#[ApiResource(
    shortName: 'Specialty',
    provider: SpecialtyProvider::class,
    processor: SpecialtyProcessor::class,
)]
class SpecialtyDto extends Specialty
{
    #[ApiProperty(identifier: true)]
    public ?int $id;

}