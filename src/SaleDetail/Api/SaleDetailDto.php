<?php

namespace App\SaleDetail\Api;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use App\Sale\Api\SaleDto;
use App\SaleDetail\Domain\SaleDetail;

#[ApiResource(
    uriTemplate: '/sales/{movimiento_id}/details',
    shortName: 'SaleDetail',
    operations: [new GetCollection()],
    uriVariables: [
        'movimiento_id' => new Link(
            fromProperty: 'movimiento_id',
            fromClass: SaleDto::class,
        ),
    ],
    provider: SaleDetailProvider::class
)]
class SaleDetailDto extends SaleDetail
{
}