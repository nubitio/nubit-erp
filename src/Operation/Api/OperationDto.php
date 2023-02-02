<?php

namespace App\Operation\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use App\Operation\Domain\Operation;

#[ApiResource(
    shortName: 'Operation',
    provider: OperationProvider::class,
    processor: OperationProcessor::class,
)]
class OperationDto extends Operation
{
    #[ApiProperty(identifier: true)]
    public ?string $operacion_id;
}