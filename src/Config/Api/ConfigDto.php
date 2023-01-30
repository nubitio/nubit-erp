<?php

namespace App\Config\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use App\Config\Domain\Config;

#[ApiResource(
    shortName: 'Setting',
    provider: ConfigProvider::class,
    processor: ConfigProcessor::class,
)]
#[ApiResource(
    uriTemplate: '/settings/find/{name}',
    shortName: 'Setting',
    operations: [
        new Get(
            uriVariables: [
                'name' => new Link(
                    fromProperty: 'name',
                    fromClass: ConfigDto::class,
                )
            ],
            provider: ConfigProvider::class
        )
    ]
)]
class ConfigDto extends Config
{
    #[ApiProperty(identifier: true)]
    public ?int $id;
}