<?php

namespace App\Position\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use App\Position\Domain\Position;

#[ApiResource(
    shortName: 'Position',
    provider: PositionProvider::class,
    processor: PositionProcessor::class,
)]
class PositionDto extends Position
{
    #[ApiProperty(identifier: true)]
    public ?int $id;

}