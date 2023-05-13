<?php

namespace App\Performance\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use App\Performance\Domain\Performance;

#[ApiResource(
    shortName: 'Performance',
    provider: PerformanceProvider::class,
    processor: PerformanceProcessor::class,
)]
class PerformanceDto extends Performance
{
    #[ApiProperty(identifier: true)]
    public ?int $id;
}
