<?php

namespace App\Physician\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use App\Physician\Domain\Physician;

#[ApiResource(
    shortName: 'Physician',
    provider: PhysicianProvider::class,
    processor: PhysicianProcessor::class,
)]
class PhysicianDto extends Physician
{
    #[ApiProperty(identifier: true)]
    public ?int $id;
}
