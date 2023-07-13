<?php

namespace App\CashboxDetail\Api;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use App\Cashbox\Api\CashboxDto;
use App\CashboxDetail\Domain\CashboxDetail;

#[ApiResource(
    uriTemplate: '/cashboxes/{caja_id}/details',
    shortName: 'CashboxDetail',
    operations: [new GetCollection()],
    uriVariables: [
        'caja_id' => new Link(
            fromProperty: 'caja_id',
            fromClass: CashboxDto::class,
        ),
    ],
    provider: CashboxDetailProvider::class
)]
class CashboxDetailDto extends CashboxDetail
{
}
