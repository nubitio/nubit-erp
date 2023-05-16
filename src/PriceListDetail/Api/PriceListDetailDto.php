<?php

namespace App\PriceListDetail\Api;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use App\PriceList\Api\PriceListDto;
use App\PriceListDetail\Domain\PriceListDetail;

#[ApiResource(
    uriTemplate: '/price-lists/{id}/details',
    shortName: 'PriceListDetail',
    operations: [new GetCollection()],
    uriVariables: [
        'id' => new Link(
            fromProperty: 'id',
            fromClass: PriceListDto::class,
        ),
    ],
    provider: PriceListDetailProvider::class
)]
class PriceListDetailDto extends PriceListDetail
{
}
