<?php

namespace App\Config\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use App\Config\Domain\Config;

#[ApiResource(
    shortName: 'Setting',
    provider: ConfigProvider::class,
    processor: ConfigProcessor::class,
)]
class ConfigDto extends Config
{
    #[ApiProperty(identifier: true)]
    public ?int $id = null;
}
