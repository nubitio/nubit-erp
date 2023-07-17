<?php

namespace App\Orden\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use App\Orden\Domain\Orden;

#[ApiResource(
    shortName: 'Orden',
    provider: OrdenProvider::class,
    processor: OrdenProcessor::class,
)]
class OrdenDto extends Orden
{
    #[ApiProperty(identifier: true)]
    public ?int $orden_id;
}
