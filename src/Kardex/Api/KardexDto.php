<?php

namespace App\Kardex\Api;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\Kardex\Domain\Kardex;

#[ApiResource(
    uriTemplate: '/kardex',
    shortName: 'Kardex',
    operations: [
        new GetCollection(),
    ],
    provider: KardexProvider::class,
)]
class KardexDto extends Kardex
{
}
