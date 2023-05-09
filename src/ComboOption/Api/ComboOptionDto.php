<?php

namespace App\ComboOption\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use App\ComboOption\Domain\ComboOption;

#[ApiResource(
    shortName: 'ComboOption',
    provider: ComboOptionProvider::class,
    processor: ComboOptionProcessor::class,
)]
class ComboOptionDto extends ComboOption
{
    #[ApiProperty(identifier: true)]
    public ?int $id;

}