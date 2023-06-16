<?php

namespace App\UnitOfMeasure\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use App\UnitOfMeasure\Domain\UnitOfMeasure;

#[ApiResource(
    shortName: 'UnitOfMeasure',
    provider: UnitOfMeasureProvider::class,
    processor: UnitOfMeasureProcessor::class,
)]
class UnitOfMeasureDto extends UnitOfMeasure
{
    #[ApiProperty(identifier: true)]
    public ?int $unidad_id;
}
