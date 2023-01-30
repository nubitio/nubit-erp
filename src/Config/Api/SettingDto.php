<?php

namespace App\Config\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use App\Config\Domain\Config;

#[ApiResource(
    uriTemplate: '/settings/find/{name}',
    shortName: 'Setting',
    operations: [
        new Get()
    ],
    uriVariables: [
        'name' => new Link(
            fromProperty: 'name',
            fromClass: SettingDto::class,
        )
    ],
    provider: ConfigProvider::class
)]
class SettingDto extends Config
{
    #[ApiProperty(identifier: true)]
    public ?string $name;
}