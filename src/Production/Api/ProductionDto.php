<?php

namespace App\Production\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use App\Production\Domain\Production;

#[ApiResource(
    shortName: 'Production',
    provider: ProductionProvider::class,
    processor: ProductionProcessor::class,
)]
class ProductionDto extends Production
{
    #[ApiProperty(identifier: true)]
    public ?int $id;
}
