<?php

namespace App\CashboxNewDetail\Api;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use App\CashboxNew\Api\CashboxDto;
use App\CashboxNewDetail\Domain\CashboxDetail;

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
    provider: CashboxDetailByCashboxProvider::class
)]
class CashboxDetailByCashboxDto extends CashboxDetail
{
}
