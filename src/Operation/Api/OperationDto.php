<?php

namespace App\Operation\Api;

use ApiPlatform\Metadata\ApiProperty;
use App\Operation\Domain\Operation;

class OperationDto extends Operation
{
    #[ApiProperty(identifier: true)]
    public ?string $operacion_id;
}
