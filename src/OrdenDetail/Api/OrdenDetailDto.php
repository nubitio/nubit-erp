<?php

namespace App\OrdenDetail\Api;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use App\Orden\Api\OrdenDto;
use App\OrdenDetail\Domain\OrdenDetail;

#[ApiResource(
    uriTemplate: '/ordens/{orden_id}/details',
    shortName: 'OrdenDetail',
    operations: [new GetCollection()],
    uriVariables: [
        'orden_id' => new Link(
            fromProperty: 'orden_id',
            fromClass: OrdenDto::class,
        ),
    ],
    provider: OrdenDetailProvider::class
)]
class OrdenDetailDto extends OrdenDetail
{
}
