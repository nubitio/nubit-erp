<?php

namespace App\Mesa\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use App\Mesa\Domain\Mesa;

#[ApiResource(
    shortName: 'Mesa',
    provider: MesaProvider::class,
    processor: MesaProcessor::class,
)]
class MesaDto extends Mesa
{
    #[ApiProperty(identifier: true)]
    public ?int $mesa_id;
}
