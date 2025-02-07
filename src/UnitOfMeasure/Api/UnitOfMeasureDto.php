<?php

namespace App\UnitOfMeasure\Api;

use ApiPlatform\Metadata\ApiProperty;
use App\UnitOfMeasure\Domain\UnitOfMeasure;

class UnitOfMeasureDto extends UnitOfMeasure
{
    #[ApiProperty(identifier: true)]
    public ?int $unidad_id;
}
