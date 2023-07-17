<?php

namespace App\Ambiente\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use App\Ambiente\Domain\Ambiente;

#[ApiResource(
    shortName: 'Ambiente',
    provider: AmbienteProvider::class,
    processor: AmbienteProcessor::class,
)]
class AmbienteDto extends Ambiente
{
    #[ApiProperty(identifier: true)]
    public ?int $ambiente_id;
}
