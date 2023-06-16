<?php

namespace App\ProductionDetail\Api;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use App\Production\Api\ProductionDto;
use App\ProductionDetail\Domain\ProductionDetail;

#[ApiResource(
    uriTemplate: '/productions/{id}/details',
    shortName: 'ProductionDetail',
    operations: [new GetCollection()],
    uriVariables: [
        'id' => new Link(
            fromProperty: 'id',
            fromClass: ProductionDto::class,
        ),
    ],
    provider: ProductionDetailProvider::class
)]
class ProductionDetailDto extends ProductionDetail
{
}
